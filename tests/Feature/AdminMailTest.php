<?php

namespace Tests\Feature;

use App\Mail\AdminAlert;
use App\Models\Listing;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMailTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_INBOX = 'yonetici@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        Mail::fake();
        $this->mailSettings();
    }

    private function mailSettings(array $extra = []): void
    {
        SiteSetting::put('mail', [
            'driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587,
            'from_address' => 'no-reply@example.com', 'recipient' => self::ADMIN_INBOX,
            ...$extra,
        ]);
    }

    /** Gönderilen yönetici maillerinin konuları ("yanıttan sonra" görevleri çalıştırılır, tekrarlar atılır) */
    private function subjects(): array
    {
        app()->terminate();

        return Mail::sent(AdminAlert::class, fn (AdminAlert $m) => $m->hasTo(self::ADMIN_INBOX))
            ->map(fn (AdminAlert $m) => $m->title)->unique()->values()->all();
    }

    /** Üye olma: SPA isteği gibi (Sanctum oturumu için Referer) */
    private function register(): void
    {
        $this->withHeader('Referer', 'http://localhost:5173/')->postJson('/api/auth/register', [
            'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@example.com', 'phone' => '0555 123 45 67',
            'password' => 'hasat2026', 'password_confirmation' => 'hasat2026', 'accept_terms' => true,
            'tc_kimlik' => '10000000146',
        ])->assertCreated();
    }

    private function listingPayload(array $overrides = []): array
    {
        return [
            'type' => 'buy', 'category' => 'sebze', 'product' => 'Domates', 'amount' => '5', 'unit' => 'ton',
            'price' => '12.5', 'price_negotiable' => '0', 'city_id' => 12, 'district_id' => 145,
            'show_phone' => '1', 'accept_responsibility' => '1', ...$overrides,
        ];
    }

    public function test_new_member_and_new_listing_are_mailed_to_the_admin(): void
    {
        $this->register();
        $this->postJson('/api/listings', $this->listingPayload())->assertCreated();

        $this->assertSame(['Yeni üye: Ayşe Yılmaz', 'Yeni ilan, onay bekliyor: 5 ton Domates'], $this->subjects());

        Mail::assertSent(AdminAlert::class, fn (AdminAlert $m) => $m->title === 'Yeni ilan, onay bekliyor: 5 ton Domates'
            && str_starts_with($m->actionUrl, rtrim(config('app.url'), '/').'/admin/listings/')
            && $m->rows['İlan sahibi'] === 'Ayşe Yılmaz');
    }

    public function test_edited_listing_is_mailed_only_when_it_goes_back_to_approval(): void
    {
        $owner = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/api/listings', $this->listingPayload())->assertCreated()->json('id');

        // Önce yalnızca telefon görünürlüğü değişir (onay durumu korunur, mail yok); sonra miktar değişir
        $this->actingAs($owner)->putJson("/api/listings/{$id}", $this->listingPayload(['show_phone' => '0']))->assertOk();
        $this->actingAs($owner)->putJson("/api/listings/{$id}", $this->listingPayload(['show_phone' => '0', 'amount' => '8']))->assertOk();

        $this->assertSame([
            'Yeni ilan, onay bekliyor: 5 ton Domates',
            'İlan güncellendi, onay bekliyor: 8 ton Domates',
        ], $this->subjects());
    }

    public function test_comments_and_new_conversations_are_mailed_but_not_admin_comments_or_every_message(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Alıcı']);
        $listing = Listing::factory()->for($seller)->create();
        $listing->approve($admin);

        $this->postJson("/api/listings/{$listing->id}/comments", ['body' => 'Misafir sorusu'])->assertCreated();
        $this->actingAs($admin)->postJson("/api/listings/{$listing->id}/comments", ['body' => 'Yönetici cevabı'])->assertCreated();
        app('auth')->forgetGuards();

        $this->actingAs($buyer)->postJson("/api/listings/{$listing->id}/conversations", ['body' => 'Merhaba'])->assertCreated();
        $this->actingAs($buyer)->postJson("/api/listings/{$listing->id}/conversations", ['body' => 'Fiyat?'])->assertCreated();

        $subjects = $this->subjects();
        $this->assertCount(1, array_filter($subjects, fn ($s) => str_starts_with($s, 'Yeni yorum: Misafir')));
        $this->assertCount(1, array_filter($subjects, fn ($s) => str_starts_with($s, 'Yeni mesajlaşma:')));
        $this->assertCount(2, $subjects);
    }

    public function test_admin_chooses_which_events_are_mailed_and_nothing_without_recipient(): void
    {
        $this->mailSettings(['admin_alerts' => ['listing']]); // yalnızca yeni ilan
        $this->register();
        $this->assertSame([], $this->subjects());

        $this->mailSettings(['recipient' => '']);
        $this->postJson('/api/listings', $this->listingPayload())->assertCreated();
        $this->assertSame([], $this->subjects());
    }
}
