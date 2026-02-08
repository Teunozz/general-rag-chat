<?php

use App\Http\Middleware\ContentSecurityPolicy;

beforeEach(function (): void {
    ContentSecurityPolicy::reset();
});

test('csp header is present', function (): void {
    $response = $this->get('/health');

    $response->assertHeader('Content-Security-Policy');
});

test('csp header includes frame ancestors none by default', function (): void {
    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'none'");
});

test('csp header restricts default src to self with nonce', function (): void {
    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toMatch("/default-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/");
});

test('csp header includes base-uri directive', function (): void {
    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("base-uri 'self'");
});

test('csp header includes object-src directive', function (): void {
    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("object-src 'none'");
});

test('addPolicy appends value to directive', function (): void {
    ContentSecurityPolicy::addPolicy('img-src', 'https://cdn.example.com');

    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("img-src 'self' data: https://cdn.example.com");
});

test('addPolicy throws on unknown directive', function (): void {
    ContentSecurityPolicy::addPolicy('unknown-src', 'value');
})->throws(\InvalidArgumentException::class, 'Unknown CSP directive: unknown-src');

test('addFrameAncestor replaces none with specified source', function (): void {
    ContentSecurityPolicy::addFrameAncestor('https://parent.example.com');

    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('frame-ancestors https://parent.example.com')
        ->not->toContain("frame-ancestors 'none'");
});

test('addFrameAncestor supports multiple sources', function (): void {
    ContentSecurityPolicy::addFrameAncestor('https://a.example.com');
    ContentSecurityPolicy::addFrameAncestor('https://b.example.com');

    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-ancestors https://a.example.com https://b.example.com');
});

test('csp header is not overwritten if already set', function (): void {
    $existingCsp = "default-src 'none'";

    $this->app['router']->get('/csp-preset', function () use ($existingCsp) {
        return response('ok', 200, ['Content-Security-Policy' => $existingCsp]);
    })->middleware(ContentSecurityPolicy::class);

    $response = $this->get('/csp-preset');

    expect($response->headers->get('Content-Security-Policy'))->toBe($existingCsp);
});

test('reset clears all additional policies', function (): void {
    ContentSecurityPolicy::addPolicy('img-src', 'https://cdn.example.com');
    ContentSecurityPolicy::addFrameAncestor('https://parent.example.com');

    ContentSecurityPolicy::reset();

    $response = $this->get('/health');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->not->toContain('https://cdn.example.com')
        ->not->toContain('https://parent.example.com')
        ->toContain("frame-ancestors 'none'");
});

test('getNonce returns vite csp nonce', function (): void {
    $this->get('/health');

    $nonce = ContentSecurityPolicy::getNonce();

    expect($nonce)->toBeString()->not->toBeEmpty();
});
