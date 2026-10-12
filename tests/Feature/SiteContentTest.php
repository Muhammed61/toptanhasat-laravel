<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Filament\Resources\MarketPrices\Pages\ManageMarketPrices;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSettings;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\MarketPrice;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    public function test_site_endpoint_returns_settings_catalog_and_only_active_prices(): void
    {
        MarketPrice::first()->update(['is_active' => true]);

        $this->getJson('/api/site')
            ->assertOk()
            ->assertJsonPath('settings.site_name', 'Toptanhasat')
            ->assertJsonPath('settings.email', 'info@toptanhasat.com')
            ->assertJsonCount(6, 'categories')
            ->assertJsonPath('categories.2.badge', 'Sezonda')
            ->assertJsonCount(1, 'market_prices');
    }

    public function test_seeder_does_not_overwrite_admin_changes(): void
    {
        Page::where('slug', 'about')->update(['title' => 'Biz Kimiz']);
        $this->seed(ContentSeeder::class);

        $this->assertSame('Biz Kimiz', Page::where('slug', 'about')->value('title'));
        $this->assertSame(8, Page::count());
    }

    public function test_admin_saves_settings_and_site_reflects_it_immediately(): void
    {
        $this->getJson('/api/site')->assertJsonPath('settings.phone', '+90 (555) 123 45 67'); // önbelleğe al

        Livewire::actingAs($this->admin)
            ->test(EditSiteSettings::class)
            ->fillForm(['data.phone' => '+90 (266) 000 00 00', 'data.social.instagram' => 'https://instagram.com/toptanhasat'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/site')
            ->assertJsonPath('settings.phone', '+90 (266) 000 00 00')
            ->assertJsonPath('settings.social.instagram', 'https://instagram.com/toptanhasat');
    }

    public function test_every_page_edit_screen_renders(): void
    {
        Livewire::actingAs($this->admin)->test(ListPages::class)->assertCanSeeTableRecords(Page::all());

        foreach (Page::all() as $page) {
            Livewire::actingAs($this->admin)
                ->test(EditPage::class, ['record' => $page->getRouteKey()])
                ->assertOk()
                ->assertFormSet(['title' => $page->title]);
        }
    }

    public function test_page_body_is_sanitized_in_api(): void
    {
        Page::where('slug', 'about')->update(['body' => '<h2>Biz</h2><script>alert(1)</script><p onclick="x()">Merhaba</p>']);

        $body = $this->getJson('/api/pages/about')->assertOk()->json('body');

        $this->assertStringContainsString('<h2>Biz</h2>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->getJson('/api/pages/yok')->assertNotFound();
    }

    public function test_admin_edits_how_it_works_steps(): void
    {
        $page = Page::where('slug', 'how-it-works')->first();

        Livewire::actingAs($this->admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['content.cta_title' => 'Hadi başlayalım'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/pages/how-it-works')
            ->assertJsonPath('content.cta_title', 'Hadi başlayalım')
            ->assertJsonCount(4, 'content.steps');
    }

    public function test_admin_creates_category_with_products(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateCategory::class)
            ->fillForm(['name' => 'Yem ve Saman', 'slug' => 'yem-saman', 'icon' => 'tahil'])
            ->set('data.products', [['name' => 'Saman', 'is_active' => true], ['name' => 'Yonca', 'is_active' => true]])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('slug', 'yem-saman')->first();
        $this->assertSame(['Saman', 'Yonca'], $category->products->pluck('name')->all());
        $this->getJson('/api/site')->assertJsonFragment(['key' => 'yem-saman', 'label' => 'Yem ve Saman']);
    }

    public function test_inactive_category_and_product_are_rejected_for_new_listings(): void
    {
        Category::where('slug', 'meyve')->update(['is_active' => false]);
        Category::where('slug', 'sebze')->first()->products()->where('name', 'Biber')->update(['is_active' => false]);

        $member = User::factory()->create();
        $base = [
            'type' => 'sell', 'amount' => 1, 'unit' => 'ton', 'price' => 10, 'city_id' => 12, 'district_id' => 145,
            'accept_responsibility' => 1,
        ];

        $this->actingAs($member)->postJson('/api/listings', [...$base, 'category' => 'meyve', 'product' => 'Elma'])
            ->assertJsonValidationErrors(['category']);
        $this->actingAs($member)->postJson('/api/listings', [...$base, 'category' => 'sebze', 'product' => 'Biber'])
            ->assertJsonValidationErrors(['product']);
        $this->actingAs($member)->postJson('/api/listings', [...$base, 'category' => 'sebze', 'product' => 'Domates'])
            ->assertCreated();
    }

    public function test_market_prices_screen_renders(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageMarketPrices::class)
            ->assertCanSeeTableRecords(MarketPrice::all());
    }

    public function test_contact_form_creates_message_and_admin_reads_it(): void
    {
        $this->postJson('/api/contact', ['name' => 'Ali', 'email' => 'ali@example.com', 'subject' => 'general', 'message' => 'Merhaba'])
            ->assertCreated();
        $this->postJson('/api/contact', ['name' => '', 'email' => 'yanlis', 'subject' => 'xx', 'message' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.subject.0', 'Bir konu seçin.');

        $message = ContactMessage::first();
        $this->assertNull($message->read_at);

        Livewire::actingAs($this->admin)->test(ViewContactMessage::class, ['record' => $message->id])->assertOk();
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_cms_screens_are_admin_only(): void
    {
        $member = User::factory()->create();

        foreach (['/admin/site-settings', '/admin/pages', '/admin/categories', '/admin/market-prices', '/admin/contact-messages'] as $url) {
            $this->actingAs($member)->get($url)->assertForbidden();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }
}
