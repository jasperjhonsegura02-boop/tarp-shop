-- Run this ONLY if you already imported the previous database.sql
USE tarp_shop;
CREATE TABLE sizes (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(40) NOT NULL UNIQUE, price DECIMAL(10,2) NOT NULL, active TINYINT(1) DEFAULT 1);
INSERT INTO sizes (label, price) VALUES ('2x3 ft',150),('3x4 ft',300),('4x6 ft',600),('5x8 ft',1000),('6x10 ft',1500);
CREATE TABLE settings (name VARCHAR(40) PRIMARY KEY, value VARCHAR(100) NOT NULL);
INSERT INTO settings VALUES ('rush_rate','0.30'),('layout_fee','200');
ALTER TABLE orders MODIFY size VARCHAR(40) NOT NULL,
  ADD service ENUM('print_only','layout') DEFAULT 'print_only' AFTER size,
  MODIFY status ENUM('Pending','Designing','For approval','Approved','Printing','Ready','Claimed') DEFAULT 'Pending';
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
INSERT INTO order_images (order_id, file, original_name) SELECT id, image, image FROM orders WHERE image IS NOT NULL;
ALTER TABLE orders DROP COLUMN image;
