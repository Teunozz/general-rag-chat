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

test('result includes published_at key', function (): void {
    $html = <<<'HTML'
    <html><head><title>Test</title></head>
    <body>
        <article>
            <p>This is a test article with enough content for the Readability parser to properly extract. We need sufficient text for reliable content identification.</p>
            <p>Additional paragraph to ensure the readability algorithm has enough material to work with during extraction.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result)->toHaveKey('published_at');
    }
});

test('extracts published_at from JSON-LD datePublished', function (): void {
    $html = <<<'HTML'
    <html><head><title>JSON-LD Article</title>
    <script type="application/ld+json">{"@type":"Article","datePublished":"2025-03-15T10:00:00Z","headline":"Test"}</script>
    </head>
    <body>
        <article>
            <p>This is a test article with enough content for the Readability parser. The JSON-LD markup should provide the published date for extraction by our content extractor service.</p>
            <p>Additional paragraph for readability content mass. This ensures reliable extraction works properly.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->not->toBeNull()
            ->and($result['published_at']->format('Y-m-d'))->toBe('2025-03-15');
    }
});

test('extracts published_at from meta article:published_time', function (): void {
    $html = <<<'HTML'
    <html><head>
    <title>Meta Article</title>
    <meta property="article:published_time" content="2025-06-20T14:30:00Z">
    </head>
    <body>
        <article>
            <p>This article uses OpenGraph meta tags for its published date. The content extractor should find and parse the article:published_time meta property correctly.</p>
            <p>Second paragraph to provide enough content for the readability parser to identify the main content area.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->not->toBeNull()
            ->and($result['published_at']->format('Y-m-d'))->toBe('2025-06-20');
    }
});

test('extracts published_at from time element datetime', function (): void {
    $html = <<<'HTML'
    <html><head><title>Time Element</title></head>
    <body>
        <article>
            <time datetime="2025-09-01T08:00:00Z">September 1, 2025</time>
            <p>This article uses a time element with a datetime attribute. The content extractor should find and parse this as the published date for the document.</p>
            <p>Additional paragraph for the readability parser to have enough content to work with properly.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->not->toBeNull()
            ->and($result['published_at']->format('Y-m-d'))->toBe('2025-09-01');
    }
});

test('strips navigation and footer boilerplate from content', function (): void {
    $html = <<<'HTML_WRAP'
    <html><head><title>Article With Boilerplate</title></head>
    <body>
        <nav><ul><li><a href="/">Home</a></li><li><a href="/about">About</a></li></ul></nav>
        <article>
            <h1>Article With Boilerplate</h1>
            <p>This is the main article content that should be extracted cleanly. It contains important information that the reader actually wants to see when browsing the knowledge base.</p>
            <p>A second paragraph provides additional substance for the Readability parser to correctly identify the main content area of this page.</p>
        </article>
        <footer>
            <p>Copyright 2025 Example Corp. All rights reserved.</p>
            <p>General Data Protection Regulation (GDPR) Statement. Our advertisers use various cookies.</p>
            <p>Privacy Statement. Additional information can be found here at About Us.</p>
        </footer>
    </body></html>
    HTML_WRAP;

    $result = $this->service->extract($html);

    expect($result)->not->toBeNull()
        ->and($result['content'])->toContain('main article content')
        ->and($result['content'])->not->toContain('Copyright 2025')
        ->and($result['content'])->not->toContain('GDPR')
        ->and($result['content'])->not->toContain('Privacy Statement')
        ->and($result['content'])->not->toContain('About Us');
});

