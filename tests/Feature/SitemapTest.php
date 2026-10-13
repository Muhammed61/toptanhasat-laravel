<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_public_pages_and_only_approved_listings(): void
    {
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $approved = Listing::factory()->create();
        $approved->approve($admin);
        $pending = Listing::factory()->create();
        $rejected = Listing::factory()->create();
        $rejected->reject($admin, 'x');

        $response = $this->getJson('/api/sitemap')->assertOk();

        $paths = collect($response->json('pages'))->pluck('path');
        $this->assertSame('/', $paths->first());
        $this->assertContains('/about', $paths);
        $this->assertNotContains('/auth', $paths);

        $listingPaths = collect($response->json('listings'))->pluck('path')->all();
        $this->assertSame(["/ilan/{$approved->id}"], $listingPaths);
        $this->assertNotContains("/ilan/{$pending->id}", $listingPaths);
        $this->assertNotNull($response->json('listings.0.updated_at'));
    }
}
