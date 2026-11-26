USE library_db;

CREATE TABLE IF NOT EXISTS books (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  call_no          VARCHAR(30)  NOT NULL UNIQUE,     
  title            VARCHAR(150) NOT NULL,
  author           VARCHAR(100) NOT NULL,
  category         VARCHAR(60)  NOT NULL,
  shelf_location   VARCHAR(30)  NULL,
  copies_total     INT UNSIGNED NOT NULL DEFAULT 1,
  copies_available INT UNSIGNED NOT NULL DEFAULT 1,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS borrow_records (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,            
  book_id          INT UNSIGNED NOT NULL,
  borrowed_at      DATETIME NOT NULL,
  due_date         DATE NOT NULL,
  returned_at      DATETIME NULL,                   
  return_condition VARCHAR(30) NULL,
  fine_amount      DECIMAL(8,2) NOT NULL DEFAULT 0,  
  fine_paid_at     DATE NULL,
  reminded_at      DATETIME NULL,
  handled_by       INT UNSIGNED NULL,              
  FOREIGN KEY (user_id)    REFERENCES users(id),
  FOREIGN KEY (book_id)    REFERENCES books(id),
  FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  book_id     INT UNSIGNED NOT NULL,
  reserved_at DATE NOT NULL,
  status      ENUM('pending','ready','completed','cancelled','expired') NOT NULL DEFAULT 'pending',
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO books (call_no, title, author, category, shelf_location, copies_total, copies_available) VALUES
('FIL-899.21', 'Florante at Laura',               'Francisco Balagtas', 'Filipiniana',      'Shelf 4-B', 5, 5),
('FIL-899.22', 'Noli Me Tangere',                 'Jose Rizal',         'Filipiniana',      'Shelf 4-B', 2, 2),
('FIL-899.23', 'El Filibusterismo',               'Jose Rizal',         'Filipiniana',      'Shelf 4-B', 2, 2),
('MAT-512.01', 'College Algebra: A Modern Approach', 'Dr. E. Sterling', 'Mathematics',     'Shelf 2-A', 3, 3),
('BIO-401.11', 'General Biology 1 & 2',           'Campbell & Reece',   'Science',          'Shelf 3-C', 4, 4),
('ENG-620.10', 'Engineering Mechanics: Statics',  'R.C. Hibbeler',      'Engineering',      'Shelf 5-A', 3, 3),
('ACC-657.04', 'Cost Accounting and Control',     'De Leon',            'Business',         'Shelf 6-B', 4, 4),
('CS-005.10',  'Programming Language Cultures',   'Brian Lennon',       'Computer Science', 'Shelf 7-A', 2, 2),
('CS-005.30',  'Data Structures & Algorithms in Java', 'Goodrich',      'Computer Science', 'Shelf 7-A', 3, 3);

ALTER TABLE users ADD COLUMN grade_section VARCHAR(40) NULL AFTER email;
