<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Sitedeki tüm mail gönderimleri admin panel → Site Ayarları → SMTP Ayarları'ndaki
 * değerlerle yapılır (.env'deki MAIL_* kullanılmaz). Değerler site_settings.data.mail
 * altında tutulur; SMTP şifresi şifrelenmiş saklanır.
 */
class MailSettings
{
    public const DRIVERS = [
        'smtp' => 'SMTP',
        'log' => 'Kapalı', // mailler gönderilmez, sadece log dosyasına yazılır
    ];

    public const ENCRYPTIONS = [
        'tls' => 'TLS (587)',
        'ssl' => 'SSL (465)',
        'none' => 'Yok',
    ];

    /** Kayıtlı ayarlar (şifre çözülmüş) */
    public static function get(): array
    {
        $mail = (array) SiteSetting::get('mail', []);

        return [
            'recipient' => (string) ($mail['recipient'] ?? ''),
            'from_address' => (string) ($mail['from_address'] ?? ''),
            'from_name' => (string) ($mail['from_name'] ?? ''),
            'driver' => (string) ($mail['driver'] ?? 'log'),
            'host' => (string) ($mail['host'] ?? ''),
            'port' => (int) ($mail['port'] ?? 587),
            'username' => (string) ($mail['username'] ?? ''),
            'encryption' => (string) ($mail['encryption'] ?? 'tls'),
            'password' => self::decrypt($mail['password'] ?? null),
        ];
    }

    /** SMTP seçili ve gönderim için gerekenler dolu mu */
    public static function isActive(?array $s = null): bool
    {
        $s ??= self::get();

        return $s['driver'] === 'smtp' && filled($s['host']) && filled($s['from_address']);
    }

    public static function statusLabel(): string
    {
        $s = self::get();

        if (self::isActive($s)) {
            return "Mail gönderimi aktif (SMTP: {$s['host']}:{$s['port']})";
        }

        return $s['driver'] === 'smtp'
            ? 'Mail gönderimi pasif: SMTP host ve gönderici e-posta girilmeli'
            : 'Mail gönderimi pasif: mailler gönderilmez, sadece kayda yazılır';
    }

    /** Ayarları Laravel'in mail yapılandırmasına uygular (önceden oluşturulmuş mailer'lar sıfırlanır) */
    public static function apply(): void
    {
        try {
            $s = self::get();
        } catch (Throwable) {
            return; // Veritabanı henüz hazır değil (kurulum, migration)
        }

        $active = self::isActive($s);

        config([
            'mail.default' => $active ? 'smtp' : 'log',
            'mail.mailers.smtp.scheme' => $s['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => $s['host'],
            'mail.mailers.smtp.port' => $s['port'],
            'mail.mailers.smtp.username' => $s['username'] ?: null,
            'mail.mailers.smtp.password' => $s['password'] ?: null,
            'mail.mailers.smtp.auto_tls' => $s['encryption'] !== 'none',
            'mail.mailers.smtp.timeout' => 15,
        ]);

        if (filled($s['from_address'])) {
            config(['mail.from.address' => $s['from_address']]);
        }
        if (filled($s['from_name'])) {
            config(['mail.from.name' => $s['from_name']]);
        }

        if (app()->resolved('mail.manager')) {
            app('mail.manager')->purge('smtp');
            app('mail.manager')->purge('log');
        }
    }

    public static function encrypt(string $password): string
    {
        return Crypt::encryptString($password);
    }

    private static function decrypt(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return ''; // APP_KEY değişmişse şifre yeniden girilmeli
        }
    }
}
