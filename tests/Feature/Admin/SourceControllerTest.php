<?php

use App\Jobs\CrawlWebsiteJob;
use App\Jobs\ProcessRssFeedJob;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('source index is displayed', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.sources.index'));

    $response->assertOk();
});

test('create page is displayed', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.sources.create'));

    $response->assertOk();
});

test('store website source', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)->post(route('admin.sources.store'), [
        'type' => 'website',
        'name' => 'Test Website',
        'url' => 'https://example.com',
        'crawl_depth' => 2,
        'min_content_length' => 100,
    ]);

    $response->assertRedirect(route('admin.sources.index'));
    $this->assertDatabaseHas('sources', [
        'name' => 'Test Website',
        'type' => 'website',
        'status' => 'pending',
    ]);
    Queue::assertPushed(CrawlWebsiteJob::class);
});

test('store rss source', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)->post(route('admin.sources.store'), [
        'type' => 'rss',
        'name' => 'Test RSS Feed',
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertRedirect(route('admin.sources.index'));
    $this->assertDatabaseHas('sources', [
        'name' => 'Test RSS Feed',
        'type' => 'rss',
    ]);
    Queue::assertPushed(ProcessRssFeedJob::class);
});

test('store rejects invalid type', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.sources.store'), [
        'type' => 'invalid',
        'name' => 'Bad Source',
    ]);

    $response->assertSessionHasErrors('type');
});

test('non admin cannot access sources', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.sources.index'));

    $response->assertForbidden();
});

test('guest cannot access sources', function () {
    $response = $this->get(route('admin.sources.index'));

    $response->assertRedirect(route('login'));
});

test('delete source', function () {
    $source = Source::create([
        'name' => 'To Delete',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $response = $this->actingAs($this->admin)->delete(route('admin.sources.destroy', $source));

    $response->assertRedirect(route('admin.sources.index'));
    $this->assertDatabaseMissing('sources', ['id' => $source->id]);
});

test('reindex dispatches job', function () {
    Queue::fake();

    $source = Source::create([
        'name' => 'Reindex Me',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.sources.reindex', $source));

    $response->assertRedirect(route('admin.sources.index'));
    Queue::assertPushed(CrawlWebsiteJob::class);
});
