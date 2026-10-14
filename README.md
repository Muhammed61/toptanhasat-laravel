# Toptanhasat — API ve Yönetim Paneli (Laravel)

Çiftçiler ile toptan alıcıları buluşturan ilan platformunun arka ucu.
Toptanhasat yalnızca aracıdır; fiyat, ödeme ve teslimat alıcı ile satıcı arasındadır.

- **API:** Laravel 12 + Sanctum (Vue sitesi için çerezli oturum)
- **Yönetim paneli:** Filament (`/admin`) — ilan onayı, üyeler, mesajlar, sayfalar, kategoriler, site ayarları
- **Veritabanı:** MySQL / MariaDB (`utf8mb4_turkish_ci`)

Web sitesi (Vue) ayrı repodadır: [Muhammed61/toptanhasat](https://github.com/Muhammed61/toptanhasat)

## Kurulum

Gerekenler: PHP 8.2+, Composer, MySQL/MariaDB.

```sh
composer install
cp .env.example .env
php artisan key:generate
```

`.env` içinde veritabanı bilgilerini girin (varsayılan: `toptanhasat`, kullanıcı `root`), sonra:

```sh
php artisan migrate --seed   # tablolar + 81 il / 973 ilçe + varsayılan site içerikleri (+ admin, aşağıya bakın)
php artisan serve            # http://localhost:8000
```

**Admin hesabı** iki yoldan oluşturulabilir:

- `.env`'e `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_FIRST_NAME`, `ADMIN_LAST_NAME`, `ADMIN_PHONE` yazıp
  `php artisan db:seed --force` (AdminSeeder). E-posta zaten kayıtlıysa o üye admin yapılır; tekrar
  çalıştırmak ikinci hesap açmaz, şifreyi değiştirmez. Sonra `ADMIN_PASSWORD`'ü `.env`'den silebilirsiniz.
- ya da soru sorarak: `php artisan admin:create`

Panel: http://localhost:8000/admin

Vue sitesi geliştirmede http://localhost:5173 adresinden çalışır; `.env` içindeki
`FRONTEND_URL` ve `SANCTUM_STATEFUL_DOMAINS` bu adrese göre ayarlıdır.

## Yayına alma (api.toptanhasat.com)

Site `https://toptanhasat.com`, bu proje `https://api.toptanhasat.com` adresinde çalışır.
Site API'ye başka bir alan adından çerezle (Sanctum oturumu) istek attığı için sunucudaki
`.env`'de şunlar olmalı:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.toptanhasat.com
FRONTEND_URL=https://toptanhasat.com
SESSION_DOMAIN=.toptanhasat.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=toptanhasat.com,www.toptanhasat.com
CORS_ALLOWED_ORIGINS=https://toptanhasat.com,https://www.toptanhasat.com
```

Ardından:

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force          # il/ilçe, içerikler ve .env'deki ADMIN_* ile admin hesabı
php artisan config:cache && php artisan route:cache
```

- Alt alan adının kök dizini projenin `public/` klasörü olmalı.
- İki adreste de HTTPS (SSL) gerekir; güvenli çerezler HTTP'de çalışmaz.
- Panelin css/js/font dosyaları (`public/{css,js,fonts}/filament`) repoda bulunur; sunucuda ayrıca
  üretmek gerekmez. Filament sürümü güncellenince yerelde `php artisan filament:assets` çalıştırıp
  değişen dosyaları commit edin.
- Cloudflare/nginx arkasında Laravel vekil sunucu başlıklarına güvenir (`bootstrap/app.php` →
  `trustProxies`) ve `APP_URL` https ise adresleri https üretir; `APP_URL=https://...` olmalı.
- Dışarıdan yüklenen tüm görseller (logo, favicon, paylaşım görseli, profil fotoğrafları) `public/uploads`
  klasörüne kaydedilir, `https://api.toptanhasat.com/uploads/...` adresinden açılır. Dokploy her deploy'da
  uygulamayı yeniden kurduğu için bu klasör **kalıcı volume** olmalı (mount path: `/app/public/uploads`).
- Mail ayarları `.env`'den değil, panelden yapılır: Site Ayarları → SMTP Ayarları.

## Testler

```sh
php artisan test
```
