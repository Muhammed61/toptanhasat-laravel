<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                 // App\Enums\ListingType
            $table->string('category', 20);             // config/catalog.php anahtarı
            $table->string('product', 50);
            $table->decimal('amount', 12, 2);
            $table->string('unit', 5);                  // ton | kg
            $table->decimal('price', 12, 2)->nullable(); // birim fiyat (₺), görüşülürse null
            $table->boolean('price_negotiable')->default(false);
            $table->date('available_on')->nullable();   // hazır olma / ihtiyaç tarihi
            $table->text('description')->nullable();
            $table->foreignId('city_id')->constrained();
            $table->foreignId('district_id')->constrained();
            $table->boolean('show_phone')->default(true);
            $table->string('status', 10)->default('pending')->index(); // App\Enums\ListingStatus
            $table->string('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('listing_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_photos');
        Schema::dropIfExists('listings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
