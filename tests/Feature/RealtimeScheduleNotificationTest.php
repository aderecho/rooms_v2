<?php

use App\Events\ScheduleNotificationCreated;
use App\Models\ScheduleNotification;
use App\Models\UserAccount;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

function realtimeNotificationUser(string $suffix): UserAccount
{
    return UserAccount::create([
        'username' => "realtime-{$suffix}",
        'email' => "realtime-{$suffix}@example.test",
        'password' => Hash::make('password'),
        'first_name' => 'Realtime',
        'last_name' => ucfirst($suffix),
        'user_type' => 'student',
        'account_status' => 'active',
    ]);
}

function useReverbBroadcaster(): void
{
    config()->set('broadcasting.default', 'reverb');
    Broadcast::purge();
    Broadcast::channel('users.{id}', fn (UserAccount $user, int $id): bool => $user->id === $id);
}

it('broadcasts a newly stored notification only to its recipient channel', function () {
    Event::fake([ScheduleNotificationCreated::class]);
    $recipient = realtimeNotificationUser('recipient');

    $notification = ScheduleNotification::create([
        'user_id' => $recipient->id,
        'type' => 'reservation_approved',
        'title' => 'Reservation approved',
        'message' => 'Your reservation was approved.',
    ]);

    Event::assertDispatched(
        ScheduleNotificationCreated::class,
        fn (ScheduleNotificationCreated $event) => $event->notification->is($notification)
            && $event->broadcastAs() === 'schedule-notification.created'
            && $event->broadcastQueue() === 'realtime'
            && $event->broadcastOn()[0] instanceof PrivateChannel
            && $event->broadcastOn()[0]->name === "private-users.{$recipient->id}",
    );
});

it('authorizes the signed-in owner of a private notification channel', function () {
    useReverbBroadcaster();
    $owner = realtimeNotificationUser('owner');

    $this->actingAs($owner)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$owner->id}",
        ])
        ->assertOk();
});

it('rejects a different user from a private notification channel', function () {
    useReverbBroadcaster();
    $owner = realtimeNotificationUser('owner');
    $other = realtimeNotificationUser('other');

    $this->actingAs($other)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$owner->id}",
        ])
        ->assertForbidden();
});
