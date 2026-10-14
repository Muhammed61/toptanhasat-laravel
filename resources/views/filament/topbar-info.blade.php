{{--
    Üst bar, hamburgerin sağı: karşılama, canlı tarih/saat (site saat dilimiyle) ve sunucu bilgisi.
    Dar ekranlarda önce sunucu bilgisi, sonra saat gizlenir (admin-styles.blade.php).
--}}
@php
    $user = filament()->auth()->user();

    // Sürüm bilgileri sık değişmez; her sayfada veritabanına sormamak için önbelleğe alınır
    $server = \Illuminate\Support\Facades\Cache::remember('admin.server_info', now()->addMinutes(10), function () {
        try {
            $db = \Illuminate\Support\Facades\DB::connection();
            $raw = $db->getDriverName() === 'sqlite'
                ? 'SQLite '.$db->selectOne('select sqlite_version() as v')->v
                : (string) $db->selectOne('select version() as v')->v;
            $database = str_contains($raw, 'MariaDB')
                ? 'MariaDB '.strtok($raw, '-')
                : (str_starts_with($raw, 'SQLite') ? $raw : 'MySQL '.strtok($raw, '-'));
        } catch (\Throwable) {
            $database = null;
        }

        return [
            'host' => gethostname() ?: 'sunucu',
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => $database,
            'os' => PHP_OS_FAMILY,
        ];
    });

    $serverLine = collect([
        'PHP '.$server['php'],
        'Laravel '.$server['laravel'],
        $server['database'],
    ])->filter()->implode(' · ');

    $serverTitle = "Sunucu: {$server['host']} ({$server['os']})\nOrtam: ".app()->environment()
        ."\nSaat dilimi: ".config('app.timezone');
@endphp

<div class="th-topbar-info">
    @if ($user)
        <span class="th-topbar-welcome">Hoş geldiniz, <strong>{{ $user->getFilamentName() }}</strong></span>
    @endif

    <span class="th-topbar-sep th-topbar-clock-part" aria-hidden="true"></span>
    <span
        class="th-topbar-clock th-topbar-clock-part"
        x-data="{
            now: '',
            fmt: new Intl.DateTimeFormat('tr-TR', {
                timeZone: @js(config('app.timezone')),
                day: 'numeric', month: 'long', year: 'numeric', weekday: 'long',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
            }),
            tick() { this.now = this.fmt.format(new Date()) },
        }"
        x-init="tick(); setInterval(() => tick(), 1000)"
        x-text="now"
    >{{ now()->locale('tr')->isoFormat('D MMMM YYYY dddd HH:mm:ss') }}</span>

    <span class="th-topbar-sep th-topbar-server-part" aria-hidden="true"></span>
    <span class="th-topbar-server th-topbar-server-part" title="{{ $serverTitle }}">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedServer) }}
        <span>{{ $server['host'] }}</span>
        <span class="th-topbar-muted">{{ $serverLine }}</span>
    </span>
</div>