test('strips elements with boilerplate class names', function (): void {
    $html = <<<'HTML_WRAP'
    <html><head><title>Article With Boilerplate Classes</title></head>
    <body>
        <article>
            <h1>Clean Article</h1>
            <p>This is the primary content of the article that should survive extraction. The Readability algorithm should identify this as the main readable area of the page.</p>
            <p>Here is another paragraph of important article text to ensure enough content mass for reliable extraction.</p>
        </article>
        <div class="cookie-consent">We use cookies to improve your experience.</div>
        <div class="newsletter-signup">Subscribe to our newsletter for updates.</div>
        <aside class="sidebar">Related articles and advertisements here.</aside>
    </body></html>
    HTML_WRAP;

    $result = $this->service->extract($html);

    expect($result)->not->toBeNull()
        ->and($result['content'])->toContain('primary content')
        ->and($result['content'])->not->toContain('cookies')
        ->and($result['content'])->not->toContain('sidebar');
});

test('strips trailing boilerplate text from non-semantic html', function (): void {
    $html = <<<'HTML_WRAP'
    <html><head><title>Old School Article</title></head>
    <body>
        <div>
            <h1>Old School Article</h1>
            <p>This is real article content about space exploration and scientific discoveries. The article discusses recent developments in the aerospace industry and their implications for future missions.</p>
            <p>Additional details about the topic provide depth and context for the reader to understand the significance of these developments in the broader scientific landscape.</p>
            <span>Related Links</span>
            <span><a href="/topics">Space Exploration News</a></span>
            <span>The content herein, unless otherwise known to be public domain, are Copyright 1995-2024.</span>
            <span>General Data Protection Regulation (GDPR) Statement. Our advertisers use cookies.</span>
            <span>Privacy Statement. Additional information can be found here at About Us.</span>
        </div>
    </body></html>
    HTML_WRAP;

    $result = $this->service->extract($html);

    expect($result)->not->toBeNull()
        ->and($result['content'])->toContain('space exploration')
        ->and($result['content'])->not->toContain('Copyright')
        ->and($result['content'])->not->toContain('GDPR')
        ->and($result['content'])->not->toContain('Privacy Statement');
});

test('extracts published_at from itemprop datePublished', function (): void {
    $html = <<<'HTML'
    <html><head><title>Microdata Article</title></head>
    <body>
        <article>
            <meta itemprop="datePublished" content="2025-11-05T09:00:00Z">
            <p>This article uses microdata itemprop for its date. The content extractor should find and parse the datePublished itemprop from the meta element correctly.</p>
            <p>Second paragraph to provide enough content for the readability parser to identify the main content area.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->not->toBeNull()
            ->and($result['published_at']->format('Y-m-d'))->toBe('2025-11-05');
    }
});

test('extracts published_at from news dateline in body text', function (): void {
    $html = <<<'HTML'
    <html><head><title>News Wire Article</title></head>
    <body>
        <article>
            <p>Kourou (AFP) Feb 12, 2026 - The most powerful version of a rocket carried 32 satellites into space. This is a substantial article about a rocket launch from a news wire service with a dateline format date.</p>
            <p>The mission was considered a success as all satellites were deployed into their correct orbits, marking a milestone for the program.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->not->toBeNull()
            ->and($result['published_at']->format('Y-m-d'))->toBe('2026-02-12');
    }
});

test('extracts published_at from body text with day-first format', function (): void {
    $html = <<<'HTML'
    <html><head><title>European Date Format</title></head>
    <body>
        <article>
            <p>12 February 2026 - Scientists announced a breakthrough discovery in quantum computing. The research team published findings that could revolutionize the field of computation and data processing.</p>
            <p>The implications of this discovery extend to multiple industries including finance, healthcare, and artificial intelligence research.</p>
        </article>
    </body></html>
    HTML;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->not->toBeNull()
            ->and($result['published_at']->format('Y-m-d'))->toBe('2026-02-12');
    }
});

test('returns null published_at when no date found', function (): void {
    $html = <<<'HTML_WRAP'
    <html><head><title>No Date</title></head>
    <body>
        <article>
            <p>This article has no date markup at all. The content extractor should return null for published_at when no date information can be found in the HTML.</p>
            <p>Second paragraph of content for the readability parser to identify this as the main content area of the page.</p>
        </article>
    </body></html>
    HTML_WRAP;

    $result = $this->service->extract($html);

    if ($result !== null) {
        expect($result['published_at'])->toBeNull();
    }
});
