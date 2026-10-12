<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function approved(array $attributes = []): Listing
    {
        $listing = Listing::factory()->create($attributes);
        $listing->approve($this->admin);

        return $listing;
    }

    public function test_only_approved_listings_are_public(): void
    {
        $approved = $this->approved();
        $pending = Listing::factory()->create();
        $rejected = Listing::factory()->create();
        $rejected->reject($this->admin, 'x');

        $this->getJson('/api/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.owner.phone');

        $this->getJson("/api/listings/{$pending->id}")->assertNotFound();
        $this->getJson("/api/listings/{$rejected->id}")->assertNotFound();
        $this->getJson("/api/listings/{$approved->id}")->assertOk()->assertJsonPath('title', $approved->title());
    }

    public function test_owner_and_admin_can_preview_pending_listing(): void
    {
        $pending = Listing::factory()->create();

        // Testte guard'lar istekler arasında kullanıcıyı önbellekler; her kullanıcı değişiminde sıfırla
        $as = function (User $user) {
            app('auth')->forgetGuards();

            return $this->actingAs($user);
        };

        $as($pending->user)->getJson("/api/listings/{$pending->id}")->assertOk()->assertJsonPath('is_own', true);
        $as($this->admin)->getJson("/api/listings/{$pending->id}")->assertOk()->assertJsonPath('is_own', false);
        $as(User::factory()->create())->getJson("/api/listings/{$pending->id}")->assertNotFound();
    }

    public function test_filters(): void
    {
        $tomato = $this->approved(['type' => 'sell', 'category' => 'sebze', 'product' => 'Domates', 'city_id' => 12, 'district_id' => 145, 'amount' => 3, 'unit' => 'ton']);
        $hazelnut = $this->approved(['type' => 'sell', 'category' => 'kuruyemis', 'product' => 'Fındık', 'city_id' => 12, 'district_id' => 152, 'amount' => 800, 'unit' => 'kg']);
        $wanted = $this->approved(['type' => 'buy', 'category' => 'sebze', 'product' => 'Domates', 'city_id' => 35, 'district_id' => 383, 'amount' => 20, 'unit' => 'ton']);

        $ids = fn (string $query) => collect($this->getJson("/api/listings?$query")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$tomato->id, $hazelnut->id], $ids('type=sell'));
        $this->assertSame([$wanted->id], $ids('type=buy'));
        $this->assertSame([$tomato->id, $wanted->id], $ids('product=Domates'));
        $this->assertSame([$hazelnut->id], $ids('category=kuruyemis'));
        $this->assertSame([$tomato->id, $hazelnut->id], $ids('city=12'));
        $this->assertSame([$hazelnut->id], $ids('city=12&district=152'));
        // Miktar kg'ye çevrilerek karşılaştırılır: 800 kg → 500kg-1ton, 3 ton → 1-5ton, 20 ton → 10ton+
        $this->assertSame([$hazelnut->id], $ids('amount=500kg-1ton'));
        $this->assertSame([$tomato->id], $ids('amount=1-5ton'));
        $this->assertSame([$wanted->id], $ids('amount=10ton%2B'));
    }

    public function test_listing_is_paginated_newest_first(): void
    {
        $listings = collect(range(1, 14))->map(fn ($i) => tap($this->approved(), fn ($l) => $l->forceFill(['created_at' => now()->subMinutes(20 - $i)])->save()));

        $first = $this->getJson('/api/listings')->assertOk();
        $first->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $listings->last()->id);

        $this->getJson('/api/listings?page=2')->assertJsonCount(2, 'data');
    }

    public function test_contact_info_is_members_only(): void
    {
        $owner = User::factory()->create(['phone' => '5321234567', 'email' => 'satici@example.com']);
        $withPhone = $this->approved(['user_id' => $owner->id, 'show_phone' => true]);
        $withoutPhone = $this->approved(['user_id' => $owner->id, 'show_phone' => false]);

        $this->getJson("/api/listings/{$withPhone->id}/contact")->assertUnauthorized();

        $member = User::factory()->create();
        $this->actingAs($member)->getJson("/api/listings/{$withPhone->id}/contact")
            ->assertOk()
            ->assertJsonPath('phone', '0532 123 45 67')
            ->assertJsonPath('phone_href', 'tel:+905321234567')
            ->assertJsonMissingPath('email');

        // Telefonunu paylaşmayan satıcıya sadece site içi mesajla ulaşılır; e-posta asla verilmez
        $this->actingAs($member)->getJson("/api/listings/{$withoutPhone->id}/contact")->assertNotFound();
        $this->getJson("/api/listings/{$withoutPhone->id}")->assertJsonPath('has_phone', false);
        $this->getJson("/api/listings/{$withPhone->id}")->assertJsonPath('has_phone', true);

        $pending = Listing::factory()->create();
        $this->actingAs($member)->getJson("/api/listings/{$pending->id}/contact")->assertNotFound();
    }

    public function test_owner_name_is_shortened_publicly(): void
    {
        $owner = User::factory()->create(['first_name' => 'Ahmet', 'last_name' => 'Çiftçi']);
        $listing = $this->approved(['user_id' => $owner->id]);

        $this->getJson("/api/listings/{$listing->id}")->assertJsonPath('owner.name', 'Ahmet Ç.');
    }
}
