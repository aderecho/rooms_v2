<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $heading }}</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;color:#1e293b;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;"><tr><td align="center" style="padding:24px 12px;">
<!--[if mso]><table role="presentation" width="600"><tr><td><![endif]-->
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;">
<tr><td style="padding:24px;background:#005740;color:#ffffff;">
@if($logoUrl)<img src="{{ $logoUrl }}" width="56" height="56" alt="University logo" style="display:block;border:0;margin-bottom:12px;">@endif
<strong style="font-size:18px;">{{ $organization }}</strong><br><span style="font-size:14px;">Room Reservation System</span>
</td></tr><tr><td style="padding:24px;">@yield('content')</td></tr>
<tr><td style="padding:20px 24px;border-top:1px solid #e2e8f0;color:#475569;font-size:13px;">
{{ $organization }}<br>
@if($contactEmail)Questions? Contact <a href="mailto:{{ $contactEmail }}" style="color:#005740;">{{ $contactEmail }}</a>.<br>@endif
This is an automated notification. Please use the reservation portal to review or manage your request.
</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table></body></html>
