{{--
    Üst barda, profil resminin hemen solunda açık / koyu / sistem tema seçici.
    Filament'in kendi theme-switcher stillerini (fi-theme-switcher*) kullanır; menü içindeki
    sürümünden farkı close() çağırmaması (menü dışında olduğu için).
    Tema, Filament'in dark-mode.js'inin dinlediği "theme-changed" olayıyla uygulanır.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $modes = [
        'light' => [Heroicon::Sun, 'Açık tema'],
        'dark' => [Heroicon::Moon, 'Koyu tema'],
        'system' => [Heroicon::ComputerDesktop, 'Sistem teması'],
    ];
@endphp

<div
    x-data="{ theme: localStorage.getItem('theme') || @js(filament()->getDefaultThemeMode()->value) }"
    x-init="$watch('theme', (value) => $dispatch('theme-changed', value))"
    role="radiogroup"
    aria-label="Tema"
    class="fi-theme-switcher th-topbar-theme"
>
    @foreach ($modes as $mode => [$icon, $label])
        <button
            type="button"
            role="radio"
            aria-label="{{ $label }}"
            x-on:click="theme = @js($mode)"
            x-bind:aria-checked="theme === @js($mode) ? 'true' : 'false'"
            x-bind:class="{ 'fi-active': theme === @js($mode) }"
            x-tooltip="{ content: @js($label), theme: $store.theme }"
            class="fi-theme-switcher-btn"
        >
            {{ \Filament\Support\generate_icon_html($icon) }}
        </button>
    @endforeach
</div>

<style>
    /* Üst barda kompakt, kenarlıklı bir grup gibi dursun */
    .th-topbar-theme {
        padding: 2px;
        border-radius: 0.6rem;
        border: 1px solid var(--gray-200);
    }
    .dark .th-topbar-theme {
        border-color: color-mix(in oklab, var(--color-white) 10%, transparent);
    }
    .th-topbar-theme .fi-theme-switcher-btn {
        padding: 0.375rem;
        color: var(--gray-400);
    }
    .th-topbar-theme .fi-theme-switcher-btn.fi-active {
        color: var(--primary-600);
    }
    .dark .th-topbar-theme .fi-theme-switcher-btn.fi-active {
        background-color: color-mix(in oklab, var(--color-white) 8%, transparent);
        color: var(--primary-400);
    }
    .th-topbar-theme svg {
        width: 1.125rem;
        height: 1.125rem;
    }
</style>
