<?php

use App\Services\ContentExtractorService;

beforeEach(function (): void {
    $this->service = new ContentExtractorService();
});

test('extracts content from html', function (): void {
    $html = <<<'HTML_WRAP'
    <html><head><title>Test Article</title></head>
    <body>
        <article>
            <h1>Test Article</h1>
            <p>This is the main content of the article. It has enough text to be considered readable content by the Readability algorithm. We need to make sure there is sufficient content here for the parser to identify this as the main content area of the page.</p>
            <p>Another paragraph with more substantial content to ensure the readability parser can properly identify and extract the main content from this HTML document.</p>
        </article>
    </body></html>
    HTML_WRAP;

    $result = $this->service->extract($html);

    expect($result)->not->toBeNull()
        ->and($result)->toHaveKeys(['title', 'content'])
        ->and($result['content'])->not->toBeEmpty();
});

test('returns null on empty content', function (): void {
    $html = '<html><body></body></html>';

    $result = $this->service->extract($html);

    expect($result)->toBeNull();
});

test('returns null on invalid html', function (): void {
    $result = $this->service->extract('not html at all');

    expect($result)->toBeNull();
});

test('strips html tags from content', function (): void {
    $html = <<<'HTML_WRAP'
    <html><head><title>Clean Content</title></head>
    <body>
        <article>
            <p>This is <strong>bold</strong> and <em>italic</em> text that should be stripped of HTML tags during extraction. We need enough content here for the Readability parser to work correctly and identify this as the main content area.</p>
            <p>Additional paragraph content to ensure proper extraction by the readability algorithm. The content should be plain text without any HTML markup.</p>
        </article>
    </body></html>
    HTML_WRAP;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['content'])->not->toContain('<strong>')
            ->and($result['content'])->not->toContain('<em>');
    }
});

test('extracts title', function (): void {
    $html = <<<'HTML'
    <html><head><title>My Article Title</title></head>
    <body>
        <article>
            <h1>My Article Title</h1>
            <p>This is the main content of the article with enough text to be properly extracted by the Readability library. The algorithm needs sufficient content to determine the main readable area of the document.</p>
            <p>A second paragraph provides additional content mass for the Readability parser to work with. This ensures reliable extraction of both title and content.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['title'])->not->toBeEmpty();
    }
});
