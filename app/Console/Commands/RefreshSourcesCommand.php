<?php

namespace App\Console\Commands;

use App\Jobs\CrawlWebsiteJob;
use App\Jobs\ProcessRssFeedJob;
use App\Models\Source;
use Illuminate\Console\Command;

class RefreshSourcesCommand extends Command
{
    protected $signature = 'app:refresh-sources';
    protected $description = 'Refresh RSS and website sources that are due for a refresh';

    public function handle(): int
    {
        $sources = Source::whereIn('type', ['rss', 'website'])
            ->where('status', '!=', 'processing')
            ->whereNotNull('refresh_interval')
            ->get()
            ->filter(function (Source $source) {
                if (! $source->last_indexed_at) {
                    return true;
                }
                return $source->last_indexed_at->addMinutes($source->refresh_interval)->isPast();
            });

        $count = $sources->count();
        $this->info("Found {$count} sources due for refresh.");

        $sources->each(function (Source $source): void {
            match ($source->type) {
                'website' => CrawlWebsiteJob::dispatch($source),
                'rss' => ProcessRssFeedJob::dispatch($source),
                default => null,
            };
            $this->line("Dispatched refresh for [{$source->type}]: {$source->name}");
        });

        return self::SUCCESS;
    }
}
