<?php

namespace App\Ai\Agents;

use App\Services\SystemSettingsService;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class RecapAgent implements Agent
{
    use Promptable;

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function instructions(): string
    {
        return $this->settings->get('recap', 'prompt') ?: config('prompts.default_recap_prompt');
    }

    public function provider(): string
    {
        return $this->settings->get('recap', 'provider') ?: $this->settings->get('llm', 'provider', 'openai');
    }

    public function model(): string
    {
        return $this->settings->get('recap', 'model') ?: $this->settings->get('llm', 'model', 'gpt-4o');
    }
}
