<?php

use App\Ai\Agents\QueryEnrichmentAgent;
use App\Services\DateFilter;
use App\Services\EnrichmentResult;
use App\Services\QueryEnrichmentService;
use Carbon\CarbonImmutable;

test('enrich returns result with all fields', function (): void {
    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => 'expanded search terms',
            'date_filter' => [
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-31',
                'expression' => 'last month',
            ],
            'source_ids' => [],
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original query');

    expect($result)->toBeInstanceOf(EnrichmentResult::class)
        ->and($result->originalQuery)->toBe('original query')
        ->and($result->enrichedQuery)->toBe('expanded search terms')
        ->and($result->dateFilter)->toBeInstanceOf(DateFilter::class)
        ->and($result->dateFilter->startDate)->toBeInstanceOf(CarbonImmutable::class)
        ->and($result->dateFilter->startDate->format('Y-m-d'))->toBe('2025-01-01')
        ->and($result->dateFilter->endDate->format('Y-m-d'))->toBe('2025-01-31')
        ->and($result->dateFilter->expression)->toBe('last month');
});

test('enrich returns null date filter when absent', function (): void {
    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => 'simple query',
            'date_filter' => null,
            'source_ids' => null,
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original');

    expect($result)->toBeInstanceOf(EnrichmentResult::class)
        ->and($result->enrichedQuery)->toBe('simple query')
        ->and($result->dateFilter)->toBeNull()
        ->and($result->sourceIds)->toBeNull();
});

test('enrich extracts date filter with only start_date', function (): void {
    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => 'query with start date',
            'date_filter' => [
                'start_date' => '2025-06-01',
                'end_date' => null,
                'expression' => 'since June',
            ],
            'source_ids' => null,
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original');

    expect($result->dateFilter)->toBeInstanceOf(DateFilter::class)
        ->and($result->dateFilter->startDate->format('Y-m-d'))->toBe('2025-06-01')
        ->and($result->dateFilter->endDate)->toBeNull()
        ->and($result->dateFilter->isActive())->toBeTrue();
});

test('enrich extracts date filter with only end_date', function (): void {
    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => 'query with end date',
            'date_filter' => [
                'start_date' => null,
                'end_date' => '2025-12-31',
                'expression' => 'before year end',
            ],
            'source_ids' => null,
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original');

    expect($result->dateFilter->endDate->format('Y-m-d'))->toBe('2025-12-31')
        ->and($result->dateFilter->startDate)->toBeNull()
        ->and($result->dateFilter->isActive())->toBeTrue();
});

test('enrich validates source IDs against database', function (): void {
    $source = \App\Models\Source::create([
        'name' => 'Test Source',
        'type' => 'web',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => 'query about source',
            'date_filter' => null,
            'source_ids' => [$source->id, 99999],
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original');

    expect($result->sourceIds)->toBe([$source->id]);
});

test('enrich returns null source_ids when none are valid', function (): void {
    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => 'query',
            'date_filter' => null,
            'source_ids' => [99999, 88888],
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original');

    expect($result->sourceIds)->toBeNull();
});

test('enrich returns null when enriched_query is empty', function (): void {
    QueryEnrichmentAgent::fake([
        [
            'enriched_query' => '',
            'date_filter' => null,
            'source_ids' => null,
        ],
    ]);

    $service = new QueryEnrichmentService();
    $result = $service->enrich('original');

    expect($result)->toBeNull();
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
