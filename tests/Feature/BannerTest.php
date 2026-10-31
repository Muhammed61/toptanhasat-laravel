<?php

namespace Tests\Feature;

use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Models\Banner;
use App\Models\User;
use App\Support\SiteData;
use Database\Seeders\BannerSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Ana sayfadaki reklam görselleri: panelden yönetilir, /api/site ile siteye gelir */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        Storage::fake('public');
    }

    public function test_starter_banners_are_seeded_once_and_listed_on_the_site(): void
    {
        $this->seed(BannerSeeder::class);
        $this->seed(BannerSeeder::class);
        $this->assertSame(count(BannerSeeder::BANNERS), Banner::count());
        Storage::disk('public')->assertExists('banners/ilan-ver.webp');

        Banner::where('url', '/?tur=satilik')->update(['is_active' => false]);
        SiteData::flush();

        $banners = $this->getJson('/api/site')->assertOk()->json('banners');
        $this->assertCount(2, $banners);
        $this->assertSame('/ilan-ver', $banners[0]['url']);
        $this->assertStringEndsWith('banners/ilan-ver.webp', $banners[0]['image_url']);
    }

    public function test_admin_manages_banners_and_file_is_removed_with_record(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->seed(BannerSeeder::class);

        $this->actingAs($admin)->get('/admin/banners')->assertOk()->assertSee('Narenciye sezonu');
        Livewire::actingAs($admin)->test(ManageBanners::class)->assertCanSeeTableRecords(Banner::all());

        Banner::where('url', '/ilan-ver')->first()->delete();
        Storage::disk('public')->assertMissing('banners/ilan-ver.webp');
        $this->assertCount(2, $this->getJson('/api/site')->json('banners'));
    }
}
