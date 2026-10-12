<?php

namespace Tests\Feature;

use App\Filament\Resources\ListingComments\Pages\ListListingComments;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListingCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $this->owner = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Çiftçi']);
        $this->listing = Listing::factory()->for($this->owner)->create();
        $this->listing->approve(User::factory()->create());
    }

    public function test_guest_can_comment_without_approval_and_everyone_sees_it(): void
    {
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['name' => ' Ayşe ', 'body' => ' Teslimat Bursa\'ya olur mu? '])
            ->assertCreated()
            ->assertJsonPath('name', 'Ayşe')
            ->assertJsonPath('body', 'Teslimat Bursa\'ya olur mu?')
            ->assertJsonPath('is_owner', false);

        $this->getJson("/api/listings/{$this->listing->id}/comments")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Ayşe');
    }

    public function test_listing_owner_reply_is_marked_and_uses_account_name(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/listings/{$this->listing->id}/comments", ['name' => 'Başka Biri', 'body' => 'Evet, olur.'])
            ->assertCreated()
            ->assertJsonPath('name', 'Ali Çiftçi')
            ->assertJsonPath('is_owner', true);
    }

    public function test_validation_honeypot_and_unpublished_listing(): void
    {
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['name' => '', 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Adınızı yazın.')
            ->assertJsonPath('errors.body.0', 'Yorumunuzu yazın.');

        $this->postJson("/api/listings/{$this->listing->id}/comments", ['name' => 'Bot', 'body' => 'Ucuz ürün', 'website' => 'http://spam.example'])
            ->assertUnprocessable();

        $pending = Listing::factory()->create(); // onay bekliyor
        $this->postJson("/api/listings/{$pending->id}/comments", ['name' => 'Ayşe', 'body' => 'Merhaba'])->assertNotFound();
        $this->getJson("/api/listings/{$pending->id}/comments")->assertNotFound();

        $this->assertSame(0, ListingComment::count());
    }

    public function test_comments_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson("/api/listings/{$this->listing->id}/comments", ['name' => 'Ayşe', 'body' => "Yorum {$i}"])->assertCreated();
        }
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['name' => 'Ayşe', 'body' => 'Bir tane daha'])->assertStatus(429);
    }

    public function test_admin_sees_and_deletes_comments(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $comment = $this->listing->comments()->create(['name' => 'Ayşe', 'body' => 'Uygunsuz yorum', 'ip_address' => '1.2.3.4']);

        $this->actingAs($admin)->get('/admin/listing-comments')->assertOk()->assertSee('Uygunsuz yorum');

        Livewire::actingAs($admin)
            ->test(ListListingComments::class)
            ->assertCanSeeTableRecords([$comment])
            ->callAction(TestAction::make('delete')->table($comment));

        $this->assertModelMissing($comment);
    }

    public function test_comments_are_removed_with_the_listing(): void
    {
        $this->listing->comments()->create(['name' => 'Ayşe', 'body' => 'Merhaba']);
        $this->listing->delete();

        $this->assertSame(0, ListingComment::count());
    }
}
