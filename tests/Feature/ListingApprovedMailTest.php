<?php

namespace Tests\Feature;

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ListingApprovedNotification;
use App\Notifications\ListingRejectedNotification;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ListingApprovedMailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->owner = User::factory()->create(['first_name' => 'Mehmet']);
        Notification::fake();
    }

    private function enableMail(): void
    {
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'mail' => [
            'driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'no-reply@example.com',
        ]];
        $setting->save();
    }

    public function test_owner_is_mailed_when_admin_approves_from_panel(): void
    {
        $this->enableMail();
        $listing = Listing::factory()->for($this->owner)->create(['product' => 'Domates', 'amount' => 5, 'unit' => 'ton']);

        Livewire::actingAs($this->admin)
            ->test(ListListings::class)
            ->callAction(TestAction::make('approve')->table($listing));
        app()->terminate(); // mail yanıttan sonra gönderilir

        Notification::assertSentTo($this->owner, ListingApprovedNotification::class, function (ListingApprovedNotification $n) use ($listing) {
            $mail = $n->toMail($this->owner);

            return $n->listing->is($listing)
                && $mail->subject === '"5 ton Domates" ilanınız yayına alındı'
                && $mail->greeting === 'Merhaba Mehmet,'
                && $mail->actionUrl === rtrim(config('app.frontend_url'), '/')."/ilan/{$listing->id}";
        });
    }

    public function test_already_approved_listing_does_not_mail_again(): void
    {
        $this->enableMail();
        $listing = Listing::factory()->for($this->owner)->create();

        $listing->approve($this->admin);
        $listing->approve($this->admin); // örn. toplu onayda yayındaki ilan da seçildiyse
        app()->terminate();

        Notification::assertSentToTimes($this->owner, ListingApprovedNotification::class, 1);
    }

    public function test_reapproval_after_edit_mails_again(): void
    {
        $this->enableMail();
        $listing = Listing::factory()->for($this->owner)->create();
        $listing->approve($this->admin);

        $listing->resubmit(); // sahibi içeriği değiştirdi → tekrar onaya düştü
        $listing->save();
        $listing->approve($this->admin);
        app()->terminate();

        Notification::assertSentToTimes($this->owner, ListingApprovedNotification::class, 2);
    }

    public function test_no_mail_when_smtp_is_off(): void
    {
        Listing::factory()->for($this->owner)->create()->approve($this->admin);
        app()->terminate();

        Notification::assertNothingSent();
    }

    public function test_owner_is_mailed_with_reason_when_admin_rejects(): void
    {
        $this->enableMail();
        $listing = Listing::factory()->for($this->owner)->create(['product' => 'Domates', 'amount' => 5, 'unit' => 'ton']);

        Livewire::actingAs($this->admin)
            ->test(ListListings::class)
            ->callAction(TestAction::make('reject')->table($listing), ['reason' => 'Fiyat bilgisi eksik'])
            ->assertHasNoFormErrors();
        app()->terminate();

        Notification::assertSentTo($this->owner, ListingRejectedNotification::class, function (ListingRejectedNotification $n) use ($listing) {
            $mail = $n->toMail($this->owner);

            return $mail->subject === '"5 ton Domates" ilanınız yayına alınmadı'
                && in_array('Sebep: Fiyat bilgisi eksik', $mail->introLines, true)
                && $mail->actionUrl === rtrim(config('app.frontend_url'), '/')."/ilanlarim/{$listing->id}/duzenle";
        });
    }

    public function test_rejecting_again_with_same_reason_does_not_mail_twice(): void
    {
        $this->enableMail();
        $listing = Listing::factory()->for($this->owner)->create();

        $listing->reject($this->admin, 'Eksik bilgi');
        $listing->reject($this->admin, 'Eksik bilgi');
        $listing->reject($this->admin, 'Kategori yanlış'); // sebep değişirse yeni mail
        app()->terminate();

        Notification::assertSentToTimes($this->owner, ListingRejectedNotification::class, 2);
    }

    public function test_public_listing_includes_owner_photo(): void
    {
        $listing = Listing::factory()->for($this->owner)->create();
        $listing->approve($this->admin);

        $this->getJson("/api/listings/{$listing->id}")->assertOk()->assertJsonPath('owner.avatar_url', null);

        $this->owner->forceFill(['avatar_path' => 'avatars/mehmet.jpg'])->saveQuietly();
        $this->getJson("/api/listings/{$listing->id}")
            ->assertJsonPath('owner.avatar_url', fn ($url) => str_ends_with($url, '/uploads/avatars/mehmet.jpg'));
    }
}
