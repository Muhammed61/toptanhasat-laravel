<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forumda alt başlıklar: bir konunun içinde ayrı konular açılır (Çeltik → Çeltik fiyatları, Çeltik ilaçları).
 * Tek seviye: alt başlığın altına yeni alt başlık açılmaz. Üst konu silinince alt başlıkları da silinir.
 * Önceki ürüne bağlı alt başlık denemesi (product sütunu) kaldırılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forum_topics', 'product')) {
            Schema::table('forum_topics', function (Blueprint $table) {
                $table->dropIndex(['product']);
                $table->dropColumn('product');
            });
        }

        Schema::table('forum_topics', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('forum_topics')->cascadeOnDelete();
            $table->unsignedInteger('subtopics_count')->default(0)->after('replies_count');
        });
    }

    public function down(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('subtopics_count');
            $table->string('product', 80)->nullable()->after('category_id')->index();
        });
    }
};
