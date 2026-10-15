<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ListingCommentController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicListingController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SitemapController;
use App\Http\Middleware\EnsureMessagingEnabled;
use Illuminate\Support\Facades\Route;

// Üyelik
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Şifremi unuttum
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    // Profilim
    Route::put('/user', [ProfileController::class, 'update'])->middleware('throttle:20,1');
    Route::put('/user/password', [ProfileController::class, 'password'])->middleware('throttle:6,1');
    Route::post('/user/avatar', [ProfileController::class, 'avatar'])->middleware('throttle:10,1');
    Route::delete('/user/avatar', [ProfileController::class, 'removeAvatar']);

    // İlanlar (üye)
    Route::post('/listings', [ListingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/my/listings', [ListingController::class, 'mine']);
    Route::get('/my/listings/{listing}', [ListingController::class, 'showOwn'])->whereNumber('listing');
    Route::put('/listings/{listing}', [ListingController::class, 'update'])->whereNumber('listing')->middleware('throttle:20,1');

    // İlan sahibinin iletişim bilgisi sadece üyelere; toplu toplanmasın diye sınırlı
    Route::get('/listings/{listing}/contact', [PublicListingController::class, 'contact'])->middleware('throttle:30,1');

    // Site içi mesajlaşma (admin panelden kapatılabilir)
    Route::middleware(EnsureMessagingEnabled::class)->group(function () {
        Route::post('/listings/{listing}/conversations', [ConversationController::class, 'start'])->middleware('throttle:20,1');
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation');
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'reply'])->middleware('throttle:30,1');
    });
});

// Yayındaki ilanlar (herkese açık)
Route::get('/listings', [PublicListingController::class, 'index']);
Route::get('/listings/{listing}', [PublicListingController::class, 'show'])->whereNumber('listing');

// İlan yorumları: herkes okur/yazar (üyelik ve onay yok); IP başına dakikada 5 yorum
Route::get('/listings/{listing}/comments', [ListingCommentController::class, 'index'])->whereNumber('listing');
Route::get('/comments/latest', [ListingCommentController::class, 'latest']);
Route::post('/listings/{listing}/comments', [ListingCommentController::class, 'store'])->whereNumber('listing')->middleware('throttle:5,1');

// İl / ilçe
Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{city}/districts', [CityController::class, 'districts']);

// Panelden yönetilen site içeriği
Route::get('/site', [SiteController::class, 'show']);
Route::get('/pages/{slug}', [SiteController::class, 'page']);
// Arama motorları için site haritası verisi (sitemap.xml web sitesinde üretilir)
Route::get('/sitemap', SitemapController::class)->middleware('throttle:30,1');
Route::post('/contact', [SiteController::class, 'contact'])->middleware('throttle:5,1');
