<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Source;
use App\Services\FeedParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRssFeedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Source $source,
    ) {
    }

    public function handle(FeedParserService $feedParser): void
    {
        $this->source->update(['status' => 'processing', 'error_message' => null]);

        try {
            $entries = $feedParser->parse($this->source->url);

            foreach ($entries as $entry) {
                if (empty($entry['content'])) {
                    continue;
                }

                $contentHash = Document::hashContent($entry['content']);

                // Check if document with same guid exists and content is unchanged
                $existing = Document::where('source_id', $this->source->id)
                    ->where('external_guid', $entry['guid'])
                    ->first();

                if ($existing && $existing->content_hash === $contentHash) {
                    continue; // Skip unchanged
                }

                $document = Document::updateOrCreate(
                    ['source_id' => $this->source->id, 'external_guid' => $entry['guid']],
                    [
                        'title' => $entry['title'],
                        'url' => $entry['url'],
                        'content' => $entry['content'],
                        'content_hash' => $contentHash,
                        'published_at' => $entry['published_at'],
                    ]
                );

                ChunkAndEmbedJob::dispatch($document);
            }

            $this->source->update([
                'status' => 'ready',
                'last_indexed_at' => now(),
                'document_count' => $this->source->documents()->count(),
            ]);
        } catch (\Throwable $e) {
            $this->source->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
