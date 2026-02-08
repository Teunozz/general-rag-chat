<?php

namespace App\Ai\Agents;

use App\Services\SystemSettingsService;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class ChatAgent implements Agent, Conversational
{
    use Promptable;

    private iterable $conversationMessages = [];

    private string $ragContext = '';

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function withMessages(iterable $messages): self
    {
        $this->conversationMessages = $messages;

        return $this;
    }

    public function withRagContext(string $ragContext): self
    {
        $this->ragContext = $ragContext;

        return $this;
    }

    public function instructions(): string
    {
        $chatSettings = $this->settings->group('chat');
        $template = $chatSettings['system_prompt'] ?? config('prompts.default_system_prompt');

        $prompt = Str::replace('{date}', now()->format('Y-m-d'), $template);

        if (Str::contains($prompt, '{context}')) {
            $prompt = Str::replace('{context}', $this->ragContext, $prompt);
        } else {
            $prompt = $prompt . "\n\nContext:\n" . $this->ragContext;
        }

        return $prompt;
    }

    public function messages(): iterable
    {
        return $this->conversationMessages;
    }

    public function provider(): string
    {
        return $this->settings->get('llm', 'provider', 'openai');
    }

    public function model(): string
    {
        return $this->settings->get('llm', 'model', 'gpt-4o');
    }
}
