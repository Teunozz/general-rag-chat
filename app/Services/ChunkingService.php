<?php

namespace App\Services;

class ChunkingService
{
    public function split(string $content, int $chunkSize = 1000, int $overlap = 200): array
    {
        if (in_array(trim($content), ['', '0'], true)) {
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
                    'content' => trim((string) $currentChunk),
                    'position' => $position,
                    'token_count' => $this->countTokens(trim((string) $currentChunk)),
                ];
                $position++;
                // Overlap: keep the end of the previous chunk
                $currentChunk = $overlap > 0 ? mb_substr((string) $currentChunk, -$overlap) . $segment : $segment;
            } else {
                $currentChunk .= $segment;
            }
        }

        // Don't forget the last chunk
        if (!in_array(trim((string) $currentChunk), ['', '0'], true)) {
            $chunks[] = [
                'content' => trim((string) $currentChunk),
                'position' => $position,
                'token_count' => $this->countTokens(trim((string) $currentChunk)),
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
