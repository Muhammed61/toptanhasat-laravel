<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AdminMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Vue SPA için çerez (session) tabanlı oturum — Sanctum statefulApi.
 * İstemci önce GET /sanctum/csrf-cookie çağırır, sonra X-XSRF-TOKEN başlığıyla POST eder.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User([
            ...$request->safe()->only(['first_name', 'last_name', 'email', 'phone', 'password']),
            'terms_accepted_at' => now(),
        ]);
        $user->setTcKimlik($request->validated('tc_kimlik'))->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        AdminMail::newUser($user);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        return new UserResource(Auth::guard('web')->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
