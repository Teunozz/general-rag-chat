<?php

use App\Services\DateFilter;
use App\Services\EnrichmentResult;
use App\Services\QueryEnrichmentService;
use App\Services\SystemSettingsService;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->settings = Mockery::mock(SystemSettingsService::class);
    $this->service = new QueryEnrichmentService($this->settings);
});

test('parseResponse handles valid JSON with all fields', function (): void {
    $json = json_encode([
        'enriched_query' => 'expanded search terms',
        'date_filter' => [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'expression' => 'last month',
        ],
        'source_ids' => [],
    ]);

    $result = $this->service->parseResponse($json, 'original query');

    expect($result)->toBeInstanceOf(EnrichmentResult::class)
        ->and($result->originalQuery)->toBe('original query')
        ->and($result->enrichedQuery)->toBe('expanded search terms')
        ->and($result->dateFilter)->toBeInstanceOf(DateFilter::class)
        ->and($result->dateFilter->startDate)->toBeInstanceOf(CarbonImmutable::class)
        ->and($result->dateFilter->startDate->format('Y-m-d'))->toBe('2025-01-01')
        ->and($result->dateFilter->endDate->format('Y-m-d'))->toBe('2025-01-31')
        ->and($result->dateFilter->expression)->toBe('last month');
});

test('parseResponse handles JSON with null filters', function (): void {
    $json = json_encode([
        'enriched_query' => 'simple expanded query',
        'date_filter' => null,
        'source_ids' => null,
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result)->toBeInstanceOf(EnrichmentResult::class)
        ->and($result->enrichedQuery)->toBe('simple expanded query')
        ->and($result->dateFilter)->toBeNull()
        ->and($result->sourceIds)->toBeNull();
});

test('parseResponse strips markdown code fences', function (): void {
    $json = "```json\n" . json_encode([
        'enriched_query' => 'fenced query',
        'date_filter' => null,
        'source_ids' => null,
    ]) . "\n```";

    $result = $this->service->parseResponse($json, 'original');

    expect($result)->toBeInstanceOf(EnrichmentResult::class)
        ->and($result->enrichedQuery)->toBe('fenced query');
});

test('parseResponse returns null for invalid JSON', function (): void {
    $result = $this->service->parseResponse('not json at all', 'original');

    expect($result)->toBeNull();
});

test('parseResponse returns null when enriched_query is missing', function (): void {
    $json = json_encode([
        'date_filter' => null,
        'source_ids' => null,
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result)->toBeNull();
});

test('parseResponse handles date filter with only start_date', function (): void {
    $json = json_encode([
        'enriched_query' => 'query with start date',
        'date_filter' => [
            'start_date' => '2025-06-01',
            'end_date' => null,
            'expression' => 'since June',
        ],
        'source_ids' => null,
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result->dateFilter)->toBeInstanceOf(DateFilter::class)
        ->and($result->dateFilter->startDate->format('Y-m-d'))->toBe('2025-06-01')
        ->and($result->dateFilter->endDate)->toBeNull()
        ->and($result->dateFilter->isActive())->toBeTrue();
});

test('parseResponse handles date filter with only end_date', function (): void {
    $json = json_encode([
        'enriched_query' => 'query with end date',
        'date_filter' => [
            'start_date' => null,
            'end_date' => '2025-12-31',
            'expression' => 'before year end',
        ],
        'source_ids' => null,
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result->dateFilter->endDate->format('Y-m-d'))->toBe('2025-12-31')
        ->and($result->dateFilter->startDate)->toBeNull()
        ->and($result->dateFilter->isActive())->toBeTrue();
});

test('parseResponse handles invalid date strings gracefully', function (): void {
    $json = json_encode([
        'enriched_query' => 'query',
        'date_filter' => [
            'start_date' => 'not-a-date',
            'end_date' => 'also-not-a-date',
            'expression' => 'bad dates',
        ],
        'source_ids' => null,
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result)->toBeInstanceOf(EnrichmentResult::class)
        ->and($result->dateFilter)->toBeNull();
});

test('parseResponse validates source IDs against database', function (): void {
    $source = \App\Models\Source::create([
        'name' => 'Test Source',
        'type' => 'web',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $json = json_encode([
        'enriched_query' => 'query about source',
        'date_filter' => null,
        'source_ids' => [$source->id, 99999],
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result->sourceIds)->toBe([$source->id]);
});

test('parseResponse returns null source_ids when none are valid', function (): void {
    $json = json_encode([
        'enriched_query' => 'query',
        'date_filter' => null,
        'source_ids' => [99999, 88888],
    ]);

    $result = $this->service->parseResponse($json, 'original');

    expect($result->sourceIds)->toBeNull();
});

test('DateFilter isActive returns false when both dates are null', function (): void {
    $filter = new DateFilter(null, null);

    expect($filter->isActive())->toBeFalse();
});

test('DateFilter isActive returns true when either date is set', function (): void {
    $filter1 = new DateFilter(CarbonImmutable::now(), null);
    $filter2 = new DateFilter(null, CarbonImmutable::now());
    $filter3 = new DateFilter(CarbonImmutable::now(), CarbonImmutable::now());

    expect($filter1->isActive())->toBeTrue()
        ->and($filter2->isActive())->toBeTrue()
        ->and($filter3->isActive())->toBeTrue();
});
