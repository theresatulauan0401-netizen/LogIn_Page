# Bus Reservation Management System
Native **PHP 8.2+ OOP** + **MySQL/MariaDB** (PDO) + Bootstrap 5 + Chart.js. No framework. Runs on XAMPP.

> **Status:** the code was written without being run (the author had no PHP/MySQL available). Follow
> `docs/TESTING.md`, and fix/report anything that breaks on your machine. Nothing here claims to be tested.

* Admin dashboard, buses, routes, schedules, passengers, bookings (seat map), payments (recorded, not a gateway),
  tickets (print / validate), users & roles, reports + CSV, settings, audit logs.
* Roles: **Super Admin**, **Administrator**, **Booking Staff** (see `config/permissions.php`).
* Architecture: `docs/ARCHITECTURE.md` (layers, ERD, deliberate deviations from the spec).

---
## 1. Requirements (Windows)
1. **XAMPP** with **PHP 8.2 or newer** (xampp.com, choose the 8.2+ build). Needs extensions `pdo_mysql`, `mbstring` (enabled by default).
2. Optional: **Composer** (only needed to run the PHPUnit tests). The app itself has its own autoloader.
3. Internet access in the browser (Bootstrap, Bootstrap Icons and Chart.js load from the jsDelivr CDN).

## 2. Install
1. Copy the folder to `C:\xampp\htdocs\bus-reservation-system`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **http://localhost/phpmyadmin** -> *Import* -> choose `database\database.sql` -> *Go*.
   This creates the database `bus_reservation_system` with demo data. (To reset: drop the database and import again.)
4. In the project folder copy `.env.example` to `.env` and edit if needed. XAMPP defaults already work:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=bus_reservation_system
   DB_USER=root
   DB_PASS=
   APP_ENV=production
   APP_DEBUG=false          # set true only on your own PC while debugging
   APP_TIMEZONE=Asia/Manila
   ```
5. **Create the first administrator** (no password is stored in the SQL file). Open *XAMPP Control Panel -> Shell*
   (or Command Prompt) and run:
   ```
   cd C:\xampp\htdocs\bus-reservation-system
   C:\xampp\php\php.exe scripts\create_admin.php
   ```
   Enter your name, e-mail and a password (min 12 characters with upper-case, lower-case and a digit).
   The script refuses to run again once a Super Admin exists (use `--allow-additional` to force).
   On Windows the typed password is visible while typing.
6. Open **http://localhost/bus-reservation-system/** and sign in with **the e-mail and password you just chose**.
   There are no default credentials and no public registration page.

## 3. Keep private files private (document root)
Only `public/` should be reachable from the web.

**Option A (quick, already included):** the root `.htaccess` rewrites everything to `public/`, so `app/`, `config/`,
`storage/`, `scripts/`, `.env` etc. return 404 under `http://localhost/bus-reservation-system/`. Requires
`mod_rewrite` and `AllowOverride All` (XAMPP default is OK; if pages 404, see Troubleshooting).

**Option B (recommended, a real VirtualHost):** edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerName busres.local
    DocumentRoot "C:/xampp/htdocs/bus-reservation-system/public"
    <Directory "C:/xampp/htdocs/bus-reservation-system/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Add `127.0.0.1 busres.local` to `C:\Windows\System32\drivers\etc\hosts` (as Administrator), restart Apache,
open http://busres.local/. For a real server also use HTTPS (session cookies become `Secure` automatically).

## 4. First steps in the app
1. **Buses** -> *Add bus* (bus no., plate, type, seat capacity). Seats are numbered 1A, 1B, 1C, 1D, 2A...
2. **Routes** -> *Add route* (origin, destination, duration, base fare).
3. **Schedules** -> *New schedule*: pick an active bus + route and a **future** departure. Leave arrival/fare blank
   to use the route's duration/base fare. Overlapping trips for the same bus are refused.
4. **Passengers** -> *Add passenger* (or register one while booking).
5. **Bookings** -> *New booking*: route -> trip -> seat -> passenger. Status is *Pending*.
6. Open the booking -> **Record payment** (cash, or a bank/e-wallet/card *reference you verified*). A successful
   payment equal to the total confirms the booking and issues the **ticket** automatically; print the ticket and receipt.
7. **Reports** for totals and CSV export; **Users** to add staff; **Settings** (Super Admin) for company data,
   currency, timezone and the cancellation window.

Demo records are marked `DEMO` (references, `@example.com`). Delete them before real use.

## 5. Security features
PDO prepared statements everywhere - `password_hash()` / `password_verify()` - session id regenerated at login,
HttpOnly + SameSite cookies, idle timeout - CSRF token on every POST - server-side validation and
`htmlspecialchars()` on all output - role/permission checks on every action (server side) - login throttling
(5 failures per e-mail / 20 per IP per 15 min) - generic login errors - destructive actions are POST only -
transactions + row locks for booking/payment/ticket changes - audit tables (`activity_logs`, `booking_status_logs`) -
errors logged to `storage/logs/` and never shown to users - no card numbers/CVVs stored (references that look like
card numbers are rejected).

## 6. Troubleshooting
| Symptom | Fix |
|---|---|
| "Could not connect to the database" | MySQL not started; wrong `DB_*` in `.env`; database not imported. Details are in `storage\logs\app-*.log`. |
| `SQLSTATE[HY000] [1049] Unknown database` | Import `database\database.sql` first, or fix `DB_NAME`. |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USER`/`DB_PASS`. XAMPP default is `root` with an empty password. |
| `could not find driver` | Enable `extension=pdo_mysql` in `C:\xampp\php\php.ini`, restart Apache. |
| Import error near `GENERATED ALWAYS` / `CHECK` | Old MySQL/MariaDB. Use XAMPP with MariaDB 10.4+ (or MySQL 8.0.16+). |
| Blank page / HTTP 500 | Set `APP_DEBUG=true` in `.env` temporarily, reload, read the message, then set it back to `false`. Also check `C:\xampp\apache\logs\error.log`. |
| Every page except `/` gives 404 | `mod_rewrite` off: in `httpd.conf` uncomment `LoadModule rewrite_module ...`; set `AllowOverride All` for `htdocs`; restart Apache. |
| Page shows but is unstyled / charts empty | No internet (CDN) or the CSS/JS URL is wrong because of the folder name. |
| "Your session expired or the form token is invalid" (419) | Session expired or cookies blocked - reload the form and retry. |
| Port 80/3306 already in use | Stop Skype/IIS/other MySQL, or change ports in XAMPP and `DB_PORT`. |
| "PHP 8.2 required" parse errors (`readonly`, `never`, `match`) | You are running an older PHP - install the 8.2+ XAMPP. |
| Forgot the admin password | Create another with `php scripts\create_admin.php --allow-additional`. |

## 7. Project layout
```
app/Core        Database, Router, Container, Auth, Session, Csrf, Validator, Request, Logger ...
app/Models      User, Bus, Route, Schedule, Passenger, Booking, Payment, Ticket, SystemSetting
app/Repositories  PDO queries only          app/Services  business rules & transactions
app/Controllers   request -> service -> view   app/Middleware  auth, guest, role, permission, csrf
app/Views         escaped templates (no SQL)   config/  app, database, permissions
database/database.sql   scripts/create_admin.php   public/ (only web-visible folder)   storage/logs   tests/   docs/
```
