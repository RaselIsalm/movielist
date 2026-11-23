<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelemetryController extends Controller
{
    private const EVENTS = [
        'page_view',
        'video_start',
        'video_complete',
        'ad_impression',
        'ad_close',
        'ad_load_error',
        'player_error',
    ];

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'events' => ['required', 'array', 'max:20'],
            'events.*.event' => ['required', 'string', 'in:' . implode(',', self::EVENTS)],
            'events.*.session_id' => ['required', 'string', 'max:100'],
            'events.*.placement' => ['nullable', 'string', 'max:80'],
            'events.*.content_id' => ['nullable', 'string', 'max:80'],
            'events.*.ad_unit' => ['nullable', 'string', 'max:80'],
            'events.*.viewport' => ['nullable', 'string', 'max:30'],
            'events.*.consent' => ['nullable', 'boolean'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);

        $now = now();
        $rows = [];
        foreach ($payload['events'] as $event) {
            $rows[] = [
                'event_name' => $event['event'],
                'session_hash' => hash('sha256', $event['session_id']),
                'content_id' => $event['content_id'] ?? null,
                'placement' => $event['placement'] ?? null,
                'ad_unit' => $event['ad_unit'] ?? null,
                'viewport' => $event['viewport'] ?? null,
                'consent_given' => array_key_exists('consent', $event)
                    ? (bool) $event['consent']
                    : null,
                'occurred_at' => $event['occurred_at'] ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('telemetry_events')->insert($rows);
        }

        return response()->json(['status' => 200, 'accepted' => count($rows)]);
    }
}
