<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordChangedMailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withoutCks()->create([
            'first_name' => 'Ayşe', 'email' => 'ayse@example.com', 'phone' => '5551112233', 'password' => 'eski-sifre-1',
        ]);
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

    public function test_mail_is_sent_when_password_changes_from_profile(): void
    {
        $this->enableMail();

        $this->actingAs($this->user)->putJson('/api/user/password', [
            'current_password' => 'eski-sifre-1', 'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertNoContent();

        Notification::assertSentTo($this->user, PasswordChangedNotification::class);
    }

    public function test_mail_is_sent_after_forgot_password_reset(): void
    {
        $this->enableMail();

        $this->postJson('/api/auth/reset-password', [
            'token' => Password::createToken($this->user), 'email' => 'ayse@example.com',
            'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertOk();

        Notification::assertSentTo($this->user, PasswordChangedNotification::class);
    }

    public function test_no_mail_for_other_profile_changes_or_when_mail_is_off(): void
    {
        $this->enableMail();
        $this->actingAs($this->user)->putJson('/api/user', [
            'first_name' => 'Ayşe', 'last_name' => 'Kaya', 'email' => 'ayse@example.com', 'phone' => '5551112233',
        ])->assertOk();
        Notification::assertNothingSent();

        // SMTP kapalıyken şifre değişse de mail denenmez
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'mail' => ['driver' => 'log']];
        $setting->save();

        $this->actingAs($this->user)->putJson('/api/user/password', [
            'current_password' => 'eski-sifre-1', 'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertNoContent();
        Notification::assertNothingSent();
    }

    public function test_mail_content_is_turkish_with_time_and_reset_link(): void
    {
        $mail = (new PasswordChangedNotification(Carbon::parse('2026-10-12 10:30:00', 'UTC')))->toMail($this->user);

        $this->assertSame('Merhaba Ayşe,', $mail->greeting);
        $this->assertStringContainsString('12 Ekim 2026, 13:30', implode(' ', $mail->introLines)); // İstanbul saati
        $this->assertSame(rtrim(config('app.frontend_url'), '/').'/sifremi-unuttum', $mail->actionUrl);
        $this->assertStringContainsString('şifreniz değiştirildi', $mail->subject);
    }
}
