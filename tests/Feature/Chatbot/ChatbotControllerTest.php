<?php

use App\Models\ChatActionDraft;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.cerebras.key' => 'test-key',
        'services.cerebras.base_url' => 'https://api.cerebras.ai/v1',
        'services.cerebras.model' => 'gpt-oss-120b',
        'services.cerebras.timeout' => 5,
        'services.cerebras.max_context_messages' => 12,
        'services.cerebras.temperature' => 0.1,
        'services.cerebras.seed' => 20260311,
        'services.cerebras.force_deterministic' => true,
        'services.cerebras.max_completion_tokens' => 300,
        'services.cerebras.retry_count' => 1,
        'services.cerebras.retry_backoff_ms' => 1,
        'services.cerebras.min_intent_confidence' => 0.70,
        'services.cerebras.enable_legacy_parser_when_ai_disabled' => true,
    ]);
});

function eventResponse(array $override = []): array
{
    $payload = array_replace_recursive([
        'ok' => true,
        'action_type' => 'create_event',
        'payload' => [
            'title' => 'Meeting Tim',
            'start_at' => '2026-03-11 09:00:00',
            'end_at' => '2026-03-11 10:00:00',
            'target_title' => '',
            'category_name' => '',
            'reminder_minutes' => -1,
            'items' => [],
        ],
        'preview' => [
            'title' => 'Meeting Tim',
            'start' => '11 Mar 2026 09:00',
            'end' => '11 Mar 2026 10:00',
            'target' => '',
            'category' => '',
            'reminder' => '',
            'items' => [],
        ],
        'question' => '',
    ], $override);

    return [
        'choices' => [
            ['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
        ],
    ];
}

test('sendMessage uses cerebras result and creates draft needs_confirm', function () {
    Http::fake([
        'https://api.cerebras.ai/v1/chat/completions' => Http::response(eventResponse(), 200),
    ]);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $response = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'jadwalkan meeting besok jam 9 pagi']
    );

    $response->assertCreated();
    $response->assertJsonPath('thread.messages.1.draft.status', 'needs_confirm');
    $response->assertJsonPath('thread.messages.1.draft.action_type', 'create_event');
});

test('sendMessage performs repair retry and still creates draft', function () {
    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'not-json']]]], 200)
        ->push(eventResponse(), 200);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $response = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'jadwalkan meeting besok jam 9 pagi']
    );

    $response->assertCreated();
    $response->assertJsonPath('thread.messages.1.draft.action_type', 'create_event');
    Http::assertSentCount(2);
});

test('sendMessage returns contextual clarification when ai fails totally', function () {
    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'bad-1']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'bad-2']]]], 200);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $response = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'JADWALKAN TANGGAL 20 MARET 2026']
    );

    $response->assertOk();
    $response->assertJsonPath('thread.messages.1.sender', 'assistant');
    expect(data_get($response->json(), 'thread.messages.1.content'))
        ->not->toContain('Perintah belum dikenali');
});

test('sendMessage rejects empty content after trim', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $response = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => '   ']
    );

    $response->assertUnprocessable();
    $response->assertJsonPath('message', 'Pesan tidak boleh kosong.');
});

test('sendMessage repeats date only with title and still creates draft via smart fallback', function () {
    Http::fake([
        'https://api.cerebras.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'invalid-json']]],
        ], 200),
    ]);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    for ($i = 0; $i < 3; $i++) {
        $response = $this->actingAs($user)->postJson(
            route('chatbot.threads.messages.store', $thread),
            ['content' => 'tolong buatkan jadwal tanggal 21 maret 2026 nikahan saya']
        );

        $response->assertCreated();
        $response->assertJsonPath('thread.messages.'.((2 * $i) + 1).'.draft.action_type', 'create_event');
    }
});

test('sendMessage avoids stale draft when same thread receives non domain prompt', function () {
    Http::fakeSequence()
        ->push(eventResponse([
            'action_type' => 'delete_event',
            'payload' => [
                'title' => '',
                'start_at' => '',
                'end_at' => '',
                'target_title' => 'rapat tim',
                'category_name' => '',
                'reminder_minutes' => -1,
                'items' => [],
            ],
            'preview' => [
                'title' => '',
                'start' => '',
                'end' => '',
                'target' => 'rapat tim',
                'category' => '',
                'reminder' => '',
                'items' => [],
            ],
        ]), 200)
        ->push(eventResponse([
            'action_type' => 'delete_event',
            'payload' => [
                'title' => '',
                'start_at' => '',
                'end_at' => '',
                'target_title' => 'rapat tim',
                'category_name' => '',
                'reminder_minutes' => -1,
                'items' => [],
            ],
            'preview' => [
                'title' => '',
                'start' => '',
                'end' => '',
                'target' => 'rapat tim',
                'category' => '',
                'reminder' => '',
                'items' => [],
            ],
        ]), 200);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $first = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'hapus event judul rapat tim']
    );
    $first->assertCreated();
    $first->assertJsonPath('thread.messages.1.draft.action_type', 'delete_event');

    $second = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'apa warna favoritmu?']
    );

    $second->assertOk();
    $second->assertJsonPath('thread.messages.3.sender', 'assistant');
    expect(data_get($second->json(), 'thread.messages.3.draft'))->toBeNull();
});

