# Architecture & Database Overview

## Request flow (layered)
```
Browser -> public/index.php -> Router -> Middleware (auth / role / csrf)
        -> Controller  (validate input, authorize, choose response)
        -> Service     (business rules, transactions, audit logging)
        -> Repository  (PDO prepared statements only)  <-> MySQL
        -> Model       (plain entity objects)
        -> View        (escaped output only; no SQL, no business rules)
```
Namespaces: `App\Core`, `App\Models`, `App\Repositories`, `App\Services`,
`App\Controllers`, `App\Middleware` (PSR-4, folder `app/`). Dependencies are injected
through `App\Core\Container` (constructor auto-wiring).

## Relationships
| Relationship | Delete rule |
|---|---|
| users 1—N bookings (created_by) | RESTRICT |
| users 1—N payments (received_by, refunded_by) | RESTRICT |
| users 1—N booking_status_logs (changed_by), activity_logs, system_settings.updated_by, tickets.used_by | RESTRICT |
| buses 1—N schedules | RESTRICT |
| routes 1—N schedules | RESTRICT |
| schedules 1—N bookings | RESTRICT |
| passengers 1—N bookings | RESTRICT |
| bookings 1—N payments | RESTRICT |
| bookings 1—0..1 tickets (UNIQUE booking_id) | RESTRICT |
| bookings 1—N booking_status_logs | RESTRICT |

Nothing with transaction history cascades. Records are deactivated (`status`), never
deleted; bookings/payments/tickets are never deleted.

## ERD
```mermaid
erDiagram
    users ||--o{ bookings : "created_by"
    users ||--o{ payments : "received_by / refunded_by"
    users ||--o{ booking_status_logs : "changed_by"
    users ||--o{ activity_logs : "performs"
    users ||--o{ system_settings : "updated_by"
    users ||--o{ tickets : "used_by"
    buses ||--o{ schedules : "runs"
    routes ||--o{ schedules : "defines"
    schedules ||--o{ bookings : "has"
    passengers ||--o{ bookings : "makes"
    bookings ||--o{ payments : "paid by"
    bookings ||--o| tickets : "issues"
    bookings ||--o{ booking_status_logs : "history"

    users { bigint id PK  string email UK  string role  string status }
    buses { bigint id PK  string bus_number UK  string plate_number UK  smallint capacity  string status }
    routes { bigint id PK  string route_code UK  string origin  string destination  decimal base_fare }
    schedules { bigint id PK  string schedule_code UK  bigint bus_id FK  bigint route_id FK  datetime departure_datetime  datetime arrival_datetime  decimal fare  smallint available_seats }
    passengers { bigint id PK  string passenger_code UK  string phone }
    bookings { bigint id PK  string booking_reference UK  bigint passenger_id FK  bigint schedule_id FK  string seat_number  decimal total_amount  string status }
    payments { bigint id PK  string payment_reference UK  bigint booking_id FK  decimal amount  string payment_status }
    tickets { bigint id PK  string ticket_number UK  bigint booking_id FK_UK  string ticket_status }
    booking_status_logs { bigint id PK  bigint booking_id FK  string old_status  string new_status }
    activity_logs { bigint id PK  bigint user_id FK  string action  string module }
    system_settings { bigint id PK  string setting_key UK }
    login_attempts { bigint id PK  string email  string ip_address  datetime attempted_at }
```

## Deliberate deviations / additions to the spec (all documented in database.sql)
1. **Seat uniqueness** uses `UNIQUE(schedule_id, seat_number, active_seat_flag)`, where
   `active_seat_flag` is a generated column that is NULL for cancelled/no_show bookings.
   Result: no double booking at DB level, yet cancelled history is kept and the seat can be resold.
2. **payments**: generated keys enforce *one successful payment per booking* and a
   *successful transaction reference only once*; added `refunded_at/by/reason` for the refund audit trail.
3. **tickets**: added `used_at`, `used_by`.
4. **passengers**: added `status` so referenced passengers are deactivated, not deleted.
5. **login_attempts** table added to support the required login throttling.
6. Rules that span tables (no overlapping trips for a bus; `available_seats <= capacity`;
   status transitions) live in the services, executed inside transactions with `SELECT ... FOR UPDATE`.
7. Demo rows have `created_by/received_by/changed_by = NULL` because no user exists until
   `scripts/create_admin.php` is run (no credentials are ever stored in SQL).

## Demo data consistency (verified by hand against the SQL)
| Booking | Status | Payment | Ticket | Seat held? |
|---|---|---|---|---|
| 1,2,4,7 | confirmed | successful (= total) | valid | yes |
| 3 | pending | pending | none | yes |
| 6 | pending | failed | none | yes |
| 5 | cancelled | failed | none | no |
| 8 | cancelled | refunded | cancelled | no |

Revenue from successful payments = 500 + 500 + 350 + 500 = **₱1,850.00**.
