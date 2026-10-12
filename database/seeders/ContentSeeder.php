<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MarketPrice;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\SiteData;
use Illuminate\Database\Seeder;

/**
 * Sitenin başlangıç içeriği. Sadece eksik kayıtları oluşturur;
 * panelden yapılan düzenlemelerin üzerine YAZMAZ, güvenle tekrar çalıştırılabilir.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->pages();
        $this->catalog();
        $this->marketPrices();

        // Seeder'lar model olaylarını kapatabildiği için önbelleği burada temizle
        SiteData::flush();
    }

    private function settings(): void
    {
        $settings = SiteSetting::current();
        if (! empty($settings->data)) {
            return;
        }

        $settings->update(['data' => [
            'site_name' => 'Toptanhasat',
            'footer_text' => 'Çiftçiler ile toptan alıcıları bir araya getiren dijital mahsül pazarı.',
            'footer_disclaimer' => 'Toptanhasat aracı bir ilan platformudur; kullanıcılar arasındaki alım-satım işlemlerine taraf değildir.',
            'copyright' => 'Toptanhasat. Tüm hakları saklıdır.',
            'phone' => '+90 (555) 123 45 67',
            'email' => 'info@toptanhasat.com',
            'address' => 'Bandırma, Balıkesir',
            'working_hours' => 'Pazartesi - Cuma, 09:00 - 18:00',
            'social' => [],
            'seo' => [
                'default_title' => "Toptanhasat - Türkiye'nin Dijital Mahsül Pazarı",
                'title_suffix' => ' | Toptanhasat',
                'description' => 'Çiftçiler ve toptan alıcılar Toptanhasat\'ta buluşuyor. Bölgenize göre mahsül ilanlarını görün, ücretsiz ilan verin.',
            ],
        ]]);
    }

    private function pages(): void
    {
        $html = fn (string $slug) => file_get_contents(database_path("data/pages/$slug.html"));

        $pages = [
            'home' => [
                'title' => "Türkiye'nin Mahsül Pazar Yeri",
                'subtitle' => 'Çiftçiler ve alıcılar burada buluşuyor. Aradığınız mahsülü bölgenize göre bulun.',
                'content' => [
                    'popular_searches' => ['Domates', 'Fındık', 'Buğday', 'Portakal'],
                    'ticker_title' => 'Hal Fiyatları',
                    'ticker_unit' => '₺/kg',
                ],
            ],
            'about' => [
                'title' => 'Hakkımızda',
                'subtitle' => "Türkiye'nin dijital mahsül pazarı",
                'body' => $html('about'),
                'content' => [
                    'values' => [
                        ['icon' => 'shield', 'title' => 'Şeffaflık', 'text' => 'Platformun nasıl işlediğini ve sorumlulukları açıkça paylaşırız.'],
                        ['icon' => 'lightbulb', 'title' => 'Yenilikçilik', 'text' => 'Teknoloji ile tarımı buluşturarak sektöre yenilik getiriyoruz.'],
                        ['icon' => 'leaf', 'title' => 'Sürdürülebilirlik', 'text' => 'Çevre dostu ve sürdürülebilir tarım uygulamalarını destekliyoruz.'],
                        ['icon' => 'scale', 'title' => 'Eşit Erişim', 'text' => 'Her üreticinin alıcılara aynı koşullarda ulaşabilmesini hedefliyoruz.'],
                    ],
                ],
            ],
            'how-it-works' => [
                'title' => 'Nasıl Çalışır?',
                'subtitle' => 'Alıcı ile satıcıyı buluşturuyoruz, anlaşma sizin aranızda.',
                'content' => [
                    'steps' => [
                        ['icon' => 'user-plus', 'title' => 'Üye olun', 'text' => 'Ücretsiz hesabınızı birkaç dakikada oluşturun.'],
                        ['icon' => 'search', 'title' => 'İlan verin veya arayın', 'text' => 'Mahsülünüzü ilan edin ya da aradığınızı bölgeye göre bulun.'],
                        ['icon' => 'phone', 'title' => 'İletişime geçin', 'text' => 'İlan sahibinin iletişim bilgilerini görün, doğrudan görüşün.'],
                        ['icon' => 'truck', 'title' => 'Kendi aranızda anlaşın', 'text' => 'Fiyatı, ödemeyi ve teslimatı taraflar birlikte belirler.'],
                    ],
                    'notice' => 'Toptanhasat aracı bir ilan platformudur. Alım-satıma taraf değildir; ürün, fiyat, ödeme ve teslimattan alıcı ile satıcı sorumludur.',
                    'faqs' => [
                        ['question' => 'Üyelik ve ilan vermek ücretli mi?', 'answer' => 'Hayır, üye olmak da ilan vermek de ücretsizdir.'],
                        ['question' => 'Ödeme ve teslimat nasıl yapılır?', 'answer' => 'Alıcı ile satıcı kendi aralarında belirler. Toptanhasat bu sürece dahil olmaz.'],
                        ['question' => 'Üründen ve anlaşmadan kim sorumlu?', 'answer' => 'İlan bilgilerinden ilan sahibi, alım-satım sürecinden alıcı ile satıcı sorumludur.'],
                        ['question' => 'Kişisel verilerim nasıl işlenir?', 'answer' => 'Verileriniz KVKK kapsamında işlenir. Ayrıntılar için KVKK Aydınlatma Metni sayfasına bakabilirsiniz.'],
                        ['question' => 'Sorun yaşarsam kime ulaşırım?', 'answer' => 'İletişim sayfasındaki formdan veya e-posta adresimizden bize yazabilirsiniz.'],
                    ],
                    'cta_title' => 'Hemen başlayın',
                    'cta_text' => 'Ücretsiz üye olun, ilk ilanınızı verin.',
                    'cta_button' => 'Ücretsiz Üye Ol',
                ],
            ],
            'contact' => [
                'title' => 'İletişim',
                'subtitle' => 'Bizimle iletişime geçin',
                'content' => [
                    'form_title' => 'Mesaj Gönderin',
                    'form_subtitle' => 'Formu doldurun, en kısa sürede size dönüş yapalım.',
                    'success_message' => 'Mesajınız alındı. En kısa sürede size dönüş yapacağız.',
                ],
            ],
            'auth' => [
                'title' => "Türkiye'nin Mahsül Pazar Yeri",
                'subtitle' => 'Çiftçiler ve alıcılar burada buluşuyor.',
                'content' => [
                    'highlights' => ['Ücretsiz üyelik', 'Doğrudan iletişim', 'Hızlı ilan verme'],
                ],
            ],
            'kvkk' => [
                'title' => 'KVKK Aydınlatma Metni',
                'subtitle' => 'Kişisel Verilerin Korunması Kanunu',
                'body' => $html('kvkk'),
                'content' => ['last_updated' => '2026-09-07'],
            ],
            'terms' => [
                'title' => 'Kullanım Koşulları',
                'subtitle' => 'Toptanhasat platformu kullanım şartları',
                'body' => $html('terms'),
                'content' => ['last_updated' => '2026-09-07'],
            ],
            'cookie' => [
                'title' => 'Çerez Politikası',
                'subtitle' => 'Toptanhasat çerez kullanımı',
                'body' => $html('cookie'),
                'content' => ['last_updated' => '2026-09-07'],
            ],
        ];

        foreach ($pages as $slug => $attributes) {
            Page::firstOrCreate(['slug' => $slug], $attributes);
        }
    }

    private function catalog(): void
    {
        $categories = [
            ['sebze', 'Sebze', null, ['Domates', 'Biber', 'Patlıcan', 'Salatalık']],
            ['meyve', 'Meyve', null, ['Elma', 'Kiraz', 'Şeftali', 'Üzüm']],
            ['narenciye', 'Narenciye', 'Sezonda', ['Portakal', 'Mandalina', 'Limon']],
            ['tahil', 'Tahıl', null, ['Buğday', 'Arpa', 'Mısır']],
            ['bakliyat', 'Bakliyat', null, ['Nohut', 'Mercimek', 'Fasulye']],
            ['kuruyemis', 'Kuruyemiş', null, ['Fındık', 'Ceviz', 'Antep Fıstığı']],
        ];

        foreach ($categories as $i => [$slug, $name, $badge, $products]) {
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'icon' => $slug, 'badge' => $badge, 'sort' => $i],
            );

            if ($category->wasRecentlyCreated) {
                foreach ($products as $j => $product) {
                    $category->products()->create(['name' => $product, 'sort' => $j]);
                }
            }
        }
    }

    /**
     * Örnek hal fiyatları PASİF eklenir: gerçek fiyatlar panelden girilip
     * aktif edilene kadar ana sayfada şerit görünmez.
     */
    private function marketPrices(): void
    {
        if (MarketPrice::exists()) {
            return;
        }

        $examples = [
            ['Domates', 14.5, 2.1], ['Biber', 22, -1.4], ['Salatalık', 12.25, -0.6], ['Patlıcan', 16.75, 0.8],
            ['Buğday', 11.4, 0.3], ['Fındık', 210, 1.2], ['Portakal', 15.9, -0.9],
        ];

        foreach ($examples as $i => [$product, $price, $change]) {
            MarketPrice::create(['product' => $product, 'price' => $price, 'change' => $change, 'sort' => $i, 'is_active' => false]);
        }
    }
}
