<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reserves system prompt ids 1–49 for built-in prompts, so a future built-in
 * can be seeded at a fixed id without colliding with a prompt the admin made.
 *
 * Admin-made prompts already in 6–49 move to 50 or above. Each is copied,
 * every AI system using it is repointed, and only then is the old row
 * deleted: the foreign key on `ai_systems.system_prompt_id` neither follows an
 * id change nor survives the old row going first (it would null the system's
 * prompt). Then the auto-increment is raised so new prompts start at 50.
 */
return new class extends Migration
{
    public const int FIRST_ADMIN_ID = 50;

    private const int FIRST_UNSEEDED_BUILTIN_ID = 6;

    public function up(): void
    {
        DB::transaction(fn () => $this->moveAdminPrompts());

        $this->raiseAutoIncrement();
    }

    /**
     * Nothing to undo: moving the prompts back would reopen the collision.
     */
    public function down(): void {}

    /**
     * @return array<int, int> old id => new id
     */
    public function moveAdminPrompts(): array
    {
        $prompts = DB::table('ai_system_prompts')
            ->whereBetween('id', [self::FIRST_UNSEEDED_BUILTIN_ID, self::FIRST_ADMIN_ID - 1])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $nextId = max(self::FIRST_ADMIN_ID, (int) DB::table('ai_system_prompts')->max('id') + 1);
        $moved = [];

        foreach ($prompts as $prompt) {
            $row = (array) $prompt;
            $row['id'] = $nextId;

            DB::table('ai_system_prompts')->insert($row);
            DB::table('ai_systems')->where('system_prompt_id', $prompt->id)->update(['system_prompt_id' => $nextId]);
            DB::table('ai_system_prompts')->where('id', $prompt->id)->delete();

            Log::info("reserve_builtin_ai_system_prompt_ids: moved prompt \"{$prompt->title}\" {$prompt->id} → {$nextId}");
            $moved[$prompt->id] = $nextId;
            $nextId++;
        }

        return $moved;
    }

    /**
     * MySQL never sets AUTO_INCREMENT below MAX(id) + 1, so this cannot clash
     * with existing rows. ALTER TABLE commits implicitly, so it runs outside
     * the transaction above.
     */
    private function raiseAutoIncrement(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $next = max(self::FIRST_ADMIN_ID, (int) DB::table('ai_system_prompts')->max('id') + 1);

        DB::statement("ALTER TABLE ai_system_prompts AUTO_INCREMENT = {$next}");
    }
};
