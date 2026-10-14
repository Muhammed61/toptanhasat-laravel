<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFilesTest extends TestCase
{
    public function test_uploaded_images_go_to_public_uploads(): void
    {
        $disk = Storage::disk('public');

        $this->assertSame(public_path('uploads'.DIRECTORY_SEPARATOR.'site'), dirname($disk->path('site/logo.png')));
        $this->assertSame(rtrim(config('app.url'), '/').'/uploads/site/logo.png', $disk->url('site/logo.png'));
    }
}
