<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['first_name' => 'Ayşe', 'email' => 'ayse@example.com', 'password' => 'eski-sifre-1']);
    }

    private function enableMail(): void
    {
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'mail' => [
            'driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'no-reply@example.com',
        ]];
        $setting->save();
    }

    public function test_forgot_sends_turkish_mail_with_site_link(): void
    {
        $this->enableMail();
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => ' AYSE@example.com '])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'şifre sıfırlama bağlantısı gönderildi'));

        Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) {
            $mail = $n->toMail($this->user);
            $url = $n->url($this->user);

            return str_starts_with($url, rtrim(config('app.frontend_url'), '/').'/sifre-sifirla?token=')
                && str_contains($url, 'email=ayse%40example.com')
                && $mail->actionUrl === $url
                && $mail->actionText === 'Yeni şifre belirle'
                && $mail->greeting === 'Merhaba Ayşe,';
        });
    }

    public function test_unknown_email_gets_same_answer_and_no_mail(): void
    {
        $this->enableMail();
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'yok@example.com'])->assertOk();
        Notification::assertNothingSent();
    }

    public function test_forgot_reports_when_mail_is_not_configured(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'ayse@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'e-posta gönderilemiyor'));
        Notification::assertNothingSent();
    }

    public function test_reset_with_valid_token_changes_password_and_ends_other_sessions(): void
    {
        $token = Password::createToken($this->user);
        DB::table('sessions')->insert([
            'id' => 'baska-cihaz', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'ayse@example.com', 'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertOk()->assertJsonPath('message', 'Şifreniz sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz.');

        $this->assertTrue(Hash::check('yeni-sifre-2', $this->user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'baska-cihaz']);

        // Bağlantı tek kullanımlık
        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'ayse@example.com', 'password' => 'baska-sifre-3', 'password_confirmation' => 'baska-sifre-3',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    }

    public function test_reset_rejects_bad_token_and_weak_password(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'token' => 'sahte', 'email' => 'ayse@example.com', 'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertUnprocessable()->assertJsonPath('errors.token.0', 'Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş. Lütfen yeni bir bağlantı isteyin.');

        $this->postJson('/api/auth/reset-password', [
            'token' => Password::createToken($this->user), 'email' => 'ayse@example.com', 'password' => 'kisa', 'password_confirmation' => 'kisa',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('eski-sifre-1', $this->user->fresh()->password));
    }

    public function test_mail_renders_in_turkish(): void
    {
        $html = (string) (new ResetPasswordNotification('abc'))->toMail($this->user)->render();

        $this->assertStringContainsString('Yeni şifre belirle', $html);
        $this->assertStringContainsString('düğmesi çalışmazsa', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
    }
}
