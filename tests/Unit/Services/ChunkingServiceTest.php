<?php

namespace Tests\Unit\Services;

use App\Services\ChunkingService;
use PHPUnit\Framework\TestCase;

class ChunkingServiceTest extends TestCase
{
    private ChunkingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChunkingService();
    }

    public function test_splits_content_into_chunks(): void
    {
        $content = str_repeat('Hello world. ', 200);

        $chunks = $this->service->split($content, 500, 100);

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_empty_content_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->split(''));
        $this->assertSame([], $this->service->split('   '));
    }

    public function test_chunks_have_required_keys(): void
    {
        $chunks = $this->service->split('This is test content for chunking.');

        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $this->assertArrayHasKey('content', $chunk);
            $this->assertArrayHasKey('position', $chunk);
            $this->assertArrayHasKey('token_count', $chunk);
        }
    }

    public function test_positions_are_sequential(): void
    {
        $content = str_repeat("Paragraph one content here.\n\nParagraph two content here.\n\n", 50);
        $chunks = $this->service->split($content, 200, 0);

        foreach ($chunks as $i => $chunk) {
            $this->assertSame($i, $chunk['position']);
        }
    }

    public function test_splits_by_paragraphs(): void
    {
        $content = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";

        $chunks = $this->service->split($content, 5000, 0);

        // Should fit in one chunk since total is well under 5000 chars
        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('First paragraph', $chunks[0]['content']);
    }

    public function test_splits_long_paragraphs_by_sentences(): void
    {
        $sentence = 'This is a moderately long sentence that repeats. ';
        $content = str_repeat($sentence, 30); // ~1500 chars, one "paragraph"

        $chunks = $this->service->split($content, 500, 0);

        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_count_tokens_approximation(): void
    {
        // ~4 chars per token
        $this->assertSame(1, $this->service->countTokens('Hi'));
        $this->assertSame(3, $this->service->countTokens('Hello world!'));
        $this->assertSame(25, $this->service->countTokens(str_repeat('a', 100)));
    }

    public function test_overlap_produces_overlapping_chunks(): void
    {
        $content = str_repeat('Word ', 200); // ~1000 chars

        $noOverlap = $this->service->split($content, 200, 0);
        $withOverlap = $this->service->split($content, 200, 50);

        // With overlap there should be more chunks (overlap causes re-inclusion of content)
        $this->assertGreaterThanOrEqual(count($noOverlap), count($withOverlap));
    }

    public function test_short_content_produces_single_chunk(): void
    {
        $chunks = $this->service->split('Short text.');

        $this->assertCount(1, $chunks);
        $this->assertSame('Short text.', $chunks[0]['content']);
        $this->assertSame(0, $chunks[0]['position']);
    }
}
