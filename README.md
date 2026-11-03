# Senior High School Management System

A PHP + MySQL web application for a Senior High School. It includes separate administrator and student portals, student records, year levels, strands, subjects, enrollment records, and quarterly grades.

## Requirements
- XAMPP (Apache, MySQL, PHP 8.0+)
- PHP extensions: `pdo` and `pdo_mysql`
- A modern web browser

## Installation (XAMPP)
1. Extract the `senior-high-system` folder into `C:\xampp\htdocs\` (Windows) or `/opt/lampp/htdocs/` (Linux).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open phpMyAdmin: `http://localhost/phpmyadmin`.
4. Select **Import**, choose `database.sql` from this project, and click **Import**. The script creates the `senior_high_db` database and its tables.
5. Open `config/db.php`. Confirm the host, database name, MySQL username, and password. The default XAMPP settings are:
   - Host: `localhost`
   - Database: `senior_high_db`
   - Username: `root`
   - Password: empty (change this if your MySQL root account has a password).
6. Visit `http://localhost/senior-high-system/create_admin.php`.
7. Create the first administrator account. Use a strong password with at least 8 characters.
8. **Important:** After creating the administrator, delete `create_admin.php` from the web server.
9. Open `http://localhost/senior-high-system/` and log in.

## Default data
The SQL file inserts Grade 11 and Grade 12, plus common strands: STEM, ABM, HUMSS, GAS, and TVL-ICT. You can add more through the admin portal.

## Accounts and features
### Administrator
- Dashboard summary
- Add, edit, and delete student records
- Manage subjects, year levels, and strands
- Encode quarterly grades and enrollment records
- Add and remove administrator accounts

### Student
- Register using a student number, year level, and strand
- Log in using student number and password
- View dashboard and profile
- View assigned subjects and quarterly grades

Student registrations start with **Pending** status. The administrator can change the status to **Enrolled** from the Students page.

## Database structure
- `admin`: administrator login accounts
- `students`: student profile and login details
- `year_levels`: Grade 11 / Grade 12
- `strands`: SHS academic and TVL strands
- `subjects`: subject catalog
- `student_subjects`: student-subject enrollment per school year and semester
- `grades`: 1st to 4th quarter grades per enrollment

## Notes
- Passwords are stored using PHP `password_hash()` and checked with `password_verify()`.
- SQL statements use PDO prepared statements.
- This is a starter academic project. Before production use, add CSRF protection, stronger audit logging, password reset, role/permission policies, and HTTPS.
- For production, do not display raw database connection errors to visitors.
