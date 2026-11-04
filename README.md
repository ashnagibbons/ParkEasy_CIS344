# ParkEasy – Parking Reservation System

CIS 344 Group Project · Fall 2026 · Lehman College

A web app for reserving parking spots by date and time. Users register, check which spots are open for a time window, book a spot, pay, and can later view or cancel their reservations.

**Stack:** PHP 8 (PDO), MySQL, HTML, CSS, JavaScript

## Features

- **Spot overview** – color-coded grid of every spot and its bookings for any date
- **Registration** – create a user with name + email
- **Reservations** – pick a date and time, see only the spots that are free, book one
- **Payment** – card form with validation; creates a transaction record
- **My Reservations** – look up bookings by email, pay unpaid ones, cancel upcoming ones
- **Responsive** layout for desktop and mobile

## Folder structure

```
parking-reservation/
├── index.php            # Spot availability grid
├── register.php         # Users form (INSERT users)
├── reserve.php          # Reservation form (INSERT reservations)
├── payment.php          # Payment form (INSERT transactions)
├── confirmation.php     # Receipt (4-table JOIN)
├── reservations.php     # Lookup by email (JOIN + LEFT JOIN)
├── cancel.php           # Cancel handler (DELETE with JOIN)
├── config/
│   └── db.php           # Database connection settings
├── includes/
│   ├── functions.php    # Helpers: escaping, CSRF, validation, pricing
│   ├── header.php
│   └── footer.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
└── sql/
    └── schema.sql       # Creates the database, tables and seed data
```

## How to run

1. Install **XAMPP** and start **Apache** and **MySQL**.
2. Copy the `parking-reservation` folder into the web root:
   - XAMPP: `C:\xampp\htdocs\`
3. Create the database: open **phpMyAdmin** (`http://localhost/phpmyadmin`) → **Import** → choose `sql/schema.sql` → **Go**.
   (Or from a terminal: `mysql -u root -p < sql/schema.sql`)
4. If your MySQL password isn't blank, edit `config/db.php` (MAMP's default is `root` / `root`).
5. Open `http://localhost/parking-reservation/` (MAMP: `http://localhost:8888/parking-reservation/`).

## How to test

1. **Register** with any name and email (or use the seeded `jane@example.com`).
2. Go to **Reserve**, enter your email, a date and a start/end time, click **Check availability**.
3. Pick a spot and click **Continue to payment**.
4. Pay with test card `4242 4242 4242 4242`, any future expiry (e.g. `12/30`) and any 3-digit CVV.
5. Check the receipt, then open **My Reservations** and look up your email.
6. Try booking the **same spot at an overlapping time** – it won't be offered.
7. Cancel the reservation and confirm the spot is free again on the **Spots** page.

## Database design

| Table | Columns | Notes |
|---|---|---|
| `users` | user_id (PK), name, email (UNIQUE) | |
| `parking_spots` | spot_id (PK), is_taken | `is_taken` = spot has a booking today or later |
| `reservations` | reservation_id (PK), spot_id (FK), user_id (FK), reservation_date, start_time, end_time | CHECK end > start; index on (spot_id, reservation_date) |
| `transactions` | transaction_id (PK), reservation_id (FK, UNIQUE), card_holder, card_brand, card_last4, amount, created_at | One payment per reservation |

Relationships: a user has many reservations; a spot has many reservations; a reservation has at most one transaction. Foreign keys use `ON DELETE CASCADE`, so cancelling a reservation removes its transaction.

### Joins used

| Page | Join | Purpose |
|---|---|---|
| `index.php` | `parking_spots LEFT JOIN reservations` + `GROUP BY` | Every spot with that day's bookings, including spots with none |
| `reserve.php` | `parking_spots LEFT JOIN reservations … WHERE r.reservation_id IS NULL` | Spots with **no overlapping** reservation for the chosen window |
| `payment.php` | `reservations JOIN users LEFT JOIN transactions` | Booking summary + check if already paid |
| `confirmation.php` | `reservations JOIN users JOIN parking_spots JOIN transactions` | Full receipt from all four tables |
| `reservations.php` | `reservations JOIN users JOIN parking_spots LEFT JOIN transactions` | A user's bookings, paid or not |
| `cancel.php` | `DELETE r FROM reservations r JOIN users u` | Only deletes if the email owns the booking |

**Overlap rule:** two bookings on the same spot and date clash when `existing.start < new.end AND existing.end > new.start`.

## Security

- **SQL injection:** every query uses PDO prepared statements with bound parameters; emulated prepares are turned off.
- **Validation:** all input is validated server-side (email format, dates not in the past, end after start, Luhn check on card numbers, expiry not passed). JavaScript checks are only for convenience.
- **XSS:** all output is escaped with `htmlspecialchars()` via the `e()` helper.
- **CSRF:** every POST form carries a random session token checked with `hash_equals()`.
- **Card data:** full card numbers and CVVs are never stored – only brand and last 4 digits.
- **Double booking:** the reservation insert runs in a DB transaction with `SELECT … FOR UPDATE` on the spot row.
- **Errors:** database connection errors show a generic message instead of credentials.
