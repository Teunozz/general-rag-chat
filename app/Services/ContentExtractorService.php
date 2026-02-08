<?php

namespace App\Services;

use Carbon\Carbon;
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

            if (in_array($content, [null, '', '0'], true)) {
                return null;
            }

            // Strip HTML tags to get plain text
            $plainText = strip_tags($content);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plainText = preg_replace('/\s+/', ' ', trim($plainText));

            return [
                'title' => $title ?? 'Untitled',
                'content' => $plainText,
                'published_at' => $this->extractPublishedDate($html),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractPublishedDate(string $html): ?Carbon
    {
        // Try JSON-LD datePublished / dateModified
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $jsonLd) {
                $date = $this->parseDateFromJsonLd($jsonLd);
                if ($date instanceof \Carbon\Carbon) {
                    return $date;
                }
            }
        }

        // Try meta tags
        $metaNames = [
            'article:published_time',
            'og:article:published_time',
            'publishdate',
            'date',
            'DC.date.issued',
        ];

        foreach ($metaNames as $name) {
            if (preg_match('/<meta[^>]*(?:property|name)=["\']' . preg_quote($name, '/') . '["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $match)) {
                $date = $this->tryParseDate($match[1]);
                if ($date instanceof \Carbon\Carbon) {
                    return $date;
                }
            }
            // Also try content before property/name
            if (preg_match('/<meta[^>]*content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote($name, '/') . '["\']/i', $html, $match)) {
                $date = $this->tryParseDate($match[1]);
                if ($date instanceof \Carbon\Carbon) {
                    return $date;
                }
            }
        }

        // Try <time datetime="..."> element
        if (preg_match('/<time[^>]*datetime=["\']([^"\']+)["\']/i', $html, $match)) {
            return $this->tryParseDate($match[1]);
        }

        return null;
    }

    private function parseDateFromJsonLd(string $json): ?Carbon
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        // Handle @graph arrays
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                $date = $this->extractDateFromJsonLdObject($item);
                if ($date instanceof \Carbon\Carbon) {
                    return $date;
                }
            }
        }

        return $this->extractDateFromJsonLdObject($data);
    }

    private function extractDateFromJsonLdObject(mixed $data): ?Carbon
    {
        if (! is_array($data)) {
            return null;
        }

        foreach (['datePublished', 'dateModified', 'dateCreated'] as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                $date = $this->tryParseDate($data[$field]);
                if ($date instanceof \Carbon\Carbon) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function tryParseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
