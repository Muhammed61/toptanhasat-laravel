<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // İlan detayındaki herkese açık yorumlar (üyelik/onay gerekmez; admin panelden silinebilir)
        Schema::create('listing_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // giriş yapmış yazdıysa
            $table->string('name', 60);
            $table->text('body');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_comments');
    }
};
