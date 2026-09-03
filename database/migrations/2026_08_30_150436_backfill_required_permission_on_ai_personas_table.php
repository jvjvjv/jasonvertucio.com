<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Maps each restricted persona's `allowed_roles` to `required_permission`
     * by what the roles imply, not a single blanket value — production data
     * (unlike dev) includes personas restricted to the plain `user` role
     * alone, which holds no permission equivalent to "any signed-in user".
     * See openspec/changes/ai-personas-permission-access/design.md.
     *
     * - Any role granting `manage-ai-tools` today (`admin`, `super-admin`)
     *   present in `allowed_roles` -> `manage-ai-tools`. This also covers
     *   personas that additionally allowed the `user` role, narrowing them
     *   to admin-only per the developer's decision.
     * - Otherwise, if `user` is present -> the `authenticated` bucket (any
     *   signed-in user, not tied to a specific permission), preserving
     *   today's access.
     * - Any other role combination is left `null` (public) and logged for
     *   manual review — this codebase has none today, but a future role
     *   added to `allowed_roles` elsewhere shouldn't be silently narrowed.
     */
    public function up(): void
    {
        $personas = DB::table('ai_personas')
            ->whereNotNull('allowed_roles')
            ->whereRaw('JSON_LENGTH(allowed_roles) > 0')
            ->get(['id', 'allowed_roles']);

        foreach ($personas as $persona) {
            $roles = json_decode($persona->allowed_roles, true) ?? [];

            $permission = match (true) {
                array_intersect(['admin', 'super-admin'], $roles) !== [] => 'manage-ai-tools',
                in_array('user', $roles, true) => 'authenticated',
                default => null,
            };

            if ($permission === null) {
                Log::warning('AiPersona backfill: no permission mapping for allowed_roles, left public.', [
                    'ai_persona_id' => $persona->id,
                    'allowed_roles' => $roles,
                ]);
            }

            DB::table('ai_personas')->where('id', $persona->id)->update([
                'required_permission' => $permission,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_personas')
            ->whereIn('required_permission', ['manage-ai-tools', 'authenticated'])
            ->update(['required_permission' => null]);
    }
};
