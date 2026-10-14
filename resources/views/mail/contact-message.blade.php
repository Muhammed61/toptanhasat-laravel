{{-- İletişim formundan gelen mesaj (yöneticiye); diğer maillerle aynı çerçeve --}}
@php($md = fn ($text) => \App\Support\MailText::md($text))
<x-mail::message>
# Yeni iletişim formu mesajı

<x-mail::table>
| | |
|:--|:--|
| **Konu** | {!! $md($contact->subjectLabel()) !!} |
| **Ad soyad** | {!! $md($contact->name) !!} |
| **E-posta** | {!! $md($contact->email) !!} |
@if ($contact->phone)
| **Telefon** | {!! $md($contact->phone) !!} |
@endif
| **Tarih** | {{ $contact->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }} |
</x-mail::table>

<x-mail::panel>
{!! nl2br($md($contact->message)) !!}
</x-mail::panel>

Bu maili yanıtladığınızda yanıtınız doğrudan gönderene gider. Mesaj yönetim panelinde **İletişim Formu** bölümünde de kayıtlıdır.
</x-mail::message>
