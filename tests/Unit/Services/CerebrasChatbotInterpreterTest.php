<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\CerebrasChatbotInterpreter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

function makeThreadWithUserMessage(User $user, string $content): ChatThread
{
    $thread = ChatThread::create([
        'user_id' => $user->id,
        'title' => 'thread',
    ]);

    ChatMessage::create([
        'thread_id' => $thread->id,
        'sender' => ChatMessage::SENDER_USER,
        'content' => $content,
        'created_at' => now(),
    ]);

    return $thread;
}

function aiPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'ok' => true,
        'action_type' => 'create_event',
        'payload' => [
            'title' => 'Hari Raya Idul Fitri',
            'start_at' => '',
            'end_at' => '',
            'target_title' => '',
            'category_name' => '',
            'reminder_minutes' => -1,
            'items' => [],
        ],
        'preview' => [
            'title' => 'Hari Raya Idul Fitri',
            'start' => '',
            'end' => '',
            'target' => '',
            'category' => '',
            'reminder' => '',
            'items' => [],
        ],
        'question' => '',
    ], $overrides);
}

test('interpreter uses all day for date without explicit time', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'JADWALKAN TANGGAL 20 MARET 2026 ITU HARI RAYA IDUL FITRI');

    Http::fake([
        'https://api.cerebras.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(aiPayload(), JSON_UNESCAPED_UNICODE)]]],
        ], 200),
    ]);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeTrue();
    expect($result['action_type'])->toBe('create_event');
    expect($result['payload']['start_at'])->toBe('2026-03-20 00:00:00');
    expect($result['payload']['end_at'])->toBe('2026-03-20 23:59:59');
    expect($result['meta']['parse_stage'])->toBe('primary');
});

test('interpreter retries repair and succeeds on second pass', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'jadwalkan tanggal 20 maret 2026 hari raya idul fitri');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'not-json']]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode(aiPayload(), JSON_UNESCAPED_UNICODE)]]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeTrue();
    expect($result['meta']['parse_stage'])->toBe('repair');
});

test('interpreter uses deterministic request options on primary pass', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'jadwalkan tanggal 20 maret 2026 hari raya idul fitri');

    Http::fake([
        'https://api.cerebras.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(aiPayload(), JSON_UNESCAPED_UNICODE)]],
            ],
        ], 200),
    ]);

    app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return data_get($body, 'temperature') === 0.0
            && data_get($body, 'seed') === 20260311;
    });
});

test('interpreter builds smart create_event fallback when ai outputs invalid json twice', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'tolong buatkan jadwal tanggal 21 maret 2026 nikahan saya reminder 10 menit');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'bad-one']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'bad-two']]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeTrue();
    expect($result['action_type'])->toBe('create_event');
    expect($result['payload']['title'])->toBe('nikahan saya');
    expect($result['payload']['start_at'])->toBe('2026-03-21 00:00:00');
    expect($result['payload']['end_at'])->toBe('2026-03-21 23:59:59');
    expect($result['payload']['reminder_minutes'])->toBe(10);
    expect($result['meta']['parse_stage'])->toBe('smart_fallback');
});

test('interpreter returns contextual clarification when both passes fail', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'jadwalkan tanggal 20 maret 2026');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'bad-one']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'bad-two']]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeFalse();
    expect($result['question'])->toContain('judul event');
    expect($result['meta']['parse_stage'])->toBe('final_clarification');
});

test('interpreter clarification does not ask title when title already present', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'tolong jadwalkan nikahan saya');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'bad-one']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'bad-two']]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeFalse();
    expect($result['question'])->toContain('tanggal');
    expect($result['question'])->not->toContain('sebutkan judul');
});

test('interpreter rejects action outside allowlist', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'hapus event rapat tim');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => json_encode(aiPayload(['action_type' => 'create_note']), JSON_UNESCAPED_UNICODE)]]]], 200)
        ->push(['choices' => [['message' => ['content' => 'still-bad']]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeFalse();
    expect($result['meta']['parse_stage'])->toBe('final_clarification');
});

test('interpreter prefilters non domain prompt before ai call', function () {
    Http::fake();

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'apa warna favoritmu?');

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeFalse();
    expect($result['meta']['parse_stage'])->toBe('prefilter_non_domain');
    Http::assertNothingSent();
});

test('interpreter sets null category and reminder for terserah phrases', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'tambahkan event di tanggal 20 maret hari nikah andino dan sayu kategorynya terserah anda remindernya terserah anda');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'bad-one']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'bad-two']]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeTrue();
    expect($result['action_type'])->toBe('create_event');
    expect($result['payload']['category_name'])->toBeNull();
    expect($result['payload']['reminder_minutes'])->toBeNull();
});

test('interpreter reads typo kategory and remindernya from prompt', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'tambahkan event tanggal 20 maret 2026 nikahan saya kategory keluarga remindernya 10 menit');

    Http::fakeSequence()
        ->push(['choices' => [['message' => ['content' => 'bad-one']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'bad-two']]]], 200);

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeTrue();
    expect($result['payload']['category_name'])->toBe('keluarga');
    expect($result['payload']['reminder_minutes'])->toBe(10);
});

test('interpreter signals legacy fallback only when ai disabled', function () {
    config(['services.cerebras.key' => '']);

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $thread = makeThreadWithUserMessage($user, 'buat todo minggu ini, revisi bab 1');

    $result = app(CerebrasChatbotInterpreter::class)->interpret($thread, $user);

    expect($result['ok'])->toBeFalse();
    expect($result['meta']['parse_stage'])->toBe('disabled');
    expect($result['meta']['fallback_to_legacy'])->toBeTrue();
});
