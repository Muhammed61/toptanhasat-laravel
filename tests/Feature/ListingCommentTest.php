<?php

namespace Tests\Feature;

use App\Filament\Resources\ListingComments\Pages\ListListingComments;
use App\Http\Controllers\Api\ListingCommentController;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ListingCommentedNotification;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        // Misafirin yazdığı ad yok sayılır; IP'ye bağlı otomatik ad verilir
        $name = $this->postJson("/api/listings/{$this->listing->id}/comments", ['name' => 'Ayşe', 'body' => ' Teslimat Bursa\'ya olur mu? '])
            ->assertCreated()
            ->assertJsonPath('body', 'Teslimat Bursa\'ya olur mu?')
            ->assertJsonPath('is_owner', false)
            ->json('name');
        $this->assertMatchesRegularExpression('/^Misafir\d{5}$/', $name);

        $this->getJson("/api/listings/{$this->listing->id}/comments")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('viewer_name', $name)
            ->assertJsonPath('data.0.name', $name);
    }

    public function test_same_ip_keeps_the_same_guest_name(): void
    {
        $post = fn (string $ip, string $body) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson("/api/listings/{$this->listing->id}/comments", ['body' => $body])->json('name');

        $first = $post('85.10.20.30', 'İlk yorum');
        $this->assertSame($first, $post('85.10.20.30', 'İkinci yorum'));
        $this->assertNotSame($first, $post('176.40.1.2', 'Başka kişi'));
    }

    public function test_listing_owner_reply_is_marked_and_uses_account_name(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/listings/{$this->listing->id}/comments", ['name' => 'Başka Biri', 'body' => 'Evet, olur.'])
            ->assertCreated()
            ->assertJsonPath('name', 'Ali Çiftçi')
            ->assertJsonPath('is_owner', true);
    }

    public function test_member_comments_show_the_profile_photo(): void
    {
        $this->owner->forceFill(['avatar_path' => 'avatars/ali.jpg'])->saveQuietly();

        $this->actingAs($this->owner)
            ->postJson("/api/listings/{$this->listing->id}/comments", ['body' => 'Evet, olur.'])
            ->assertCreated()
            ->assertJsonPath('avatar_url', fn ($url) => str_ends_with($url, '/uploads/avatars/ali.jpg'));

        app('auth')->forgetGuards();
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['body' => 'Misafir sorusu'])->assertCreated();

        $this->getJson("/api/listings/{$this->listing->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.avatar_url', null) // misafir: baş harf
            ->assertJsonPath('data.1.avatar_url', fn ($url) => str_ends_with($url, '/uploads/avatars/ali.jpg'));
    }

    public function test_validation_honeypot_and_unpublished_listing(): void
    {
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['body' => ''])
            ->assertUnprocessable()
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

    public function test_links_and_emails_are_rejected_for_guests_and_members(): void
    {
        $url = "/api/listings/{$this->listing->id}/comments";

        $this->postJson($url, ['body' => 'En ucuz gübre için ucuzgubre . com'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'Yorumlarda bağlantı, web sitesi veya e-posta adresi paylaşılamaz.');
        $this->actingAs($this->owner)->postJson($url, ['body' => 'Bilgi için https://ornek.com'])->assertUnprocessable();
        $this->actingAs($this->owner)->postJson($url, ['body' => 'Mail: ali@gmail.com'])->assertUnprocessable();

        $this->assertSame(0, ListingComment::count());
        $this->postJson($url, ['body' => 'Ürün taze. Su ile yıkandı mı?'])->assertCreated();
    }

    public function test_same_comment_cannot_be_sent_twice(): void
    {
        $url = "/api/listings/{$this->listing->id}/comments";

        $this->postJson($url, ['body' => 'Fiyat nedir?'])->assertCreated();
        $this->postJson($url, ['body' => '  fiyat NEDİR?  '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'Bu yorumu zaten gönderdiniz.');

        // Başka bir misafir (farklı IP) aynı soruyu sorabilir
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson($url, ['body' => 'Fiyat nedir?'])->assertCreated();
        $this->assertSame(2, ListingComment::count());
    }

    public function test_guests_have_a_daily_comment_limit(): void
    {
        foreach (range(1, ListingCommentController::GUEST_DAILY_LIMIT) as $i) {
            $this->listing->comments()->create(['name' => 'Misafir', 'body' => "Eski yorum {$i}", 'ip_address' => '127.0.0.1']);
        }

        $this->postJson("/api/listings/{$this->listing->id}/comments", ['body' => 'Bir tane daha'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'Bugün çok fazla yorum yazdınız. Lütfen yarın tekrar deneyin ya da üye olun.');

        // Üyeler bu sınıra takılmaz; ertesi gün misafir de yazabilir
        $this->actingAs($this->owner)->postJson("/api/listings/{$this->listing->id}/comments", ['body' => 'Üye cevabı'])->assertCreated();
        app('auth')->forgetGuards();
        $this->travel(25)->hours();
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['body' => 'Ertesi gün'])->assertCreated();
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

    private function enableMail(): void
    {
        SiteSetting::put('mail', ['driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'no-reply@example.com']);
    }

    private function comment(?User $as, string $body): void
    {
        app('auth')->forgetGuards();
        $request = $as ? $this->actingAs($as) : $this;
        $request->postJson("/api/listings/{$this->listing->id}/comments", ['body' => $body])->assertCreated();
    }

    public function test_new_comment_mails_owner_and_members_who_commented_but_not_the_author(): void
    {
        $this->enableMail();
        Notification::fake();
        $ayse = User::factory()->create(['first_name' => 'Ayşe']);
        $veli = User::factory()->create(['first_name' => 'Veli']);

        // Kimin hangi yorum için mail aldığı (testte aynı uygulama birden çok istek işlediği için
        // "yanıttan sonra" görevleri tekrar çalışabilir; yorum metnine göre tekilleştirilir)
        $received = fn (User $u) => Notification::sent($u, ListingCommentedNotification::class)
            ->map(fn (ListingCommentedNotification $n) => $n->comment->body)->unique()->values()->all();

        $this->comment(null, 'Misafir sorusu');        // misafir: sadece ilan sahibine
        $this->comment($ayse, 'Ayşe soruyor');         // Ayşe: sahibe, kendisine değil
        $this->comment($veli, 'Veli de soruyor');      // Veli: sahibe ve önceden yazan Ayşe'ye
        $this->comment($this->owner, 'Sahibin cevabı'); // sahip: kendisine değil, Ayşe ve Veli'ye
        $this->comment(null, 'Misafir tekrar soruyor'); // misafir: sahibe ve yorum yazmış tüm üyelere

        $this->assertSame(['Misafir sorusu', 'Ayşe soruyor', 'Veli de soruyor', 'Misafir tekrar soruyor'], $received($this->owner));
        $this->assertSame(['Veli de soruyor', 'Sahibin cevabı', 'Misafir tekrar soruyor'], $received($ayse));
        $this->assertSame(['Sahibin cevabı', 'Misafir tekrar soruyor'], $received($veli));

        Notification::assertSentTo($ayse, ListingCommentedNotification::class, function (ListingCommentedNotification $n) use ($ayse) {
            $mail = $n->toMail($ayse);

            return ! $n->toOwner
                && str_contains($mail->subject, 'Yorum yaptığınız')
                && $mail->actionUrl === rtrim(config('app.frontend_url'), '/')."/ilan/{$this->listing->id}#yorumlar";
        });
    }

    public function test_owner_mail_mentions_comment_and_no_mail_when_smtp_off(): void
    {
        Notification::fake();
        $this->comment(null, 'SMTP kapalıyken yorum');
        Notification::assertNothingSent();

        $comment = $this->listing->comments()->first();
        $mail = (new ListingCommentedNotification($comment, toOwner: true))->toMail($this->owner);
        $this->assertStringContainsString('ilanınıza yeni yorum yapıldı', $mail->subject);
        $this->assertStringContainsString('SMTP kapalıyken yorum', implode(' ', $mail->introLines));
    }

    public function test_admin_can_turn_comments_off_and_edit_a_comment(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $comment = $this->listing->comments()->create(['name' => 'Ayşe', 'body' => 'Kötü söz içeren yorum']);

        Livewire::actingAs($admin)
            ->test(ListListingComments::class)
            ->callAction(TestAction::make('edit')->table($comment), ['name' => 'Ayşe', 'body' => 'Düzeltilmiş yorum'])
            ->assertHasNoFormErrors()
            ->callAction('toggleComments')
            ->assertNotified('İlan yorumları kapatıldı');

        $this->assertSame('Düzeltilmiş yorum', $comment->fresh()->body);
        $this->assertFalse(SiteSetting::commentsEnabled());

        app('auth')->forgetGuards();
        $this->getJson('/api/site')->assertJsonPath('settings.comments_enabled', false);
        $this->getJson("/api/listings/{$this->listing->id}/comments")->assertForbidden();
        $this->postJson("/api/listings/{$this->listing->id}/comments", ['body' => 'Kapalıyken'])
            ->assertForbidden()
            ->assertJsonPath('message', 'İlan yorumları şu anda kapalı.');
        $this->assertModelExists($comment); // kapatınca silinmez
    }
}
