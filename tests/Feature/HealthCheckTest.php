<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_200_json(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertExactJson(['status' => 'ok']);
    }

    public function test_health_endpoint_does_not_require_auth(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
    }

    public function test_health_endpoint_returns_json_content_type(): void
    {
        $response = $this->getJson('/health');

        $response->assertHeader('Content-Type', 'application/json');
    }
}
