@extends('emails.reservations.layout')
@section('content')
<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#163c2f;">{{ $heading }}</h1>
<p style="margin:0 0 12px;">Hello{{ $status === 'Pending' ? '' : ' '.$studentName }},</p>
<p style="margin:0 0 16px;">{{ $intro }}</p>
@php($statusColor = $status === 'Approved' ? '#166534' : ($status === 'Rejected' ? '#991b1b' : '#92400e'))
<p style="margin:0 0 16px;font-weight:bold;color:{{ $statusColor }};">Status: {{ $status }}</p>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
@foreach($details as $label => $value)
<tr><th scope="row" width="35%" align="left" valign="top" style="padding:10px 8px;border-bottom:1px solid #e2e8f0;background:#f8fafc;color:#475569;font-weight:bold;">{{ $label }}</th><td valign="top" style="padding:10px 8px;border-bottom:1px solid #e2e8f0;overflow-wrap:anywhere;word-break:break-word;">{{ $value ?? 'Not specified' }}</td></tr>
@endforeach
</table>
@if($reason)<h2 style="font-size:18px;margin:24px 0 8px;">Reason for the decision</h2><p style="margin:0;white-space:pre-line;">{{ $reason }}</p>@endif
@if($instructions)<h2 style="font-size:18px;margin:24px 0 8px;">Reservation reminders</h2><p style="margin:0;white-space:pre-line;">{{ $instructions }}</p>@endif
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;"><tr><td bgcolor="#005740" style="border-radius:6px;"><a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 20px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;">{{ $actionLabel }}</a></td></tr></table>
<p style="font-size:13px;color:#475569;">Sign in to the portal to access the request. If the button does not work, open:<br><a href="{{ $actionUrl }}" style="color:#005740;word-break:break-all;">{{ $actionUrl }}</a></p>
@endsection
