<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\RagContextBuilder;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Laravel\Ai\agent;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = $request->user()
            ->conversations()
            ->orderByDesc('updated_at')
            ->take(20)
            ->get();

        return view('chat.show', [
            'conversations' => $conversations,
            'conversation' => null,
            'messages' => collect(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()->orderBy('created_at')->get();
        $renderedHtml = $messages->mapWithKeys(fn (Message $message): array => [
            $message->id => $this->renderMessageHtml($message),
        ]);

        $conversations = $request->user()
            ->conversations()
            ->orderByDesc('updated_at')
            ->take(20)
            ->get();

        return view('chat.show', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'renderedHtml' => $renderedHtml,
        ]);
    }

    public function stream(
        Request $request,
        Conversation $conversation,
        RagContextBuilder $ragBuilder,
        SystemSettingsService $settings,
    ): StreamedResponse {
        $this->authorize('view', $conversation);

        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $userMessage = $request->input('message');

        // Store user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Build RAG context
        $ragContext = $ragBuilder->build($userMessage, $conversation);

        // Build conversation history for the agent
        /** @var \Illuminate\Database\Eloquent\Collection<int, Message> $rawMessages */
        $rawMessages = $conversation->messages()
            ->where('is_summary', false)
            ->orderBy('created_at')
            ->get();
        $history = $rawMessages
            ->map(fn (Message $msg): \Laravel\Ai\Messages\UserMessage|\Laravel\Ai\Messages\AssistantMessage|null => match ($msg->role) {
                'user' => new UserMessage($msg->content),
                'assistant' => new AssistantMessage($msg->content),
                default => null,
            })
            ->filter()
            ->values()
            ->toArray();

        // Remove the last user message (we'll send it as the prompt)
        array_pop($history);

        $chatSettings = $settings->group('chat');
        $systemPromptTemplate = $chatSettings['system_prompt'] ?? config('chat.default_system_prompt');

        $systemPrompt = Str::replace('{date}', now()->format('Y-m-d'), $systemPromptTemplate);

        if (Str::contains($systemPrompt, '{context}')) {
            $systemPrompt = Str::replace('{context}', $ragContext->formattedChunks, $systemPrompt);
        } else {
            $systemPrompt = $systemPrompt . "\n\nContext:\n" . $ragContext->formattedChunks;
        }

        $chatAgent = agent(
            instructions: $systemPrompt,
            messages: $history,
        );

        $fullResponse = '';

        return response()->stream(function () use ($chatAgent, $userMessage, $conversation, $ragContext, $settings, &$fullResponse): void {
            $streamResponse = $chatAgent->stream(
                $userMessage,
                provider: $settings->get('llm', 'provider', 'openai'),
                model: $settings->get('llm', 'model', 'gpt-4o'),
            );

            $streamResponse->each(function ($event) use (&$fullResponse): void {
                if ($event instanceof TextDelta) {
                    $fullResponse .= $event->delta;
                    echo "data: " . json_encode(['type' => 'text', 'content' => $event->delta]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            });

            // Send citations
            if ($ragContext->citations !== []) {
                echo "data: " . json_encode(['type' => 'citations', 'citations' => $ragContext->citations]) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            // Store assistant message
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $fullResponse,
                'citations' => $ragContext->citations,
            ]);

            // Auto-generate title for new conversations
            if ($conversation->messages()->count() === 2 && ! $conversation->title) {
                $this->generateTitle($conversation, $userMessage, $settings);
            }

            $conversation->touch();

            echo "data: " . json_encode(['type' => 'done']) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function search(Request $request, RagContextBuilder $ragBuilder): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:10000'],
            'source_ids' => ['nullable', 'array'],
            'source_ids.*' => ['integer', 'exists:sources,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $chunks = $ragBuilder->rawSearch(
            $request->input('query'),
            $request->input('source_ids'),
            $request->input('limit', 20),
        );

        return response()->json([
            'results' => $chunks->map(fn ($chunk): array => [
                'chunk_id' => $chunk->id,
                'content' => $chunk->content,
                'score' => $chunk->neighbor_distance ?? null,
                'document_title' => $chunk->document->title,
                'document_url' => $chunk->document->url,
                'source_name' => $chunk->document->source->name,
            ]),
        ]);
    }

    private function renderMessageHtml(Message $message): string
    {
        if ($message->role === 'user') {
            return e($message->content);
        }

        $html = Str::markdown($message->content);

        /** @var array<int, array{number: int, document_title: string, document_url: string|null, source_name?: string}> $citations */
        $citations = $message->citations ?? [];

        if ($citations === []) {
            return $html;
        }

        // Index citations by number for O(1) lookup
        $citationsByNumber = [];
        foreach ($citations as $citation) {
            $citationsByNumber[$citation['number']] = $citation;
        }

        // Render the pill template once with placeholders, then str_replace per citation
        $pillTemplate = view('components.citation-pill', [
            'url' => '__PILL_URL__',
            'domain' => '__PILL_DOMAIN__',
            'title' => '__PILL_TITLE__',
            'source' => '__PILL_SOURCE__',
        ])->render();

        return (string) preg_replace_callback('/\[(\d+)\]/', function (array $matches) use ($citationsByNumber, $pillTemplate): string {
            $number = (int) $matches[1];

            if (! isset($citationsByNumber[$number]) || ($citationsByNumber[$number]['document_url'] ?? null) === null) {
                return $matches[0];
            }

            $citation = $citationsByNumber[$number];
            $domain = preg_replace('/^www\./', '', (string) parse_url($citation['document_url'], PHP_URL_HOST));

            return str_replace(
                ['__PILL_URL__', '__PILL_DOMAIN__', '__PILL_TITLE__', '__PILL_SOURCE__'],
                [e($citation['document_url']), e($domain), e($citation['document_title']), e($citation['source_name'] ?? $domain)],
                $pillTemplate,
            );
        }, $html);
    }

    private function generateTitle(Conversation $conversation, string $firstMessage, SystemSettingsService $settings): void
    {
        try {
            $titleAgent = agent(
                instructions: 'Generate a short title (max 50 characters) for a conversation that starts with the following message. Return only the title, nothing else.',
            );

            $response = $titleAgent->prompt(
                $firstMessage,
                provider: $settings->get('llm', 'provider', 'openai'),
                model: $settings->get('llm', 'model', 'gpt-4o'),
            );

            $conversation->update(['title' => mb_substr(trim($response->text, '"'), 0, 255)]);
        } catch (\Throwable) {
            // Title generation is non-critical
        }
    }
}
