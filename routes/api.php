<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\PublicListingController;
use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

// Üyelik
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    // İlanlar (üye)
    Route::post('/listings', [ListingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/my/listings', [ListingController::class, 'mine']);
    Route::get('/my/listings/{listing}', [ListingController::class, 'showOwn'])->whereNumber('listing');
    Route::put('/listings/{listing}', [ListingController::class, 'update'])->whereNumber('listing')->middleware('throttle:20,1');

    // İlan sahibinin iletişim bilgisi sadece üyelere; toplu toplanmasın diye sınırlı
    Route::get('/listings/{listing}/contact', [PublicListingController::class, 'contact'])->middleware('throttle:30,1');

    // Site içi mesajlaşma
    Route::post('/listings/{listing}/conversations', [ConversationController::class, 'start'])->middleware('throttle:20,1');
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'reply'])->middleware('throttle:30,1');
});

// Yayındaki ilanlar (herkese açık)
Route::get('/listings', [PublicListingController::class, 'index']);
Route::get('/listings/{listing}', [PublicListingController::class, 'show'])->whereNumber('listing');

// İl / ilçe
Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{city}/districts', [CityController::class, 'districts']);

// Panelden yönetilen site içeriği
Route::get('/site', [SiteController::class, 'show']);
Route::get('/pages/{slug}', [SiteController::class, 'page']);
Route::post('/contact', [SiteController::class, 'contact'])->middleware('throttle:5,1');
