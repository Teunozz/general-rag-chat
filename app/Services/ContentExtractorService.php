<?php

namespace App\Services;

use Carbon\Carbon;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;

class ContentExtractorService
{
    /**
     * HTML tag names that typically contain non-article content.
     */
    private const NON_CONTENT_TAGS = ['nav', 'footer', 'aside', 'header'];

    /**
     * CSS class/ID substrings that signal non-article boilerplate.
     */
    private const BOILERPLATE_PATTERNS = [
        'cookie',
        'consent',
        'gdpr',
        'privacy',
        'newsletter',
        'subscribe',
        'sidebar',
        'social-share',
        'share-buttons',
        'related-posts',
        'related-links',
        'advertisement',
        'ad-wrapper',
        'banner',
        'popup',
        'modal',
        'breadcrumb',
    ];

    /**
     * Text markers that indicate the start of trailing boilerplate.
     * Content from the earliest match to the end of text will be removed.
     */
    private const TRAILING_BOILERPLATE_MARKERS = [
        'Related Links',
        'Related Articles',
        'Related Stories',
        'Subscribe Free To Our',
        'Subscribe to our newsletter',
        'Sign up for our newsletter',
        'The content herein, unless otherwise',
        'All rights reserved.',
        'General Data Protection Regulation',
        'Advertising does not imply endorsement',
        'You must confirm your public display name before commenting',
        'Post navigation',
    ];

    public function extract(string $html): ?array
    {
        try {
            $cleanedHtml = $this->stripNonContentElements($html);

            $readability = new Readability(new Configuration());
            $readability->parse($cleanedHtml);

            $title = $readability->getTitle();
            $content = $readability->getContent();

            if (in_array($content, [null, '', '0'], true)) {
                return null;
            }

            // Strip HTML tags to get plain text
            $plainText = strip_tags($content);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plainText = preg_replace('/\s+/', ' ', trim($plainText));

            $plainText = $this->stripTrailingBoilerplate($plainText);

            return [
                'title' => $title ?? 'Untitled',
                'content' => $plainText,
                'published_at' => $this->extractPublishedDate($html),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Truncate text at the earliest trailing boilerplate marker.
     */
    private function stripTrailingBoilerplate(string $text): string
    {
        $earliestPos = strlen($text);

        foreach (self::TRAILING_BOILERPLATE_MARKERS as $marker) {
            $pos = stripos($text, $marker);
            if ($pos !== false && $pos < $earliestPos) {
                $earliestPos = $pos;
            }
        }

        if ($earliestPos < strlen($text)) {
            $text = trim(substr($text, 0, $earliestPos));
        }

        return $text;
    }

    /**
     * Remove non-content DOM elements before Readability processes the HTML.
     */
    private function stripNonContentElements(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $toRemove = [];

        // Remove non-content tags (nav, footer, aside, header)
        foreach (self::NON_CONTENT_TAGS as $tag) {
            foreach ($xpath->query('//' . $tag) as $node) {
                $toRemove[] = $node;
            }
        }

        // Remove elements whose class or id matches boilerplate patterns
        $conditions = [];
        foreach (self::BOILERPLATE_PATTERNS as $pattern) {
            $conditions[] = 'contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"' . $pattern . '")';
            $conditions[] = 'contains(translate(@id,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"' . $pattern . '")';
        }
        $query = '//*[' . implode(' or ', $conditions) . ']';
        foreach ($xpath->query($query) as $node) {
            $toRemove[] = $node;
        }

        if ($toRemove === []) {
            return $html;
        }

        foreach ($toRemove as $node) {
            $node->parentNode?->removeChild($node);
        }

        return $dom->saveHTML() ?: $html;
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
            $json = preg_replace('/\r\n|\r|\n/', ' ', $json);
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
