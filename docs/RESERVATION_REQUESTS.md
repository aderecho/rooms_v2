# Reservation dashboard and email notifications

The existing Laravel 12 / Vue 3 / Inertia reservation workflow now includes combined search, status/reservation-date/submission-date filters, 10/25/50/100 server-side pagination, pending-first/newest-first ordering, result ranges, a compact table with keyboard-accessible expandable rows, complete detail records, confirmation for both decisions, and disabled controls during processing.

## Permissions and data

Active administrators retain access to all administrative requests and counts. Existing rules do not designate a separate approver role, so this implementation does not grant new approval permissions to faculty or staff. Students can only access their own requests. Decisions are limited to pending requests, authorized at the controller and service, and recorded using the authenticated user's identity.

The user schema has no dedicated student-number column. Search includes existing username/account identifier, employee identifier, student first/last/full name and email, room name/code, building name, and purpose. The interface labels the username as **Account identifier** rather than inventing a student number. Details include college, department, contact number and room location when available.

Approval locks the request and room, rechecks operating hours, date, capacity and approved/in-progress schedule conflicts, and atomically creates the schedule, decision, history and mail record. Overlapping approvals use the existing room lock; adjacent time ranges are allowed. Standard decisions cannot modify finalized requests. Histories are recorded for new submissions and decisions; older requests retain their existing decision fields without fabricated audit entries.

## Migration

```bash
php artisan migrate
# For a production deployment:
php artisan migrate --force
```

`2026_10_09_000003_add_reservation_history_and_mail_deliveries.php` adds status/submission/date indexes, `reservation_status_histories`, and `reservation_mail_deliveries`. It reuses existing approver, approved/rejected timestamps, rejection message and schedule columns. Existing reservation rows are unchanged. Rolling this migration back removes the new history/delivery records and indexes; existing reservation and decision records remain. Back up audit data before rollback.

The earlier `000001`/`000002` room visibility migrations also run if pending; the second intentionally sets all existing rooms private.

## Mail configuration

The existing mail transport is reused. `.env.example` defaults to `MAIL_MAILER=log`, which does not send external mail. Tests use `array`; no SMTP credentials or real recipients are required for tests.

Production example (set actual values privately):

```dotenv
APP_URL=https://your-room-portal.example
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=your-approved-sender@example.edu
MAIL_FROM_NAME="UP Cebu Room Reservations"
MAIL_TIMEOUT=30
QUEUE_CONNECTION=database
RESERVATION_ORGANIZATION="University of the Philippines Cebu"
RESERVATION_PORTAL_URL="${APP_URL}"
RESERVATION_LOGO_URL=
RESERVATION_CONTACT_EMAIL=your-office@example.edu
RESERVATION_APPROVAL_INSTRUCTIONS="Please follow the office's room-use guidelines."
RESERVATION_NOTIFICATION_RECIPIENTS=
RESERVATION_MAIL_TRIES=3
RESERVATION_MAIL_BACKOFF=60,300,900
```

Use the SMTP scheme/port required by your provider (`smtps` for implicit TLS). Production action URLs require an HTTPS portal URL. The logo defaults to `/image/uplogo.png` under the portal URL; an invalid/non-HTTPS optional logo is omitted in production. Additional recipients are optional comma-separated office addresses, deduplicated against active administrators; invalid addresses are skipped and logged without revealing their values. Ensure staff recipients are authorized to review reservation data.

New requests notify active administrators and additional configured staff addresses. Decisions notify the requesting student's saved email address. Account email is validated on submission. Existing records with invalid recipient addresses log a warning; correct the account before processing where email is needed. Emails use actual reservation values, a shared green/white table-based HTML layout, and a plain-text alternative. User-supplied values are escaped in HTML. Portal links require normal authentication and contain no login tokens.

After configuration:

```bash
php artisan config:cache
php artisan queue:restart
php artisan queue:work database --queue=mail,realtime,default --sleep=3 --tries=3 --timeout=60
```

Use the supplied Supervisor configuration (`deploy/supervisor/cebu-rooms.conf`) for a persistent worker. Job retry count/backoff come from `RESERVATION_MAIL_TRIES` and `RESERVATION_MAIL_BACKOFF`; mail timeout is 30 seconds by default, notification/worker timeout 60, and database `retry_after` 90. Keep worker timeout below queue retry-after when customizing these values. Cache must support atomic locks (existing database/Redis caches do); the same delivery record cannot be sent concurrently by normal queued workers.

Run Laravel's scheduler every minute, using the existing server account/project path:

```cron
* * * * * cd /path/to/cebu_rooms2 && php artisan schedule:run >> /dev/null 2>&1
```

## Recovery and diagnostics

Mail intent is persisted in the same transaction as the request/decision. Queue publication occurs only after the outermost commit. A queue failure leaves a pending record for the scheduled recovery command and does not reverse the decision. Successful transport acceptance marks the record sent; exhausted job failures mark it failed. Details show delivery event/status/attempt count to administrators. Logs include record IDs, event and exception class; they do not persist exception messages that might expose SMTP credentials.

```bash
# Scheduled automatically: publish pending records
php artisan reservations:retry-mail
# Retry exhausted deliveries without changing decisions
php artisan reservations:retry-mail --failed
# Recover queued records lost/stalled for over an hour (minimum 10 minutes)
php artisan reservations:retry-mail --stale=60
# Inspect Laravel failed jobs
php artisan queue:failed
```

