<?php

test('health endpoint returns 200 json', function () {
    $response = $this->getJson('/health');

    $response->assertOk();
    $response->assertExactJson(['status' => 'ok']);
});

test('health endpoint does not require auth', function () {
    $response = $this->getJson('/health');

    $response->assertOk();
});

test('health endpoint returns json content type', function () {
    $response = $this->getJson('/health');

    $response->assertHeader('Content-Type', 'application/json');
});
