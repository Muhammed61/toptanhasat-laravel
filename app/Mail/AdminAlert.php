<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Yöneticiye "sitede şu oldu" maili (bkz. App\Support\AdminMail) */
class AdminAlert extends Mailable
{
    use Queueable;

    /** @param  array<string, string|null>  $rows */
    public function __construct(
        public string $title,
        public string $intro,
        public array $rows,
        public string $actionText,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin-alert');
    }
}
