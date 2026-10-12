<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '5551112233', 'password' => 'eski-sifre-1',
        ]);
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    public function test_profile_page_is_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/admin/profile')->assertOk()->assertSee('Profil ayarları');
        $this->actingAs(User::factory()->create())->get('/admin/profile')->assertForbidden();
    }

    public function test_theme_switcher_is_in_topbar_not_in_user_menu(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="fi-theme-switcher th-topbar-theme"'));
        // Filament'in kullanıcı menüsündeki kendi seçicisi (class="fi-theme-switcher") kapalı
        $this->assertStringNotContainsString('class="fi-theme-switcher"', $html);
    }

    public function test_admin_updates_name_and_phone(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditProfile::class)
            ->fillForm(['first_name' => 'Ayşegül', 'phone' => '0532 000 11 22'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->admin->refresh();
        $this->assertSame('Ayşegül', $this->admin->first_name);
        $this->assertSame('5320001122', $this->admin->phone);
        $this->assertTrue($this->admin->is_admin);
    }

    public function test_phone_must_be_valid_and_unique(): void
    {
        User::factory()->create(['phone' => '5329998877']);

        Livewire::actingAs($this->admin)
            ->test(EditProfile::class)
            ->fillForm(['phone' => '+90 532 999 88 77'])
            ->call('save')
            ->assertHasFormErrors(['phone']);

        Livewire::actingAs($this->admin)
            ->test(EditProfile::class)
            ->fillForm(['phone' => '0212 123 45 67'])
            ->call('save')
            ->assertHasFormErrors(['phone']);

        $this->assertSame('5551112233', $this->admin->fresh()->phone);
    }

    public function test_password_change_requires_current_password(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditProfile::class)
            ->fillForm(['password' => 'Yeni-Sifre-2026', 'passwordConfirmation' => 'Yeni-Sifre-2026', 'currentPassword' => 'yanlis'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);
        $this->assertTrue(Hash::check('eski-sifre-1', $this->admin->fresh()->password));

        Livewire::actingAs($this->admin)
            ->test(EditProfile::class)
            ->fillForm(['password' => 'Yeni-Sifre-2026', 'passwordConfirmation' => 'Yeni-Sifre-2026', 'currentPassword' => 'eski-sifre-1'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('Yeni-Sifre-2026', $this->admin->fresh()->password));
    }

    public function test_avatar_upload_replaces_old_file(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(EditProfile::class)
            ->fillForm(['avatar_path' => UploadedFile::fake()->image('ben.jpg', 300, 300)])
            ->call('save')
            ->assertHasNoFormErrors();

        $first = $this->admin->fresh()->avatar_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);
        $this->assertStringContainsString($first, $this->admin->fresh()->getFilamentAvatarUrl());

        // Ekrandaki gibi: önce mevcut fotoğraf kaldırılır, sonra yenisi yüklenir
        Livewire::actingAs($this->admin->fresh())
            ->test(EditProfile::class)
            ->fillForm(['avatar_path' => null])
            ->fillForm(['avatar_path' => [UploadedFile::fake()->image('yeni.png', 300, 300)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $second = $this->admin->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Fotoğrafı tamamen kaldırma: dosya da silinir, baş harf avatarına dönülür
        Livewire::actingAs($this->admin->fresh())
            ->test(EditProfile::class)
            ->fillForm(['avatar_path' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($this->admin->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_site_api_returns_avatar_url(): void
    {
        $this->actingAs($this->admin)->getJson('/api/user')->assertJsonPath('avatar_url', null);

        $this->admin->update(['avatar_path' => 'avatars/ben.jpg']);
        $this->actingAs($this->admin)->getJson('/api/user')
            ->assertJsonPath('avatar_url', fn ($url) => str_ends_with($url, '/storage/avatars/ben.jpg'));
    }

    public function test_default_avatar_is_generated_locally(): void
    {
        $url = (new InitialsAvatarProvider)->get($this->admin);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringContainsString('>AY<', base64_decode(substr($url, strlen('data:image/svg+xml;base64,'))));
        $this->assertStringNotContainsString('ui-avatars.com', $this->actingAs($this->admin)->get('/admin')->getContent());
    }
}
