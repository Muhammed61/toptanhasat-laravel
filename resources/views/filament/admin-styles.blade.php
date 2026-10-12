{{-- Panel genelinde küçük görünüm ayarları (özel tema derlemeden) --}}
<style>
    /* Sol menüde gruplar (İlanlar/Üyeler, Mesajlar, İçerik, Ayarlar) arasında ayırıcı çizgi */
    .fi-sidebar-nav-groups > .fi-sidebar-group + .fi-sidebar-group {
        padding-top: 1rem;
        border-top: 1px solid var(--gray-200);
    }

    .dark .fi-sidebar-nav-groups > .fi-sidebar-group + .fi-sidebar-group {
        border-top-color: color-mix(in oklab, var(--color-white) 10%, transparent);
    }

    /* Sol menü ile sayfa arasında belirgin çizgi */
    @media (min-width: 64rem) {
        .fi-main-sidebar {
            border-inline-end: 1px solid var(--gray-300);
        }

        .dark .fi-main-sidebar {
            border-inline-end-color: color-mix(in oklab, var(--color-white) 16%, transparent);
        }

        /* Aynı çizgi üst barda da devam eder (logo ile hamburger arasından) */
        .fi-topbar {
            position: relative;
        }

        .fi-topbar::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            inset-inline-start: calc(var(--sidebar-width) - 1px);
            width: 1px;
            background: var(--gray-300);
            pointer-events: none;
        }

        .dark .fi-topbar::after {
            background: color-mix(in oklab, var(--color-white) 16%, transparent);
        }

        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar::after {
            inset-inline-start: calc(var(--collapsed-sidebar-width) - 1px);
        }
    }

    /* Hamburger düğmesi logonun sağında (mobil ve masaüstü) */
    .fi-topbar-start {
        order: -1;
    }

    .fi-topbar-collapse-sidebar-btn-ctn {
        order: 2;
        margin-inline-start: 0.75rem;
    }

    .fi-topbar-start + .fi-topbar-open-sidebar-btn,
    .fi-topbar-open-sidebar-btn,
    .fi-topbar-close-sidebar-btn {
        order: 0;
        margin-inline-start: 0.5rem;
    }

    /* Menü daraltılınca: tam logo gizlenir, yerine yaprak işareti çıkar */
    .th-logo-mark {
        display: none;
    }

    .th-logo-mark img {
        width: 2rem;
        height: 2rem;
        display: block;
    }

    @media (min-width: 64rem) {
        /*
         * Logo alanı menü genişliği kadar (üst barın 1rem iç boşluğu düşülür); böylece
         * hamburger her zaman menü çizgisinin hemen dışında durur.
         */
        .fi-topbar-start > a:has(.fi-logo) {
            width: calc(var(--sidebar-width) - 1rem);
        }

        /* Yaprak, daralmış menünün (ikonların) tam ortasında */
        .th-logo-mark {
            width: var(--collapsed-sidebar-width);
            margin-inline-start: -1rem;
            justify-content: center;
        }

        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar-start > a:has(.fi-logo) {
            display: none;
        }

        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .th-logo-mark {
            display: flex;
        }
    }
</style>
