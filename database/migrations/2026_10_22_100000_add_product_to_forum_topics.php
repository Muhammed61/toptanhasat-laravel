<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forumda alt başlık: kategorinin ürünleri (Tahıl → Çeltik, Buğday…). Konu bir ürüne bağlanabilir;
 * ürün adı saklanır (katalogdan ürün silinse de konu kaybolmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->string('product', 80)->nullable()->after('category_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropIndex(['product']);
            $table->dropColumn('product');
        });
    }
};
