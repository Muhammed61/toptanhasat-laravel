<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Yayında HTTPS Cloudflare/nginx'te sonlanır; Laravel'e istek http olarak gelir */
class HttpsBehindProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_urls_are_https_when_proxy_says_so(): void
    {
        $html = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'api.toptanhasat.com',
            'X-Forwarded-Port' => '443',
        ])->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('https://api.toptanhasat.com/livewire', $html);
        $this->assertStringNotContainsString('http://api.toptanhasat.com/', $html);
    }
}
