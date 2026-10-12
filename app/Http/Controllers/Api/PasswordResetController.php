<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/** "Şifremi unuttum": e-postaya bağlantı gönderir; bağlantıdaki token ile yeni şifre belirlenir */
class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['email' => ['required', 'email:rfc', 'max:255']],
            ['email.required' => 'E-posta adresinizi girin.', 'email.email' => 'Geçerli bir e-posta adresi girin.'],
        );

        if (! MailSettings::isActive()) {
            return $this->mailUnavailable();
        }

        try {
            Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);
        } catch (Throwable $e) {
            report($e);

            return $this->mailUnavailable();
        }

        // Hesap var mı yok mu belli edilmez (e-posta taraması yapılamasın)
        return response()->json([
            'message' => 'Bu e-posta adresiyle kayıtlı bir hesap varsa şifre sıfırlama bağlantısı gönderildi. Gelen kutunuzu (ve gereksiz klasörünü) kontrol edin.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string', 'min:8', 'regex:/\pL/u', 'regex:/\d/', 'confirmed'],
        ], [
            'token.required' => 'Şifre sıfırlama bağlantısı eksik.',
            'email.required' => 'Şifre sıfırlama bağlantısı eksik.',
            'password.required' => 'Yeni şifrenizi girin.',
            'password.min' => 'Şifre en az 8 karakter olmalı.',
            'password.regex' => 'Şifre en az bir harf ve bir rakam içermeli.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ]);

        $status = Password::reset(
            [...$data, 'email' => mb_strtolower(trim($data['email']))],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                // Başka cihazlardaki açık oturumlar kapatılır
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $message = 'Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş. Lütfen yeni bir bağlantı isteyin.';

            return response()->json(['message' => $message, 'errors' => ['token' => [$message]]], 422);
        }

        return response()->json(['message' => 'Şifreniz sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz.']);
    }

    private function mailUnavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Şu anda e-posta gönderilemiyor. Lütfen biraz sonra tekrar deneyin veya iletişim sayfasından bize ulaşın.',
        ], 503);
    }
}
