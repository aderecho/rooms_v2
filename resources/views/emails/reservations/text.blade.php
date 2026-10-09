{!! $organization !!} — Room Reservation System

{!! $heading !!}

Hello{!! $status === 'Pending' ? '' : ' '.$studentName !!},
{!! $intro !!}
Status: {!! $status !!}

@foreach($details as $label => $value)
{!! $label !!}: {!! $value ?? 'Not specified' !!}
@endforeach

@if($reason)
Reason for the decision: {!! $reason !!}
@endif
@if($instructions)
Reservation reminders: {!! $instructions !!}
@endif

{!! $actionLabel !!}: {!! $actionUrl !!}
Sign in to the portal to access the request.

@if($contactEmail)
Questions? Contact {!! $contactEmail !!}.
@endif
This is an automated notification from {!! $organization !!}. Please use the reservation portal to review or manage your request.
