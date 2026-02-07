<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRssFeedJob;
use App\Models\Source;
use Illuminate\Console\Command;

class RefreshFeedsCommand extends Command
{
    protected $signature = 'app:refresh-feeds';
    protected $description = 'Refresh RSS sources that are due for a refresh';

    public function handle(): int
    {
        $sources = Source::where('type', 'rss')
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
        $this->info("Found {$count} RSS sources due for refresh.");

        $sources->each(function ($source): void {
            ProcessRssFeedJob::dispatch($source);
            $this->line("Dispatched refresh for: {$source->name}");
        });

        return self::SUCCESS;
    }
}
