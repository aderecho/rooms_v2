<?php

namespace App\Policies;

use App\Models\ReservationRequest;
use App\Models\UserAccount;

class ReservationRequestPolicy
{
    public function viewAny(UserAccount $user): bool
    {
        return $user->user_type === 'admin';
    }

    public function view(UserAccount $user, ReservationRequest $reservationRequest): bool
    {
        return $user->user_type === 'admin' || $reservationRequest->student_id === $user->id;
    }

    public function create(UserAccount $user): bool
    {
        return $user->user_type === 'student' && $user->account_status === 'active';
    }

    public function approve(UserAccount $user, ReservationRequest $reservationRequest): bool
    {
        return $user->user_type === 'admin'
            && $reservationRequest->status === ReservationRequest::STATUS_PENDING;
    }

    public function reject(UserAccount $user, ReservationRequest $reservationRequest): bool
    {
        return $this->approve($user, $reservationRequest);
    }
}
