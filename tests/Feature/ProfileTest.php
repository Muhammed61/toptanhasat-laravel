<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'first_name' => 'Ayşe', 'last_name' => 'Yılmaz',
            'email' => 'ayse@example.com', 'phone' => '5551112233', 'password' => 'eski-sifre-1',
        ]);
    }

    public function test_guest_cannot_use_profile_endpoints(): void
    {
        $this->putJson('/api/user', [])->assertUnauthorized();
        $this->putJson('/api/user/password', [])->assertUnauthorized();
        $this->postJson('/api/user/avatar', [])->assertUnauthorized();
    }

    public function test_member_updates_own_info(): void
    {
        $this->actingAs($this->user)->putJson('/api/user', [
            'first_name' => '  Ayşe Nur ', 'last_name' => 'Kaya', 'email' => 'AYSE.NUR@Example.com', 'phone' => '0555 444 33 22',
        ])
            ->assertOk()
            ->assertJsonPath('first_name', 'Ayşe Nur')
            ->assertJsonPath('email', 'ayse.nur@example.com')
            ->assertJsonPath('phone', '5554443322');

        $this->assertSame('Kaya', $this->user->fresh()->last_name);
    }

    public function test_email_and_phone_must_be_unique_but_own_values_are_fine(): void
    {
        User::factory()->create(['email' => 'baska@example.com', 'phone' => '5559998877']);
        $base = ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz'];

        $this->actingAs($this->user)->putJson('/api/user', [...$base, 'email' => 'baska@example.com', 'phone' => '0555 999 88 77'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Bu e-posta adresiyle kayıtlı bir hesap var.')
            ->assertJsonPath('errors.phone.0', 'Bu telefon numarasıyla kayıtlı bir hesap var.');

        // Kendi e-postası/telefonu değişmeden kaydedilebilir
        $this->actingAs($this->user)->putJson('/api/user', [...$base, 'email' => 'ayse@example.com', 'phone' => '5551112233'])
            ->assertOk();
    }

    public function test_password_change_requires_current_password(): void
    {
        $this->actingAs($this->user)->putJson('/api/user/password', [
            'current_password' => 'yanlis', 'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'Mevcut şifreniz hatalı.');

        $this->actingAs($this->user)->putJson('/api/user/password', [
            'current_password' => 'eski-sifre-1', 'password' => 'kisa', 'password_confirmation' => 'kisa',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->actingAs($this->user)->putJson('/api/user/password', [
            'current_password' => 'eski-sifre-1', 'password' => 'yeni-sifre-2', 'password_confirmation' => 'yeni-sifre-2',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('yeni-sifre-2', $this->user->fresh()->password));
    }

    public function test_avatar_upload_replace_and_remove(): void
    {
        Storage::fake('public');

        $first = $this->actingAs($this->user)
            ->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('avatar_url');
        $this->assertNotNull($first);
        $firstPath = $this->user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($firstPath);

        // Yenisi yüklenince eskisi silinir
        $this->actingAs($this->user)
            ->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->image('b.png', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('public')->assertMissing($firstPath);

        $this->actingAs($this->user)
            ->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->create('belge.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);

        $secondPath = $this->user->fresh()->avatar_path;
        $this->actingAs($this->user)->deleteJson('/api/user/avatar')->assertOk()->assertJsonPath('avatar_url', null);
        Storage::disk('public')->assertMissing($secondPath);
    }
}
