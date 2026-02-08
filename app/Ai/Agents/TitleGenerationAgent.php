<?php

namespace App\Ai\Agents;

use App\Services\SystemSettingsService;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class TitleGenerationAgent implements Agent
{
    use Promptable;

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function instructions(): string
    {
        return 'Generate a short, plain-text title (max 6 words) for a conversation based on the user\'s first message. Reply with ONLY the title text. No markdown, no quotes, no punctuation at the end, no explanation.';
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
