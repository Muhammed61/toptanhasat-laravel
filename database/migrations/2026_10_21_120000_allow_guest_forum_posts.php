<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forumda üye olmadan da konu açılır ve cevap yazılır: misafirler IP'ye bağlı
 * "Misafir04217" gibi bir adla görünür (ilan yorumlarındaki gibi).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['forum_topics', 'forum_replies'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('user_id')->nullable()->change();
                $t->string('author_name', 60)->nullable()->after('user_id');
                $t->string('ip_address', 45)->nullable()->after('author_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['forum_topics', 'forum_replies'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['author_name', 'ip_address']);
            });
        }
    }
};
