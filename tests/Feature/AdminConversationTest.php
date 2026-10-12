<?php

namespace Tests\Feature;

use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $buyer;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();

        $this->seller = User::factory()->create(['first_name' => 'Satıcı', 'last_name' => 'Bey']);
        $this->buyer = User::factory()->create(['first_name' => 'Alıcı', 'last_name' => 'Hanım']);
        $listing = Listing::factory()->for($this->seller)->create();
        $listing->approve($this->admin);

        $this->conversation = Conversation::create([
            'listing_id' => $listing->id,
            'listing_title' => $listing->title(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);
        $this->conversation->addMessage($this->buyer, 'Fiyatta pazarlık olur mu?');
        $this->conversation->addMessage($this->seller, 'Tonu 9000 TL son fiyat.');
    }

    public function test_member_cannot_open_admin_conversations(): void
    {
        $this->actingAs($this->buyer)->get('/admin/conversations')->assertForbidden();
    }

    public function test_admin_lists_and_searches_conversations(): void
    {
        $other = Conversation::create([
            'listing_id' => null,
            'listing_title' => '3 ton Elma',
            'buyer_id' => User::factory()->create()->id,
            'seller_id' => User::factory()->create()->id,
        ]);
        $other->addMessage($other->buyer, 'Elmalar ne zaman hazır?');

        $this->actingAs($this->admin)->get('/admin/conversations')->assertOk()
            ->assertSee('Satıcı Bey')->assertSee('Alıcı Hanım')->assertSee('İlan silinmiş');

        Livewire::actingAs($this->admin)
            ->test(ListConversations::class)
            ->assertCanSeeTableRecords([$this->conversation, $other])
            ->searchTable('pazarlık')
            ->assertCanSeeTableRecords([$this->conversation])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('Alıcı Hanım')
            ->assertCanSeeTableRecords([$this->conversation])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable(null)
            ->filterTable('user', $this->seller->id)
            ->assertCanSeeTableRecords([$this->conversation])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_admin_reads_without_changing_read_state(): void
    {
        $before = $this->conversation->fresh()->only(['buyer_read_id', 'seller_read_id']);

        $this->actingAs($this->admin)->get("/admin/conversations/{$this->conversation->id}")->assertOk()
            ->assertSee('Fiyatta pazarlık olur mu?')
            ->assertSee('Tonu 9000 TL son fiyat.');

        $this->assertSame($before, $this->conversation->fresh()->only(['buyer_read_id', 'seller_read_id']));
        $this->assertTrue($this->conversation->fresh()->isUnreadFor($this->buyer));
    }

    public function test_admin_deletes_single_message_and_last_message_follows(): void
    {
        $last = $this->conversation->messages()->latest('id')->first();

        Livewire::actingAs($this->admin)
            ->test(ViewConversation::class, ['record' => $this->conversation->id])
            ->call('deleteMessage', $last->id)
            ->assertNotified('Mesaj silindi');

        $c = $this->conversation->fresh();
        $this->assertSame(1, $c->messages()->count());
        $this->assertSame($c->messages()->first()->id, $c->last_message_id);
        $this->assertFalse($c->isUnreadFor($this->buyer)); // silinen mesaj artık okunmamış sayılmaz
    }

    public function test_admin_deletes_whole_conversation(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewConversation::class, ['record' => $this->conversation->id])
            ->callAction('delete');

        $this->assertModelMissing($this->conversation);
        $this->assertDatabaseCount('messages', 0);
    }
}
