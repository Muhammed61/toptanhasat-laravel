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

## Testler

```sh
php artisan test
```
