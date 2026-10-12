{{--
    Menü masaüstünde daraltıldığında tam logo yerine görünen küçük işaret (favicon'daki yaprak).
    Görünürlüğü admin-styles.blade.php'deki CSS yönetir.
--}}
@php
    $mark = \App\Models\SiteSetting::get('favicon');
@endphp

@if ($mark)
    <a href="{{ filament()->getHomeUrl() }}" class="th-logo-mark" aria-label="{{ filament()->getBrandName() }}">
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($mark) }}" alt="" />
    </a>
@endif
