<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Sitedeki "Profilim" sayfası: bilgiler, şifre ve profil fotoğrafı */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user());
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
