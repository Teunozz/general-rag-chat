<?php

namespace App\Services;

use Laminas\Feed\Reader\Reader;

class FeedParserService
{
    public function parse(string $feedUrl): array
    {
        $feed = Reader::import($feedUrl);
        $entries = [];

        foreach ($feed as $entry) {
            $content = $entry->getContent() ?? $entry->getDescription() ?? '';
            // Strip HTML from content
            $plainContent = strip_tags($content);
            $plainContent = html_entity_decode($plainContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plainContent = preg_replace('/\s+/', ' ', trim($plainContent));

            $entries[] = [
                'title' => $entry->getTitle() ?? 'Untitled',
                'url' => $entry->getLink() ?? '',
                'content' => $plainContent,
                'published_at' => $entry->getDateModified() ?? $entry->getDateCreated(),
                'guid' => $entry->getId() ?? $entry->getLink() ?? uniqid(),
            ];
        }

        return $entries;
    }
}
