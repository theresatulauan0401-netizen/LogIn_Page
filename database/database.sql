-- =============================================================================
-- BUS RESERVATION MANAGEMENT SYSTEM  -  database/database.sql
-- Target: MySQL 8.0+ / MariaDB 10.4+ (XAMPP)   Engine: InnoDB   Charset: utf8mb4
--
-- IMPORT:  phpMyAdmin > Import > choose this file.  (Creates the database too.)
-- RESET:   DROP DATABASE bus_reservation_system;  then import again.
--
-- DEMO DATA: every sample record is marked DEMO (references contain "DEMO",
-- passenger e-mails use @example.com, notes say "DEMO RECORD"). Delete them
-- before using real data. Sample fares are demonstration values only.
-- NO USER ROW IS INSERTED: create the first Super Admin with
--   php scripts/create_admin.php
-- so that no password (plaintext or hash) ever lives in this file.
-- Demo rows therefore have created_by / received_by / changed_by = NULL.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS bus_reservation_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bus_reservation_system;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- 1. users --
CREATE TABLE users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(150) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password      VARCHAR(255) NOT NULL COMMENT 'password_hash() output only',
  role          ENUM('super_admin','admin','booking_staff') NOT NULL DEFAULT 'booking_staff',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- 2. buses --
CREATE TABLE buses (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bus_number   VARCHAR(50)  NOT NULL,
  plate_number VARCHAR(30)  NOT NULL,
  bus_type     VARCHAR(100) NOT NULL,
  bus_model    VARCHAR(100) NULL,
  capacity     SMALLINT UNSIGNED NOT NULL,
  amenities    JSON NULL,
  status       ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_buses_number (bus_number),
  UNIQUE KEY uq_buses_plate (plate_number),
  KEY idx_buses_status (status),
  CONSTRAINT chk_buses_capacity CHECK (capacity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- 3. routes --
CREATE TABLE routes (
  id                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  route_code                 VARCHAR(50)  NOT NULL,
  origin                     VARCHAR(150) NOT NULL,
  destination                VARCHAR(150) NOT NULL,
  distance_km                DECIMAL(8,2) NULL,
  estimated_duration_minutes INT UNSIGNED NULL,
  base_fare                  DECIMAL(10,2) NOT NULL,
  status                     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_routes_code (route_code),
  KEY idx_routes_origin_dest (origin, destination),
  KEY idx_routes_status (status),
  CONSTRAINT chk_routes_fare CHECK (base_fare >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 4. schedules --
-- available_seats is a maintained counter: capacity minus bookings that hold a
-- seat (pending/confirmed/completed). BookingService updates it inside the same
-- transaction that changes a booking, after locking the schedule row.
-- "available_seats <= bus capacity" and "no overlapping trips per bus" span
-- tables, so ScheduleService enforces them (inside a transaction that locks the
-- bus row); they cannot be expressed as a single-table CHECK.
CREATE TABLE schedules (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  schedule_code      VARCHAR(50) NOT NULL,
  bus_id             BIGINT UNSIGNED NOT NULL,
  route_id           BIGINT UNSIGNED NOT NULL,
  departure_datetime DATETIME NOT NULL,
  arrival_datetime   DATETIME NOT NULL,
  fare               DECIMAL(10,2) NOT NULL,
  available_seats    SMALLINT UNSIGNED NOT NULL,
  status             ENUM('scheduled','boarding','departed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schedules_code (schedule_code),
  KEY idx_schedules_bus_time (bus_id, departure_datetime, arrival_datetime),
  KEY idx_schedules_route (route_id),
  KEY idx_schedules_departure_status (departure_datetime, status),
  CONSTRAINT fk_schedules_bus   FOREIGN KEY (bus_id)   REFERENCES buses(id)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_schedules_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_schedules_dates CHECK (arrival_datetime > departure_datetime),
  CONSTRAINT chk_schedules_fare  CHECK (fare >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ 5. passengers --
-- DOCUMENTED ADDITION: "status" so passengers with booking history can be
-- deactivated instead of deleted.
CREATE TABLE passengers (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  passenger_code VARCHAR(50)  NOT NULL,
  first_name     VARCHAR(100) NOT NULL,
  last_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(190) NULL,
  phone          VARCHAR(30)  NOT NULL,
  gender         VARCHAR(30)  NULL,
  date_of_birth  DATE NULL,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_passengers_code (passenger_code),
  KEY idx_passengers_name (last_name, first_name),
  KEY idx_passengers_email (email),
  KEY idx_passengers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 6. bookings --
-- DOCUMENTED DEVIATION (seat uniqueness): a plain UNIQUE(schedule_id, seat_number)
-- would keep a seat blocked forever after a cancellation, because history rows are
-- never deleted. active_seat_flag is a generated column that is 1 while the booking
-- holds its seat and NULL once it is cancelled / no_show. MySQL/MariaDB allow many
-- NULLs in a unique index, so the unique key still makes double booking impossible
-- at database level while cancelled history is preserved and the seat can be resold.
CREATE TABLE bookings (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_reference VARCHAR(50) NOT NULL,
  passenger_id      BIGINT UNSIGNED NOT NULL,
  schedule_id       BIGINT UNSIGNED NOT NULL,
  booking_date      DATETIME NOT NULL,
  seat_number       VARCHAR(10) NOT NULL,
  passenger_count   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  total_amount      DECIMAL(10,2) NOT NULL,
  status            ENUM('pending','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  notes             TEXT NULL,
  created_by        BIGINT UNSIGNED NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  active_seat_flag  TINYINT UNSIGNED GENERATED ALWAYS AS
                    (CASE WHEN status IN ('cancelled','no_show') THEN NULL ELSE 1 END) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bookings_reference (booking_reference),
  UNIQUE KEY uq_bookings_active_seat (schedule_id, seat_number, active_seat_flag),
  KEY idx_bookings_passenger (passenger_id),
  KEY idx_bookings_status (status),
  KEY idx_bookings_date (booking_date),
  KEY idx_bookings_created_by (created_by),
  CONSTRAINT fk_bookings_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_schedule  FOREIGN KEY (schedule_id)  REFERENCES schedules(id)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_created_by FOREIGN KEY (created_by)  REFERENCES users(id)      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_bookings_total CHECK (total_amount >= 0),
  CONSTRAINT chk_bookings_pax   CHECK (passenger_count = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 7. payments --
-- DOCUMENTED ADDITIONS: refunded_at / refunded_by / refund_reason (refund audit),
-- and two generated "key" columns that give database-level duplicate protection:
--   * only ONE successful payment per booking (uq_payments_one_success)
--   * a transaction reference can be successful only once (uq_payments_success_txn)
-- Failed/pending attempts are unrestricted, so every attempt is its own record.
-- A refund marks the original payment 'refunded' (kept, never deleted); revenue
-- sums payment_status = 'successful' only, so refunded money drops out of revenue.
-- These are RECORDED payments (cash / manually verified references), not a gateway.
CREATE TABLE payments (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_reference     VARCHAR(50) NOT NULL,
  booking_id            BIGINT UNSIGNED NOT NULL,
  amount                DECIMAL(10,2) NOT NULL,
  payment_method        ENUM('cash','bank_transfer','e_wallet','card') NOT NULL,
  payment_status        ENUM('pending','successful','failed','refunded') NOT NULL DEFAULT 'pending',
  transaction_reference VARCHAR(150) NULL,
  paid_at               DATETIME NULL,
  received_by           BIGINT UNSIGNED NULL,
  refunded_at           DATETIME NULL,
  refunded_by           BIGINT UNSIGNED NULL,
  refund_reason         TEXT NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  success_booking_key   BIGINT UNSIGNED GENERATED ALWAYS AS
                        (CASE WHEN payment_status = 'successful' THEN booking_id ELSE NULL END) STORED,
  success_txn_key       VARCHAR(150) GENERATED ALWAYS AS
                        (CASE WHEN payment_status = 'successful' THEN transaction_reference ELSE NULL END) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_reference (payment_reference),
  UNIQUE KEY uq_payments_one_success (success_booking_key),
  UNIQUE KEY uq_payments_success_txn (success_txn_key),
  KEY idx_payments_booking (booking_id),
  KEY idx_payments_status_paid (payment_status, paid_at),
  KEY idx_payments_method (payment_method),
  KEY idx_payments_received_by (received_by),
  KEY idx_payments_refunded_by (refunded_by),
  CONSTRAINT fk_payments_booking     FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_payments_received_by FOREIGN KEY (received_by) REFERENCES users(id)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_payments_refunded_by FOREIGN KEY (refunded_by) REFERENCES users(id)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- 8. tickets --
-- DOCUMENTED ADDITIONS: used_at / used_by record who validated the ticket.
CREATE TABLE tickets (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_number VARCHAR(50) NOT NULL,
  booking_id    BIGINT UNSIGNED NOT NULL,
  issued_at     DATETIME NULL,
  ticket_status ENUM('valid','used','cancelled','expired') NOT NULL DEFAULT 'valid',
  used_at       DATETIME NULL,
  used_by       BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tickets_number (ticket_number),
  UNIQUE KEY uq_tickets_booking (booking_id),
  KEY idx_tickets_status (ticket_status),
  KEY idx_tickets_used_by (used_by),
  CONSTRAINT fk_tickets_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tickets_used_by FOREIGN KEY (used_by)    REFERENCES users(id)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------- 9. booking_status_logs --
CREATE TABLE booking_status_logs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  remarks    TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bsl_booking (booking_id, created_at),
  KEY idx_bsl_changed_by (changed_by),
  CONSTRAINT fk_bsl_booking    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bsl_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------ 10. activity_logs --
-- Never store passwords, tokens or other secrets in description.
CREATE TABLE activity_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  action      VARCHAR(100) NOT NULL,
  module      VARCHAR(100) NOT NULL,
  description TEXT NULL,
  ip_address  VARCHAR(45) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_user (user_id),
  KEY idx_activity_module_time (module, created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------- 11. system_settings --
CREATE TABLE system_settings (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_by    BIGINT UNSIGNED NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key),
  KEY idx_settings_updated_by (updated_by),
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------ 12. login_attempts --
-- DOCUMENTED ADDITION (supports the required login throttling). Stores only the
-- e-mail typed, the client IP and the outcome. Never the password.
CREATE TABLE login_attempts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_email_time (email, attempted_at),
  KEY idx_la_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--                               REFERENCE / DEMO DATA
-- =============================================================================

-- System settings (sample values; editable under Settings) ---------------------
INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('company_name',                  'Sample Bus Transportation'),
  ('company_address',               'Manila, Philippines'),
  ('company_phone',                 '09171234567'),
  ('company_email',                 'support@example.com'),
  ('currency',                      'PHP'),
  ('timezone',                      'Asia/Manila'),
  ('booking_cancellation_enabled',  '1'),
  ('cancellation_cutoff_hours',     '2'),
  ('demo_data_loaded',              '1');

-- Buses (BUS-003 is in maintenance, so it has no schedules) --------------------
INSERT INTO buses (id, bus_number, plate_number, bus_type, bus_model, capacity, amenities, status) VALUES
  (1, 'BUS-001', 'ABC-1234', 'Ordinary Bus',   'Hino RK1J',        45, '["Overhead racks"]',                              'active'),
  (2, 'BUS-002', 'XYZ-5678', 'Air Conditioned','Yutong ZK6122H',   40, '["Air conditioning","USB charging","Reclining seats"]', 'active'),
  (3, 'BUS-003', 'DEF-9012', 'Deluxe Bus',     'Daewoo BH120F',    30, '["Air conditioning","WiFi","USB charging","Onboard restroom","Leg rest"]', 'maintenance');

-- Routes (fares are DEMONSTRATION data) ----------------------------------------
INSERT INTO routes (id, route_code, origin, destination, distance_km, estimated_duration_minutes, base_fare, status) VALUES
  (1, 'RT-001', 'Manila',        'Baguio',   250.00, 330, 500.00, 'active'),
  (2, 'RT-002', 'Manila',        'Angeles City', 80.00, 120, 250.00, 'active'),
  (3, 'RT-003', 'Angeles City',  'Baguio',   170.00, 240, 350.00, 'active'),
  (4, 'RT-004', 'Manila',        'Olongapo', 130.00, 180, 300.00, 'active');

-- Schedules: dates are relative to the moment this file is imported, so they are
-- always in the future at demo time. Bus 1 and Bus 2 have no overlapping trips.
SET @today = CURDATE();
INSERT INTO schedules (id, schedule_code, bus_id, route_id, departure_datetime, arrival_datetime, fare, available_seats, status) VALUES
  (1, 'SCH-DEMO-0001', 1, 1, TIMESTAMP(@today + INTERVAL 1 DAY, '06:00:00'), TIMESTAMP(@today + INTERVAL 1 DAY, '11:30:00'), 500.00, 43, 'scheduled'),
  (2, 'SCH-DEMO-0002', 2, 2, TIMESTAMP(@today + INTERVAL 1 DAY, '08:00:00'), TIMESTAMP(@today + INTERVAL 1 DAY, '10:00:00'), 250.00, 39, 'scheduled'),
  (3, 'SCH-DEMO-0003', 1, 3, TIMESTAMP(@today + INTERVAL 2 DAY, '07:00:00'), TIMESTAMP(@today + INTERVAL 2 DAY, '11:00:00'), 350.00, 44, 'scheduled'),
  (4, 'SCH-DEMO-0004', 2, 4, TIMESTAMP(@today + INTERVAL 2 DAY, '09:00:00'), TIMESTAMP(@today + INTERVAL 2 DAY, '12:00:00'), 300.00, 40, 'scheduled'),
  (5, 'SCH-DEMO-0005', 1, 1, TIMESTAMP(@today + INTERVAL 3 DAY, '06:00:00'), TIMESTAMP(@today + INTERVAL 3 DAY, '11:30:00'), 500.00, 43, 'scheduled'),
  (6, 'SCH-DEMO-0006', 2, 2, TIMESTAMP(@today + INTERVAL 3 DAY, '14:00:00'), TIMESTAMP(@today + INTERVAL 3 DAY, '16:00:00'), 250.00, 40, 'scheduled');
-- available_seats = capacity - bookings holding a seat (pending/confirmed/completed):
--   SCH1: 45-2=43  SCH2: 40-1=39  SCH3: 45-1=44  SCH4: 40-0=40 (its only booking is cancelled)
--   SCH5: 45-2=43  SCH6: 40-0=40 (its only booking is cancelled)

-- Passengers (fictional people, @example.com addresses) ------------------------
INSERT INTO passengers (id, passenger_code, first_name, last_name, email, phone, gender, date_of_birth, status) VALUES
  (1, 'PAX-0001', 'Juan',    'Dela Cruz', 'juan.delacruz@example.com', '09170000001', 'Male',   '1990-03-14', 'active'),
  (2, 'PAX-0002', 'Maria',   'Santos',    'maria.santos@example.com',  '09170000002', 'Female', '1994-07-22', 'active'),
  (3, 'PAX-0003', 'Jose',    'Reyes',     NULL,                        '09170000003', 'Male',   '1985-11-02', 'active'),
  (4, 'PAX-0004', 'Ana',     'Garcia',    'ana.garcia@example.com',    '09170000004', 'Female', '2000-01-30', 'active'),
  (5, 'PAX-0005', 'Pedro',   'Ramos',     'pedro.ramos@example.com',   '09170000005', 'Male',   '1978-09-09', 'active'),
  (6, 'PAX-0006', 'Liza',    'Mendoza',   'liza.mendoza@example.com',  '09170000006', 'Female', '1998-05-18', 'active'),
  (7, 'PAX-0007', 'Carlo',   'Bautista',  'carlo.bautista@example.com','09170000007', 'Male',   '1992-12-25', 'active'),
  (8, 'PAX-0008', 'Elena',   'Aquino',    'elena.aquino@example.com',  '09170000008', 'Female', '1989-08-08', 'active');

-- Bookings: one passenger, one seat each. total_amount equals the schedule fare.
INSERT INTO bookings (id, booking_reference, passenger_id, schedule_id, booking_date, seat_number, passenger_count, total_amount, status, notes) VALUES
  (1, 'BKG-DEMO-0001', 1, 1, NOW() - INTERVAL 2 DAY,  '1A', 1, 500.00, 'confirmed', 'DEMO RECORD'),
  (2, 'BKG-DEMO-0002', 2, 1, NOW() - INTERVAL 2 DAY,  '1B', 1, 500.00, 'confirmed', 'DEMO RECORD'),
  (3, 'BKG-DEMO-0003', 3, 2, NOW() - INTERVAL 5 HOUR, '2A', 1, 250.00, 'pending',   'DEMO RECORD - awaiting payment'),
  (4, 'BKG-DEMO-0004', 4, 3, NOW() - INTERVAL 1 DAY,  '3A', 1, 350.00, 'confirmed', 'DEMO RECORD'),
  (5, 'BKG-DEMO-0005', 5, 4, NOW() - INTERVAL 1 DAY,  '1A', 1, 300.00, 'cancelled', 'DEMO RECORD - cancelled after failed payment'),
  (6, 'BKG-DEMO-0006', 6, 5, NOW() - INTERVAL 3 HOUR, '4C', 1, 500.00, 'pending',   'DEMO RECORD - first payment attempt failed'),
  (7, 'BKG-DEMO-0007', 7, 5, NOW() - INTERVAL 1 DAY,  '4D', 1, 500.00, 'confirmed', 'DEMO RECORD'),
  (8, 'BKG-DEMO-0008', 8, 6, NOW() - INTERVAL 2 DAY,  '1A', 1, 250.00, 'cancelled', 'DEMO RECORD - paid, then cancelled and refunded');

-- Payments: pending (3), successful (1,2,4,7), failed (5,6), refunded (8).
-- Each successful amount equals its booking total. Revenue (successful only) = 1,850.00.
INSERT INTO payments (id, payment_reference, booking_id, amount, payment_method, payment_status, transaction_reference, paid_at, refunded_at, refund_reason) VALUES
  (1, 'PAY-DEMO-0001', 1, 500.00, 'cash',          'successful', NULL,                 NOW() - INTERVAL 2 DAY,  NULL, NULL),
  (2, 'PAY-DEMO-0002', 2, 500.00, 'bank_transfer', 'successful', 'BT-DEMO-77120',      NOW() - INTERVAL 2 DAY,  NULL, NULL),
  (3, 'PAY-DEMO-0003', 3, 250.00, 'bank_transfer', 'pending',    NULL,                 NULL,                    NULL, NULL),
  (4, 'PAY-DEMO-0004', 4, 350.00, 'e_wallet',      'successful', 'EW-DEMO-55231',      NOW() - INTERVAL 1 DAY,  NULL, NULL),
  (5, 'PAY-DEMO-0005', 5, 300.00, 'e_wallet',      'failed',     'EW-DEMO-FAIL-0001',  NULL,                    NULL, NULL),
  (6, 'PAY-DEMO-0006', 6, 500.00, 'bank_transfer', 'failed',     'BT-DEMO-FAIL-0002',  NULL,                    NULL, NULL),
  (7, 'PAY-DEMO-0007', 7, 500.00, 'cash',          'successful', NULL,                 NOW() - INTERVAL 1 DAY,  NULL, NULL),
  (8, 'PAY-DEMO-0008', 8, 250.00, 'cash',          'refunded',   NULL,                 NOW() - INTERVAL 2 DAY,  NOW() - INTERVAL 1 DAY, 'DEMO: passenger cancelled before departure');

-- Tickets: only for confirmed bookings (valid). Booking 8 was confirmed first and
-- cancelled later, so its ticket exists with status 'cancelled' (never 'valid').
INSERT INTO tickets (id, ticket_number, booking_id, issued_at, ticket_status) VALUES
  (1, 'TKT-DEMO-0001', 1, NOW() - INTERVAL 2 DAY, 'valid'),
  (2, 'TKT-DEMO-0002', 2, NOW() - INTERVAL 2 DAY, 'valid'),
  (3, 'TKT-DEMO-0003', 4, NOW() - INTERVAL 1 DAY, 'valid'),
  (4, 'TKT-DEMO-0004', 7, NOW() - INTERVAL 1 DAY, 'valid'),
  (5, 'TKT-DEMO-0005', 8, NOW() - INTERVAL 2 DAY, 'cancelled');

-- Booking status history (changed_by NULL = seeded demo data) -------------------
INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, remarks, created_at) VALUES
  (1, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 2 DAY),
  (1, 'pending',   'confirmed', NULL, 'DEMO: cash payment recorded',           NOW() - INTERVAL 2 DAY),
  (2, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 2 DAY),
  (2, 'pending',   'confirmed', NULL, 'DEMO: bank transfer verified',          NOW() - INTERVAL 2 DAY),
  (3, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 5 HOUR),
  (4, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 1 DAY),
  (4, 'pending',   'confirmed', NULL, 'DEMO: e-wallet reference verified',     NOW() - INTERVAL 1 DAY),
  (5, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 1 DAY),
  (5, 'pending',   'cancelled', NULL, 'DEMO: cancelled after failed payment',  NOW() - INTERVAL 20 HOUR),
  (6, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 3 HOUR),
  (7, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 1 DAY),
  (7, 'pending',   'confirmed', NULL, 'DEMO: cash payment recorded',           NOW() - INTERVAL 1 DAY),
  (8, NULL,        'pending',   NULL, 'DEMO: booking created',                 NOW() - INTERVAL 2 DAY),
  (8, 'pending',   'confirmed', NULL, 'DEMO: cash payment recorded',           NOW() - INTERVAL 2 DAY),
  (8, 'confirmed', 'cancelled', NULL, 'DEMO: cancelled by passenger, refunded',NOW() - INTERVAL 1 DAY);

-- Activity log (user_id NULL = seeded demo data) ---------------------------------
INSERT INTO activity_logs (user_id, action, module, description, ip_address) VALUES
  (NULL, 'seed',            'system',    'DEMO: reference and sample data imported from database.sql', NULL),
  (NULL, 'create_bus',      'buses',     'DEMO: seeded buses BUS-001 to BUS-003', NULL),
  (NULL, 'create_route',    'routes',    'DEMO: seeded routes RT-001 to RT-004', NULL),
  (NULL, 'create_schedule', 'schedules', 'DEMO: seeded schedules SCH-DEMO-0001 to SCH-DEMO-0006', NULL),
  (NULL, 'record_payment',  'payments',  'DEMO: seeded payments PAY-DEMO-0001 to PAY-DEMO-0008', NULL),
  (NULL, 'cancel_booking',  'bookings',  'DEMO: BKG-DEMO-0008 cancelled and refunded', NULL);
