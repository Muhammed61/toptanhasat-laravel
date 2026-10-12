<!doctype html>
<html lang="tr">
<body style="margin:0;padding:24px;background:#f5f5f4;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;padding:24px;">
        <h2 style="margin:0 0 16px;font-size:18px;color:#2d6a4f;">Yeni iletişim formu mesajı</h2>

        <table style="width:100%;font-size:14px;border-collapse:collapse;">
            <tr><td style="padding:4px 0;color:#6b7280;width:110px;">Konu</td><td>{{ $contact->subjectLabel() }}</td></tr>
            <tr><td style="padding:4px 0;color:#6b7280;">Ad soyad</td><td>{{ $contact->name }}</td></tr>
            <tr><td style="padding:4px 0;color:#6b7280;">E-posta</td><td>{{ $contact->email }}</td></tr>
            @if ($contact->phone)
                <tr><td style="padding:4px 0;color:#6b7280;">Telefon</td><td>{{ $contact->phone }}</td></tr>
            @endif
            <tr><td style="padding:4px 0;color:#6b7280;">Tarih</td><td>{{ $contact->created_at->format('d.m.Y H:i') }}</td></tr>
        </table>

        <div style="margin-top:16px;padding:12px 14px;background:#f9fafb;border-radius:6px;font-size:14px;line-height:1.5;white-space:pre-line;">{{ $contact->message }}</div>

        <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">Bu maili yanıtladığınızda yanıt doğrudan gönderene gider. Mesaj admin paneldeki İletişim Formu bölümünde de kayıtlıdır.</p>
    </div>
</body>
</html>
