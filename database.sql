-- Veritabanını sil (varsa)
DROP DATABASE IF EXISTS gorev_sistemi;

-- Veritabanını oluştur
CREATE DATABASE gorev_sistemi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gorev_sistemi;

-- Tabloları sil (varsa)
DROP TABLE IF EXISTS support_messages;
DROP TABLE IF EXISTS support_tickets;
DROP TABLE IF EXISTS task_participants;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS users;

-- Kullanıcılar tablosu
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    points INT NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_vip TINYINT(1) NOT NULL DEFAULT 0,
    vip_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB;

-- Görevler tablosu
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    creator_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    points_required INT NOT NULL DEFAULT 0,
    points_reward INT NOT NULL DEFAULT 0,
    max_participants INT NOT NULL DEFAULT 1,
    task_link TEXT NOT NULL,
    status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Görev katılımcıları tablosu
CREATE TABLE task_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
    screenshot_path VARCHAR(255) NULL,
    auto_approved TINYINT(1) NOT NULL DEFAULT 0,
    rejection_reason TEXT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Destek talepleri tablosu
CREATE TABLE support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Destek mesajları tablosu
CREATE TABLE support_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Varsayılan admin kullanıcısı
INSERT INTO users (username, email, password, points, is_admin) VALUES 
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1000, 1); 