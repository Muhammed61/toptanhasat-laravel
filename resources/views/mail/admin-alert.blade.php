{{-- Yöneticiye bildirim: yeni üye, ilan, yorum, mesajlaşma (App\Support\AdminMail) --}}
@php($md = fn ($text) => \App\Support\MailText::md($text))
<x-mail::message>
# {!! $md($title) !!}

{!! $md($intro) !!}

<x-mail::table>
| | |
|:--|:--|
@foreach ($rows as $label => $value)
| **{!! $md($label) !!}** | {!! $md(str_replace(["\r\n", "\n"], ' ', (string) $value)) !!} |
@endforeach
</x-mail::table>

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Bu maili hangi olaylarda alacağınızı yönetim panelinden değiştirebilirsiniz: Site Ayarları → SMTP Ayarları.
</x-mail::message>
