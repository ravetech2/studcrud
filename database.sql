-- Senior High School Management System database schema and starter records.
CREATE DATABASE IF NOT EXISTS senior_high_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE senior_high_db;

CREATE TABLE admin (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE year_levels (
  year_level_id INT AUTO_INCREMENT PRIMARY KEY,
  year_level_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE strands (
  strand_id INT AUTO_INCREMENT PRIMARY KEY,
  strand_code VARCHAR(20) NOT NULL UNIQUE,
  strand_name VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  student_number VARCHAR(40) NOT NULL UNIQUE,
  firstname VARCHAR(80) NOT NULL,
  middlename VARCHAR(80) NULL,
  lastname VARCHAR(80) NOT NULL,
  gender ENUM('Male','Female','Other') NULL,
  birthdate DATE NULL,
  email VARCHAR(160) UNIQUE NULL,
  password VARCHAR(255) NOT NULL,
  contact_number VARCHAR(40) NULL,
  address TEXT NULL,
  year_level_id INT NOT NULL,
  strand_id INT NOT NULL,
  enrollment_status ENUM('Enrolled','Pending','Graduated','Dropped') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_year FOREIGN KEY (year_level_id) REFERENCES year_levels(year_level_id),
  CONSTRAINT fk_student_strand FOREIGN KEY (strand_id) REFERENCES strands(strand_id)
) ENGINE=InnoDB;

CREATE TABLE subjects (
  subject_id INT AUTO_INCREMENT PRIMARY KEY,
  subject_code VARCHAR(30) NOT NULL UNIQUE,
  subject_name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  units DECIMAL(4,1) DEFAULT 3.0,
  year_level_id INT NOT NULL,
  strand_id INT NULL,
  semester ENUM('1st Semester','2nd Semester') NOT NULL,
  FOREIGN KEY (year_level_id) REFERENCES year_levels(year_level_id),
  FOREIGN KEY (strand_id) REFERENCES strands(strand_id)
) ENGINE=InnoDB;

CREATE TABLE student_subjects (
  enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  semester ENUM('1st Semester','2nd Semester') NOT NULL,
  enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id),
  FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
  UNIQUE KEY unique_enrollment (student_id,subject_id,school_year,semester)
) ENGINE=InnoDB;

CREATE TABLE grades (
  grade_id INT AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT NOT NULL,
  grading_period ENUM('1st Quarter','2nd Quarter','3rd Quarter','4th Quarter') NOT NULL,
  grade DECIMAL(5,2) NULL,
  remarks VARCHAR(100) NULL,
  date_recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enrollment_id) REFERENCES student_subjects(enrollment_id),
  UNIQUE KEY unique_grade (enrollment_id,grading_period),
  CHECK (grade IS NULL OR (grade >= 0 AND grade <= 100))
) ENGINE=InnoDB;

INSERT INTO year_levels (year_level_name) VALUES ('Grade 11'),('Grade 12');
INSERT INTO strands (strand_code,strand_name) VALUES
('STEM','Science, Technology, Engineering and Mathematics'),
('ABM','Accountancy, Business and Management'),
('HUMSS','Humanities and Social Sciences'),
('GAS','General Academic Strand'),
('TVL-ICT','Technical-Vocational-Livelihood - ICT');
