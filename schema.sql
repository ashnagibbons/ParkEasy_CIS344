-- Database Schema
-- Run this file once in MySQL (phpMyAdmin > Import, or:
--   mysql -u root -p < sql/schema.sql)
-- =============================================================

DROP DATABASE IF EXISTS parking_db;
CREATE DATABASE parking_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parking_db;

-- -------------------------------------------------------------
-- Users: people who reserve parking spots
-- -------------------------------------------------------------
CREATE TABLE users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Parking spots: is_taken = 1 while the spot has an upcoming
-- (today or later) reservation. Exact time-slot availability is
-- checked against the reservations table with a JOIN.
-- -------------------------------------------------------------
CREATE TABLE parking_spots (
    spot_id    INT AUTO_INCREMENT PRIMARY KEY,
    is_taken   BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Reservations: links a user to a spot for a date + time window
-- -------------------------------------------------------------
CREATE TABLE reservations (
    reservation_id   INT AUTO_INCREMENT PRIMARY KEY,
    spot_id          INT  NOT NULL,
    user_id          INT  NOT NULL,
    reservation_date DATE NOT NULL,
    start_time       TIME NOT NULL,
    end_time         TIME NOT NULL,
    CONSTRAINT fk_res_spot FOREIGN KEY (spot_id) REFERENCES parking_spots(spot_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_times CHECK (end_time > start_time),
    -- speeds up the overlap check and the per-day availability join
    INDEX idx_spot_date (spot_id, reservation_date)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Transactions: one payment per reservation.
-- Security note: only the cardholder name, card brand and LAST 4
-- digits are stored - never the full card number or CVV.
-- -------------------------------------------------------------
CREATE TABLE transactions (
    transaction_id  INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id  INT NOT NULL UNIQUE,
    card_holder     VARCHAR(100) NOT NULL,
    card_brand      VARCHAR(20)  NOT NULL,
    card_last4      CHAR(4)      NOT NULL,
    amount          DECIMAL(8,2) NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_res FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Seed data: 12 parking spots and two sample users
-- -------------------------------------------------------------
INSERT INTO parking_spots (is_taken) VALUES
(0),(0),(0),(0),(0),(0),(0),(0),(0),(0),(0),(0);

INSERT INTO users (name, email) VALUES
('Jane Doe',  'jane@example.com'),
('John Smith','john@example.com');


