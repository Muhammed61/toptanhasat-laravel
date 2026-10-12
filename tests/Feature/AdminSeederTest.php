<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private function configure(array $overrides = []): void
    {
        config(['admin' => [
            'email' => 'Yonetici@Example.com',
            'password' => 'Guclu-Sifre-2026',
            'first_name' => 'Muhammed',
            'last_name' => 'Günaydın',
            'phone' => '0555 111 22 33',
            ...$overrides,
        ]]);
    }

    public function test_creates_admin_from_env_values(): void
    {
        $this->configure();
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'yonetici@example.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertSame('5551112233', $admin->phone);
        $this->assertSame('Muhammed Günaydın', $admin->fullName());
        $this->assertTrue(Hash::check('Guclu-Sifre-2026', $admin->password));
        $this->assertNotNull($admin->terms_accepted_at);
    }

    public function test_running_again_does_not_duplicate_or_change_password(): void
    {
        $this->configure();
        $this->seed(AdminSeeder::class);

        $this->configure(['password' => 'baska-sifre-99']);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('Guclu-Sifre-2026', User::first()->password));
    }

    public function test_existing_member_with_that_email_becomes_admin(): void
    {
        $member = User::factory()->create(['email' => 'yonetici@example.com', 'password' => 'kendi-sifresi-1']);
        $this->configure();
        $this->seed(AdminSeeder::class);

        $member->refresh();
        $this->assertTrue($member->is_admin);
        $this->assertTrue(Hash::check('kendi-sifresi-1', $member->password));
        $this->assertSame(1, User::count());
    }

    public function test_defaults_create_admin_toptanhasat_with_password(): void
    {
        // .env'de ADMIN_* yokken config/admin.php varsayılanları
        $this->assertSame('admin@toptanhasat.com', config('admin.email'));
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@toptanhasat.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_does_nothing_without_or_with_invalid_values(): void
    {
        $this->configure(['email' => null, 'password' => null]);
        $this->seed(AdminSeeder::class);

        $this->configure(['password' => 'kisa']);
        $this->seed(AdminSeeder::class);

        $this->configure(['phone' => '123']);
        $this->seed(AdminSeeder::class);

        $this->assertSame(0, User::count());
    }
}
