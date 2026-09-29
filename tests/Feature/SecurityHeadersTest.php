<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_allows_vite_assets_and_live_updates_in_local_environment(): void
    {
        $this->app->instance('env', 'local');

        $response = $this->get(route('admin.login'));

        $response->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' http://127.0.0.1:5173 data: https:; style-src 'self' http://127.0.0.1:5173 'unsafe-inline'; script-src 'self' http://127.0.0.1:5173; font-src 'self' http://127.0.0.1:5173; connect-src 'self' http://127.0.0.1:5173 ws://127.0.0.1:5173;");
        $response->assertHeader('X-XSS-Protection', '0');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    #[TestWith(['testing'])]
    public function test_refuses_vite_sources_outside_local_environment(string $environment): void
    {
        $this->app->instance('env', $environment);

        $response = $this->get(route('admin.login'));

        $response->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self';");
    }

    public function test_sensitive_pages_are_not_cached(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->app->instance('env', 'production');

        $response = $this->get('https://localhost/admin/login');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
