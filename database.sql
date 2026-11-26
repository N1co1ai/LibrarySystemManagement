
CREATE DATABASE IF NOT EXISTS library_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_no         VARCHAR(30)  NOT NULL UNIQUE,  
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,         
  role          ENUM('student','librarian') NOT NULL DEFAULT 'student',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
  