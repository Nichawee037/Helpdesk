CREATE DATABASE smart_it_helpdesk
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_it_helpdesk;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('User','Technician','Admin') NOT NULL DEFAULT 'User',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  technician_id INT NULL,
  category_id INT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  status ENUM('Open','Assigned','InProgress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  attachment VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE ticket_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ticket_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL UNIQUE,
  user_id INT NOT NULL,
  score TINYINT NOT NULL,
  feedback TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ticket_id INT NULL,
  message VARCHAR(500) NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
);

INSERT INTO categories(name) VALUES
('Hardware'),('Software'),('Network'),('Other');

-- Password ทุกบัญชีในตัวอย่างคือ password
INSERT INTO users(name,email,password_hash,role) VALUES
('สมชาย ใจดี','user@example.com', '$2y$12$YBjmzlkso2ClU15cmEqXMemUMugBQbZ8Dvgh9oqQuXUWPk.zCpG.O', 'User'),
('ช่างเอก','tech@example.com', '$2y$12$YBjmzlkso2ClU15cmEqXMemUMugBQbZ8Dvgh9oqQuXUWPk.zCpG.O', 'Technician'),
('ผู้ดูแลระบบ','admin@example.com', '$2y$12$YBjmzlkso2ClU15cmEqXMemUMugBQbZ8Dvgh9oqQuXUWPk.zCpG.O', 'Admin');


CREATE TABLE email_settings (
  id INT PRIMARY KEY DEFAULT 1,
  smtp_host VARCHAR(190) NOT NULL DEFAULT '',
  smtp_port INT NOT NULL DEFAULT 587,
  smtp_secure ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
  smtp_username VARCHAR(190) NOT NULL DEFAULT '',
  smtp_password VARCHAR(255) NOT NULL DEFAULT '',
  from_email VARCHAR(190) NOT NULL DEFAULT '',
  from_name VARCHAR(190) NOT NULL DEFAULT 'Smart IT Helpdesk',
  admin_email VARCHAR(190) NOT NULL DEFAULT '',
  notify_new_ticket TINYINT(1) NOT NULL DEFAULT 1,
  notify_rejected TINYINT(1) NOT NULL DEFAULT 1,
  notify_closed TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO email_settings (id, admin_email, notify_new_ticket) VALUES (1, '654230048@webmail.npru.ac.th', 1);

INSERT INTO tickets(user_id,category_id,title,description,priority,status)
VALUES
(1,1,'คอมพิวเตอร์เปิดไม่ติด','กดปุ่ม Power แล้วไม่มีภาพและไม่มีเสียง','High','Open'),
(1,3,'Wi-Fi ใช้งานไม่ได้','เชื่อมต่อ Wi-Fi แล้วขึ้นว่า No Internet','High','Assigned');
UPDATE tickets SET technician_id=2 WHERE id=2;
INSERT INTO ticket_logs(ticket_id,user_id,old_status,new_status,note)
VALUES (1,1,NULL,'Open','สร้างรายการแจ้งซ่อม'),
       (2,1,NULL,'Open','สร้างรายการแจ้งซ่อม'),
       (2,3,'Open','Assigned','มอบหมายให้ช่างเอก');
