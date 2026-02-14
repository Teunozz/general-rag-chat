<?php

use App\Jobs\CrawlWebsiteJob;
use App\Jobs\ProcessRssFeedJob;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('dispatches CrawlWebsiteJob for website due for refresh', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'My Website',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
        'refresh_interval' => 60,
        'last_indexed_at' => now()->subMinutes(90),
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertPushed(CrawlWebsiteJob::class);
});

test('does not dispatch for website not yet due', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'Recent Website',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
        'refresh_interval' => 60,
        'last_indexed_at' => now()->subMinutes(30),
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertNotPushed(CrawlWebsiteJob::class);
});

test('does not dispatch for website without refresh interval', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'No Refresh',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
        'refresh_interval' => null,
        'last_indexed_at' => now()->subDay(),
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertNotPushed(CrawlWebsiteJob::class);
});

test('skips sources in processing status', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'Processing',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'processing',
        'refresh_interval' => 60,
        'last_indexed_at' => now()->subDay(),
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertNotPushed(CrawlWebsiteJob::class);
});

test('dispatches ProcessRssFeedJob for RSS sources', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'My Feed',
        'type' => 'rss',
        'url' => 'https://example.com/feed',
        'status' => 'ready',
        'refresh_interval' => 30,
        'last_indexed_at' => now()->subMinutes(60),
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertPushed(ProcessRssFeedJob::class);
});

test('refreshes both website and rss sources in one run', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'Website',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
        'refresh_interval' => 60,
        'last_indexed_at' => now()->subMinutes(90),
    ]);

    Source::create([
        'name' => 'Feed',
        'type' => 'rss',
        'url' => 'https://example.com/feed',
        'status' => 'ready',
        'refresh_interval' => 30,
        'last_indexed_at' => now()->subMinutes(60),
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertPushed(CrawlWebsiteJob::class);
    Queue::assertPushed(ProcessRssFeedJob::class);
});

test('dispatches for website with no last_indexed_at', function (): void {
    Queue::fake();

    Source::create([
        'name' => 'Never Indexed',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
        'refresh_interval' => 60,
        'last_indexed_at' => null,
    ]);

    $this->artisan('app:refresh-sources')->assertSuccessful();

    Queue::assertPushed(CrawlWebsiteJob::class);
});
