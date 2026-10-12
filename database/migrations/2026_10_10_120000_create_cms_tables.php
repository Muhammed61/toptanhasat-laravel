<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin panelden yönetilen site içeriği: ayarlar, sayfalar, katalog, hal fiyatları, iletişim mesajları.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tek satırlık ayar kaydı; alanlar JSON içinde (logo, iletişim, SEO, sosyal medya...)
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->longText('body')->nullable();   // zengin metin (HTML)
            $table->json('content')->nullable();    // sayfaya özel alanlar (adımlar, SSS...)
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();   // ilanlarda saklanan anahtar
            $table->string('name', 50);
            $table->string('icon', 30);              // Vue ProduceIcon adı
            $table->string('badge', 20)->nullable(); // örn. "Sezonda"
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::create('market_prices', function (Blueprint $table) {
            $table->id();
            $table->string('product', 50);
            $table->decimal('price', 10, 2);
            $table->decimal('change', 6, 2)->default(0); // yüzde değişim
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject', 30);
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('market_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('site_settings');
    }
};
