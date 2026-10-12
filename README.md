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
php artisan migrate --seed   # tablolar + 81 il / 973 ilçe + varsayılan site içerikleri
php artisan storage:link     # yüklenen logo vb. dosyalar için
php artisan admin:create     # panel yöneticisi oluşturur
php artisan serve            # http://localhost:8000
```

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
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

- Alt alan adının kök dizini projenin `public/` klasörü olmalı.
- İki adreste de HTTPS (SSL) gerekir; güvenli çerezler HTTP'de çalışmaz.
- Mail ayarları `.env`'den değil, panelden yapılır: Site Ayarları → SMTP Ayarları.

## Testler

```sh
php artisan test
```
