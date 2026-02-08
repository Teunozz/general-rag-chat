<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Recap;
use App\Services\SystemSettingsService;
use Illuminate\Console\Command;

use function Laravel\Ai\agent;

class GenerateRecapCommand extends Command
{
    protected $signature = 'app:generate-recap {type : daily, weekly, or monthly} {--force : Bypass the time check and run immediately}';
    protected $description = 'Generate a recap for the specified period if conditions are met';

    public function handle(SystemSettingsService $settings): int
    {
        $type = $this->argument('type');

        if (! in_array($type, ['daily', 'weekly', 'monthly'])) {
            $this->error('Type must be daily, weekly, or monthly.');
            return self::FAILURE;
        }

        // Check if enabled
        if (! $settings->get('recap', "{$type}_enabled", true)) {
            return self::SUCCESS;
        }

        // Check if it's the right time (bypass with --force)
        if (! $this->option('force') && ! $this->isRightTime($type, $settings)) {
            return self::SUCCESS;
        }

        // Calculate period
        [$periodStart, $periodEnd] = $this->calculatePeriod($type);

        // Check if recap already exists for this period
        $exists = Recap::where('type', $type)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->exists();

        if ($exists) {
            return self::SUCCESS;
        }

        // Get documents from period
        $documents = Document::with('source')->whereBetween('created_at', [$periodStart, $periodEnd])->get();

        if ($documents->isEmpty()) {
            $this->info('No documents in period, skipping recap.');
            return self::SUCCESS;
        }

        // Generate summary
        $docSummaries = $documents->map(fn ($d): string => "- [{$d->source->name}]({$d->url}) {$d->title}: " . mb_substr((string) $d->content, 0, 200))->implode("\n");

        try {
            $recapAgent = agent(
                instructions: 'You are writing an engaging recap newsletter for a knowledge base. From the ingested documents, pick 3-5 of the most interesting or noteworthy topics. For each topic, write a short markdown heading (##) and a brief, engaging paragraph underneath. Keep the tone informative but lively. Do not list every document — focus on the highlights that would make someone want to read more. After each paragraph, note the source(s) it drew from in italics with a markdown link, e.g. *Source: [Example Site](https://example.com)*.',
            );

            $response = $recapAgent->prompt(
                "Documents ingested between {$periodStart->format('M j, Y')} and {$periodEnd->format('M j, Y')}:\n\n{$docSummaries}",
                provider: $settings->get('llm', 'provider', 'openai'),
                model: $settings->get('llm', 'model', 'gpt-4o'),
            );

            Recap::create([
                'type' => $type,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'document_count' => $documents->count(),
                'summary' => $response->text,
            ]);

            $this->info("Generated {$type} recap with {$documents->count()} documents.");

            // Dispatch email jobs
            \App\Jobs\SendRecapEmailJob::dispatch(
                Recap::where('type', $type)->where('period_start', $periodStart)->first()
            );
        } catch (\Throwable $e) {
            $this->error("Failed to generate recap: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function isRightTime(string $type, SystemSettingsService $settings): bool
    {
        $currentHour = (int) now()->format('G');
        $currentDay = (int) now()->format('w'); // 0=Sunday
        $currentDayOfMonth = (int) now()->format('j');

        return match ($type) {
            'daily' => $currentHour === (int) $settings->get('recap', 'daily_hour', 8),
            'weekly' => $currentDay === (int) $settings->get('recap', 'weekly_day', 1)
                && $currentHour === (int) $settings->get('recap', 'weekly_hour', 8),
            'monthly' => $currentDayOfMonth === (int) $settings->get('recap', 'monthly_day', 1)
                && $currentHour === (int) $settings->get('recap', 'monthly_hour', 8),
            default => false,
        };
    }

    private function calculatePeriod(string $type): array
    {
        return match ($type) {
            'daily' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'weekly' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'monthly' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            default => throw new \LogicException("Unexpected recap type: {$type}"),
        };
    }
}
