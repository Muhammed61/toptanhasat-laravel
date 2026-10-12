<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteSettings\Pages\EditSiteSettings;
use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Mail\ContactMessageReceived;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\MailSettings;
use Database\Seeders\ContentSeeder;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function smtpForm(array $overrides = []): array
    {
        return [
            'data.mail.recipient' => 'gelen@example.com',
            'data.mail.from_address' => 'gonderen@example.com',
            'data.mail.from_name' => 'Toptanhasat',
            'data.mail.driver' => 'smtp',
            'data.mail.host' => 'smtp.example.com',
            'data.mail.port' => 587,
            'data.mail.username' => 'gonderen@example.com',
            'data.mail.encryption' => 'tls',
            'data.mail.password' => 'gizli-sifre-123',
            ...$overrides,
        ];
    }

    public function test_defaults_to_not_sending(): void
    {
        $this->assertFalse(MailSettings::isActive());
        Livewire::actingAs($this->admin)
            ->test(EditSiteSettings::class)
            ->assertSchemaStateSet(['data.mail.driver' => 'log', 'data.mail.encryption' => 'tls', 'data.mail.port' => 587]);
    }

    public function test_saves_settings_with_encrypted_password_and_applies_them(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditSiteSettings::class)
            ->fillForm($this->smtpForm())
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['data.mail.password' => 'gizli-sifre-123']); // kayıttan sonra alanda kalır

        $stored = SiteSetting::get('mail.password');
        $this->assertNotSame('gizli-sifre-123', $stored);
        $this->assertStringNotContainsString('gizli-sifre-123', json_encode(SiteSetting::current()->data));
        $this->assertSame('gizli-sifre-123', MailSettings::get()['password']);
        $this->assertTrue(MailSettings::isActive());
        $this->assertSame('Mail gönderimi aktif (SMTP: smtp.example.com:587)', MailSettings::statusLabel());

        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('gizli-sifre-123', config('mail.mailers.smtp.password'));
        $this->assertSame('gonderen@example.com', config('mail.from.address'));

        // Form açılınca kayıtlı şifre alanda (gizli, göster/gizle ile) gelir; başka alan değişince korunur
        Livewire::actingAs($this->admin)
            ->test(EditSiteSettings::class)
            ->assertSchemaStateSet(['data.mail.password' => 'gizli-sifre-123', 'data.mail.host' => 'smtp.example.com'])
            ->fillForm(['data.mail.host' => 'smtp.gmail.com'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('gizli-sifre-123', MailSettings::get()['password']);
        $this->assertSame('smtp.gmail.com', MailSettings::get()['host']);

        // Şifre değiştirilip kaydedilir; alan silinirse şifre kaldırılır
        Livewire::actingAs($this->admin)->test(EditSiteSettings::class)
            ->fillForm(['data.mail.password' => 'yeni-sifre-456'])->call('save');
        $this->assertSame('yeni-sifre-456', MailSettings::get()['password']);

        Livewire::actingAs($this->admin)->test(EditSiteSettings::class)
            ->fillForm(['data.mail.password' => ''])->call('save');
        $this->assertSame('', MailSettings::get()['password']);
    }

    public function test_mail_settings_are_not_public(): void
    {
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'mail' => ['host' => 'smtp.example.com', 'password' => MailSettings::encrypt('gizli')]];
        $setting->save();

        $response = $this->getJson('/api/site')->assertOk()->assertJsonMissingPath('settings.mail');
        $this->assertStringNotContainsString('smtp.example.com', $response->getContent());
    }

    public function test_contact_form_is_forwarded_when_mail_is_active(): void
    {
        Mail::fake();
        $payload = ['name' => 'Ali Veli', 'email' => 'ali@example.com', 'subject' => 'general', 'message' => 'Merhaba'];

        // Pasifken mail gitmez, mesaj yine kaydedilir
        $this->postJson('/api/contact', $payload)->assertCreated();
        Mail::assertNothingSent();

        Livewire::actingAs($this->admin)->test(EditSiteSettings::class)->fillForm($this->smtpForm())->call('save');
        app('auth')->forgetGuards();

        $this->postJson('/api/contact', $payload)->assertCreated();
        Mail::assertSent(ContactMessageReceived::class, fn (ContactMessageReceived $mail) => $mail->hasTo('gelen@example.com')
            && $mail->hasReplyTo('ali@example.com')
            && $mail->contact->message === 'Merhaba');
        $this->assertDatabaseCount('contact_messages', 2);
    }

    private function storeMail(array $mail): void
    {
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'mail' => $mail];
        $setting->save();
    }

    public function test_test_mail_asks_for_recipient_first(): void
    {
        SiteSettingResource::sendTestMail();
        Notification::assertNotified('Önce "Siteden Gelen Mailler (Alıcı)" adresini girip kaydedin');
    }

    public function test_test_mail_reports_connection_errors(): void
    {
        // Ulaşılamayan sunucu: hata mesajıyla bildirilir, uygulama çökmez
        $this->storeMail([
            'recipient' => 'gelen@example.com', 'from_address' => 'gonderen@example.com', 'driver' => 'smtp',
            'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none',
        ]);

        SiteSettingResource::sendTestMail();
        Notification::assertNotified('Test maili gönderilemedi');
    }

    public function test_test_mail_is_sent_with_saved_settings(): void
    {
        $this->storeMail([
            'recipient' => 'gelen@example.com', 'from_address' => 'gonderen@example.com', 'driver' => 'smtp',
            'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
        ]);
        Mail::fake();

        SiteSettingResource::sendTestMail();
        Notification::assertNotified('Test maili gelen@example.com adresine gönderildi');
    }
}
