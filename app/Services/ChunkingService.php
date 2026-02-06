<?php

namespace App\Services;

class ChunkingService
{
    public function split(string $content, int $chunkSize = 1000, int $overlap = 200): array
    {
        if (empty(trim($content))) {
            return [];
        }

        $chunks = [];
        $position = 0;

        // Try splitting by paragraphs first
        $segments = $this->splitRecursive($content, $chunkSize);

        $currentChunk = '';
        foreach ($segments as $segment) {
            if (mb_strlen($currentChunk . $segment) > $chunkSize && ! empty($currentChunk)) {
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'position' => $position,
                    'token_count' => $this->countTokens(trim($currentChunk)),
                ];
                $position++;

                // Overlap: keep the end of the previous chunk
                if ($overlap > 0) {
                    $currentChunk = mb_substr($currentChunk, -$overlap) . $segment;
                } else {
                    $currentChunk = $segment;
                }
            } else {
                $currentChunk .= $segment;
            }
        }

        // Don't forget the last chunk
        if (! empty(trim($currentChunk))) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'position' => $position,
                'token_count' => $this->countTokens(trim($currentChunk)),
            ];
        }

        return $chunks;
    }

    public function countTokens(string $text): int
    {
        // Approximate token count: ~4 characters per token for English
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    private function splitRecursive(string $content, int $chunkSize): array
    {
        // Split by paragraphs
        $paragraphs = preg_split('/\n\n+/', $content);

        $segments = [];
        foreach ($paragraphs as $paragraph) {
            if (mb_strlen($paragraph) <= $chunkSize) {
                $segments[] = $paragraph . "\n\n";
            } else {
                // Split long paragraphs by sentences
                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                foreach ($sentences as $sentence) {
                    if (mb_strlen($sentence) <= $chunkSize) {
                        $segments[] = $sentence . ' ';
                    } else {
                        // Split long sentences by character boundary
                        for ($i = 0; $i < mb_strlen($sentence); $i += $chunkSize) {
                            $segments[] = mb_substr($sentence, $i, $chunkSize);
                        }
                    }
                }
            }
        }

        return $segments;
    }
}
