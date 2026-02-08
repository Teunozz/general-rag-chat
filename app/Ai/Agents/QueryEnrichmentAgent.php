<?php

namespace App\Ai\Agents;

use App\Models\Source;
use App\Services\SystemSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class QueryEnrichmentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'enriched_query' => $schema->string()->required()
                ->description('The rewritten query with temporal and source references removed'),
            'date_filter' => $schema->object([
                'start_date' => $schema->string()->nullable()->description('Start date in YYYY-MM-DD format'),
                'end_date' => $schema->string()->nullable()->description('End date in YYYY-MM-DD format'),
                'expression' => $schema->string()->nullable()->description('The original temporal expression, e.g. "last week"'),
            ])->nullable()->description('Date range extracted from temporal expressions, null when none found'),
            'source_ids' => $schema->array()->items($schema->integer())->nullable()
                ->description('IDs of sources the user references by name, null when none referenced'),
        ];
    }

    public function instructions(): string
    {
        $chatSettings = $this->settings->group('chat');
        $template = ($chatSettings['enrichment_prompt'] ?? '') ?: config('prompts.default_enrichment_prompt');

        $today = CarbonImmutable::now()->format('Y-m-d');
        $sourcesJson = json_encode($this->getAvailableSources(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = Str::replace('{date}', $today, $template);

        if (Str::contains($prompt, '{sources}')) {
            $prompt = Str::replace('{sources}', $sourcesJson, $prompt);
        } else {
            $prompt = $prompt . "\n\nAvailable sources:\n" . $sourcesJson;
        }

        return $prompt;
    }

    public function provider(): string
    {
        return $this->settings->get('llm', 'provider', 'openai');
    }

    public function model(): string
    {
        return $this->settings->get('llm', 'model', 'gpt-4o');
    }

    /**
     * @return array<int, array{id: int, name: string, type: string}>
     */
    private function getAvailableSources(): array
    {
        return Source::where('status', 'ready')
            ->select(['id', 'name', 'type'])
            ->get()
            ->map(fn (Source $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
            ])
            ->toArray();
    }
}
