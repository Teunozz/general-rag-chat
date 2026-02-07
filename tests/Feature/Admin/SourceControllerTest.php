<?php

namespace Tests\Feature\Admin;

use App\Jobs\CrawlWebsiteJob;
use App\Jobs\ProcessRssFeedJob;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SourceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_source_index_is_displayed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.sources.index'));

        $response->assertOk();
    }

    public function test_create_page_is_displayed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.sources.create'));

        $response->assertOk();
    }

    public function test_store_website_source(): void
    {
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
    }

    public function test_store_rss_source(): void
    {
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
    }

    public function test_store_rejects_invalid_type(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.sources.store'), [
            'type' => 'invalid',
            'name' => 'Bad Source',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_non_admin_cannot_access_sources(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.sources.index'));

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_sources(): void
    {
        $response = $this->get(route('admin.sources.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_delete_source(): void
    {
        $source = Source::create([
            'name' => 'To Delete',
            'type' => 'website',
            'url' => 'https://example.com',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.sources.destroy', $source));

        $response->assertRedirect(route('admin.sources.index'));
        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    }

    public function test_reindex_dispatches_job(): void
    {
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
    }
}
