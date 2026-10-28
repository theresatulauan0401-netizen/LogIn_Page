# Testing

## A. Automated tests (PHPUnit)
```
composer install
# create an EMPTY MySQL user/db access: the tests drop & recreate "bus_reservation_test" themselves
vendor\bin\phpunit            (Windows)   |   vendor/bin/phpunit
```
* `CoreTest` needs no database (validator, CSRF, permissions, escalation rules, seat layout).
* `BookingServiceTest`, `PaymentTicketTest`, `ScheduleServiceTest` need MySQL and use database
  `bus_reservation_test` (name set in `phpunit.xml`, **must contain "test"**; it is dropped and rebuilt
  from `database/database.sql` before each test). If MySQL is unreachable they are *skipped*, not failed.
* These tests were written but **have not been executed by the author**. Run them and report failures.

## B. Manual checklist (browser). Tick each after you try it.
| # | Area | Steps | Expected |
|---|------|-------|----------|
| 1 | Login | Correct e-mail + password | Dashboard opens |
| 2 | Login | Wrong password / unknown e-mail | Same generic message both times |
| 3 | Throttle | 6 wrong passwords for one e-mail | "Too many failed attempts" even with the right password, until ~15 min pass |
| 4 | Disabled user | Deactivate a staff user, then use their open session | Next click sends them to login |
| 5 | Direct access | Log out, open `/dashboard`, `/buses`, `/payments/1` | Redirect to login |
| 6 | RBAC | Log in as Booking Staff, open `/buses/create`, `/users`, `/settings` (POST), `/payments/1/refund` (POST) | 403 page |
| 7 | RBAC | Admin tries to edit a Super Admin (`/users/<id>/edit`) | 403 |
| 8 | Escalation | Admin creates a user: role list only offers Booking Staff; tampering the role field to `super_admin` | Rejected |
| 9 | Last Super Admin | Try to deactivate / demote the only Super Admin | Refused |
| 10 | Buses | Add, edit, search, filter, deactivate a bus | Works; bus with open trips cannot be deactivated |
| 11 | Routes | Add, edit, deactivate | Code RT-00x generated; origin = destination rejected |
| 12 | Schedules | Create trip; create overlapping trip for same bus | Overlap refused with the clashing trip named |
| 13 | Schedules | Departure in the past / arrival before departure / fare -1 | Rejected |
| 14 | Booking | New booking: route -> trip -> seat -> passenger | Pending booking, seat count -1 |
| 15 | Double booking | Open the seat picker in two tabs, book the same seat in both | Second one fails with "seat just taken" |
| 16 | Cancelled trip | Cancel a trip without bookings, then try to book it | Not listed / rejected |
| 17 | Payment | Record cash payment with wrong amount | Rejected |
| 18 | Payment | Record successful cash payment (tick verify) | Booking confirmed, ticket issued once |
| 19 | Payment | Bank transfer without reference / with a 16-digit number | Rejected |
| 20 | Ticket | Print ticket; mark used; mark used again | Second time refused |
| 21 | Cancel | Cancel a confirmed booking with refund (as admin) | Seat freed, ticket cancelled, payment refunded, log entry |
| 22 | Cutoff | As Booking Staff cancel a booking whose trip leaves within the cutoff | Refused; admin can do it |
| 23 | Reports | Run each report, change dates, export CSV | Totals match the tables; CSV opens in Excel |
| 24 | CSRF | In dev tools delete the hidden `_token` field and submit any form | 419 page, nothing saved |
| 25 | XSS | Create a passenger named `<script>alert(1)</script>` | Shown as text everywhere |
| 26 | GET safety | Open `/logout`, `/buses/1/status` by URL | 405 (POST only) |
| 27 | Errors | Stop MySQL and reload | Generic error, no credentials/stack trace; details in `storage/logs` |
| 28 | Settings | Super Admin changes company name/currency; Admin opens page | Super Admin saves; Admin sees read-only |
