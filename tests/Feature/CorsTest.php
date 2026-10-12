<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vue sitesi API'ye başka bir alan adından çerezle istek atar (toptanhasat.com → api.toptanhasat.com) */
class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_origin_is_allowed_with_credentials(): void
    {
        $origin = rtrim(config('app.frontend_url'), '/');

        $this->withHeaders(['Origin' => $origin])->getJson('/api/site')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        // Tarayıcının ön kontrol (preflight) isteği
        $this->call('OPTIONS', '/api/auth/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_other_origins_are_not_allowed(): void
    {
        // Başka bir siteye izin verilmez (başlıkta yalnızca bizim site adresi olur, tarayıcı reddeder)
        $response = $this->withHeaders(['Origin' => 'https://baska-site.example'])->getJson('/api/site')->assertOk();
        $this->assertNotSame('https://baska-site.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
