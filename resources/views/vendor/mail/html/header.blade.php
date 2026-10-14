@props(['url'])
{{-- Mail başlığı: panelde yüklü logo (Site Ayarları), yoksa site adı --}}
@php($site = \App\Support\SiteData::get()['settings'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($site['logo_url'])
<img src="{{ $site['logo_url'] }}" class="logo" alt="{{ $site['site_name'] }}">
@else
{{ $site['site_name'] }}
@endif
</a>
</td>
</tr>
