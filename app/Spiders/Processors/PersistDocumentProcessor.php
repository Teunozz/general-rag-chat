<?php

namespace App\Spiders\Processors;

use App\Models\Document;
use App\Models\Source;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;
use RoachPHP\Support\Configurable;

class PersistDocumentProcessor implements ItemProcessorInterface
{
    use Configurable;

    public function processItem(ItemInterface $item): ItemInterface
    {
        $sourceId = $this->option('sourceId');
        $title = $item->get('title');
        $url = $item->get('url');
        $content = $item->get('content');

        if (empty($content)) {
            return $item->drop('Empty content');
        }

        $contentHash = Document::hashContent($content);

        // Check if document with same content already exists for this source
        $existing = Document::where('source_id', $sourceId)
            ->where('url', $url)
            ->first();

        if ($existing && $existing->content_hash === $contentHash) {
            return $item->drop('Content unchanged');
        }

        $publishedAt = $item->get('published_at');

        $attributes = [
            'title' => $title,
            'content' => $content,
            'content_hash' => $contentHash,
        ];

        if ($publishedAt !== null) {
            $attributes['published_at'] = $publishedAt;
        }

        $document = Document::updateOrCreate(
            ['source_id' => $sourceId, 'url' => $url],
            $attributes,
        );

        $item->set('document_id', $document->id);
        $item->set('content_changed', true);

        return $item;
    }

    /** @phpstan-ignore method.unused (called by Configurable trait) */
    private function defaultOptions(): array
    {
        return ['sourceId' => null];
    }
}
