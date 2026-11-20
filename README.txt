LIBRARY LOGIN (PHP + MySQL)

1. Install XAMPP and start Apache + MySQL.
2. Copy this folder to  C:\xampp\htdocs\library-login
3. Open http://localhost/phpmyadmin > SQL tab > paste database.sql > Go.
4. Open http://localhost/library-login/setup.php  (creates demo accounts), then DELETE setup.php.
5. Open http://localhost/library-login/

Demo accounts
  Librarian:  LIB-001     / librarian123
  Student:    2024-0012   / student123

Flow: index.php (role selection) -> student_login.php or librarian_login.php -> student_home.php or librarian_home.php
