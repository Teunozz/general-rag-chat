<?php

test('health endpoint returns ok', function () {
    $response = $this->get('/health');

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});
