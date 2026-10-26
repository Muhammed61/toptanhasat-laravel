<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // SPA isteği gibi davran: Sanctum session middleware'ini devreye sokar
        $this->withHeader('Referer', 'http://localhost:5173/');
    }

    private function registerPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'email' => 'Ayse@Example.com',
            'phone' => '0555 123 45 67',
            'password' => 'hasat2026',
            'password_confirmation' => 'hasat2026',
            'accept_terms' => true,
            'tc_kimlik' => '10000000146',
            ...$overrides,
        ];
    }

    public function test_register_creates_user_and_logs_in(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload())
            ->assertCreated()
            ->assertJson(['first_name' => 'Ayşe', 'email' => 'ayse@example.com', 'phone' => '5551234567'])
            ->assertJsonMissingPath('password');

        $this->assertAuthenticated();
        $this->assertNotNull(User::first()->terms_accepted_at);
    }

    public function test_register_validates_input_with_turkish_messages(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload([
            'phone' => '0212 123 45 67',
            'password' => 'kisa',
            'password_confirmation' => 'farkli',
            'accept_terms' => false,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'password', 'accept_terms'])
            ->assertJsonPath('errors.accept_terms.0', 'Devam etmek için kullanım koşullarını kabul etmelisiniz.');

        $this->assertGuest();
    }

    public function test_register_rejects_duplicate_email_and_phone(): void
    {
        User::factory()->create(['email' => 'ayse@example.com', 'phone' => '5551234567']);

        $this->postJson('/api/auth/register', $this->registerPayload(['phone' => '+90 555 123 4567']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Bu e-posta adresiyle kayıtlı bir hesap var.')
            ->assertJsonPath('errors.phone.0', 'Bu telefon numarasıyla kayıtlı bir hesap var.');
    }

    public function test_login_with_email_or_phone(): void
    {
        User::factory()->create(['email' => 'ayse@example.com', 'phone' => '5551234567', 'password' => 'hasat2026']);

        $this->postJson('/api/auth/login', ['identifier' => 'AYSE@example.com', 'password' => 'hasat2026'])
            ->assertOk()->assertJsonPath('email', 'ayse@example.com');
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest('web');

        $this->postJson('/api/auth/login', ['identifier' => '0555 123 45 67', 'password' => 'hasat2026'])
            ->assertOk()->assertJsonPath('phone', '5551234567');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ayse@example.com', 'password' => 'hasat2026']);

        $this->postJson('/api/auth/login', ['identifier' => 'ayse@example.com', 'password' => 'yanlis123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.identifier.0', 'E-posta/telefon veya şifre hatalı.');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['email' => 'ayse@example.com']);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', ['identifier' => 'ayse@example.com', 'password' => 'yanlis']);
        }

        $this->postJson('/api/auth/login', ['identifier' => 'ayse@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.identifier.0', fn ($m) => str_starts_with($m, 'Çok fazla deneme'));
    }

    public function test_current_user_endpoint(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }
}
