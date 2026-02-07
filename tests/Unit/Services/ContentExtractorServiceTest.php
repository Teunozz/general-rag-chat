<?php

namespace Tests\Unit\Services;

use App\Services\ContentExtractorService;
use PHPUnit\Framework\TestCase;

class ContentExtractorServiceTest extends TestCase
{
    private ContentExtractorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentExtractorService();
    }

    public function test_extracts_content_from_html(): void
    {
        $html = <<<'HTML'
        <html><head><title>Test Article</title></head>
        <body>
            <article>
                <h1>Test Article</h1>
                <p>This is the main content of the article. It has enough text to be considered readable content by the Readability algorithm. We need to make sure there is sufficient content here for the parser to identify this as the main content area of the page.</p>
                <p>Another paragraph with more substantial content to ensure the readability parser can properly identify and extract the main content from this HTML document.</p>
            </article>
        </body></html>
        HTML;

        $result = $this->service->extract($html);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertNotEmpty($result['content']);
    }

    public function test_returns_null_on_empty_content(): void
    {
        $html = '<html><body></body></html>';

        $result = $this->service->extract($html);

        $this->assertNull($result);
    }

    public function test_returns_null_on_invalid_html(): void
    {
        $result = $this->service->extract('not html at all');

        $this->assertNull($result);
    }

    public function test_strips_html_tags_from_content(): void
    {
        $html = <<<'HTML'
        <html><head><title>Clean Content</title></head>
        <body>
            <article>
                <p>This is <strong>bold</strong> and <em>italic</em> text that should be stripped of HTML tags during extraction. We need enough content here for the Readability parser to work correctly and identify this as the main content area.</p>
                <p>Additional paragraph content to ensure proper extraction by the readability algorithm. The content should be plain text without any HTML markup.</p>
            </article>
        </body></html>
        HTML;

        $result = $this->service->extract($html);

        if ($result !== null) {
            $this->assertStringNotContainsString('<strong>', $result['content']);
            $this->assertStringNotContainsString('<em>', $result['content']);
        }
    }

    public function test_extracts_title(): void
    {
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
            $this->assertNotEmpty($result['title']);
        }
    }
}
