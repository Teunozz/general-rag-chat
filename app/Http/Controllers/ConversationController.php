<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Conversation $conversation */
        $conversation = $request->user()->conversations()->create([
            'title' => null,
        ]);

        return response()->json(['id' => $conversation->id]);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('chat.index')->with('success', 'Conversation deleted.');
    }
}
