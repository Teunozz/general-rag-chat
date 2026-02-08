<?php

test('csp header is present', function (): void {
    $response = $this->get('/health');

    $response->assertHeader('Content-Security-Policy');
});

test('csp header includes frame ancestors none', function (): void {
    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'none'");
});

test('csp header restricts default src to self', function (): void {
    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'");
});
