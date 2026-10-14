<?php

namespace Tests\Feature;

use App\Filament\Pages\Notifications;
use App\Livewire\AdminNotifications;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Support\AdminNotifier;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->seller = User::factory()->create(['first_name' => 'Satıcı', 'last_name' => 'Bey']);
        $this->buyer = User::factory()->create(['first_name' => 'Alıcı', 'last_name' => 'Hanım']);
        $this->listing = Listing::factory()->for($this->seller)->create();
        $this->listing->approve($this->admin);
    }

    private function types(User $user): array
    {
        return $user->notifications()->get()->pluck('data.viewData.type')->all();
    }

    public function test_new_comment_notifies_admins_but_not_the_admin_who_wrote_it(): void
    {
        $this->listing->comments()->create(['name' => 'Misafir12345', 'body' => 'Fiyat nedir?']);

        $notification = $this->admin->unreadNotifications()->sole();
        $this->assertSame(AdminNotifier::TYPE_COMMENT, $notification->data['viewData']['type']);
        $this->assertSame('Yeni yorum: Misafir12345', $notification->data['title']);
        $this->assertStringContainsString('Fiyat nedir?', $notification->data['body']);
        $this->assertStringStartsWith('/admin/listing-comments?', $notification->data['viewData']['url']);

        $this->listing->comments()->create(['name' => 'Site Yöneticisi', 'body' => 'Teşekkürler', 'user_id' => $this->admin->id]);
        $this->assertCount(1, $this->admin->notifications()->get());
    }

    public function test_messages_in_one_conversation_are_grouped_until_read(): void
    {
        $conversation = Conversation::create([
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing->title(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);

        $conversation->addMessage($this->buyer, 'Merhaba');
        $conversation->addMessage($this->buyer, 'Pazarlık olur mu?');
        $conversation->addMessage($this->seller, 'Olur');

        $notification = $this->admin->unreadNotifications()->sole();
        $this->assertSame('3 yeni mesaj: Satıcı Bey', $notification->data['title']);
        $this->assertStringContainsString('Olur', $notification->data['body']);

        // Okunduktan sonraki mesaj yeni bildirim açar
        $notification->markAsRead();
        $conversation->addMessage($this->buyer, 'Tamam');
        $this->assertCount(2, $this->admin->notifications()->get());
        $this->assertSame('Yeni mesaj: Alıcı Hanım', $this->admin->unreadNotifications()->sole()->data['title']);
    }

    public function test_contact_form_message_notifies_admins(): void
    {
        ContactMessage::create(['name' => 'Ali', 'email' => 'ali@example.test', 'subject' => 'general', 'message' => 'Merhaba']);

        $this->assertSame([AdminNotifier::TYPE_CONTACT], $this->types($this->admin));
        $this->assertSame([], $this->types($this->seller));
    }

    public function test_notifications_page_lists_filters_and_marks_all_as_read(): void
    {
        $this->listing->comments()->create(['name' => 'Misafir12345', 'body' => 'Fiyat nedir?']);
        ContactMessage::create(['name' => 'Ali', 'email' => 'ali@example.test', 'subject' => 'general', 'message' => 'Merhaba']);
        $byType = $this->admin->notifications()->get()->keyBy(fn ($n) => $n->data['viewData']['type']);
        [$contact, $comment] = [$byType[AdminNotifier::TYPE_CONTACT], $byType[AdminNotifier::TYPE_COMMENT]];

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $this->get(Notifications::getUrl())->assertOk()->assertSee('Bildirimler');

        Livewire::test(Notifications::class)
            ->assertCanSeeTableRecords([$contact, $comment])
            ->filterTable('type', AdminNotifier::TYPE_CONTACT)
            ->assertCanSeeTableRecords([$contact])
            ->assertCanNotSeeTableRecords([$comment])
            ->callAction('markAllAsRead');

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertNull(Notifications::getNavigationBadge());

        // Zil penceresinde "Tüm bildirimler" bağlantısı
        Livewire::test(AdminNotifications::class)->assertSee('Tüm bildirimleri görüntüle (2)');
    }

    public function test_members_cannot_open_the_notifications_page(): void
    {
        $this->actingAs($this->buyer)->get('/admin/bildirimler')->assertForbidden();
    }
}
