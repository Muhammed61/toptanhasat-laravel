<?php

use App\Http\Controllers\Admin\CksDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Panel: üyenin ÇKS belgesi (gizli diskte; yalnızca yöneticiler)
Route::get('/admin/cks-belgesi/{user}', CksDocumentController::class)->whereNumber('user')->name('admin.cks-document');
