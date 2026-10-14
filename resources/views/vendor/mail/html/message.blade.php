{{-- Tüm mailler için ortak çerçeve: logo, içerik, alt bilgi (iletişim + aracı platform notu) --}}
@php
    $site = \App\Support\SiteData::get()['settings'];
    $siteUrl = rtrim(config('app.frontend_url'), '/');
    $contact = collect([
        $site['email'] ? "[{$site['email']}](mailto:{$site['email']})" : null,
        $site['phone'] ?: null,
    ])->filter()->implode(' · ');
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $site['site_name'] }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**[{{ $site['site_name'] }}]({{ $siteUrl }})**@if ($site['footer_text']) — {{ $site['footer_text'] }}@endif

@if ($contact)
{!! $contact !!}

@endif
{{ $site['footer_disclaimer'] ?: $site['site_name'].' aracı bir ilan platformudur; kullanıcılar arasındaki alım-satım işlemlerine taraf değildir.' }}

© {{ date('Y') }} {{ $site['copyright'] ?: $site['site_name'].'. Tüm hakları saklıdır.' }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
