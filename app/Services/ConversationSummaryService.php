<?php

namespace App\Services;

use App\Models\Conversation;

use function Laravel\Ai\agent;

class ConversationSummaryService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function maybeSummarize(Conversation $conversation): void
    {
        $messageCount = $conversation->messages()->where('is_summary', false)->count();

        if ($messageCount < 20) {
            return;
        }

        // Check if we already have a recent summary
        $lastSummary = $conversation->messages()->where('is_summary', true)->latest()->first();
        $messagesSinceSummary = $lastSummary
            ? $conversation->messages()->where('is_summary', false)->where('created_at', '>', $lastSummary->created_at)->count()
            : $messageCount;

        if ($messagesSinceSummary < 20) {
            return;
        }

        // Get messages to summarize
        $messages = $conversation->messages()
            ->where('is_summary', false)
            ->orderBy('created_at')
            ->get();

        $transcript = $messages->map(fn ($m): string => "{$m->role}: {$m->content}")->implode("\n");

        try {
            $summaryAgent = agent(
                instructions: 'Summarize the following conversation concisely, preserving key facts, decisions, and context. This summary will be used as context for continuing the conversation.',
            );

            $response = $summaryAgent->prompt(
                $transcript,
                provider: $this->settings->get('llm', 'provider', 'openai'),
                model: $this->settings->get('llm', 'model', 'gpt-4o'),
            );

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response->text,
                'is_summary' => true,
            ]);

            $conversation->update(['summary' => $response->text]);
        } catch (\Throwable) {
            // Non-critical; skip summarization
        }
    }
}
