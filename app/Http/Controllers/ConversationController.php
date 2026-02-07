<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = $request->user()
            ->conversations()
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('conversations.index', ['conversations' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Conversation $conversation */
        $conversation = $request->user()->conversations()->create([
            'title' => null,
        ]);

        return response()->json(['id' => $conversation->id]);
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'source_ids' => ['nullable', 'array'],
            'source_ids.*' => ['integer', 'exists:sources,id'],
        ]);

        if (isset($validated['title'])) {
            $conversation->update(['title' => $validated['title']]);
        }

        if (isset($validated['source_ids'])) {
            $conversation->sources()->sync($validated['source_ids']);
        }

        return redirect()->back()->with('success', 'Conversation updated.');
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('conversations.index')->with('success', 'Conversation deleted.');
    }
}
