<?php

namespace App\Services;

use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;

class ContentExtractorService
{
    public function extract(string $html): ?array
    {
        try {
            $readability = new Readability(new Configuration());
            $readability->parse($html);

            $title = $readability->getTitle();
            $content = $readability->getContent();

            if (empty($content)) {
                return null;
            }

            // Strip HTML tags to get plain text
            $plainText = strip_tags($content);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plainText = preg_replace('/\s+/', ' ', trim($plainText));

            return [
                'title' => $title ?? 'Untitled',
                'content' => $plainText,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
