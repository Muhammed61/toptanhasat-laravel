<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Forumun başlangıç konuları: "Toptanhasat Ekibi" adına (yönetici hesabıyla) açılan, cevap bekleyen
 * sorular ve kısa rehberler. Sahte kullanıcı ya da sahte cevap yoktur; cevapları gerçek kullanıcılar yazar.
 *
 * Çalıştırma: php artisan db:seed --class=ForumStarterSeeder --force
 * Tekrar çalıştırılırsa aynı başlıklı konular yeniden açılmaz.
 */
class ForumStarterSeeder extends Seeder
{
    /** [kategori (boşsa genel), başlık, metin] — listede yukarıdakiler en yeni görünür */
    public const TOPICS = [
        [null, 'Foruma hoş geldiniz: nasıl kullanılır?', "Merhaba! Bu forum çiftçilerin ve toptan alıcıların mahsüller hakkında soru sorup deneyim paylaşması için.\n\n• Kategorisini seçip yeni bir konu açabilirsiniz.\n• Bir konuya cevap yazmak için üye olmanız gerekmez; üye olursanız adınızla görünürsünüz.\n• Bağlantı ve e-posta paylaşılamaz; telefon veya adres gibi kişisel bilgilerinizi de yazmamanızı öneririz.\n\nSorunuz, öneriniz ya da paylaşmak istediğiniz bir deneyim varsa çekinmeyin."],
        [null, 'Bu haftanın hal fiyatları: sizin bölgede durum ne?', "Hal fiyatları ilden ile, hatta haftadan haftaya değişiyor. Bu hafta sizin bölgenizde hangi ürün kaçtan alınıp satılıyor?\n\nCevap yazarken il/ilçe, ürün ve kilogram fiyatını belirtirseniz herkes için faydalı bir karşılaştırma olur."],
        ['sebze', 'Domates fiyatları sizin ilde ne durumda?', 'Toptan domateste fiyatlar bölgeye ve döneme göre çok değişebiliyor. Sizin ilinizde bu sezon domates kaçtan alıcı buluyor? Sera ve tarla domatesi arasında fark görüyor musunuz?'],
        ['sebze', 'Biber hasadında en çok hangi sorunla karşılaşıyorsunuz?', 'Hasat zamanlaması, işçilik, nakliye ya da fiyat... Biber üretiminde sizi en çok zorlayan konu hangisi? Çözüm bulduysanız deneyiminizi paylaşabilirsiniz.'],
        ['sebze', 'Sera ürünlerinde toptan alıcıya nasıl ulaşıyorsunuz?', 'Salatalık, domates, biber gibi sera ürünlerini satarken hal komisyoncusu, doğrudan market ya da ihracatçı... Hangi yolu tercih ediyorsunuz, neden?'],
        ['meyve', 'Kiraz hasadı için doğru zamanı nasıl belirliyorsunuz?', 'Kirazda hasat zamanı hem kaliteyi hem fiyatı etkiliyor. Renk, tat, şeker ölçümü... Siz hasat kararını neye bakarak veriyorsunuz?'],
        ['meyve', 'Elmada soğuk hava deposu mu, hasattan hemen satış mı?', 'Elmayı hasattan hemen satmak mı, depolayıp fiyatın yükselmesini beklemek mi daha mantıklı? Depolama yapanlar maliyet ve fire konusundaki deneyimlerini paylaşabilir mi?'],
        ['narenciye', 'Portakal ve mandalinada hasat zamanlaması', 'Narenciyede erken ya da geç hasadın fiyata ve kaliteye etkisini nasıl değerlendiriyorsunuz? Bu sezon sizin bölgede hasat ne zaman başladı?'],
        ['tahil', 'Buğday hasadı: bu yıl verim nasıl?', 'Yağış ve hava koşulları her yıl farklı. Bu yıl buğdayda dekar başına verim sizin bölgede nasıl? Geçen yıla göre artış ya da düşüş var mı?'],
        ['tahil', 'Arpa ve buğdayı depolarken nelere dikkat ediyorsunuz?', 'Hasat sonrası depolamada nem, haşere ve havalandırma önemli. Siz depolamada hangi önlemleri alıyorsunuz? Ürününüzü hemen mi satıyorsunuz, bekletiyor musunuz?'],
        ['bakliyat', 'Nohut ekiminde hangi çeşitleri tercih ediyorsunuz?', 'Nohutta çeşit seçimi verimi ve satış fiyatını etkiliyor. Sizin bölgede hangi çeşitler iyi sonuç veriyor? Toptan alıcılar hangi özelliklere dikkat ediyor?'],
        ['kuruyemis', 'Fındık kurutma: serende mi, makinede mi?', 'Fındığı güneşte serende mi kurutuyorsunuz, yoksa kurutma makinesi mi kullanıyorsunuz? Kalite, süre ve maliyet açısından farkı nasıl buluyorsunuz?'],
        ['kuruyemis', 'Ceviz ve Antep fıstığında toptan satış', 'Ceviz ve Antep fıstığını satarken kabuklu/iç, boy ve nem gibi konularda alıcılar nelere bakıyor? Satış deneyimi olanların görüşlerini merak ediyoruz.'],
        [null, 'Ürününüzü alıcıya nasıl ulaştırıyorsunuz? (Nakliye)', 'Toptan satışta nakliye çoğu zaman en zor kısım. Kendi aracınız mı, kamyon kiralama mı, alıcının aracı mı? Nakliye ücretini kim ödüyor, nasıl anlaşıyorsunuz?'],
        [null, 'Rehber: İlan verirken nelere dikkat etmeli?', "Daha çok alıcıya ulaşmak için birkaç öneri:\n\n• Başlığa ürünü ve miktarı açıkça yazın (örn. \"5 ton Domates\").\n• Konumu (il/ilçe) doğru seçin; alıcılar bölgeye göre arıyor.\n• Fiyatı biliyorsanız yazın; bilmiyorsanız \"Fiyat görüşülür\" seçeneğini kullanın.\n• Ürünün ne zaman hazır olacağını belirtin.\n• Açıklamaya çeşit, boy, ambalaj gibi bilgileri ekleyin.\n\nSizin eklemek istediğiniz öneriler varsa aşağıya yazabilirsiniz."],
        [null, 'Rehber: Alıcıyla ya da satıcıyla görüşürken dikkat edilecekler', "Toptanhasat yalnızca alıcı ile satıcıyı buluşturan bir ilan platformudur; fiyat, ödeme ve teslimat taraflar arasında belirlenir. Görüşürken şunlar işinize yarayabilir:\n\n• Miktarı, kaliteyi ve teslim tarihini net konuşun.\n• Ödeme şeklini ve zamanını önceden kararlaştırın.\n• Mümkünse ürünü yerinde görün ya da numune isteyin.\n• Anlaştığınız koşulları yazılı hale getirin.\n\nKendi deneyimlerinizden öneriler eklemek isterseniz memnun oluruz."],
    ];

    public function run(): void
    {
        $team = User::query()->where('is_admin', true)->orderBy('id')->first();
        if (! $team) {
            $this->command?->warn('Yönetici hesabı yok; önce AdminSeeder çalıştırın.');

            return;
        }

        $categories = Category::query()->pluck('id', 'slug');
        $created = 0;

        foreach (self::TOPICS as $i => [$slug, $title, $body]) {
            if (ForumTopic::where('title', $title)->exists()) {
                continue;
            }

            $topic = new ForumTopic(['category_id' => $slug ? ($categories[$slug] ?? null) : null, 'title' => $title, 'body' => $body]);
            $topic->user()->associate($team);
            // Listede sırayı korumak için birer dakika arayla (ilk konu en üstte)
            $topic->created_at = $topic->last_activity_at = now()->subMinutes($i);
            $topic->save();
            $created++;
        }

        $this->command?->info("{$created} forum konusu açıldı.");
    }
}
