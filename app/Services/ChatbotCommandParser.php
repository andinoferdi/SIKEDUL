<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ChatbotCommandParser
{
    public function parse(string $command, string $timezone): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $command) ?? '');
        $lower = Str::lower($normalized);

        if (Str::startsWith($lower, 'buat event')) {
            return $this->parseCreateEvent($normalized, $timezone);
        }

        if (Str::startsWith($lower, 'ubah event')) {
            return $this->parseUpdateEvent($normalized, $timezone);
        }

        if (Str::startsWith($lower, 'hapus event')) {
            return $this->parseDeleteEvent($normalized);
        }

        if (Str::startsWith($lower, 'buat todo')) {
            return $this->parseCreateTodo($normalized);
        }

        return [
            'ok' => false,
            'question' => 'Perintah belum dikenali. Contoh: "buat event 10 januari 09.00 sampai 10.00 judul rapat skripsi".',
        ];
    }

    private function parseCreateEvent(string $command, string $timezone): array
    {
        $pattern = '/^buat event\s+(.+?)\s+(\d{1,2}[.:]\d{2})\s+sampai\s+(\d{1,2}[.:]\d{2})\s+judul\s+(.+?)(?:\s+kategori\s+(.+?))?(?:\s+ingatkan\s+(\d+)\s+menit)?$/i';
        if (! preg_match($pattern, $command, $m)) {
            return [
                'ok' => false,
                'question' => 'Format kurang lengkap. Tolong isi tanggal, jam mulai-selesai, dan judul event.',
            ];
        }

        try {
            $start = $this->parseDateTime($m[1], $m[2], $timezone);
            $end = $this->parseDateTime($m[1], $m[3], $timezone);
        } catch (\Throwable) {
            return [
                'ok' => false,
                'question' => 'Format tanggal/jam belum valid. Contoh tanggal: 10 januari 09.00 sampai 10.00.',
            ];
        }

        return [
            'ok' => true,
            'action_type' => 'create_event',
            'payload' => [
                'title' => trim($m[4]),
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $end->format('Y-m-d H:i:s'),
                'category_name' => isset($m[5]) ? trim($m[5]) : null,
                'reminder_minutes' => isset($m[6]) ? (int) $m[6] : null,
            ],
            'preview' => [
                'title' => trim($m[4]),
                'start' => $start->format('d M Y H:i'),
                'end' => $end->format('d M Y H:i'),
                'category' => isset($m[5]) ? trim($m[5]) : '-',
                'reminder' => isset($m[6]) ? "{$m[6]} menit" : '-',
            ],
        ];
    }

    private function parseUpdateEvent(string $command, string $timezone): array
    {
        $pattern = '/^ubah event\s+(.+?)\s+jadi\s+(.+?)\s+(\d{1,2}[.:]\d{2})\s+sampai\s+(\d{1,2}[.:]\d{2})$/i';
        if (! preg_match($pattern, $command, $m)) {
            return [
                'ok' => false,
                'question' => 'Format kurang lengkap. Contoh: ubah event rapat skripsi jadi 10 januari 10.00 sampai 11.00',
            ];
        }

        try {
            $start = $this->parseDateTime($m[2], $m[3], $timezone);
            $end = $this->parseDateTime($m[2], $m[4], $timezone);
        } catch (\Throwable) {
            return [
                'ok' => false,
                'question' => 'Format tanggal/jam update belum valid.',
            ];
        }

        return [
            'ok' => true,
            'action_type' => 'update_event',
            'payload' => [
                'target_title' => trim($m[1]),
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $end->format('Y-m-d H:i:s'),
            ],
            'preview' => [
                'target' => trim($m[1]),
                'start' => $start->format('d M Y H:i'),
                'end' => $end->format('d M Y H:i'),
            ],
        ];
    }

    private function parseDeleteEvent(string $command): array
    {
        $pattern = '/^hapus event\s+(.+)$/i';
        if (! preg_match($pattern, $command, $m)) {
            return [
                'ok' => false,
                'question' => 'Sebutkan judul event yang ingin dihapus.',
            ];
        }

        return [
            'ok' => true,
            'action_type' => 'delete_event',
            'payload' => [
                'target_title' => trim($m[1]),
            ],
            'preview' => [
                'target' => trim($m[1]),
            ],
        ];
    }

    private function parseCreateTodo(string $command): array
    {
        $raw = trim(preg_replace('/^buat todo\s+/i', '', $command) ?? '');
        $segments = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if (count($segments) < 2) {
            return [
                'ok' => false,
                'question' => 'Untuk buat todo, isi judul list lalu minimal 1 item. Contoh: buat todo minggu ini, revisi bab 1',
            ];
        }

        $listTitle = array_shift($segments);

        return [
            'ok' => true,
            'action_type' => 'create_todo_list',
            'payload' => [
                'title' => $listTitle,
                'items' => $segments,
            ],
            'preview' => [
                'title' => $listTitle,
                'items' => $segments,
            ],
        ];
    }

    private function parseDateTime(string $datePart, string $timePart, string $timezone): Carbon
    {
        $time = str_replace('.', ':', trim($timePart));
        $tokens = preg_split('/\s+/', Str::lower(trim($datePart))) ?: [];

        if (count($tokens) < 2) {
            throw new \InvalidArgumentException('Invalid date token.');
        }

        $day = (int) $tokens[0];
        $month = $this->monthToNumber($tokens[1]);
        $year = isset($tokens[2]) ? (int) $tokens[2] : Carbon::now($timezone)->year;

        if ($day <= 0 || $month <= 0) {
            throw new \InvalidArgumentException('Invalid date value.');
        }

        return Carbon::createFromFormat(
            'Y-n-j H:i',
            "{$year}-{$month}-{$day} {$time}",
            $timezone
        );
    }

    private function monthToNumber(string $month): int
    {
        $map = [
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

        return $map[$month] ?? 0;
    }
}