Use the reservation retry command as the normal recovery path; do not retry both an old failed job and its replacement unnecessarily. Sent records are skipped. The event/recipient unique key, atomic queue-publication claim, worker lock, and sent check prevent ordinary duplicate emails/decisions. SMTP cannot guarantee exactly-once delivery if a worker crashes after the provider accepts a message but before the database records success; inspect provider logs before recovering an ambiguous stale delivery. “Sent” means the configured transport accepted the message, not proof of inbox delivery (the log/array transport also accepts messages).

## Tests and build

```bash
php artisan test --filter='ReservationDashboardEnhancementTest|ReservationRequestWorkflowTest|ReservationMailDeliveryTest'
php artisan test
npm run build
```

New tests cover pagination/ranges/filter preservation, combined filtering/search, access controls, immutable decisions, conflict/availability/capacity checks, history, after-commit/rollback behavior, publication failures and recovery, failed-job retries, duplicate recipient/send suppression, branded HTML escaping and plain-text rendering. Transactional mail tests use a fresh SQLite test database and real commits with safe `array` mail. Production MySQL lock races, actual SMTP delivery and individual Gmail/Outlook/Apple Mail client rendering require deployment validation.

## Files created or modified for this enhancement

- `.env.example`
- `app/Http/Controllers/AdminReservationRequestController.php`
- `app/Http/Controllers/StudentReservationController.php`
- `app/Http/Requests/StoreReservationRequest.php`
- `app/Http/Resources/ReservationRequestResource.php`
- `app/Models/ReservationRequest.php`
- `app/Models/ReservationStatusHistory.php` (new)
- `app/Models/ReservationMailDelivery.php` (new)
- `app/Notifications/ReservationRequestMailNotification.php`
- `app/Policies/ReservationRequestPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/ReservationRequestService.php`
- `app/Services/ReservationNotificationService.php`
- `config/mail.php`
- `config/reservations.php` (new)
- `database/migrations/2026_10_09_000003_add_reservation_history_and_mail_deliveries.php` (new)
- `deploy/supervisor/cebu-rooms.conf`
- `resources/js/Pages/ReservationRequests.vue`
- `resources/js/Pages/ReservationRequestDetail.vue`
- `resources/views/emails/reservations/layout.blade.php` (new)
- `resources/views/emails/reservations/message.blade.php` (new)
- `resources/views/emails/reservations/text.blade.php` (new)
- `routes/console.php`
- `tests/Feature/ReservationRequestWorkflowTest.php`
- `tests/Feature/ReservationDashboardEnhancementTest.php` (new)
- `tests/Unit/ReservationMailDeliveryTest.php` (new)
- `docs/RESERVATION_REQUESTS.md` (new)

Earlier room visibility and panel-color changes remain in the worktree and are separate from this enhancement.

## Verification results (October 9, 2026)

- Focused reservation/mail/room/API/realtime regression run: **83 passed, 639 assertions**.
- Full suite, with `RANDFILE=/tmp/cebu-reservations-openssl.rnd php artisan test`: **121 passed, 23 failed**. The remaining failures are 21 existing authentication/profile/example tests (primarily the missing `App\Models\User::factory()` and outdated authentication expectations) and two unrelated session-expiration tests whose random factory role selected a student and therefore received the existing redirect to My Reservations. These unrelated application/tests were not rewritten. An earlier full run had the same 21 legacy failures without the random-role session failures.
- `npm run build`: passed. Pint validation of all 19 changed PHP files for this enhancement: passed. `git diff --check`: passed.
- All migrations applied successfully to a temporary SQLite QA database; transactional mail tests also exercise migration rollback. No migrations were applied to the configured MySQL database.
- Browser QA used `http://localhost:8003` with temporary SQLite fixtures, safe `array` mail and a dedicated session cookie. The temporary server was stopped after testing. QA authentication routes existed only in the external temporary test harness, not in application routes or source.
- Desktop (1440 × 1000) and mobile (390 × 844) dashboard checks passed for page identity/content, no framework overlay, and no horizontal overflow on mobile. The reference green palette, white rounded cards and status indicators are retained; the search field spans two desktop columns.
- Verified interactions: status-card filtering; building search followed by page 2 preserving its search term and showing the correct range; Enter on a request card opening details; approval confirmation followed by a recorded approval and refreshed counts; rejection confirmation followed by recorded reason/reviewer/history, a queued student notification, and removal of finalized decision actions. The dashboard's nested rejection confirmation and disabled-submit state were also inspected.
- The shared HTML email was rendered at mobile width with the logo, heading, details table and review button. Automated tests render all three events and both HTML and plain text; actual Gmail/Outlook/Apple Mail client rendering and SMTP inbox delivery were not tested.
- Browser integration used its degraded single-tab debug fallback because the proposed multi-tab API was unavailable. Initial temporary-harness routing errors were resolved; no relevant console exceptions occurred during the successful dashboard/decision flows.

The optional `RANDFILE` setting directs the existing OpenSSL/SAML test fixture's random-state file into a writable temporary directory in restricted test environments. It is not a production mail setting.

Room booking availability follows the selected date and time, not the legacy rooms.status field. Overlapping approved/in-progress schedules and pending/approved reservation requests block student submissions. Legacy available, occupied, maintenance and closed values do not independently block bookings.

Pending schedules do not block student reservations until approved. Pending student reservation requests still block overlapping student requests.

Approving a schedule or student reservation automatically rejects overlapping pending schedules and student reservation requests for the same room and date. An already approved/in-progress schedule retains the slot; a competing pending schedule is rejected. Adjacent time slots remain eligible. Student automatic rejections include decision history and rejection notifications.
