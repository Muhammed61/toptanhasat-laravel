<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);

        $this->seller = User::factory()->create(['first_name' => 'Satıcı', 'last_name' => 'Bey']);
        $this->buyer = User::factory()->create(['first_name' => 'Alıcı', 'last_name' => 'Hanım']);
        $this->listing = Listing::factory()->for($this->seller)->create(['show_phone' => false]);
        $this->listing->approve(User::factory()->create());
    }

    /** Testte guard'lar istekler arasında kullanıcıyı önbellekler; her kullanıcı değişiminde sıfırla */
    private function as(User $user): static
    {
        app('auth')->forgetGuards();

        return $this->actingAs($user);
    }

    public function test_guest_cannot_message(): void
    {
        $this->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Merhaba'])->assertUnauthorized();
        $this->getJson('/api/conversations')->assertUnauthorized();
    }

    public function test_buyer_starts_conversation_and_seller_replies(): void
    {
        $id = $this->as($this->buyer)
            ->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => '  Merhaba, hâlâ satılık mı?  '])
            ->assertCreated()
            ->json('id');

        // Satıcıda okunmamış olarak görünür
        $this->as($this->seller)->getJson('/api/conversations/unread-count')->assertJsonPath('count', 1);
        $this->as($this->seller)->getJson('/api/conversations')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.role', 'seller')
            ->assertJsonPath('data.0.other.name', 'Alıcı Hanım')
            ->assertJsonPath('data.0.unread', true)
            ->assertJsonPath('data.0.last_message.body', 'Merhaba, hâlâ satılık mı?');

        // Açınca okundu sayılır
        $this->as($this->seller)->getJson("/api/conversations/$id")
            ->assertOk()
            ->assertJsonPath('messages.0.mine', false)
            ->assertJsonPath('conversation.listing.title', $this->listing->title());
        $this->as($this->seller)->getJson('/api/conversations/unread-count')->assertJsonPath('count', 0);

        $this->as($this->seller)->postJson("/api/conversations/$id/messages", ['body' => 'Evet, satılık.'])
            ->assertCreated()
            ->assertJsonPath('mine', true);

        $this->as($this->buyer)->getJson('/api/conversations/unread-count')->assertJsonPath('count', 1);
        $this->as($this->seller)->getJson('/api/conversations/unread-count')->assertJsonPath('count', 0);
    }

    public function test_second_message_on_same_listing_reuses_conversation(): void
    {
        $first = $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Bir'])->json('id');
        $second = $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'İki'])->json('id');

        $this->assertSame($first, $second);
        $this->assertSame(2, Conversation::find($first)->messages()->count());
    }

    public function test_polling_returns_only_new_messages(): void
    {
        $id = $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Bir'])->json('id');
        $lastId = $this->as($this->buyer)->getJson("/api/conversations/$id")->json('messages.0.id');

        $this->as($this->seller)->postJson("/api/conversations/$id/messages", ['body' => 'İki']);

        $this->as($this->buyer)->getJson("/api/conversations/$id?after=$lastId")
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'İki');
    }

    public function test_outsiders_cannot_read_or_write(): void
    {
        $id = $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Gizli'])->json('id');
        $stranger = User::factory()->create();

        $this->as($stranger)->getJson("/api/conversations/$id")->assertNotFound();
        $this->as($stranger)->postJson("/api/conversations/$id/messages", ['body' => 'x'])->assertNotFound();
        $this->as($stranger)->getJson('/api/conversations')->assertJsonCount(0, 'data');
    }

    public function test_cannot_message_own_or_unpublished_listing(): void
    {
        $this->as($this->seller)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'x'])->assertStatus(422);

        $pending = Listing::factory()->create();
        $this->as($this->buyer)->postJson("/api/listings/{$pending->id}/conversations", ['body' => 'x'])->assertNotFound();
    }

    public function test_body_is_validated(): void
    {
        $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'Mesajınızı yazın.');
        $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => str_repeat('a', 2001)])
            ->assertJsonPath('errors.body.0', 'Mesaj en fazla 2000 karakter olabilir.');
    }

    public function test_conversation_survives_listing_deletion(): void
    {
        $id = $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Merhaba'])->json('id');
        $title = $this->listing->title();

        $this->listing->delete();

        $this->as($this->buyer)->getJson("/api/conversations/$id")
            ->assertOk()
            ->assertJsonPath('conversation.listing.available', false)
            ->assertJsonPath('conversation.listing.title', $title)
            ->assertJsonCount(1, 'messages');
    }

    private function setMessaging(bool $enabled): void
    {
        $setting = SiteSetting::current();
        $setting->data = [...$setting->data, 'messaging_enabled' => $enabled];
        $setting->save();
    }

    public function test_admin_can_turn_messaging_off_and_messages_are_kept(): void
    {
        $id = $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Merhaba'])->json('id');
        $this->getJson('/api/site')->assertJsonPath('settings.messaging_enabled', true);

        $this->setMessaging(false);

        $this->getJson('/api/site')->assertJsonPath('settings.messaging_enabled', false);
        $this->as($this->buyer)->postJson("/api/listings/{$this->listing->id}/conversations", ['body' => 'Tekrar'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Site içi mesajlaşma şu anda kapalı.');
        $this->as($this->seller)->postJson("/api/conversations/$id/messages", ['body' => 'Cevap'])->assertForbidden();
        $this->as($this->seller)->getJson('/api/conversations')->assertForbidden();
        $this->as($this->seller)->getJson("/api/conversations/$id")->assertForbidden();
        $this->assertDatabaseCount('messages', 1);

        // Telefonla iletişim etkilenmez
        $this->listing->forceFill(['show_phone' => true])->save();
        $this->as($this->buyer)->getJson("/api/listings/{$this->listing->id}/contact")->assertOk();

        // Tekrar açınca kaldığı yerden
        $this->setMessaging(true);
        $this->as($this->seller)->getJson("/api/conversations/$id")->assertOk()->assertJsonCount(1, 'messages');
        $this->as($this->seller)->postJson("/api/conversations/$id/messages", ['body' => 'Cevap'])->assertCreated();
    }
}
