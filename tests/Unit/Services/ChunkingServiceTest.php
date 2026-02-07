<?php

use App\Services\ChunkingService;

beforeEach(function () {
    $this->service = new ChunkingService();
});

test('splits content into chunks', function () {
    $content = str_repeat('Hello world. ', 200);

    $chunks = $this->service->split($content, 500, 100);

    expect($chunks)->not->toBeEmpty()
        ->and(count($chunks))->toBeGreaterThan(1);
});

test('empty content returns empty array', function () {
    expect($this->service->split(''))->toBe([])
        ->and($this->service->split('   '))->toBe([]);
});

test('chunks have required keys', function () {
    $chunks = $this->service->split('This is test content for chunking.');

    expect($chunks)->not->toBeEmpty();
    foreach ($chunks as $chunk) {
        expect($chunk)->toHaveKeys(['content', 'position', 'token_count']);
    }
});

test('positions are sequential', function () {
    $content = str_repeat("Paragraph one content here.\n\nParagraph two content here.\n\n", 50);
    $chunks = $this->service->split($content, 200, 0);

    foreach ($chunks as $i => $chunk) {
        expect($chunk['position'])->toBe($i);
    }
});

test('splits by paragraphs', function () {
    $content = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";

    $chunks = $this->service->split($content, 5000, 0);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['content'])->toContain('First paragraph');
});

test('splits long paragraphs by sentences', function () {
    $sentence = 'This is a moderately long sentence that repeats. ';
    $content = str_repeat($sentence, 30);

    $chunks = $this->service->split($content, 500, 0);

    expect(count($chunks))->toBeGreaterThan(1);
});

test('count tokens approximation', function () {
    expect($this->service->countTokens('Hi'))->toBe(1)
        ->and($this->service->countTokens('Hello world!'))->toBe(3)
        ->and($this->service->countTokens(str_repeat('a', 100)))->toBe(25);
});

test('overlap produces overlapping chunks', function () {
    $content = str_repeat('Word ', 200);

    $noOverlap = $this->service->split($content, 200, 0);
    $withOverlap = $this->service->split($content, 200, 50);

    expect(count($withOverlap))->toBeGreaterThanOrEqual(count($noOverlap));
});

test('short content produces single chunk', function () {
    $chunks = $this->service->split('Short text.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['content'])->toBe('Short text.')
        ->and($chunks[0]['position'])->toBe(0);
});
