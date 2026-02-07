<?php

namespace Tests\Feature\Admin;

use App\Jobs\ChunkAndEmbedJob;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Document;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SourceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_edit_source_page(): void
    {
        $source = Source::create([
            'name' => 'Edit Me',
            'type' => 'website',
            'url' => 'https://example.com',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.sources.edit', $source));

        $response->assertOk();
    }

    public function test_update_source(): void
    {
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
        $this->assertSame('New Name', $source->name);
        $this->assertSame(5, $source->crawl_depth);
    }

    public function test_delete_cascades_to_documents(): void
    {
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
    }

    public function test_reindex_website_dispatches_crawl_job(): void
    {
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
    }

    public function test_rechunk_dispatches_chunk_jobs(): void
    {
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
    }

    public function test_rechunk_all_dispatches_for_ready_sources(): void
    {
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
    }
}
