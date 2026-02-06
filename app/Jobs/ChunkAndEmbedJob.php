<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChunkAndEmbedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public Document $document,
    ) {
    }

    public function handle(ChunkingService $chunker, EmbeddingService $embedder): void
    {
        // Delete existing chunks for this document
        $this->document->chunks()->delete();

        // Split content into chunks
        $chunks = $chunker->split($this->document->content);

        if (empty($chunks)) {
            return;
        }

        // Generate embeddings in batch
        $texts = array_column($chunks, 'content');
        $embeddings = $embedder->embedBatch($texts);

        // Insert chunks with embeddings
        $chunkRecords = [];
        foreach ($chunks as $i => $chunk) {
            $chunkRecords[] = [
                'document_id' => $this->document->id,
                'content' => $chunk['content'],
                'position' => $chunk['position'],
                'token_count' => $chunk['token_count'],
                'embedding' => json_encode($embeddings[$i]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->document->chunks()->insert($chunkRecords);

        // Update source counters
        $source = $this->document->source;
        $source->update([
            'chunk_count' => $source->documents()->withCount('chunks')->get()->sum('chunks_count'),
        ]);
    }
}
