<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
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

    public function test_guest_is_redirected_and_member_is_forbidden(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_dashboard_and_pending_listings(): void
    {
        $pending = Listing::factory()->create(['product' => 'Domates', 'category' => 'sebze']);
        $approved = Listing::factory()->create();
        $approved->approve($this->admin);

        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Onay bekleyen ilan');
        $this->actingAs($this->admin)->get("/admin/listings/{$pending->id}")->assertOk()->assertSee($pending->title());

        Livewire::actingAs($this->admin)
            ->test(ListListings::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved]); // varsayılan sekme: onay bekleyen
    }

    public function test_admin_approves_and_rejects_from_table(): void
    {
        $listing = Listing::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(ListListings::class)
            ->callAction(TestAction::make('reject')->table($listing), ['reason' => 'Fotoğraflar ürünle ilgisiz'])
            ->assertHasNoFormErrors();

        $this->assertSame(ListingStatus::Rejected, $listing->fresh()->status);
        $this->assertSame('Fotoğraflar ürünle ilgisiz', $listing->fresh()->rejection_reason);

        Livewire::actingAs($this->admin)
            ->test(ViewListing::class, ['record' => $listing->id])
            ->callAction('approve');

        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->assertSame($this->admin->id, $listing->fresh()->reviewed_by);
    }

    public function test_reject_requires_a_reason(): void
    {
        $listing = Listing::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(ListListings::class)
            ->callAction(TestAction::make('reject')->table($listing), ['reason' => ''])
            ->assertHasFormErrors(['reason' => 'required']);

        $this->assertSame(ListingStatus::Pending, $listing->fresh()->status);
    }

    public function test_admin_can_grant_admin_but_not_revoke_own(): void
    {
        $member = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $member->id])
            ->fillForm(['is_admin' => true])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($member->fresh()->is_admin);

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->admin->id])
            ->assertFormFieldIsDisabled('is_admin');
    }

    public function test_deleting_user_removes_listing_photos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $member = User::factory()->create();
        $listing = Listing::factory()->for($member)->create();
        $path = \Illuminate\Http\UploadedFile::fake()->image('a.jpg')->store("listings/{$listing->id}", 'public');
        $listing->photos()->create(['path' => $path]);

        $member->delete();

        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
    }
}
