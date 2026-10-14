<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\User;
use App\Notifications\ListingCommentedNotification;
use Database\Seeders\CitySeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_mails_use_the_site_layout_and_user_text_cannot_inject_links(): void
    {
        $this->seed([CitySeeder::class, ContentSeeder::class]);
        $owner = User::factory()->create(['first_name' => 'Ayşe']);
        $listing = Listing::factory()->for($owner)->create();
        $comment = new ListingComment(['name' => 'Misafir12345', 'body' => '[Hesabınızı doğrulayın](http://phish.test) **acil**']);
        $comment->setRelation('listing', $listing);
        $comment->listing_id = $listing->id;
        $comment->id = 1;

        $html = (string) (new ListingCommentedNotification($comment, true))->toMail($owner)->render();

        // Ortak çerçeve: marka rengi, alt bilgideki aracı platform notu, Türkçe imza
        $this->assertStringContainsString('#2d6a4f', $html);
        $this->assertStringContainsString('aracı bir ilan platformudur', $html);
        $this->assertStringContainsString('Merhaba Ayşe,', $html);
        $this->assertStringNotContainsString('Laravel', $html);

        // Yorumdaki Markdown bağlantıya/biçime dönüşmez, metin olduğu gibi görünür
        $this->assertStringNotContainsString('href="http://phish.test"', $html);
        $this->assertStringNotContainsString('<strong>acil</strong>', $html);
        $this->assertStringContainsString('[Hesabınızı doğrulayın](http://phish.test)', html_entity_decode(strip_tags($html)));
    }
}
