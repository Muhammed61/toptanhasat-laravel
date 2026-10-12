<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** İletişim formundan gelen mesaj, panelde girilen alıcı adresine iletilir; "Yanıtla" gönderene gider. */
class ContactMessageReceived extends Mailable
{
    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: SiteSetting::get('site_name', 'Toptanhasat').' iletişim formu: '.$this->contact->subjectLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-message');
    }
}