test('sendMessage create_event then non-domain results in clarification without draft', function () {
    Http::fake([
        'https://api.cerebras.ai/v1/chat/completions' => Http::response(eventResponse([
            'action_type' => 'create_event',
            'payload' => [
                'title' => 'Nikahan Saya',
                'start_at' => '2026-03-21 00:00:00',
                'end_at' => '2026-03-21 23:59:59',
                'target_title' => '',
                'category_name' => '',
                'reminder_minutes' => -1,
                'items' => [],
            ],
            'preview' => [
                'title' => 'Nikahan Saya',
                'start' => '2026-03-21 00:00:00',
                'end' => '2026-03-21 23:59:59',
                'target' => '',
                'category' => '-',
                'reminder' => '-',
                'items' => [],
            ],
        ]), 200),
    ]);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $first = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'jadwalkan tanggal 21 maret 2026 nikahan saya']
    );
    $first->assertCreated();
    $first->assertJsonPath('thread.messages.1.draft.action_type', 'create_event');

    $second = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'apa warna favoritmu?']
    );

    $second->assertOk();
    expect(data_get($second->json(), 'thread.messages.3.draft'))->toBeNull();
});

test('sendMessage create_event then delete intent must not reuse create_event payload', function () {
    Http::fakeSequence()
        ->push(eventResponse([
            'action_type' => 'create_event',
            'payload' => [
                'title' => 'Nikahan Saya',
                'start_at' => '2026-03-21 00:00:00',
                'end_at' => '2026-03-21 23:59:59',
                'target_title' => '',
                'category_name' => '',
                'reminder_minutes' => -1,
                'items' => [],
            ],
            'preview' => [
                'title' => 'Nikahan Saya',
                'start' => '2026-03-21 00:00:00',
                'end' => '2026-03-21 23:59:59',
                'target' => '',
                'category' => '-',
                'reminder' => '-',
                'items' => [],
            ],
        ]), 200)
        ->push(eventResponse([
            'action_type' => 'delete_event',
            'payload' => [
                'title' => '',
                'start_at' => '',
                'end_at' => '',
                'target_title' => 'rapat tim',
                'category_name' => '',
                'reminder_minutes' => -1,
                'items' => [],
            ],
            'preview' => [
                'title' => '',
                'start' => '',
                'end' => '',
                'target' => 'rapat tim',
                'category' => '',
                'reminder' => '',
                'items' => [],
            ],
        ]), 200);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $first = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'jadwalkan tanggal 21 maret 2026 nikahan saya']
    );
    $first->assertCreated();

    $second = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'hapus event judul rapat tim']
    );

    $second->assertCreated();
    $second->assertJsonPath('thread.messages.3.draft.action_type', 'delete_event');
});

test('sendMessage falls back to legacy parser only when ai disabled', function () {
    config(['services.cerebras.key' => '']);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => null]);

    $response = $this->actingAs($user)->postJson(
        route('chatbot.threads.messages.store', $thread),
        ['content' => 'buat todo minggu ini, revisi bab 1, cari jurnal']
    );

    $response->assertCreated();
    $response->assertJsonPath('thread.messages.1.draft.action_type', 'create_todo_list');
    $response->assertJsonPath('thread.messages.1.draft.status', 'needs_confirm');
});

test('confirmDraft keeps executor flow and sets draft status executed', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => 'Thread Confirm']);

    $assistantMessage = ChatMessage::create([
        'thread_id' => $thread->id,
        'sender' => ChatMessage::SENDER_ASSISTANT,
        'content' => 'Draft siap dieksekusi.',
        'created_at' => now(),
    ]);

    $draft = ChatActionDraft::create([
        'thread_id' => $thread->id,
        'message_id' => $assistantMessage->id,
        'action_type' => 'create_todo_list',
        'payload_json' => [
            'payload' => [
                'title' => 'Minggu Ini',
                'items' => ['Revisi Bab 1', 'Cari jurnal'],
            ],
            'preview' => [
                'title' => 'Minggu Ini',
                'items' => ['Revisi Bab 1', 'Cari jurnal'],
            ],
        ],
        'status' => ChatActionDraft::STATUS_NEEDS_CONFIRM,
    ]);

    $response = $this->actingAs($user)->postJson(route('chatbot.drafts.confirm', $draft));

    $response->assertOk();
    $response->assertJsonPath('result.ok', true);
    $this->assertDatabaseHas('chat_action_drafts', ['id' => $draft->id, 'status' => ChatActionDraft::STATUS_EXECUTED]);
});

test('cancelDraft updates status to canceled', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = ChatThread::create(['user_id' => $user->id, 'title' => 'Thread Cancel']);

    $assistantMessage = ChatMessage::create([
        'thread_id' => $thread->id,
        'sender' => ChatMessage::SENDER_ASSISTANT,
        'content' => 'Draft siap dibatalkan.',
        'created_at' => now(),
    ]);

    $draft = ChatActionDraft::create([
        'thread_id' => $thread->id,
        'message_id' => $assistantMessage->id,
        'action_type' => 'delete_event',
        'payload_json' => [
            'payload' => ['target_title' => 'Rapat Lama'],
            'preview' => ['target' => 'Rapat Lama'],
        ],
        'status' => ChatActionDraft::STATUS_NEEDS_CONFIRM,
    ]);

    $response = $this->actingAs($user)->postJson(route('chatbot.drafts.cancel', $draft));

    $response->assertOk();
    $this->assertDatabaseHas('chat_action_drafts', ['id' => $draft->id, 'status' => ChatActionDraft::STATUS_CANCELED]);
});
