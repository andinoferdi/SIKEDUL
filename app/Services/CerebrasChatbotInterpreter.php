<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CerebrasChatbotInterpreter
{
    private const ACTION_CREATE_EVENT = 'create_event';
    private const ACTION_UPDATE_EVENT = 'update_event';
    private const ACTION_DELETE_EVENT = 'delete_event';
    private const ACTION_CREATE_TODO_LIST = 'create_todo_list';

    private const ALLOWED_ACTIONS = [
        self::ACTION_CREATE_EVENT,
        self::ACTION_UPDATE_EVENT,
        self::ACTION_DELETE_EVENT,
        self::ACTION_CREATE_TODO_LIST,
    ];

    private const ALLOWED_REMINDER_MINUTES = [0, 5, 10, 15, 30, 60, 1440];
    private const DOMAIN_CLARIFICATION_QUESTION = 'Saya fokus membantu aksi event atau todo. Mohon tulis perintah yang terkait jadwal atau daftar tugas, beserta detail utamanya.';

    public function interpret(ChatThread $thread, User $user): array
    {
        $context = $this->buildPromptContext($thread, $user);

        if (! $this->isAiEnabled()) {
            return $this->buildAiDisabledResult($context);
        }

        if ($this->isClearlyOutOfDomain($context)) {
            return [
                'ok' => false,
                'question' => self::DOMAIN_CLARIFICATION_QUESTION,
                'meta' => [
                    'parse_stage' => 'prefilter_non_domain',
                    'fallback_to_legacy' => false,
                    'failure_reason' => 'out_of_domain',
                    'action_guess' => $context['hints']['intent_guess'],
                    'intent_confidence' => 0.0,
                    'original_command_hash' => $context['last_user_hash'],
                ],
            ];
        }

        $primary = $this->requestInterpretation($context, 'primary');

        if ($primary['decoded']) {
            $normalized = $this->normalizeOutput($primary['decoded'], $user->timezone, $context);

            if ($normalized) {
                $guard = $this->applyOutputGuards($normalized, $context);
                if ($guard['accept']) {
                    return $this->withMeta($guard['result'], [
                        'parse_stage' => 'primary',
                        'fallback_to_legacy' => false,
                        'failure_reason' => null,
                        'action_guess' => $context['hints']['intent_guess'],
                        'intent_confidence' => $guard['intent_confidence'],
                        'original_command_hash' => $context['last_user_hash'],
                    ]);
                }

                return $this->withMeta($guard['result'], [
                    'parse_stage' => 'primary_guarded',
                    'fallback_to_legacy' => false,
                    'failure_reason' => $guard['failure_reason'],
                    'action_guess' => $context['hints']['intent_guess'],
                    'intent_confidence' => $guard['intent_confidence'],
                    'original_command_hash' => $context['last_user_hash'],
                ]);
            }
        }

        $retryCount = max(0, (int) config('services.cerebras.retry_count', 1));
        $retryBackoffMs = max(0, (int) config('services.cerebras.retry_backoff_ms', 250));
        $lastFailureReason = $primary['failure_reason'] ?? 'unknown';
        $lastRawSnippet = $primary['raw_snippet'] ?? '';

        for ($attempt = 1; $attempt <= $retryCount; $attempt++) {
            if ($retryBackoffMs > 0) {
                usleep($retryBackoffMs * 1000);
            }

            $repair = $this->requestRepairInterpretation($context, $lastRawSnippet, $lastFailureReason, $attempt);

            if ($repair['decoded']) {
                $normalized = $this->normalizeOutput($repair['decoded'], $user->timezone, $context);

                if ($normalized) {
                    $guard = $this->applyOutputGuards($normalized, $context);
                    if ($guard['accept']) {
                        return $this->withMeta($guard['result'], [
                            'parse_stage' => 'repair',
                            'fallback_to_legacy' => false,
                            'failure_reason' => null,
                            'action_guess' => $context['hints']['intent_guess'],
                            'intent_confidence' => $guard['intent_confidence'],
                            'original_command_hash' => $context['last_user_hash'],
                        ]);
                    }

                    return $this->withMeta($guard['result'], [
                        'parse_stage' => 'repair_guarded',
                        'fallback_to_legacy' => false,
                        'failure_reason' => $guard['failure_reason'],
                        'action_guess' => $context['hints']['intent_guess'],
                        'intent_confidence' => $guard['intent_confidence'],
                        'original_command_hash' => $context['last_user_hash'],
                    ]);
                }
            }

            $lastFailureReason = $repair['failure_reason'] ?? $lastFailureReason;
            $lastRawSnippet = $repair['raw_snippet'] ?? $lastRawSnippet;
        }

        $smartFallback = $this->buildSmartCreateEventFallback($context, $lastFailureReason);
        if ($smartFallback) {
            return $smartFallback;
        }

        return [
            'ok' => false,
            'question' => $this->buildClarificationQuestion($context),
            'meta' => [
                'parse_stage' => 'final_clarification',
                'fallback_to_legacy' => false,
                'failure_reason' => $lastFailureReason,
                'action_guess' => $context['hints']['intent_guess'],
                'intent_confidence' => 0.0,
                'original_command_hash' => $context['last_user_hash'],
            ],
        ];
    }

    private function isAiEnabled(): bool
    {
        return trim((string) config('services.cerebras.key')) !== '';
    }

    private function buildModelOptions(bool $isRepair): array
    {
        $forceDeterministic = (bool) config('services.cerebras.force_deterministic', true);
        $temperature = $isRepair || $forceDeterministic
            ? 0.0
            : (float) config('services.cerebras.temperature', 0.1);
        $seed = (int) config('services.cerebras.seed', 20260311);

        return [
            'temperature' => $temperature,
            'max_completion_tokens' => (int) config('services.cerebras.max_completion_tokens', 500),
            'seed' => $seed > 0 ? $seed : 20260311,
        ];
    }

    private function buildAiDisabledResult(array $context): array
    {
        $allowLegacy = (bool) config('services.cerebras.enable_legacy_parser_when_ai_disabled', true);

        Log::notice('Cerebras disabled. AI interpreter skipped.', [
            'parse_stage' => 'disabled',
            'failure_reason' => 'ai_disabled',
            'action_guess' => $context['hints']['intent_guess'],
        ]);

        return [
            'ok' => false,
            'question' => $allowLegacy
                ? 'Asisten AI dinonaktifkan. Sistem menggunakan parser lokal.'
                : 'Asisten AI saat ini nonaktif. Mohon aktifkan konfigurasi AI atau gunakan format perintah yang didukung.',
            'meta' => [
                'parse_stage' => 'disabled',
                'fallback_to_legacy' => $allowLegacy,
                'failure_reason' => 'ai_disabled',
                'action_guess' => $context['hints']['intent_guess'],
            ],
        ];
    }

    private function requestInterpretation(array $context, string $stage): array
    {
        $modelOptions = $this->buildModelOptions(false);

        $payload = [
            'model' => (string) config('services.cerebras.model', 'gpt-oss-120b'),
            'stream' => false,
            'temperature' => $modelOptions['temperature'],
            'max_completion_tokens' => $modelOptions['max_completion_tokens'],
            'seed' => $modelOptions['seed'],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt($context)],
                ['role' => 'user', 'content' => $this->buildPrimaryUserMessage($context)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'sikedul_chatbot_action',
                    'strict' => true,
                    'schema' => $this->responseSchema(),
                ],
            ],
        ];

        return $this->sendCerebrasRequest($payload, $stage, $context['hints']['intent_guess']);
    }

    private function requestRepairInterpretation(array $context, string $rawSnippet, string $failureReason, int $attempt): array
    {
        $modelOptions = $this->buildModelOptions(true);

        $payload = [
            'model' => (string) config('services.cerebras.model', 'gpt-oss-120b'),
            'stream' => false,
            'temperature' => $modelOptions['temperature'],
            'max_completion_tokens' => $modelOptions['max_completion_tokens'],
            'seed' => $modelOptions['seed'],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt($context)],
                ['role' => 'user', 'content' => $this->buildRepairUserMessage($context, $rawSnippet, $failureReason, $attempt)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'sikedul_chatbot_action_repair',
                    'strict' => true,
                    'schema' => $this->responseSchema(),
                ],
            ],
        ];

        return $this->sendCerebrasRequest($payload, 'repair_attempt_'.$attempt, $context['hints']['intent_guess']);
    }

    private function sendCerebrasRequest(array $payload, string $stage, string $actionGuess): array
    {
        $apiKey = trim((string) config('services.cerebras.key'));
        $baseUrl = rtrim((string) config('services.cerebras.base_url', 'https://api.cerebras.ai/v1'), '/');
        $endpoint = "{$baseUrl}/chat/completions";
        $timeoutSeconds = max(1, (int) config('services.cerebras.timeout', 20));
        $finishReason = null;
        $contentLength = null;

        try {
            $response = Http::withToken($apiKey)->acceptJson()->timeout($timeoutSeconds)->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            $reason = 'connection_or_timeout';
            $this->logFailure($stage, $reason, $actionGuess, ['message' => $exception->getMessage()]);

            return ['decoded' => null, 'failure_reason' => $reason, 'raw_snippet' => ''];
        }

        $finishReason = data_get($response->json(), 'choices.0.finish_reason');
        $rawContent = data_get($response->json(), 'choices.0.message.content');
        $contentLength = is_string($rawContent) ? mb_strlen($rawContent) : null;

        if ($response->status() === 429) {
            $reason = 'rate_limit';
            $this->logFailure($stage, $reason, $actionGuess, [
                'status' => 429,
                'finish_reason' => $finishReason,
                'content_length' => $contentLength,
            ]);

            return ['decoded' => null, 'failure_reason' => $reason, 'raw_snippet' => $this->extractResponseContentSnippet($response)];
        }

        if ($response->serverError()) {
            $reason = 'server_error';
            $this->logFailure($stage, $reason, $actionGuess, [
                'status' => $response->status(),
                'finish_reason' => $finishReason,
                'content_length' => $contentLength,
            ]);

            return ['decoded' => null, 'failure_reason' => $reason, 'raw_snippet' => $this->extractResponseContentSnippet($response)];
        }
        if (! $response->successful()) {
            $reason = 'http_error';
            $this->logFailure($stage, $reason, $actionGuess, [
                'status' => $response->status(),
                'finish_reason' => $finishReason,
                'content_length' => $contentLength,
                'body' => $this->truncateBodyForLog($response),
            ]);

            return ['decoded' => null, 'failure_reason' => $reason, 'raw_snippet' => $this->extractResponseContentSnippet($response)];
        }

        $decoded = $this->decodeResponseContent($response);

        if (! $decoded) {
            $reason = 'invalid_json_schema_output';
            $snippet = $this->extractResponseContentSnippet($response);
            $this->logFailure($stage, $reason, $actionGuess, [
                'finish_reason' => $finishReason,
                'content_length' => $contentLength,
                'content' => $snippet,
            ]);

            return ['decoded' => null, 'failure_reason' => $reason, 'raw_snippet' => $snippet];
        }

        return ['decoded' => $decoded, 'failure_reason' => null, 'raw_snippet' => ''];
    }

    private function buildPromptContext(ChatThread $thread, User $user): array
    {
        $timezone = $user->timezone ?: 'Asia/Jakarta';
        $contextSize = max(1, (int) config('services.cerebras.max_context_messages', 16));

        $history = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->orderByDesc('id')
            ->take($contextSize)
            ->get(['id', 'sender', 'content'])
            ->reverse()
            ->values();

        $lastUserMessage = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('sender', ChatMessage::SENDER_USER)
            ->orderByDesc('id')
            ->first(['id', 'content']);
        $lastUserOriginal = trim((string) ($lastUserMessage?->content ?? ''));

        $normalized = $this->normalizeUserMessage($lastUserOriginal);
        $hints = $this->extractHints($normalized, $timezone);

        return [
            'timezone' => $timezone,
            'now' => Carbon::now($timezone)->format('Y-m-d H:i:s'),
            'history' => $history,
            'latest_user_message_id' => $lastUserMessage?->id,
            'last_user_original' => $lastUserOriginal,
            'last_user_normalized' => $normalized,
            'last_user_hash' => $this->hashCommand($lastUserOriginal),
            'context_summary' => $this->buildContextSummary($history, $lastUserMessage?->id),
            'hints' => $hints,
        ];
    }

    private function normalizeUserMessage(string $message): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? '');

        if ($text === '') {
            return $text;
        }

        if ($this->isAllCaps($text)) {
            $text = mb_strtolower($text, 'UTF-8');
        }

        $text = preg_replace('/\b(kategory|katagori|category)\b/iu', 'kategori', $text) ?? $text;
        $text = preg_replace('/\b(remindernya|pengingatnya|pengingat)\b/iu', 'reminder', $text) ?? $text;
        $text = preg_replace('/\bterserah\s+anda\b/iu', 'terserah', $text) ?? $text;

        $text = preg_replace('/\b(\d{1,2})\.(\d{2})\b/u', '$1:$2', $text) ?? $text;
        $text = preg_replace_callback(
            '/\b(?:jam|pukul)\s*(\d{1,2})(?:[:.](\d{2}))?\s*(pagi|siang|sore|malam)?\b/ui',
            function (array $match): string {
                $hour = (int) $match[1];
                $minute = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 0;
                $period = isset($match[3]) ? Str::lower($match[3]) : '';

                if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                    return $match[0];
                }

                if ($period === 'siang' && $hour >= 1 && $hour <= 11) {
                    $hour += 12;
                }

                if (($period === 'sore' || $period === 'malam') && $hour >= 1 && $hour <= 11) {
                    $hour += 12;
                }

                return sprintf('%02d:%02d', $hour, $minute);
            },
            $text
        ) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function extractHints(string $normalized, string $timezone): array
    {
        $lower = Str::lower($normalized);
        $intentGuess = $this->guessIntent($lower);

        $datePhrases = [];
        preg_match_all('/\b(?:tanggal\s+)?\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+\d{4})?\b/ui', $lower, $dateMatches);
        if (! empty($dateMatches[0])) {
            $datePhrases = array_values(array_unique(array_map('trim', $dateMatches[0])));
        }

        foreach (['hari ini', 'besok', 'lusa'] as $token) {
            if (Str::contains($lower, $token)) {
                $datePhrases[] = $token;
            }
        }

        $timePhrases = [];
        preg_match_all('/\b(?:[01]?\d|2[0-3]):[0-5]\d\b/u', $normalized, $timeMatches);
        if (! empty($timeMatches[0])) {
            $timePhrases = array_values(array_unique(array_map('trim', $timeMatches[0])));
        }

        $hasExplicitTime = count($timePhrases) > 0;
        $allDayDate = null;

        if ($intentGuess === self::ACTION_CREATE_EVENT && count($datePhrases) > 0 && ! $hasExplicitTime) {
            $allDayDate = $this->extractDateForAllDay($lower, $timezone);
        }

        $categoryCandidate = $this->extractCategoryCandidate($normalized);
        $reminderCandidate = $this->extractReminderCandidate($normalized);
        $titleCandidate = $this->extractCreateEventTitleCandidate($normalized);

        return [
            'intent_guess' => $intentGuess,
            'date_phrases' => array_values(array_unique($datePhrases)),
            'time_phrases' => $timePhrases,
            'has_explicit_time' => $hasExplicitTime,
            'all_day_date' => $allDayDate,
            'title_candidate' => $titleCandidate,
            'category_candidate' => $categoryCandidate,
            'reminder_candidate' => $reminderCandidate,
        ];
    }

    private function buildSystemPrompt(array $context): string
    {
        $hintsJson = json_encode($context['hints'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Anda adalah interpreter perintah chatbot untuk aplikasi jadwal dan todo.
Keluaran wajib JSON yang patuh schema. Jangan keluarkan markdown, penjelasan, atau teks lain.

Aturan:
- Bahasa pertanyaan klarifikasi harus Bahasa Indonesia.
- Aksi yang diizinkan hanya create_event, update_event, delete_event, create_todo_list.
- Jika intent belum jelas, set ok=false dan isi question dengan pertanyaan singkat, spesifik, dan bisa ditindaklanjuti.
- Timezone user: {$context['timezone']}.
- Waktu saat ini: {$context['now']}.
- Format waktu payload start_at/end_at wajib YYYY-MM-DD HH:MM:SS.
- Jika user menyebut tanggal tanpa jam untuk create_event, gunakan event seharian: 00:00:00 sampai 23:59:59.
- Gunakan pesan user terakhir sebagai sumber utama, konteks lama hanya pendukung.
- Dilarang menyalin judul atau target dari konteks lama jika tidak muncul pada pesan user terakhir.
- Jika prompt non-domain, keluarkan ok=false dengan pertanyaan klarifikasi domain.
- Untuk field yang tidak relevan, isi nilai default aman sesuai schema.

Ringkasan konteks relevan:
{$context['context_summary']}

Hint analisis awal:
{$hintsJson}
PROMPT;
    }

    private function buildPrimaryUserMessage(array $context): string
    {
        return <<<TEXT
Pesan user terakhir (asli): {$context['last_user_original']}
Pesan user terakhir (normalisasi): {$context['last_user_normalized']}

Tugas:
1) Tentukan aksi jika memungkinkan.
2) Keluarkan JSON valid sesuai schema.
3) Jika belum cukup data, set ok=false dan berikan question yang actionable.
TEXT;
    }
    private function buildRepairUserMessage(array $context, string $rawSnippet, string $failureReason, int $attempt): string
    {
        $snippet = trim($rawSnippet) !== '' ? trim($rawSnippet) : '(kosong)';

        return <<<TEXT
Mode perbaikan output JSON. Ini percobaan ke-{$attempt}.

Pesan user terakhir (asli): {$context['last_user_original']}
Pesan user terakhir (normalisasi): {$context['last_user_normalized']}
Alasan kegagalan sebelumnya: {$failureReason}
Cuplikan output sebelumnya: {$snippet}

Instruksi:
- Perbaiki dan keluarkan hanya JSON valid sesuai schema.
- Jangan menambah field di luar schema.
- Jika data kurang, set ok=false dan isi question yang spesifik.
TEXT;
    }

    private function buildContextSummary($history, ?int $lastUserMessageId): string
    {
        $lines = [];

        foreach ($history as $message) {
            $content = trim((string) $message->content);

            if ($content === '') {
                continue;
            }

            if ($lastUserMessageId && $message->id === $lastUserMessageId) {
                continue;
            }

            if ($message->sender !== ChatMessage::SENDER_USER) {
                continue;
            }

            $role = 'user';
            $lines[] = "- {$role}: ".Str::limit($content, 140, '...');
        }

        if (count($lines) === 0) {
            return '- Tidak ada konteks tambahan.';
        }

        return implode("\n", array_slice($lines, -2));
    }

    private function guessIntent(string $lowerMessage): string
    {
        if (preg_match('/\b(hapus|delete|batalkan)\b/u', $lowerMessage)) {
            return self::ACTION_DELETE_EVENT;
        }

        if (preg_match('/\b(ubah|pindah|reschedule|jadwal ulang)\b/u', $lowerMessage)) {
            return self::ACTION_UPDATE_EVENT;
        }

        if (preg_match('/\b(todo|to do|tugas|daftar)\b/u', $lowerMessage)) {
            return self::ACTION_CREATE_TODO_LIST;
        }

        if (preg_match('/\b(jadwal|jadwalkan|event|meeting|rapat|agenda)\b/u', $lowerMessage)) {
            return self::ACTION_CREATE_EVENT;
        }

        return 'unknown';
    }

    private function extractDateForAllDay(string $lowerMessage, string $timezone): ?string
    {
        $monthMap = [
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];

        if (preg_match('/\b(?:tanggal\s+)?(\d{1,2})\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+(\d{4}))?\b/ui', $lowerMessage, $match)) {
            $day = (int) $match[1];
            $monthName = Str::lower($match[2]);
            $month = $monthMap[$monthName] ?? 0;
            $year = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : Carbon::now($timezone)->year;

            if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12) {
                try {
                    return Carbon::create($year, $month, $day, 0, 0, 0, $timezone)->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        if (Str::contains($lowerMessage, 'lusa')) {
            return Carbon::now($timezone)->addDays(2)->format('Y-m-d');
        }

        if (Str::contains($lowerMessage, 'besok')) {
            return Carbon::now($timezone)->addDay()->format('Y-m-d');
        }

        if (Str::contains($lowerMessage, 'hari ini')) {
            return Carbon::now($timezone)->format('Y-m-d');
        }

        return null;
    }

    private function isAllCaps(string $text): bool
    {
        return preg_match('/\pL/u', $text) === 1
            && mb_strtoupper($text, 'UTF-8') === $text;
    }

    private function extractCategoryCandidate(string $normalized): ?string
    {
        if (! preg_match('/\bkategori(?:nya)?\s+(.+?)(?:\s+(?:reminder|ingatkan)\b|$)/iu', $normalized, $matches)) {
            return null;
        }

        $value = trim((string) ($matches[1] ?? ''));
        if ($value === '' || preg_match('/^\s*terserah\s*$/iu', $value) === 1) {
            return null;
        }

        return $value === '' ? null : Str::limit($value, 80, '');
    }

    private function extractReminderCandidate(string $normalized): ?int
    {
        if (preg_match('/\b(?:reminder|ingatkan)\s+terserah\b/iu', $normalized) === 1) {
            return null;
        }

        if (preg_match('/\b(?:reminder(?:nya)?|ingatkan)\s*(\d{1,4})\s*menit\b/iu', $normalized, $matches)) {
            return $this->normalizeReminderMinutes((int) $matches[1]);
        }

        return null;
    }

    private function extractCreateEventTitleCandidate(string $normalized): ?string
    {
        $text = Str::lower($normalized);
        $text = preg_replace('/\b(?:tolong|please|dong|ya)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:buatkan|buat|jadwalkan|jadwal|event|agenda)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:hari\s+ini|besok|lusa)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:tanggal\s+)?\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+\d{4})?\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:jam|pukul)?\s*(?:[01]?\d|2[0-3])(?::[0-5]\d)?\s*(?:pagi|siang|sore|malam)?\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:dari|sampai|hingga|itu)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:reminder(?:nya)?|ingatkan)\s*\d{1,4}\s*menit\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:reminder|ingatkan)\s+terserah\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:reminder(?:nya)?|ingatkan|menit)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\bkategori(?:nya)?\s+terserah\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\bkategori(?:nya)?\s+.+$/u', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return null;
        }

        return Str::limit($text, 120, '');
    }

    private function buildSmartCreateEventFallback(array $context, string $failureReason): ?array
    {
        if (($context['hints']['intent_guess'] ?? 'unknown') !== self::ACTION_CREATE_EVENT) {
            return null;
        }

        if ($this->containsDeleteOrUpdateVerb((string) ($context['last_user_normalized'] ?? ''))) {
            return null;
        }

        $title = trim((string) ($context['hints']['title_candidate'] ?? ''));
        $allDayDate = $context['hints']['all_day_date'] ?? null;

        if ($title === '' || ! is_string($allDayDate) || $allDayDate === '') {
            return null;
        }

        $payload = [
            'title' => $title,
            'start_at' => "{$allDayDate} 00:00:00",
            'end_at' => "{$allDayDate} 23:59:59",
            'category_name' => $context['hints']['category_candidate'] ?? null,
            'reminder_minutes' => $context['hints']['reminder_candidate'] ?? null,
        ];

        return [
            'ok' => true,
            'action_type' => self::ACTION_CREATE_EVENT,
            'payload' => $payload,
            'preview' => [
                'title' => $payload['title'],
                'start' => $payload['start_at'],
                'end' => $payload['end_at'],
                'category' => $payload['category_name'] ?? '-',
                'reminder' => $payload['reminder_minutes'] === null ? '-' : "{$payload['reminder_minutes']} menit",
            ],
            'meta' => [
                'parse_stage' => 'smart_fallback',
                'fallback_to_legacy' => false,
                'failure_reason' => $failureReason,
                'action_guess' => $context['hints']['intent_guess'],
                'intent_confidence' => 0.75,
                'original_command_hash' => $context['last_user_hash'],
            ],
        ];
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
                'action_type' => [
                    'type' => 'string',
                    'enum' => [
                        self::ACTION_CREATE_EVENT,
                        self::ACTION_UPDATE_EVENT,
                        self::ACTION_DELETE_EVENT,
                        self::ACTION_CREATE_TODO_LIST,
                        'unknown',
                    ],
                ],
                'payload' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'start_at' => ['type' => 'string'],
                        'end_at' => ['type' => 'string'],
                        'target_title' => ['type' => 'string'],
                        'category_name' => ['type' => 'string'],
                        'reminder_minutes' => ['type' => 'integer'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['title', 'start_at', 'end_at', 'target_title', 'category_name', 'reminder_minutes', 'items'],
                    'additionalProperties' => false,
                ],
                'preview' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'start' => ['type' => 'string'],
                        'end' => ['type' => 'string'],
                        'target' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'reminder' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['title', 'start', 'end', 'target', 'category', 'reminder', 'items'],
                    'additionalProperties' => false,
                ],
                'question' => ['type' => 'string'],
            ],
            'required' => ['ok', 'action_type', 'payload', 'preview', 'question'],
            'additionalProperties' => false,
        ];
    }

    private function decodeResponseContent(Response $response): ?array
    {
        $content = data_get($response->json(), 'choices.0.message.content');

        if (is_array($content)) {
            if (array_is_list($content)) {
                $textBuffer = '';

                foreach ($content as $segment) {
                    if (is_array($segment)) {
                        $textBuffer .= (string) ($segment['text'] ?? $segment['content'] ?? '');
                    } else {
                        $textBuffer .= (string) $segment;
                    }
                }

                return $this->decodeJsonText($textBuffer);
            }

            return $content;
        }

        if (! is_string($content)) {
            return null;
        }

        return $this->decodeJsonText($content);
    }
    private function decodeJsonText(string $jsonText): ?array
    {
        $trimmed = trim($jsonText);
        $trimmed = preg_replace('/^```(?:json)?/m', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/```$/m', '', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);

        $decoded = $this->decodeJsonArray($trimmed);

        if ($decoded) {
            return $decoded;
        }

        $jsonSlice = $this->extractOutermostJsonObject($trimmed);

        return $jsonSlice ? $this->decodeJsonArray($jsonSlice) : null;
    }

    private function decodeJsonArray(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function extractOutermostJsonObject(string $value): ?string
    {
        $start = strpos($value, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($value);

        for ($i = $start; $i < $length; $i++) {
            $char = $value[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($value, $start, ($i - $start) + 1);
                }
            }
        }

        return null;
    }

    private function normalizeOutput(array $output, string $timezone, array $context): ?array
    {
        if (! array_key_exists('ok', $output) || ! is_bool($output['ok'])) {
            return null;
        }

        if (! $output['ok']) {
            $question = trim((string) ($output['question'] ?? ''));

            return [
                'ok' => false,
                'question' => $question !== '' ? $question : $this->buildClarificationQuestion($context),
            ];
        }

        $actionType = trim((string) ($output['action_type'] ?? ''));
        $payload = $output['payload'] ?? null;
        $preview = $output['preview'] ?? null;

        if (! in_array($actionType, self::ALLOWED_ACTIONS, true) || ! is_array($payload)) {
            return null;
        }

        $normalizedPayload = $this->normalizePayloadByAction($actionType, $payload, $timezone, $context);

        if (! $normalizedPayload) {
            return null;
        }

        return [
            'ok' => true,
            'action_type' => $actionType,
            'payload' => $normalizedPayload,
            'preview' => $this->normalizePreviewByAction($actionType, is_array($preview) ? $preview : [], $normalizedPayload),
        ];
    }

    private function isClearlyOutOfDomain(array $context): bool
    {
        $intentGuess = (string) ($context['hints']['intent_guess'] ?? 'unknown');
        if ($intentGuess !== 'unknown') {
            return false;
        }

        $normalized = Str::lower((string) ($context['last_user_normalized'] ?? ''));
        if ($normalized === '') {
            return true;
        }

        if (($context['hints']['all_day_date'] ?? null) !== null) {
            return false;
        }

        if (count($context['hints']['date_phrases'] ?? []) > 0 || count($context['hints']['time_phrases'] ?? []) > 0) {
            return false;
        }

        return ! preg_match('/\b(event|agenda|jadwal|rapat|meeting|todo|tugas|daftar|hapus|ubah|pindah|jadwal ulang)\b/u', $normalized);
    }

    private function applyOutputGuards(array $result, array $context): array
    {
        if (! ($result['ok'] ?? false)) {
            return [
                'accept' => true,
                'result' => $result,
                'failure_reason' => null,
                'intent_confidence' => 0.0,
            ];
        }

        $actionType = (string) ($result['action_type'] ?? '');
        $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
        $confidence = $this->calculateIntentConfidence($actionType, $payload, $context);
        $threshold = (float) config('services.cerebras.min_intent_confidence', 0.70);

        if ($confidence < $threshold) {
            return [
                'accept' => false,
                'result' => [
                    'ok' => false,
                    'question' => $this->buildClarificationQuestion($context),
                ],
                'failure_reason' => 'low_confidence',
                'intent_confidence' => $confidence,
            ];
        }

        if ($this->looksLikeStalePayload($actionType, $payload, $context)) {
            return [
                'accept' => false,
                'result' => [
                    'ok' => false,
                    'question' => $this->buildClarificationQuestion($context),
                ],
                'failure_reason' => 'stale_payload',
                'intent_confidence' => $confidence,
            ];
        }

        return [
            'accept' => true,
            'result' => $result,
            'failure_reason' => null,
            'intent_confidence' => $confidence,
        ];
    }

    private function calculateIntentConfidence(string $actionType, array $payload, array $context): float
    {
        $guess = (string) ($context['hints']['intent_guess'] ?? 'unknown');
        $normalized = (string) ($context['last_user_normalized'] ?? '');

        $score = $guess === $actionType ? 0.85 : ($guess === 'unknown' ? 0.40 : 0.55);

        $targetText = match ($actionType) {
            self::ACTION_CREATE_EVENT => (string) ($payload['title'] ?? ''),
            self::ACTION_UPDATE_EVENT, self::ACTION_DELETE_EVENT => (string) ($payload['target_title'] ?? ''),
            self::ACTION_CREATE_TODO_LIST => (string) ($payload['title'] ?? ''),
            default => '',
        };

        if ($targetText !== '') {
            $score += $this->tokenOverlapScore($targetText, $normalized) * 0.2;
        }

        if ($actionType === self::ACTION_CREATE_EVENT && ($context['hints']['all_day_date'] ?? null) !== null) {
            $score += 0.05;
        }

        return max(0.0, min(1.0, round($score, 4)));
    }

    private function looksLikeStalePayload(string $actionType, array $payload, array $context): bool
    {
        $normalized = (string) ($context['last_user_normalized'] ?? '');
        $titleHint = trim((string) ($context['hints']['title_candidate'] ?? ''));

        if ($actionType === self::ACTION_DELETE_EVENT || $actionType === self::ACTION_UPDATE_EVENT) {
            $targetTitle = trim((string) ($payload['target_title'] ?? ''));
            if ($targetTitle === '') {
                return true;
            }

            return $this->tokenOverlapScore($targetTitle, $normalized) < 0.5;
        }

        if ($actionType === self::ACTION_CREATE_EVENT) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                return true;
            }

            if ($titleHint === '') {
                return $this->tokenOverlapScore($title, $normalized) < 0.35;
            }

            return $this->tokenOverlapScore($title, $titleHint) < 0.5;
        }

        if ($actionType === self::ACTION_CREATE_TODO_LIST) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                return true;
            }

            return $this->tokenOverlapScore($title, $normalized) < 0.3;
        }

        return false;
    }

    private function tokenOverlapScore(string $subject, string $normalizedMessage): float
    {
        $subjectTokens = $this->tokenizeForOverlap($subject);
        if (count($subjectTokens) === 0) {
            return 0.0;
        }

        $messageTokens = $this->tokenizeForOverlap($normalizedMessage);
        if (count($messageTokens) === 0) {
            return 0.0;
        }

        $messageSet = array_fill_keys($messageTokens, true);
        $hits = 0;
        foreach ($subjectTokens as $token) {
            if (isset($messageSet[$token])) {
                $hits++;
            }
        }

        return $hits / count($subjectTokens);
    }

    private function tokenizeForOverlap(string $value): array
    {
        $normalized = Str::lower($value);
        $normalized = preg_replace('/[^a-z0-9\s]/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];
        $stopwords = [
            'yang', 'dan', 'atau', 'untuk', 'dengan', 'pada', 'dari', 'ke', 'di', 'itu', 'ini',
            'saya', 'anda', 'the', 'a', 'an', 'jam', 'tanggal', 'event', 'todo',
        ];
        $stopwordSet = array_fill_keys($stopwords, true);

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || strlen($part) < 2 || isset($stopwordSet[$part])) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function hashCommand(string $command): string
    {
        return hash('sha256', trim(Str::lower($command)));
    }

    private function containsDeleteOrUpdateVerb(string $normalized): bool
    {
        return preg_match('/\b(hapus|delete|batalkan|ubah|pindah|reschedule|jadwal ulang)\b/u', Str::lower($normalized)) === 1;
    }

    private function normalizePayloadByAction(string $actionType, array $payload, string $timezone, array $context): ?array
    {
        return match ($actionType) {
            self::ACTION_CREATE_EVENT => $this->normalizeCreateEventPayload($payload, $timezone, $context),
            self::ACTION_UPDATE_EVENT => $this->normalizeUpdateEventPayload($payload, $timezone),
            self::ACTION_DELETE_EVENT => $this->normalizeDeleteEventPayload($payload),
            self::ACTION_CREATE_TODO_LIST => $this->normalizeCreateTodoPayload($payload),
            default => null,
        };
    }

    private function normalizeCreateEventPayload(array $payload, string $timezone, array $context): ?array
    {
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        $startAt = $this->normalizeDateTime((string) ($payload['start_at'] ?? ''), $timezone);
        $endAt = $this->normalizeDateTime((string) ($payload['end_at'] ?? ''), $timezone);

        if ((! $startAt || ! $endAt) && is_string($context['hints']['all_day_date'] ?? null)) {
            $date = $context['hints']['all_day_date'];
            $startAt = "{$date} 00:00:00";
            $endAt = "{$date} 23:59:59";
        }

        if (! $startAt || ! $endAt || Carbon::parse($startAt, $timezone)->greaterThanOrEqualTo(Carbon::parse($endAt, $timezone))) {
            return null;
        }

        $categoryName = trim((string) ($payload['category_name'] ?? ''));

        return [
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'category_name' => $categoryName !== '' ? $categoryName : null,
            'reminder_minutes' => $this->normalizeReminderMinutes($payload['reminder_minutes'] ?? null),
        ];
    }

    private function normalizeUpdateEventPayload(array $payload, string $timezone): ?array
    {
        $targetTitle = trim((string) ($payload['target_title'] ?? ''));
        $startAt = $this->normalizeDateTime((string) ($payload['start_at'] ?? ''), $timezone);
        $endAt = $this->normalizeDateTime((string) ($payload['end_at'] ?? ''), $timezone);

        if ($targetTitle === '' || ! $startAt || ! $endAt || Carbon::parse($startAt, $timezone)->greaterThanOrEqualTo(Carbon::parse($endAt, $timezone))) {
            return null;
        }

        return ['target_title' => $targetTitle, 'start_at' => $startAt, 'end_at' => $endAt];
    }

    private function normalizeDeleteEventPayload(array $payload): ?array
    {
        $targetTitle = trim((string) ($payload['target_title'] ?? ''));

        return $targetTitle === '' ? null : ['target_title' => $targetTitle];
    }

    private function normalizeCreateTodoPayload(array $payload): ?array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $rawItems = $payload['items'] ?? [];

        if (! is_array($rawItems)) {
            return null;
        }

        $items = [];
        foreach ($rawItems as $item) {
            $cleanItem = trim((string) $item);
            if ($cleanItem !== '') {
                $items[] = $cleanItem;
            }
        }

        return $title === '' || count($items) === 0 ? null : ['title' => $title, 'items' => $items];
    }

    private function normalizePreviewByAction(string $actionType, array $preview, array $payload): array
    {
        return match ($actionType) {
            self::ACTION_CREATE_EVENT => [
                'title' => trim((string) ($preview['title'] ?? $payload['title'])),
                'start' => trim((string) ($preview['start'] ?? $payload['start_at'])),
                'end' => trim((string) ($preview['end'] ?? $payload['end_at'])),
                'category' => trim((string) ($preview['category'] ?? ($payload['category_name'] ?? '-'))) ?: '-',
                'reminder' => trim((string) ($preview['reminder'] ?? ($payload['reminder_minutes'] === null ? '-' : "{$payload['reminder_minutes']} menit"))) ?: '-',
            ],
            self::ACTION_UPDATE_EVENT => [
                'target' => trim((string) ($preview['target'] ?? $payload['target_title'])),
                'start' => trim((string) ($preview['start'] ?? $payload['start_at'])),
                'end' => trim((string) ($preview['end'] ?? $payload['end_at'])),
            ],
            self::ACTION_DELETE_EVENT => ['target' => trim((string) ($preview['target'] ?? $payload['target_title']))],
            self::ACTION_CREATE_TODO_LIST => [
                'title' => trim((string) ($preview['title'] ?? $payload['title'])),
                'items' => is_array($preview['items'] ?? null) ? array_values(array_filter(array_map('strval', $preview['items']))) : $payload['items'],
            ],
            default => [],
        };
    }

    private function normalizeDateTime(string $value, string $timezone): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed, $timezone)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeReminderMinutes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value === -1 || ! in_array($value, self::ALLOWED_REMINDER_MINUTES, true)) {
            return null;
        }

        return $value;
    }

    private function buildClarificationQuestion(array $context): string
    {
        $titleDetected = trim((string) ($context['hints']['title_candidate'] ?? '')) !== '';
        $hasDate = is_string($context['hints']['all_day_date'] ?? null) || count($context['hints']['date_phrases'] ?? []) > 0;
        $needsTimeRange = (bool) ($context['hints']['has_explicit_time'] ?? false)
            && count($context['hints']['time_phrases'] ?? []) < 2;

        return match ($context['hints']['intent_guess'] ?? 'unknown') {
            self::ACTION_CREATE_EVENT => (! $titleDetected && ! $hasDate)
                ? 'Agar saya bisa membuat draft event, mohon sebutkan judul dan tanggal event. Contoh: "jadwalkan nikahan saya tanggal 21 maret 2026".'
                : (! $titleDetected
                    ? 'Tanggal event sudah terbaca. Mohon sebutkan judul eventnya. Contoh: "jadwalkan tanggal 21 maret 2026 nikahan saya".'
                    : (! $hasDate
                        ? 'Judul event sudah terbaca. Mohon sebutkan tanggal eventnya. Contoh: "jadwalkan nikahan saya tanggal 21 maret 2026".'
                        : ($needsTimeRange
                            ? 'Saya butuh jam mulai dan jam selesai agar tidak ambigu. Contoh: "jadwalkan nikahan saya 21 maret 2026 jam 09:00 sampai 10:00".'
                            : 'Perintah event Anda belum cukup jelas. Mohon kirim ulang dengan format natural yang lebih lengkap.'))),
            self::ACTION_UPDATE_EVENT => 'Agar saya bisa mengubah event, mohon sebutkan judul event yang ingin diubah serta tanggal dan jam baru. Contoh: "ubah event rapat skripsi ke 22 maret 2026 jam 10:00 sampai 11:00".',
            self::ACTION_DELETE_EVENT => 'Agar saya bisa menghapus event, mohon sebutkan judul event yang ingin dihapus. Contoh: "hapus event rapat skripsi".',
            self::ACTION_CREATE_TODO_LIST => 'Agar saya bisa membuat todo list, mohon sebutkan judul list dan minimal satu item. Contoh: "buat todo minggu ini, revisi bab 1".',
            default => 'Perintah Anda belum cukup jelas. Tolong jelaskan aksi yang diinginkan berikut detail utamanya. Contoh: "jadwalkan rapat 20 maret 2026 jam 09:00 sampai 10:00".',
        };
    }

    private function withMeta(array $result, array $meta): array
    {
        $result['meta'] = $meta;

        return $result;
    }

    private function logFailure(string $stage, string $failureReason, string $actionGuess, array $extra = []): void
    {
        Log::warning('Cerebras parse attempt failed.', array_merge([
            'parse_stage' => $stage,
            'failure_reason' => $failureReason,
            'action_guess' => $actionGuess,
        ], $extra));
    }

    private function truncateBodyForLog(Response $response): string
    {
        $body = trim((string) $response->body());

        return $body === '' ? '' : mb_substr($body, 0, 300);
    }

    private function extractResponseContentSnippet(Response $response): string
    {
        $content = data_get($response->json(), 'choices.0.message.content');

        if (is_string($content)) {
            return mb_substr(trim($content), 0, 300);
        }

        if (is_array($content)) {
            return mb_substr(json_encode($content, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);
        }

        return '';
    }
}
