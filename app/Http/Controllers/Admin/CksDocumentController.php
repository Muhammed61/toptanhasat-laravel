<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Panelde "ÇKS belgesini aç": belge gizli diskte, yalnızca yöneticiler görür (tarayıcıda açılır) */
class CksDocumentController extends Controller
{
    public function __invoke(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $path = $user->cks_document_path;
        abort_unless($path && Storage::disk('documents')->exists($path), 404);

        return Storage::disk('documents')->response($path, "cks-{$user->id}.".pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
