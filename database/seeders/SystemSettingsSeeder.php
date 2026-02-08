<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'branding', 'key' => 'app_name', 'value' => json_encode('Knowledge Base')],
            ['group' => 'branding', 'key' => 'app_description', 'value' => json_encode('')],
            ['group' => 'branding', 'key' => 'primary_color', 'value' => json_encode('#4F46E5')],
            ['group' => 'branding', 'key' => 'secondary_color', 'value' => json_encode('#7C3AED')],

            ['group' => 'llm', 'key' => 'provider', 'value' => json_encode('openai')],
            ['group' => 'llm', 'key' => 'model', 'value' => json_encode('gpt-4o')],

            ['group' => 'embedding', 'key' => 'provider', 'value' => json_encode('openai')],
            ['group' => 'embedding', 'key' => 'model', 'value' => json_encode('text-embedding-3-small')],
            ['group' => 'embedding', 'key' => 'dimensions', 'value' => json_encode(1536)],

            ['group' => 'chat', 'key' => 'context_chunk_count', 'value' => json_encode(100)],
            ['group' => 'chat', 'key' => 'temperature', 'value' => json_encode(0.25)],
            ['group' => 'chat', 'key' => 'system_prompt', 'value' => json_encode('You are a helpful knowledge base assistant. Answer questions based ONLY on the provided context. Always cite your sources using numbered references like [1], [2], etc. If the context does not contain relevant information, say so honestly.')],
            ['group' => 'chat', 'key' => 'query_enrichment_enabled', 'value' => json_encode(false)],
            ['group' => 'chat', 'key' => 'enrichment_prompt', 'value' => json_encode('Expand the following user query into a more detailed search query that captures the intent and related concepts. Return only the expanded query, nothing else.')],
            ['group' => 'chat', 'key' => 'context_window_size', 'value' => json_encode(2)],
            ['group' => 'chat', 'key' => 'full_doc_score_threshold', 'value' => json_encode(0.85)],
            ['group' => 'chat', 'key' => 'max_full_doc_characters', 'value' => json_encode(10000)],
            ['group' => 'chat', 'key' => 'max_context_tokens', 'value' => json_encode(16000)],

            ['group' => 'recap', 'key' => 'daily_enabled', 'value' => json_encode(true)],
            ['group' => 'recap', 'key' => 'weekly_enabled', 'value' => json_encode(true)],
            ['group' => 'recap', 'key' => 'monthly_enabled', 'value' => json_encode(true)],
            ['group' => 'recap', 'key' => 'daily_hour', 'value' => json_encode(8)],
            ['group' => 'recap', 'key' => 'weekly_day', 'value' => json_encode(1)],
            ['group' => 'recap', 'key' => 'weekly_hour', 'value' => json_encode(8)],
            ['group' => 'recap', 'key' => 'monthly_day', 'value' => json_encode(1)],
            ['group' => 'recap', 'key' => 'monthly_hour', 'value' => json_encode(8)],

            ['group' => 'email', 'key' => 'system_enabled', 'value' => json_encode(true)],
        ];

        $now = now();

        foreach ($settings as &$setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;
        }

        DB::table('system_settings')->upsert(
            $settings,
            ['group', 'key'],
            ['value', 'updated_at']
        );
    }
}
