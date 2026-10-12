<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Support\MailSettings;
use App\Support\SiteData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    /** Ayarlar + katalog + hal fiyatları (Vue açılışta bir kez alır) */
    public function show(): JsonResponse
    {
        return response()->json(SiteData::get());
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return response()->json([
            'slug' => $page->slug,
            'title' => $page->title,
            'subtitle' => $page->subtitle,
            // Admin girişi de olsa HTML'i temizleyerek gönder (script, onclick vb. atılır)
            'body' => $page->body ? Str::sanitizeHtml($page->body) : null,
            'content' => $page->content ?? (object) [],
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
        ]);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', Rule::in(array_keys(ContactMessage::SUBJECTS))],
            'message' => ['required', 'string', 'max:3000'],
        ], [
            'name.required' => 'Adınızı girin.',
            'email.required' => 'E-posta adresinizi girin.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'subject.required' => 'Bir konu seçin.',
            'subject.in' => 'Bir konu seçin.',
            'message.required' => 'Mesajınızı yazın.',
            'message.max' => 'Mesaj en fazla 3000 karakter olabilir.',
        ]);

        $contact = ContactMessage::create([...$data, 'ip_address' => $request->ip()]);

        // Panelde alıcı adresi ve SMTP ayarlıysa mesaj maille de iletilir
        $recipient = MailSettings::get()['recipient'];
        if (filled($recipient)) {
            MailSettings::sendLater(fn () => Mail::to($recipient)->send(new ContactMessageReceived($contact)));
        }

        return response()->json(['message' => 'Mesajınız alındı.'], 201);
    }
}
