<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentUploadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Source $source,
        public string $filePath,
        public string $originalName,
    ) {
    }

    public function handle(): void
    {
        $this->source->update(['status' => 'processing', 'error_message' => null]);

        try {
            $content = Storage::get($this->filePath);
            $extension = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));

            $text = match ($extension) {
                'txt', 'md' => $content,
                'html', 'htm' => $this->extractHtml($content),
                default => $content, // Fallback to raw content
            };

            if (empty($text)) {
                $this->source->update([
                    'status' => 'error',
                    'error_message' => 'Could not extract text from uploaded file.',
                ]);
                return;
            }

            $contentHash = Document::hashContent($text);

            $document = Document::updateOrCreate(
                ['source_id' => $this->source->id, 'url' => $this->originalName],
                [
                    'title' => pathinfo($this->originalName, PATHINFO_FILENAME),
                    'content' => $text,
                    'content_hash' => $contentHash,
                ]
            );

            ChunkAndEmbedJob::dispatch($document);

            $this->source->update([
                'status' => 'ready',
                'last_indexed_at' => now(),
                'document_count' => $this->source->documents()->count(),
            ]);

            // Clean up uploaded file
            Storage::delete($this->filePath);
        } catch (\Throwable $e) {
            $this->source->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function extractHtml(string $html): string
    {
        $extractor = app(\App\Services\ContentExtractorService::class);
        $result = $extractor->extract($html);

        return $result ? $result['content'] : strip_tags($html);
    }
}
