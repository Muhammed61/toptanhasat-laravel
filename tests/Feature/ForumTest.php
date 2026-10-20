<?php

namespace Tests\Feature;

use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\ForumTopics\Pages\ViewForumTopic;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ForumRepliedNotification;
use App\Support\AdminNotifier;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ayse;

    private User $veli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->ayse = User::factory()->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        $this->veli = User::factory()->create(['first_name' => 'Veli', 'last_name' => 'Kaya']);
    }

    private function openTopic(User $as, array $overrides = []): int
    {
        return $this->actingAs($as)->postJson('/api/forum', [
            'category' => 'sebze',
            'title' => 'Domates fiyatları bu yıl nasıl?',
            'body' => 'Bandırma tarafında domates kaç liradan alınıyor, bilgisi olan var mı?',
            ...$overrides,
        ])->assertCreated()->json('id');
    }

    public function test_members_open_topics_and_reply_guests_only_read(): void
    {
        $this->postJson('/api/forum', ['category' => 'sebze', 'title' => 'Misafir konusu', 'body' => 'Misafir olarak yazıyorum.'])->assertUnauthorized();

        $id = $this->openTopic($this->ayse);
        $this->actingAs($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'Geçen hafta 15 TL idi.'])
            ->assertCreated()
            ->assertJsonPath('author.name', 'Veli Kaya')
            ->assertJsonPath('is_topic_owner', false);
        app('auth')->forgetGuards();

        $this->getJson("/api/forum/{$id}")
            ->assertOk()
            ->assertJsonPath('topic.title', 'Domates fiyatları bu yıl nasıl?')
            ->assertJsonPath('topic.category.label', 'Sebze')
            ->assertJsonPath('topic.author.name', 'Ayşe Yılmaz')
            ->assertJsonPath('topic.replies_count', 1)
            ->assertJsonPath('replies.0.body', 'Geçen hafta 15 TL idi.');

        $this->postJson("/api/forum/{$id}/replies", ['body' => 'Misafir cevabı'])->assertUnauthorized();
    }

    public function test_list_is_filtered_by_category_and_sorted_by_latest_activity(): void
    {
        $old = $this->openTopic($this->ayse, ['title' => 'Eski sebze konusu']);
        $this->travel(1)->hours();
        $fruit = $this->openTopic($this->veli, ['category' => 'meyve', 'title' => 'Kiraz hasadı başladı mı?']);
        $this->travel(1)->hours();
        // Eski konuya cevap gelince en üste çıkar
        $this->actingAs($this->veli)->postJson("/api/forum/{$old}/replies", ['body' => 'Güncel bilgi ekliyorum.'])->assertCreated();
        app('auth')->forgetGuards();

        $this->assertSame([$old, $fruit], $this->getJson('/api/forum')->json('data.*.id'));
        $this->assertSame([$fruit], $this->getJson('/api/forum?kategori=meyve')->json('data.*.id'));
        $this->getJson('/api/forum?adet=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_links_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->ayse)->postJson('/api/forum', [
            'category' => 'sebze', 'title' => 'Ucuz gübre burada', 'body' => 'Detaylar için ucuzgubre . com adresine bakın',
        ])->assertUnprocessable()->assertJsonPath('errors.body.0', 'Yorumlarda bağlantı, web sitesi veya e-posta adresi paylaşılamaz.');

        $id = $this->openTopic($this->ayse);
        $this->actingAs($this->ayse)->postJson('/api/forum', [
            'category' => 'sebze', 'title' => 'domates fiyatları bu yıl NASIL?', 'body' => 'Aynı konuyu tekrar açmaya çalışıyorum.',
        ])->assertUnprocessable()->assertJsonPath('errors.title.0', 'Bu başlıkla zaten bir konu açtınız.');

        $this->actingAs($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'Merhaba'])->assertCreated();
        $this->actingAs($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'merhaba'])
            ->assertUnprocessable()->assertJsonPath('errors.body.0', 'Bu cevabı zaten yazdınız.');
    }

    public function test_replies_notify_topic_owner_participants_and_admins(): void
    {
        SiteSetting::put('mail', ['driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'no-reply@example.com']);
        Notification::fake();

        $id = $this->openTopic($this->ayse);
        $this->actingAs($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'Veli cevap yazdı.'])->assertCreated();
        $this->actingAs($this->ayse)->postJson("/api/forum/{$id}/replies", ['body' => 'Ayşe teşekkür etti.'])->assertCreated();
        app()->terminate();

        // Veli'nin cevabı konu sahibi Ayşe'ye; Ayşe'nin cevabı önceden yazan Veli'ye (kendine değil)
        Notification::assertSentTo($this->ayse, ForumRepliedNotification::class, fn ($n) => $n->toOwner && $n->reply->body === 'Veli cevap yazdı.');
        Notification::assertSentTo($this->veli, ForumRepliedNotification::class, fn ($n) => ! $n->toOwner && $n->reply->body === 'Ayşe teşekkür etti.');
        Notification::assertNotSentTo($this->ayse, ForumRepliedNotification::class, fn ($n) => $n->reply->body === 'Ayşe teşekkür etti.');

        // Yöneticinin zil bildirimi (konu ve cevaplar)
        Notification::assertSentTo($this->admin, DatabaseNotification::class, fn ($n) => ($n->data['viewData']['type'] ?? null) === AdminNotifier::TYPE_FORUM);
    }

    public function test_admin_sees_topics_and_deletes_a_reply(): void
    {
        // Veriler doğrudan oluşturulur (aynı testte API isteği Filament oturumunu karıştırıyor)
        $topic = new ForumTopic(['category_id' => null, 'title' => 'Domates fiyatları', 'body' => 'Bilgisi olan var mı?']);
        $topic->user()->associate($this->ayse)->save();
        $reply = new ForumReply(['body' => 'Uygunsuz cevap']);
        $reply->user()->associate($this->veli);
        $topic->replies()->save($reply);
        $id = $topic->id;
        $this->assertSame(1, $topic->fresh()->replies_count);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(ListForumTopics::class)->assertCanSeeTableRecords([$topic]);
        $this->get("/admin/forum/{$id}")->assertOk()->assertSee('Uygunsuz cevap');

        Livewire::test(ViewForumTopic::class, ['record' => $id])->call('deleteReply', $reply->id);
        $this->assertSame(0, $topic->fresh()->replies_count);
        $this->assertDatabaseMissing('forum_replies', ['id' => $reply->id]);
    }
}
