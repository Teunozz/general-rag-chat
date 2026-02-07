<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    public function test_csp_header_is_present(): void
    {
        $response = $this->get('/health');

        $response->assertHeader('Content-Security-Policy');
    }

    public function test_csp_header_includes_frame_ancestors_none(): void
    {
        $response = $this->get('/health');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_header_restricts_default_src_to_self(): void
    {
        $response = $this->get('/health');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
    }
}
