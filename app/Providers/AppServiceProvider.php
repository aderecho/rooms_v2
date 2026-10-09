<?php

namespace App\Providers;

use App\Models\ReservationRequest;
use App\Policies\ReservationRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ReservationRequest::class, ReservationRequestPolicy::class);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($event) {
            $notification = $event->notification;
            if ($notification instanceof \App\Notifications\ReservationRequestMailNotification && $notification->deliveryId && $event->channel === 'mail') {
                \App\Models\ReservationMailDelivery::whereKey($notification->deliveryId)->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                \Illuminate\Support\Facades\Log::info('Reservation email accepted by mail transport.', ['delivery_id' => $notification->deliveryId]);
            }
        });
        Vite::prefetch(concurrency: 3);
    }
}
