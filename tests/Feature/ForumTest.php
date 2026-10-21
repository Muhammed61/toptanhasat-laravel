<?php

namespace Tests\Feature;

use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\ForumTopics\Pages\ViewForumTopic;
use App\Http\Controllers\Api\ForumController;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ForumRepliedNotification;
use App\Support\AdminNotifier;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\ForumStarterSeeder;
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

    /** Kullanıcı değiştir (testte önceki isteğin oturumu önbellekte kalmasın) */
    private function as(User $user): static
    {
        app('auth')->forgetGuards();

        return $this->actingAs($user);
    }

    private function openTopic(User $as, array $overrides = []): int
    {
        return $this->as($as)->postJson('/api/forum', [
            'category' => 'sebze',
            'title' => 'Domates fiyatları bu yıl nasıl?',
            'body' => 'Bandırma tarafında domates kaç liradan alınıyor, bilgisi olan var mı?',
            ...$overrides,
        ])->assertCreated()->json('id');
    }

    public function test_members_open_topics_and_reply_and_everyone_reads(): void
    {
        $id = $this->openTopic($this->ayse);
        $this->as($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'Geçen hafta 15 TL idi.'])
            ->assertCreated()
            ->assertJsonPath('author.name', 'Veli Kaya')
            ->assertJsonPath('author.is_guest', false)
            ->assertJsonPath('is_topic_owner', false);
        app('auth')->forgetGuards();

        $this->getJson("/api/forum/{$id}")
            ->assertOk()
            ->assertJsonPath('topic.title', 'Domates fiyatları bu yıl nasıl?')
            ->assertJsonPath('topic.category.label', 'Sebze')
            ->assertJsonPath('topic.author.name', 'Ayşe Yılmaz')
            ->assertJsonPath('topic.replies_count', 1)
            ->assertJsonPath('replies.0.body', 'Geçen hafta 15 TL idi.');
    }

    public function test_guests_open_topics_and_reply_with_an_ip_based_name(): void
    {
        $guest = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        $name = $guest('85.10.20.30')->getJson('/api/forum/viewer')->assertOk()->assertJsonPath('is_member', false)->json('name');
        $this->assertMatchesRegularExpression('/^Misafir\d{5}$/', $name);

        $id = $guest('85.10.20.30')->postJson('/api/forum', [
            'category' => 'meyve', 'title' => 'Kiraz hasadı başladı mı?', 'body' => 'Bu hafta kiraz toplamaya başlayan var mı?',
        ])->assertCreated()->assertJsonPath('author.name', $name)->assertJsonPath('author.is_guest', true)->json('id');

        // Aynı IP'den cevap: konuyu açan sayılır; başka IP başka misafir
        $guest('85.10.20.30')->postJson("/api/forum/{$id}/replies", ['body' => 'Bizde başladı.'])
            ->assertCreated()->assertJsonPath('author.name', $name)->assertJsonPath('is_topic_owner', true);
        $other = $guest('31.40.50.60')->postJson("/api/forum/{$id}/replies", ['body' => 'Bizde haftaya.'])
            ->assertCreated()->assertJsonPath('is_topic_owner', false)->json('author.name');
        $this->assertNotSame($name, $other);

        $this->assertDatabaseHas('forum_topics', ['id' => $id, 'user_id' => null, 'author_name' => $name, 'ip_address' => '85.10.20.30']);
    }

    public function test_guest_limits_and_bot_trap(): void
    {
        $guest = $this->withServerVariables(['REMOTE_ADDR' => '85.10.20.30']);
        $guest->postJson('/api/forum', ['category' => 'sebze', 'title' => 'Robot konusu', 'body' => 'Ucuz ürün satıyoruz.', 'website' => 'x'])
            ->assertUnprocessable();

        foreach (range(1, ForumController::GUEST_DAILY_TOPICS) as $i) {
            $topic = new ForumTopic(['category_id' => null, 'title' => "Eski konu {$i}", 'body' => 'Eski konu metni burada.']);
            $topic->forceFill(['author_name' => 'Misafir00001', 'ip_address' => '85.10.20.30'])->save();
        }
        $guest->postJson('/api/forum', ['category' => 'sebze', 'title' => 'Bir konu daha', 'body' => 'Bir konu daha açmak istiyorum.'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'Bugün çok fazla konu açtınız. Lütfen yarın tekrar deneyin ya da üye olun.');
    }

    public function test_list_is_filtered_by_category_and_sorted_by_latest_activity(): void
    {
        $old = $this->openTopic($this->ayse, ['title' => 'Eski sebze konusu']);
        $this->travel(1)->hours();
        $fruit = $this->openTopic($this->veli, ['category' => 'meyve', 'title' => 'Kiraz hasadı başladı mı?']);
        $this->travel(1)->hours();
        // Eski konuya cevap gelince en üste çıkar
        $this->as($this->veli)->postJson("/api/forum/{$old}/replies", ['body' => 'Güncel bilgi ekliyorum.'])->assertCreated();
        app('auth')->forgetGuards();

        $this->assertSame([$old, $fruit], $this->getJson('/api/forum')->json('data.*.id'));
        $this->assertSame([$fruit], $this->getJson('/api/forum?kategori=meyve')->json('data.*.id'));
        $this->getJson('/api/forum?adet=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_links_and_duplicates_are_rejected(): void
    {
        $this->as($this->ayse)->postJson('/api/forum', [
            'category' => 'sebze', 'title' => 'Ucuz gübre burada', 'body' => 'Detaylar için ucuzgubre . com adresine bakın',
        ])->assertUnprocessable()->assertJsonPath('errors.body.0', 'Yorumlarda bağlantı, web sitesi veya e-posta adresi paylaşılamaz.');

        $id = $this->openTopic($this->ayse);
        $this->as($this->ayse)->postJson('/api/forum', [
            'category' => 'sebze', 'title' => 'domates fiyatları bu yıl NASIL?', 'body' => 'Aynı konuyu tekrar açmaya çalışıyorum.',
        ])->assertUnprocessable()->assertJsonPath('errors.title.0', 'Bu başlıkla zaten bir konu açtınız.');

        $this->as($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'Merhaba'])->assertCreated();
        $this->as($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'merhaba'])
            ->assertUnprocessable()->assertJsonPath('errors.body.0', 'Bu cevabı zaten yazdınız.');
    }

    public function test_replies_notify_topic_owner_participants_and_admins(): void
    {
        SiteSetting::put('mail', ['driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'no-reply@example.com']);
        Notification::fake();

        $id = $this->openTopic($this->ayse);
        $this->as($this->veli)->postJson("/api/forum/{$id}/replies", ['body' => 'Veli cevap yazdı.'])->assertCreated();
        $this->as($this->ayse)->postJson("/api/forum/{$id}/replies", ['body' => 'Ayşe teşekkür etti.'])->assertCreated();
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

    public function test_team_posts_and_starter_topics(): void
    {
        $this->seed(ForumStarterSeeder::class);
        $this->seed(ForumStarterSeeder::class); // ikinci kez çalışınca tekrar açmaz
        $this->assertSame(count(ForumStarterSeeder::TOPICS), ForumTopic::count());
        $this->assertSame(0, ForumReply::count()); // cevap üretilmez

        $first = $this->getJson('/api/forum?adet=1')->assertOk();
        $first->assertJsonPath('data.0.title', ForumStarterSeeder::TOPICS[0][1])
            ->assertJsonPath('data.0.author.name', 'Toptanhasat Ekibi')
            ->assertJsonPath('data.0.author.is_team', true);

        // Panelden ekip olarak konu açma ve cevap yazma
        $this->as($this->admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(ListForumTopics::class)
            ->callAction('newTeamTopic', ['category_id' => null, 'title' => 'Ekipten yeni konu', 'body' => 'Bu konu panelden açıldı.'])
            ->assertHasNoActionErrors();
        $topic = ForumTopic::where('title', 'Ekipten yeni konu')->sole();
        Livewire::test(ViewForumTopic::class, ['record' => $topic->id])
            ->callAction('teamReply', ['body' => 'Ekip cevabı'])
            ->assertHasNoActionErrors();
        $this->assertSame(1, $topic->fresh()->replies_count);
        $this->assertTrue($topic->replies()->sole()->isTeam());

        // Üyenin cevabı ekip sayılmaz
        $id = $this->openTopic($this->ayse);
        $this->assertFalse(ForumTopic::find($id)->isTeam());
    }

    public function test_topics_have_sub_topics_inside(): void
    {
        // "Çeltik" konusu açılır, içinde "Çeltik fiyatları" ve "Çeltik ilaçları" alt başlıkları
        $parent = $this->openTopic($this->ayse, ['category' => 'tahil', 'title' => 'Çeltik', 'body' => 'Çeltik ekenler için genel konu.']);
        $price = $this->openTopic($this->veli, ['category' => null, 'parent' => $parent, 'title' => 'Çeltik fiyatları', 'body' => 'Bu yıl çeltik kaç liradan alınıyor?']);
        $this->openTopic($this->ayse, ['category' => null, 'parent' => $parent, 'title' => 'Çeltik ilaçları', 'body' => 'Yabancı otlara karşı ne kullanıyorsunuz?']);

        // Alt başlığın altına yeni alt başlık açılmaz
        $this->as($this->ayse)->postJson('/api/forum', ['parent' => $price, 'title' => 'Üçüncü seviye', 'body' => 'Bu açılmamalı, tek seviye.'])
            ->assertUnprocessable()->assertJsonPath('errors.parent.0', 'Bu konunun altına başlık açılamaz.');
        app('auth')->forgetGuards();

        // Listede yalnızca üst konu; alt başlık sayısıyla
        $this->getJson('/api/forum?kategori=tahil')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Çeltik')
            ->assertJsonPath('data.0.subtopics_count', 2);

        $thread = $this->getJson("/api/forum/{$parent}")->assertJsonCount(2, 'subtopics');
        $this->assertEqualsCanonicalizing(['Çeltik fiyatları', 'Çeltik ilaçları'], $thread->json('subtopics.*.title'));

        // Alt başlık üst konunun kategorisini alır, üst konuyu gösterir; cevabı üst konuyu da yukarı taşır
        $this->getJson("/api/forum/{$price}")
            ->assertJsonPath('topic.category.key', 'tahil')
            ->assertJsonPath('topic.parent.title', 'Çeltik')
            ->assertJsonCount(0, 'subtopics');
        $this->travel(5)->minutes();
        $this->as($this->ayse)->postJson("/api/forum/{$price}/replies", ['body' => 'Geçen hafta 20 TL idi.'])->assertCreated();
        $this->assertTrue(ForumTopic::find($parent)->last_activity_at->equalTo(ForumReply::latest('id')->first()->created_at));

        // Üst konu silinince alt başlıkları da gider
        ForumTopic::find($parent)->delete();
        $this->assertSame(0, ForumTopic::count());
    }
}
