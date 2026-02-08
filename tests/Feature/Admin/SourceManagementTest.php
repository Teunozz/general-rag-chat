<?php

use App\Jobs\ChunkAndEmbedJob;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Document;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('edit source page', function (): void {
    $source = Source::create([
        'name' => 'Edit Me',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.sources.edit', $source));

    $response->assertOk();
});

test('update source', function (): void {
    $source = Source::create([
        'name' => 'Old Name',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
        'crawl_depth' => 2,
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.sources.update', $source), [
        'name' => 'New Name',
        'crawl_depth' => 5,
    ]);

    $response->assertRedirect(route('admin.sources.index'));
    $source->refresh();
    expect($source->name)->toBe('New Name')
        ->and($source->crawl_depth)->toBe(5);
});

test('delete cascades to documents', function (): void {
    $source = Source::create([
        'name' => 'With Docs',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $document = Document::create([
        'source_id' => $source->id,
        'title' => 'Test Doc',
        'content' => 'Test content',
        'content_hash' => Document::hashContent('Test content'),
    ]);

    $this->actingAs($this->admin)->delete(route('admin.sources.destroy', $source));

    $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
});

test('reindex website dispatches crawl job', function (): void {
    Queue::fake();

    $source = Source::create([
        'name' => 'Reindex',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.sources.reindex', $source));

    $response->assertRedirect(route('admin.sources.index'));
    Queue::assertPushed(CrawlWebsiteJob::class);
});

test('rechunk dispatches chunk jobs', function (): void {
    Queue::fake();

    $source = Source::create([
        'name' => 'Rechunk',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    Document::create([
        'source_id' => $source->id,
        'title' => 'Doc',
        'content' => 'Content',
        'content_hash' => Document::hashContent('Content'),
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.sources.rechunk', $source));

    $response->assertRedirect(route('admin.sources.index'));
    Queue::assertPushed(ChunkAndEmbedJob::class);
});

test('rechunk all dispatches for ready sources', function (): void {
    Queue::fake();

    $readySource = Source::create([
        'name' => 'Ready',
        'type' => 'website',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    Document::create([
        'source_id' => $readySource->id,
        'title' => 'Doc',
        'content' => 'Content',
        'content_hash' => Document::hashContent('Content'),
    ]);

    Source::create([
        'name' => 'Processing',
        'type' => 'website',
        'url' => 'https://example2.com',
        'status' => 'processing',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.sources.rechunk-all'));

    $response->assertRedirect(route('admin.sources.index'));
    Queue::assertPushed(ChunkAndEmbedJob::class, 1);
});
