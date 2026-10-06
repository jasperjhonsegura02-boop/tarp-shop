-- FRESH INSTALL. (If you already imported the old database, run upgrade.sql instead.)
CREATE DATABASE IF NOT EXISTS tarp_shop CHARACTER SET utf8mb4;
USE tarp_shop;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(30), password VARCHAR(255) NOT NULL, role ENUM('customer','admin') DEFAULT 'customer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE sizes (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(40) NOT NULL UNIQUE, price DECIMAL(10,2) NOT NULL, active TINYINT(1) DEFAULT 1);
INSERT INTO sizes (label, price) VALUES ('2x3 ft',150),('3x4 ft',300),('4x6 ft',600),('5x8 ft',1000),('6x10 ft',1500);
CREATE TABLE settings (name VARCHAR(40) PRIMARY KEY, value VARCHAR(100) NOT NULL);
INSERT INTO settings VALUES ('rush_rate','0.30'),('layout_fee','200');
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, theme VARCHAR(50) NOT NULL,
  event_date DATE NOT NULL, deadline DATE NOT NULL, size VARCHAR(40) NOT NULL,
  service ENUM('print_only','layout') DEFAULT 'print_only', is_rush TINYINT(1) DEFAULT 0,
  celebrant_name VARCHAR(100), celebrant_age INT, notes TEXT, price DECIMAL(10,2) NOT NULL,
  status ENUM('Pending','Designing','For approval','Approved','Printing','Ready','Claimed') DEFAULT 'Pending',
  paid_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE order_images (
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, file VARCHAR(255) NOT NULL, original_name VARCHAR(255),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE TABLE order_proofs (
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, file VARCHAR(255) NOT NULL, note TEXT,
  response ENUM('pending','approved','changes') DEFAULT 'pending', feedback TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, responded_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
