<?php

use App\Models\CommentEmailOptOut;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An opt-out keeps two keys: `email`, the address that opted out (which
     * alone can lift it), and `mailbox`, the broader key that decides which
     * spellings it blocks. Existing rows were stored by mailbox key, so their
     * `mailbox` is computed from `email`. The table has never held production
     * data, so this only touches local rows.
     */
    public function up(): void
    {
        Schema::table('comment_email_opt_outs', function (Blueprint $table) {
            $table->string('mailbox')->nullable()->after('email');
        });

        DB::table('comment_email_opt_outs')->orderBy('id')->each(function (object $row): void {
            DB::table('comment_email_opt_outs')
                ->where('id', $row->id)
                ->update(['mailbox' => CommentEmailOptOut::normalize($row->email)]);
        });

        Schema::table('comment_email_opt_outs', function (Blueprint $table) {
            $table->string('mailbox')->nullable(false)->change();
            $table->index('mailbox');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comment_email_opt_outs', function (Blueprint $table) {
            $table->dropIndex(['mailbox']);
            $table->dropColumn('mailbox');
        });
    }
};
