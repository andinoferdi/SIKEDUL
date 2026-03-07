<?php

namespace App\Http\Controllers;

use App\Models\ChatActionDraft;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Services\ChatbotCommandParser;
use App\Services\ChatbotDraftExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $threads = ChatThread::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        $activeThreadId = $request->integer('thread');
        $activeThread = null;

        if ($activeThreadId) {
            $activeThread = ChatThread::query()
                ->where('user_id', $user->id)
                ->find($activeThreadId);
        }

        if (! $activeThread && $threads->isNotEmpty()) {
            $activeThread = ChatThread::query()
                ->where('user_id', $user->id)
                ->find($threads->first()->id);
        }

        return Inertia::render('dashboard/chatbot/index', [
            'threads' => $threads,
            'activeThread' => $activeThread ? $this->serializeThread($activeThread) : null,
            'timezone' => $user->timezone,
        ]);
    }

    public function storeThread(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $thread = ChatThread::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
        ]);

        return response()->json([
            'thread' => $thread,
        ], 201);
    }

    public function showThread(Request $request, ChatThread $thread): JsonResponse
    {
        if ($thread->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'thread' => $this->serializeThread($thread),
        ]);
    }

    public function sendMessage(
        Request $request,
        ChatThread $thread,
        ChatbotCommandParser $parser
    ): JsonResponse {
        if ($thread->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $content = trim($validated['content']);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'sender' => ChatMessage::SENDER_USER,
            'content' => $content,
            'created_at' => now(),
        ]);

        if (! $thread->title) {
            $thread->update([
                'title' => Str::limit($content, 120, ''),
            ]);
        } else {
            $thread->touch();
        }

        $parsed = $parser->parse($content, $request->user()->timezone);

        if (! ($parsed['ok'] ?? false)) {
            ChatMessage::create([
                'thread_id' => $thread->id,
                'sender' => ChatMessage::SENDER_ASSISTANT,
                'content' => $parsed['question'] ?? 'Perintah belum bisa diproses.',
                'created_at' => now(),
            ]);

            return response()->json([
                'thread' => $this->serializeThread($thread->fresh()),
            ]);
        }

        $assistantMessage = ChatMessage::create([
            'thread_id' => $thread->id,
            'sender' => ChatMessage::SENDER_ASSISTANT,
            'content' => 'Saya menyiapkan draft aksi. Silakan periksa preview lalu konfirmasi atau batalkan.',
            'created_at' => now(),
        ]);

        ChatActionDraft::create([
            'thread_id' => $thread->id,
            'message_id' => $assistantMessage->id,
            'action_type' => $parsed['action_type'],
            'payload_json' => [
                'payload' => $parsed['payload'],
                'preview' => $parsed['preview'] ?? null,
                'original_command' => $content,
            ],
            'status' => ChatActionDraft::STATUS_NEEDS_CONFIRM,
        ]);

        $thread->touch();

        return response()->json([
            'thread' => $this->serializeThread($thread->fresh()),
        ], 201);
    }

    public function confirmDraft(
        Request $request,
        ChatActionDraft $draft,
        ChatbotDraftExecutor $executor
    ): JsonResponse {
        $draft->load('thread');

        if (! $draft->thread || $draft->thread->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $result = $executor->execute($request->user(), $draft);

        ChatMessage::create([
            'thread_id' => $draft->thread_id,
            'sender' => ChatMessage::SENDER_ASSISTANT,
            'content' => $result['message'],
            'created_at' => now(),
        ]);

        $draft->thread->touch();

        return response()->json([
            'thread' => $this->serializeThread($draft->thread->fresh()),
            'result' => $result,
        ]);
    }

    public function cancelDraft(
        Request $request,
        ChatActionDraft $draft,
        ChatbotDraftExecutor $executor
    ): JsonResponse {
        $draft->load('thread');

        if (! $draft->thread || $draft->thread->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $executor->cancel($draft);

        ChatMessage::create([
            'thread_id' => $draft->thread_id,
            'sender' => ChatMessage::SENDER_ASSISTANT,
            'content' => 'Draft aksi dibatalkan.',
            'created_at' => now(),
        ]);

        $draft->thread->touch();

        return response()->json([
            'thread' => $this->serializeThread($draft->thread->fresh()),
        ]);
    }

    private function serializeThread(ChatThread $thread): array
    {
        $thread->load([
            'messages.drafts',
        ]);

        return [
            'id' => $thread->id,
            'title' => $thread->title,
            'created_at' => $thread->created_at?->toISOString(),
            'updated_at' => $thread->updated_at?->toISOString(),
            'messages' => $thread->messages->map(function (ChatMessage $message) {
                $draft = $message->drafts->sortByDesc('id')->first();

                return [
                    'id' => $message->id,
                    'sender' => $message->sender,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toISOString(),
                    'draft' => $draft ? [
                        'id' => $draft->id,
                        'action_type' => $draft->action_type,
                        'status' => $draft->status,
                        'payload_json' => $draft->payload_json,
                    ] : null,
                ];
            })->values()->all(),
        ];
    }
}

