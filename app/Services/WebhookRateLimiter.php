<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WebhookRateLimiter
{
    public function acquire(string $host, int $limit): int
    {
        $limit = max(1, min($limit, (int) config('webhooks.maximum_rate_limit', 30)));

        return DB::transaction(function () use ($host, $limit) {
            $now = Carbon::now('America/Hermosillo');
            $row = DB::table('webhook_rate_limits')->where('host', $host)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('webhook_rate_limits')->insert([
                    'host' => $host,
                    'window_started_at' => $now,
                    'request_count' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return 0;
            }

            $windowStartedAt = Carbon::parse($row->window_started_at);
            $elapsed = $windowStartedAt->diffInSeconds($now, false);

            if ($elapsed >= 60 || $elapsed < 0) {
                DB::table('webhook_rate_limits')->where('host', $host)->update([
                    'window_started_at' => $now,
                    'request_count' => 1,
                    'updated_at' => $now,
                ]);

                return 0;
            }

            if ((int) $row->request_count >= $limit) {
                return max(1, 60 - $elapsed);
            }

            DB::table('webhook_rate_limits')->where('host', $host)->update([
                'request_count' => (int) $row->request_count + 1,
                'updated_at' => $now,
            ]);

            return 0;
        }, 3);
    }
}
