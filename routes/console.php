<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:retry-mail {--failed : Retry exhausted mail deliveries} {--stale= : Recover queued deliveries older than this many minutes (minimum 10)}', function () {
    $stale = $this->option('stale');
    if ($stale !== null && (! ctype_digit((string) $stale) || (int) $stale < 10)) {
        $this->error('--stale must be at least 10 minutes.');

        return 1;
    }
    $query = \App\Models\ReservationMailDelivery::query()->where(function ($query) use ($stale) {
        $query->where('status', 'pending');
        if ($this->option('failed')) {
            $query->orWhere('status', 'failed');
        }
        if ($stale !== null) {
            $query->orWhere(fn ($q) => $q->where('status', 'queued')->where('queued_at', '<', now()->subMinutes((int) $stale)));
        }
    });
    $count = 0;
    $query->chunkById(100, function ($deliveries) use (&$count) {
        foreach ($deliveries as $delivery) {
            if ($delivery->status === 'pending') {
                app(\App\Services\ReservationNotificationService::class)->dispatchDelivery($delivery);
                $count++;

                continue;
            }
            $reset = \App\Models\ReservationMailDelivery::whereKey($delivery->id)
                ->where('status', $delivery->status)->where('updated_at', $delivery->updated_at)
                ->update(['status' => 'pending']);
            if ($reset) {
                app(\App\Services\ReservationNotificationService::class)->dispatchDelivery($delivery);
                $count++;
            }
        }
    });
    $this->info("Processed {$count} mail delivery records. Inspect their statuses for dispatch results.");
})->purpose('Publish pending reservation emails or retry failed deliveries without repeating decisions');

\Illuminate\Support\Facades\Schedule::command('reservations:retry-mail')->everyMinute()->withoutOverlapping();
