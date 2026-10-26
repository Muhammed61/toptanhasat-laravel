<?php

namespace Tests\Feature;

use App\Enums\CksStatus;
use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Listing;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\CksReviewedNotification;
use App\Support\TcKimlik;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** TC kimlik (kayıtta ve sonradan) ve satılık ilan için ÇKS belgesi + panel onayı */
class IdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        Storage::fake('documents');
        Storage::fake('public');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();

        // Üyeye giden mailler SMTP açıkken gönderilir
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'mail' => [
            'driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'no-reply@example.com',
        ]];
        $setting->save();
    }

    private function as(User $user): static
    {
        app('auth')->forgetGuards();

        return $this->actingAs($user);
    }

    private function register(array $overrides = [])
    {
        return $this->withHeader('Referer', 'http://localhost:5173/')->postJson('/api/auth/register', [
            'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@example.com', 'phone' => '0555 123 45 67',
            'password' => 'hasat2026', 'password_confirmation' => 'hasat2026', 'accept_terms' => true,
            'tc_kimlik' => '100 000 001 46',
            ...$overrides,
        ]);
    }

    private function listing(string $type): array
    {
        return [
            'type' => $type, 'category' => 'sebze', 'product' => 'Domates', 'amount' => '5', 'unit' => 'ton',
            'price' => '12.5', 'price_negotiable' => '0', 'city_id' => 12, 'district_id' => 145,
            'show_phone' => '1', 'accept_responsibility' => '1',
        ];
    }

    public function test_tc_number_checksum(): void
    {
        $this->assertTrue(TcKimlik::isValid('10000000146'));
        $this->assertFalse(TcKimlik::isValid('10000000147'));
        $this->assertFalse(TcKimlik::isValid('01234567890'));
        $this->assertFalse(TcKimlik::isValid('1234'));
        foreach (range(1, 20) as $_) {
            $this->assertTrue(TcKimlik::isValid(TcKimlik::random()));
        }
        $this->assertSame('100******46', TcKimlik::mask('10000000146'));
    }

    public function test_register_requires_a_valid_unique_tc_stored_encrypted(): void
    {
        $this->register(['tc_kimlik' => ''])->assertUnprocessable()->assertJsonPath('errors.tc_kimlik.0', 'TC kimlik numarası zorunludur.');
        $this->register(['tc_kimlik' => '12345678901'])->assertUnprocessable()
            ->assertJsonPath('errors.tc_kimlik.0', 'Geçerli bir TC kimlik numarası girin (11 hane).');

        $this->register()->assertCreated()
            ->assertJsonPath('has_tc', true)
            ->assertJsonPath('tc_masked', '100******46')
            ->assertJsonMissingPath('tc_kimlik');

        $user = User::where('email', 'ayse@example.com')->sole();
        $this->assertSame('10000000146', $user->tc_kimlik);
        $this->assertStringNotContainsString('10000000146', (string) DB::table('users')->where('id', $user->id)->value('tc_kimlik'));

        // Aynı TC ile ikinci hesap açılmaz
        app('auth')->forgetGuards();
        $this->register(['email' => 'baska@example.com', 'phone' => '0555 999 88 77'])->assertUnprocessable()
            ->assertJsonPath('errors.tc_kimlik.0', 'Bu TC kimlik numarasıyla kayıtlı bir hesap var.');
    }

    public function test_old_member_adds_tc_before_listing_or_messaging(): void
    {
        $old = User::factory()->withoutTc()->create();

        $this->as($old)->postJson('/api/listings', $this->listing('sell'))->assertForbidden()->assertJsonPath('code', 'tc_required');
        $other = Listing::factory()->create();
        $other->approve($this->admin);
        $this->as($old)->postJson("/api/listings/{$other->id}/conversations", ['body' => 'Merhaba'])->assertForbidden();

        $this->as($old)->postJson('/api/user/tc', ['tc_kimlik' => '1000000014'])->assertUnprocessable();
        $this->as($old)->postJson('/api/user/tc', ['tc_kimlik' => '10000000146'])->assertOk()->assertJsonPath('has_tc', true);
        // Sonradan değiştirilemez
        $this->as($old)->postJson('/api/user/tc', ['tc_kimlik' => TcKimlik::random()])->assertUnprocessable();

        $this->as($old)->postJson('/api/listings', $this->listing('sell'))->assertCreated();
    }

    public function test_sell_listing_needs_cks_and_admin_approval(): void
    {
        Notification::fake();
        $buyer = User::factory()->withoutCks()->create(['first_name' => 'Veli', 'last_name' => 'Kaya']);

        // Alım ilanı için ÇKS gerekmez; satılık için önce belge
        $this->as($buyer)->postJson('/api/listings', $this->listing('buy'))->assertCreated();
        $this->as($buyer)->postJson('/api/listings', $this->listing('sell'))->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Satılık ilan vermek için önce ÇKS numaranızı ve belgenizi gönderin.');

        $this->as($buyer)->postJson('/api/user/cks', ['cks_number' => '12345678'])->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->as($buyer)->post('/api/user/cks', [
            'cks_number' => '12345678',
            'document' => UploadedFile::fake()->create('cks.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('cks.status', 'pending');

        $buyer->refresh();
        Storage::disk('documents')->assertExists($buyer->cks_document_path);
        Notification::assertSentTo($this->admin, DatabaseNotification::class);

        // Onay beklerken satılık ilan verilir ama yayına alınamaz
        $id = $this->as($buyer)->postJson('/api/listings', $this->listing('sell'))->assertCreated()->json('id');
        app('auth')->forgetGuards();
        Livewire::actingAs($this->admin)->test(ViewListing::class, ['record' => $id])
            ->callAction('approve')
            ->assertNotified('Önce üyenin ÇKS belgesini onaylayın');
        $this->assertSame(ListingStatus::Pending, Listing::find($id)->status);

        // Belge panelden açılır (yalnızca yöneticiye)
        $this->as($buyer)->get("/admin/cks-belgesi/{$buyer->id}")->assertForbidden();
        $this->as($this->admin)->get("/admin/cks-belgesi/{$buyer->id}")->assertOk();

        app('auth')->forgetGuards();
        Livewire::actingAs($this->admin)->test(EditUser::class, ['record' => $buyer->id])
            ->callAction('approveCks')
            ->assertNotified('ÇKS belgesi onaylandı');
        $this->assertSame(CksStatus::Approved, $buyer->fresh()->cks_status);
        Notification::assertSentTo($buyer, CksReviewedNotification::class, fn ($n) => $n->approved);

        Livewire::actingAs($this->admin)->test(ViewListing::class, ['record' => $id])->callAction('approve');
        $this->assertSame(ListingStatus::Approved, Listing::find($id)->status);

        // Onaydan sonra ad soyad sitede değiştirilemez
        $this->as($buyer->fresh())->putJson('/api/user', [
            'first_name' => 'Başka', 'last_name' => 'Kaya', 'email' => $buyer->email, 'phone' => $buyer->phone,
        ])->assertUnprocessable()->assertJsonValidationErrors('first_name');
    }

    public function test_rejected_cks_can_be_sent_again(): void
    {
        Notification::fake();
        $user = User::factory()->withoutCks()->create();
        $user->submitCks('1234', 'cks/old.pdf');
        Storage::disk('documents')->put('cks/old.pdf', 'x');

        Livewire::actingAs($this->admin)->test(EditUser::class, ['record' => $user->id])
            ->callAction('rejectCks', ['reason' => 'Belge okunmuyor'])
            ->assertNotified('ÇKS belgesi reddedildi');
        $this->assertSame(CksStatus::Rejected, $user->fresh()->cks_status);
        Notification::assertSentTo($user, CksReviewedNotification::class, fn ($n) => ! $n->approved && $n->reason === 'Belge okunmuyor');

        $this->as($user->fresh())->getJson('/api/user')->assertJsonPath('cks.rejection_reason', 'Belge okunmuyor');
        $this->as($user->fresh())->post('/api/user/cks', [
            'cks_number' => '98765432',
            'document' => UploadedFile::fake()->image('cks.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('cks.status', 'pending');

        Storage::disk('documents')->assertMissing('cks/old.pdf');
    }
}
