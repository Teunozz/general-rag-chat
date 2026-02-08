<?php

test('health endpoint returns 200 json', function (): void {
    $response = $this->getJson('/health');

    $response->assertOk();
    $response->assertExactJson(['status' => 'ok']);
});

test('health endpoint does not require auth', function (): void {
    $response = $this->getJson('/health');

    $response->assertOk();
});

test('health endpoint returns json content type', function (): void {
    $response = $this->getJson('/health');

    $response->assertHeader('Content-Type', 'application/json');
});
