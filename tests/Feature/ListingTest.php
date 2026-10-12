<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        Storage::fake('public');
    }

    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'sell',
            'category' => 'sebze',
            'product' => 'Domates',
            'amount' => '5',
            'unit' => 'ton',
            'price' => '12.5',
            'price_negotiable' => '0',
            'city_id' => 12,      // Balıkesir
            'district_id' => 145, // Bandırma
            'show_phone' => '1',
            'accept_responsibility' => '1',
            ...$overrides,
        ];
    }

    public function test_guest_cannot_create_listing(): void
    {
        $this->postJson('/api/listings', $this->payload())->assertUnauthorized();
    }

    public function test_member_creates_pending_listing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/listings', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('status_label', 'Onay bekliyor')
            ->assertJsonPath('title', '5 ton Domates')
            ->assertJsonPath('city.name', 'Balıkesir')
            ->assertJsonMissingPath('photos');

        $listing = Listing::first();
        $this->assertSame($user->id, $listing->user_id);
        $this->assertSame(ListingStatus::Pending, $listing->status);
    }

    public function test_negotiable_price_is_stored_as_null(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/listings', $this->payload(['price' => '', 'price_negotiable' => '1']))
            ->assertCreated()
            ->assertJsonPath('price', null)
            ->assertJsonPath('price_negotiable', true);
    }

    public function test_validates_product_district_and_responsibility(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/listings', $this->payload([
                'product' => 'Fındık',       // sebze kategorisinde yok
                'district_id' => 1,           // Adana/Aladağ, Balıkesir'e ait değil
                'accept_responsibility' => '0',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product', 'district_id', 'accept_responsibility'])
            ->assertJsonPath('errors.district_id.0', 'Seçilen ilçe bu ile ait değil.');
    }

    public function test_listings_have_no_photos_and_uploads_are_ignored(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/api/listings', $this->payload([
                'photos' => [UploadedFile::fake()->image('a.jpg')],
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertDatabaseCount('listing_photos', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_users_only_see_their_own_listings(): void
    {
        $user = User::factory()->create();
        Listing::factory()->for($user)->count(2)->create();
        Listing::factory()->create(); // başka kullanıcı

        $this->actingAs($user)->getJson('/api/my/listings')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_status_cannot_be_set_by_member(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/listings', $this->payload(['status' => 'approved']))
            ->assertCreated()
            ->assertJsonPath('status', 'pending');
    }

    public function test_approve_and_reject(): void
    {
        $admin = User::factory()->create();
        $listing = Listing::factory()->create();

        $listing->reject($admin, 'Fotoğraf ürünle ilgisiz');
        $this->assertSame(ListingStatus::Rejected, $listing->fresh()->status);
        $this->assertSame('Fotoğraf ürünle ilgisiz', $listing->fresh()->rejection_reason);

        $listing->approve($admin);
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->assertNull($listing->fresh()->rejection_reason);
        $this->assertSame($admin->id, $listing->fresh()->reviewed_by);
    }

    /** Onaylı ilan: payload ile oluşturulup onaylanır */
    private function approvedListing(User $owner): Listing
    {
        $this->actingAs($owner)->postJson('/api/listings', $this->payload())->assertCreated();
        $listing = Listing::latest('id')->first();
        $listing->approve(User::factory()->create());

        return $listing;
    }

    public function test_owner_edits_listing_and_it_goes_back_to_review(): void
    {
        $owner = User::factory()->create();
        $listing = $this->approvedListing($owner);

        $this->actingAs($owner)->getJson("/api/my/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('product', 'Domates')
            ->assertJsonPath('district.id', 145);

        $this->actingAs($owner)
            ->putJson("/api/listings/{$listing->id}", $this->payload(['amount' => '8', 'price' => '', 'price_negotiable' => '1']))
            ->assertOk()
            ->assertJsonPath('title', '8 ton Domates')
            ->assertJsonPath('price', null)
            ->assertJsonPath('status', 'pending');

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertNull($fresh->reviewed_at);
        $this->assertNull($fresh->reviewed_by);
    }

    public function test_only_phone_visibility_change_keeps_approval(): void
    {
        $owner = User::factory()->create();
        $listing = $this->approvedListing($owner);

        $this->actingAs($owner)
            ->putJson("/api/listings/{$listing->id}", $this->payload(['show_phone' => '0']))
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('show_phone', false);
    }

    public function test_others_cannot_view_or_edit_and_rules_still_apply(): void
    {
        $owner = User::factory()->create();
        $listing = $this->approvedListing($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson("/api/my/listings/{$listing->id}")->assertNotFound();
        $this->actingAs($stranger)->putJson("/api/listings/{$listing->id}", $this->payload(['amount' => '1']))->assertNotFound();
        $this->assertEquals(5, $listing->fresh()->amount);

        $this->actingAs($owner)
            ->putJson("/api/listings/{$listing->id}", $this->payload(['accept_responsibility' => '0']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_responsibility']);
    }
}
