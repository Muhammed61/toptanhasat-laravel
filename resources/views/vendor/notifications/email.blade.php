{{-- Bildirim mailleri (şifre sıfırlama, ilan onay/red, yorum...) — Laravel şablonundan; imza satır satır --}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {!! \App\Support\MailText::md($greeting) !!}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{!! \App\Support\MailText::md($line) !!}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{!! \App\Support\MailText::md($line) !!}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{!! nl2br(\App\Support\MailText::md($salutation)) !!}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
