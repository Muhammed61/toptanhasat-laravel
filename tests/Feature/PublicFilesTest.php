<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFilesTest extends TestCase
{
    public function test_uploaded_files_are_served_without_storage_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/logo.png', 'png-bytes');

        $this->get('/storage/site/logo.png')->assertOk();
        $this->get('/storage/site/yok.png')->assertNotFound();
    }

    public function test_files_cannot_be_written_without_a_signed_url(): void
    {
        Storage::fake('public');

        $this->call('PUT', '/storage/site/hack.php?upload=1', content: '<?php echo 1;')->assertForbidden();
        Storage::disk('public')->assertMissing('site/hack.php');
    }
}
