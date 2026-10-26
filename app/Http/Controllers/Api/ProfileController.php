<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Rules\TcKimlikNo;
use App\Support\AdminMail;
use App\Support\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Sitedeki "Profilim" sayfası: bilgiler, şifre, profil fotoğrafı, TC kimlik ve ÇKS belgesi */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();

        // ÇKS belgesindeki adla eşleştiği onaylandıysa ad soyad sitede değiştirilemez
        if ($user->canPublishSellListing() && ! $user->is_admin
            && ($data['first_name'] !== $user->first_name || $data['last_name'] !== $user->last_name)) {
            throw ValidationException::withMessages([
                'first_name' => 'ÇKS belgeniz onaylandığı için ad soyad değiştirilemez. Değişiklik için bizimle iletişime geçin.',
            ]);
        }

        $user->update($data);

        return new UserResource($user);
    }

    /** Doğrulama gelmeden önce üye olanlar TC'lerini bir kez ekler (sonra değiştirilemez) */
    public function tc(Request $request): UserResource
    {
        $user = $request->user();
        if ($user->hasTc()) {
            throw ValidationException::withMessages(['tc_kimlik' => 'TC kimlik numaranız zaten kayıtlı. Değiştirmek için bizimle iletişime geçin.']);
        }

        $request->merge(['tc_kimlik' => preg_replace('/\D/', '', (string) $request->input('tc_kimlik'))]);
        $data = $request->validate(['tc_kimlik' => ['required', new TcKimlikNo]], ['tc_kimlik.required' => 'TC kimlik numaranızı girin.']);

        $user->setTcKimlik($data['tc_kimlik'])->save();

        return new UserResource($user);
    }

    /** ÇKS numarası ve e-Devlet belgesi: satılık ilan için; yönetici onaylar */
    public function cks(Request $request): UserResource
    {
        $user = $request->user();
        if ($user->canPublishSellListing() && ! $user->is_admin) {
            throw ValidationException::withMessages(['cks_number' => 'ÇKS belgeniz zaten onaylı.']);
        }

        $data = $request->validate([
            'cks_number' => ['required', 'string', 'regex:/^[0-9A-Za-z\-\/]{4,30}$/'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'cks_number.required' => 'ÇKS numaranızı girin.',
            'cks_number.regex' => 'Geçerli bir ÇKS numarası girin.',
            'document.required' => 'e-Devlet\'ten aldığınız ÇKS belgesini yükleyin.',
            'document.mimes' => 'Belge PDF, JPG, PNG veya WEBP olmalı.',
            'document.max' => 'Belge en fazla 5 MB olabilir.',
        ]);

        $path = $request->file('document')->store("cks/{$user->id}", 'documents');
        $user->submitCks(trim($data['cks_number']), $path);

        rescue(fn () => AdminNotifier::cks($user));
        AdminMail::cks($user);

        return new UserResource($user);
    }

    public function password(ChangePasswordRequest $request): Response
    {
        $request->user()->update(['password' => $request->validated('password')]);

        // Oturum kimliği yenilenir; mevcut oturum açık kalır
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->noContent();
    }

    /** Eski fotoğraf dosyası User modelindeki "updated" olayında silinir */
    public function avatar(Request $request): UserResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'avatar.required' => 'Bir fotoğraf seçin.',
            'avatar.image' => 'Yalnızca fotoğraf yükleyebilirsiniz.',
            'avatar.mimes' => 'Fotoğraf JPG, PNG veya WEBP olmalı.',
            'avatar.max' => 'Fotoğraf en fazla 2 MB olabilir.',
            'avatar.dimensions' => 'Fotoğraf en fazla 4000×4000 piksel olabilir.',
        ]);

        $request->user()->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        return new UserResource($request->user());
    }

    public function removeAvatar(Request $request): UserResource
    {
        $request->user()->update(['avatar_path' => null]);

        return new UserResource($request->user());
    }
}
