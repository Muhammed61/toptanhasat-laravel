<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site içi mesajlaşma: her ilan için alıcı-satıcı arasında tek konuşma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // İlan silinse de konuşma kalsın; başlık ayrıca saklanır
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('listing_title');
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();   // konuşmayı başlatan
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();  // ilan sahibi
            $table->timestamp('last_message_at')->nullable()->index(); // sıralama için
            // Okunmamış takibi mesaj id'siyle (zaman damgası saniye hassasiyetinde çakışabilir)
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedBigInteger('buyer_read_id')->nullable();
            $table->unsignedBigInteger('seller_read_id')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'buyer_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
