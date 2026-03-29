-- ============================================================
-- IIT SHELF - Combined Database Installer
-- Creates:
--   1. iit_shelf
--   2. iit_shelf_prereg
--   3. iit_shelf_auth_temp
--
-- This file consolidates the working schema and runtime-required
-- add-ons from the repo into one install path.
-- ============================================================

-- ============================================================
-- DATABASES
-- ============================================================
CREATE DATABASE IF NOT EXISTS iit_shelf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS iit_shelf_prereg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS iit_shelf_auth_temp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================
-- MAIN APP DATABASE
-- ============================================================
USE iit_shelf;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS fine_payment;
DROP TABLE IF EXISTS PDF_Uploads;
DROP TABLE IF EXISTS Notifications;
DROP TABLE IF EXISTS Reports;
DROP TABLE IF EXISTS Requests;
DROP TABLE IF EXISTS Fines;
DROP TABLE IF EXISTS Payments;
DROP TABLE IF EXISTS Reservations;
DROP TABLE IF EXISTS Approved_Transactions;
DROP TABLE IF EXISTS Transaction_Requests;
DROP TABLE IF EXISTS Digital_Resources;
DROP TABLE IF EXISTS Book_Courses;
DROP TABLE IF EXISTS Book_Copies;
DROP TABLE IF EXISTS Books;
DROP TABLE IF EXISTS Shelves;
DROP TABLE IF EXISTS Course_Prerequisites;
DROP TABLE IF EXISTS Courses;
DROP TABLE IF EXISTS Teachers;
DROP TABLE IF EXISTS Students;
DROP TABLE IF EXISTS Users;
DROP TABLE IF EXISTS Temp_User_Verification;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE Temp_User_Verification (
  email VARCHAR(150) NOT NULL,
  otp_code VARCHAR(20) NOT NULL,
  purpose ENUM('EmailVerification','PasswordReset') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (email, purpose),
  INDEX idx_tuv_expires (expires_at),
  INDEX idx_tuv_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Users (
  email VARCHAR(150) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Student','Teacher','Librarian','Director') NOT NULL DEFAULT 'Student',
  contact VARCHAR(20),
  profile_image VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Students (
  email VARCHAR(150) PRIMARY KEY,
  roll VARCHAR(50) NOT NULL UNIQUE,
  session VARCHAR(50),
  CONSTRAINT fk_students_users FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_students_roll (roll)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Teachers (
  email VARCHAR(150) PRIMARY KEY,
  designation VARCHAR(120),
  CONSTRAINT fk_teachers_users FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Courses (
  course_id VARCHAR(50) PRIMARY KEY,
  course_name VARCHAR(200) NOT NULL,
  semester VARCHAR(50) NOT NULL,
  INDEX idx_courses_semester (semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Course_Prerequisites (
  course_id VARCHAR(50) NOT NULL,
  prerequisite_course_id VARCHAR(50) NOT NULL,
  PRIMARY KEY (course_id, prerequisite_course_id),
  CONSTRAINT fk_cp_course FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_prereq FOREIGN KEY (prerequisite_course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Shelves (
  shelf_id INT PRIMARY KEY AUTO_INCREMENT,
  total_compartments INT NOT NULL DEFAULT 0,
  total_subcompartments INT NOT NULL DEFAULT 0,
  is_deleted BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Books (
  isbn VARCHAR(30) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  category VARCHAR(120),
  publisher VARCHAR(255),
  publication_year YEAR,
  edition VARCHAR(50),
  description TEXT,
  pic_path VARCHAR(500),
  INDEX idx_books_title (title),
  INDEX idx_books_author (author),
  INDEX idx_books_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Book_Courses (
  isbn VARCHAR(30) NOT NULL,
  course_id VARCHAR(50) NOT NULL,
  PRIMARY KEY (isbn, course_id),
  CONSTRAINT fk_bc_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_bc_course FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE,
  INDEX idx_book_courses_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Book_Copies (
  copy_id VARCHAR(60) PRIMARY KEY,
  isbn VARCHAR(30) NOT NULL,
  shelf_id INT,
  compartment_no INT,
  subcompartment_no INT,
  status ENUM('Available','Borrowed','Reserved','Unavailable','Lost','Discarded') DEFAULT 'Available',
  condition_note VARCHAR(255),
  CONSTRAINT fk_copy_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_copy_shelf FOREIGN KEY (shelf_id) REFERENCES Shelves(shelf_id) ON DELETE SET NULL,
  INDEX idx_copy_status (status),
  INDEX idx_copy_isbn (isbn),
  INDEX idx_copy_isbn_status (isbn, status),
  INDEX idx_copy_shelf_loc (shelf_id, compartment_no, subcompartment_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Digital_Resources (
  resource_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30),
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  resource_type ENUM('PDF','E-Book','Other') NOT NULL,
  uploaded_by VARCHAR(150),
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dr_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_dr_uploader FOREIGN KEY (uploaded_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_dr_isbn (isbn),
  INDEX idx_dr_isbn_type (isbn, resource_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Transaction_Requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  requester_email VARCHAR(150) NOT NULL,
  request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  reviewed_by VARCHAR(150),
  reviewed_at DATETIME,
  CONSTRAINT fk_tr_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_tr_requester FOREIGN KEY (requester_email) REFERENCES Users(email) ON DELETE CASCADE,
  CONSTRAINT fk_tr_reviewer FOREIGN KEY (reviewed_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_tr_status (status),
  INDEX idx_tr_requester (requester_email),
  INDEX idx_tr_requester_status (requester_email, status),
  INDEX idx_tr_isbn_status (isbn, status),
  INDEX idx_tr_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Approved_Transactions (
  transaction_id INT PRIMARY KEY AUTO_INCREMENT,
  request_id INT NOT NULL,
  copy_id VARCHAR(60) NOT NULL,
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE,
  status ENUM('Borrowed','Returned','Overdue','Lost') DEFAULT 'Borrowed',
  CONSTRAINT fk_at_request FOREIGN KEY (request_id) REFERENCES Transaction_Requests(request_id) ON DELETE CASCADE,
  CONSTRAINT fk_at_copy FOREIGN KEY (copy_id) REFERENCES Book_Copies(copy_id) ON DELETE CASCADE,
  INDEX idx_at_status (status),
  INDEX idx_at_due (due_date),
  INDEX idx_at_copy (copy_id),
  INDEX idx_at_request (request_id),
  INDEX idx_at_status_due (status, due_date),
  INDEX idx_at_copy_status (copy_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Reservations (
  reservation_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  queue_position INT NOT NULL,
  status ENUM('Active','Cancelled','Completed','Expired') DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notified_at DATETIME,
  expires_at DATETIME,
  completed_at DATETIME NULL,
  CONSTRAINT fk_res_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_res_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_res_status (status),
  INDEX idx_res_user (user_email),
  INDEX idx_res_isbn_status (isbn, status),
  INDEX idx_res_user_status (user_email, status),
  INDEX idx_res_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Fines (
  fine_id INT PRIMARY KEY AUTO_INCREMENT,
  transaction_id INT NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  paid BOOLEAN DEFAULT FALSE,
  payment_date DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fine_txn FOREIGN KEY (transaction_id) REFERENCES Approved_Transactions(transaction_id) ON DELETE CASCADE,
  CONSTRAINT fk_fine_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_fine_user (user_email),
  INDEX idx_fine_paid (paid),
  INDEX idx_fine_user_paid (user_email, paid),
  INDEX idx_fine_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Payments (
  payment_id INT PRIMARY KEY AUTO_INCREMENT,
  user_email VARCHAR(150) NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) NULL,
  status ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
  gateway_txn_id VARCHAR(100),
  paid_at DATETIME,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_payment_status (status),
  INDEX idx_payment_user (user_email),
  INDEX idx_payment_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fine_payment (
  fine_id INT NOT NULL,
  payment_id INT NOT NULL,
  PRIMARY KEY (fine_id, payment_id),
  CONSTRAINT fk_fp_fine FOREIGN KEY (fine_id) REFERENCES Fines(fine_id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_payment FOREIGN KEY (payment_id) REFERENCES Payments(payment_id) ON DELETE CASCADE,
  INDEX idx_fp_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Reports (
  report_id INT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('MostBorrowed','Overdue','FinesCollected','MostRequested') NOT NULL,
  generated_by VARCHAR(150) NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_user FOREIGN KEY (generated_by) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_report_type (type),
  INDEX idx_report_generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Notifications (
  notification_id INT PRIMARY KEY AUTO_INCREMENT,
  user_email VARCHAR(150),
  message TEXT NOT NULL,
  type ENUM(
    'DueDateReminder',
    'DueDateToday',
    'ReservedBookAvailable',
    'ReservationQueueUpdate',
    'ReservationMissed',
    'PaymentConfirmation',
    'BorrowRequestApproved',
    'BorrowRequestPending',
    'BorrowRequestRejected',
    'ReturnRequestApproved',
    'ReturnRequestPending',
    'AdditionRequestApproved',
    'FineReminder',
    'FineLimitReached',
    'BookAdded',
    'InventoryUpdate',
    'UserTransaction',
    'ReportGenerated',
    'System'
  ) DEFAULT 'System',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_notif_user (user_email),
  INDEX idx_notif_type (type),
  INDEX idx_notif_user_type (user_email, type),
  INDEX idx_notif_user_type_sent (user_email, type, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  requester_identifier VARCHAR(150) NOT NULL,
  isbn VARCHAR(30),
  title VARCHAR(255),
  author VARCHAR(255),
  pdf_path VARCHAR(500),
  category VARCHAR(120),
  publisher VARCHAR(255),
  publication_year YEAR,
  edition VARCHAR(50),
  description TEXT,
  pic_path VARCHAR(500),
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  approved_by VARCHAR(150),
  approved_at DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_req_requester FOREIGN KEY (requester_identifier) REFERENCES Users(email) ON DELETE CASCADE,
  CONSTRAINT fk_req_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_req_approver FOREIGN KEY (approved_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_req_status (status),
  INDEX idx_req_requester (requester_identifier),
  INDEX idx_req_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PDF_Uploads (
  upload_id INT PRIMARY KEY AUTO_INCREMENT,
  uploader_email VARCHAR(150) NOT NULL,
  book_isbn VARCHAR(30),
  book_id INT NULL,
  pdf_url VARCHAR(500) NOT NULL,
  upload_type ENUM('Update','New Request') NOT NULL,
  notes TEXT,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by VARCHAR(150),
  reviewed_at DATETIME,
  rejection_reason TEXT,
  CONSTRAINT fk_pdf_uploader FOREIGN KEY (uploader_email) REFERENCES Users(email) ON DELETE CASCADE,
  CONSTRAINT fk_pdf_isbn FOREIGN KEY (book_isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_pdf_reviewer FOREIGN KEY (reviewed_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_pdf_uploader (uploader_email),
  INDEX idx_pdf_status (status),
  INDEX idx_pdf_submitted (submitted_at),
  INDEX idx_pdf_isbn (book_isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAMPLE DATA
-- ============================================================
INSERT INTO Users (email, name, password_hash, role, contact) VALUES
('student@iit.edu', 'Test Student', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', '01712345678'),
('teacher@iit.edu', 'Test Teacher', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teacher', '01787654321'),
('librarian@iit.edu', 'Test Librarian', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Librarian', '01756789012'),
('director@iit.edu', 'Test Director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Director', '01798765432')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO Students (email, roll, session) VALUES
('student@iit.edu', '2021001', '2021-22')
ON DUPLICATE KEY UPDATE roll = VALUES(roll), session = VALUES(session);

INSERT INTO Teachers (email, designation) VALUES
('teacher@iit.edu', 'Professor')
ON DUPLICATE KEY UPDATE designation = VALUES(designation);

INSERT INTO Courses (course_id, course_name, semester) VALUES
('CSE101', 'Introduction to Programming', '1'),
('CSE201', 'Data Structures', '2'),
('CSE301', 'Algorithms', '3'),
('CSE401', 'Database Systems', '4')
ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), semester = VALUES(semester);

INSERT INTO Shelves (total_compartments, total_subcompartments) VALUES
(10, 5),
(15, 8),
(12, 6);

INSERT INTO Books (isbn, title, author, category, publisher, publication_year, edition, description) VALUES
('978-0-13-110362-7', 'Introduction to Algorithms', 'Cormen, Leiserson, Rivest, Stein', 'Computer Science', 'MIT Press', 2009, '3rd', 'Comprehensive guide to algorithms'),
('978-0-13-235088-4', 'Clean Code', 'Robert C. Martin', 'Software Engineering', 'Prentice Hall', 2008, '1st', 'A Handbook of Agile Software Craftsmanship'),
('978-0-134-68599-1', 'Operating System Concepts', 'Silberschatz, Galvin, Gagne', 'Computer Science', 'Wiley', 2018, '10th', 'Operating system fundamentals')
ON DUPLICATE KEY UPDATE title = VALUES(title), author = VALUES(author);

INSERT INTO Book_Copies (copy_id, isbn, shelf_id, compartment_no, subcompartment_no, status) VALUES
('978-0-13-110362-7-001', '978-0-13-110362-7', 1, 1, 1, 'Available'),
('978-0-13-110362-7-002', '978-0-13-110362-7', 1, 1, 2, 'Available'),
('978-0-13-235088-4-001', '978-0-13-235088-4', 1, 2, 1, 'Available'),
('978-0-134-68599-1-001', '978-0-134-68599-1', 2, 1, 1, 'Available')
ON DUPLICATE KEY UPDATE isbn = VALUES(isbn), status = VALUES(status);

INSERT INTO Book_Courses (isbn, course_id) VALUES
('978-0-13-110362-7', 'CSE301'),
('978-0-134-68599-1', 'CSE401')
ON DUPLICATE KEY UPDATE course_id = VALUES(course_id);

-- ============================================================
-- PRE-REGISTRATION DATABASE
-- ============================================================
USE iit_shelf_prereg;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS Library_Settings;
DROP TABLE IF EXISTS PreReg_Directors;
DROP TABLE IF EXISTS PreReg_Librarians;
DROP TABLE IF EXISTS PreReg_Teachers;
DROP TABLE IF EXISTS PreReg_Students;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE PreReg_Students (
  email VARCHAR(150) NOT NULL PRIMARY KEY,
  roll VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  contact VARCHAR(20),
  session VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PreReg_Teachers (
  email VARCHAR(150) NOT NULL PRIMARY KEY,
  designation VARCHAR(120) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  contact VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PreReg_Librarians (
  email VARCHAR(150) NOT NULL PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  contact VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PreReg_Directors (
  email VARCHAR(150) NOT NULL PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  contact VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Library_Settings (
  setting_id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(50) UNIQUE NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_library_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO PreReg_Students (email, roll, full_name, contact, session) VALUES
('student1@iit.edu', 'CS2023001', 'Ahmed Hassan', '+8801712345671', '2023-2024'),
('student2@iit.edu', 'CS2023002', 'Fatima Rahman', '+8801712345672', '2023-2024'),
('student3@iit.edu', 'EE2023001', 'Karim Abdullah', '+8801712345673', '2023-2024'),
('tamal2517@student.nstu.edu.bd', 'CS2021017', 'Tamal Ahmed', '+8801712345678', '2021-2022')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), contact = VALUES(contact), session = VALUES(session);

INSERT INTO PreReg_Teachers (email, designation, full_name, contact) VALUES
('teacher1@iit.edu', 'Associate Professor', 'Dr. Ayesha Khan', '+8801812345671'),
('teacher2@iit.edu', 'Assistant Professor', 'Dr. Mohammad Ali', '+8801812345672'),
('teacher3@iit.edu', 'Professor', 'Dr. Sarah Ahmed', '+8801812345673')
ON DUPLICATE KEY UPDATE designation = VALUES(designation), full_name = VALUES(full_name), contact = VALUES(contact);

INSERT INTO PreReg_Librarians (email, full_name, contact) VALUES
('librarian@iit.edu', 'Zainab Hossain', '+8801912345671'),
('librarian2@iit.edu', 'Ibrahim Khan', '+8801912345672')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), contact = VALUES(contact);

INSERT INTO PreReg_Directors (email, full_name, contact) VALUES
('director@iit.edu', 'Prof. Dr. Rahman Mahmud', '+8801612345671')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), contact = VALUES(contact);

INSERT INTO Library_Settings (setting_key, setting_value) VALUES
('library_email', 'library@nstu.edu.bd'),
('library_phone', '+880 1234-567890'),
('library_hours', 'Mon-Fri: 9:00 AM - 5:00 PM'),
('library_location', 'Central Library, NSTU Campus')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ============================================================
-- AUTH TEMP DATABASE
-- ============================================================
USE iit_shelf_auth_temp;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS Temp_User_Verification;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE Temp_User_Verification (
  email VARCHAR(150) NOT NULL,
  otp_code VARCHAR(20) NOT NULL,
  purpose ENUM('EmailVerification','PasswordReset') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (email, purpose),
  INDEX idx_auth_temp_created (created_at),
  INDEX idx_auth_temp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OPTIONAL EVENTS / PROCEDURES
-- ============================================================
USE iit_shelf;

DROP EVENT IF EXISTS send_due_date_reminders;
DROP EVENT IF EXISTS send_due_date_today_reminders;
DROP EVENT IF EXISTS send_fine_reminders;
DROP EVENT IF EXISTS cleanup_old_notifications;
DROP EVENT IF EXISTS check_reservation_expiry;
DROP EVENT IF EXISTS check_fine_limits;
DROP EVENT IF EXISTS cleanup_expired_borrow_requests;
DROP PROCEDURE IF EXISTS cleanup_expired_requests_now;

DELIMITER //

CREATE EVENT send_due_date_reminders
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '08:00:00')
DO
BEGIN
  INSERT INTO Notifications (user_email, message, type, sent_at)
  SELECT DISTINCT
    tr.requester_email,
    CONCAT('Reminder: Your borrowed book "', b.title, '" is due tomorrow (',
           DATE_FORMAT(at.due_date, '%M %d, %Y'), '). Please return it to avoid late fees.'),
    'DueDateReminder',
    NOW()
  FROM Approved_Transactions at
  JOIN Transaction_Requests tr ON at.request_id = tr.request_id
  JOIN Book_Copies bc ON at.copy_id = bc.copy_id
  JOIN Books b ON bc.isbn = b.isbn
  WHERE at.status = 'Borrowed'
    AND at.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    AND NOT EXISTS (
      SELECT 1
      FROM Notifications n
      WHERE n.user_email = tr.requester_email
        AND n.type = 'DueDateReminder'
        AND n.message LIKE CONCAT('%', b.title, '%')
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    );
END//

CREATE EVENT send_due_date_today_reminders
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '07:00:00')
DO
BEGIN
  INSERT INTO Notifications (user_email, message, type, sent_at)
  SELECT DISTINCT
    tr.requester_email,
    CONCAT('Your borrowed book "', b.title, '" is due TODAY (',
           DATE_FORMAT(at.due_date, '%M %d, %Y'), '). Please return it to avoid late fees.'),
    'DueDateToday',
    NOW()
  FROM Approved_Transactions at
  JOIN Transaction_Requests tr ON at.request_id = tr.request_id
  JOIN Book_Copies bc ON at.copy_id = bc.copy_id
  JOIN Books b ON bc.isbn = b.isbn
  WHERE at.status = 'Borrowed'
    AND at.due_date = CURDATE()
    AND NOT EXISTS (
      SELECT 1
      FROM Notifications n
      WHERE n.user_email = tr.requester_email
        AND n.type = 'DueDateToday'
        AND n.message LIKE CONCAT('%', b.title, '%')
        AND DATE(n.sent_at) = CURDATE()
    );
END//

CREATE EVENT send_fine_reminders
ON SCHEDULE EVERY 3 DAY
STARTS TIMESTAMP(CURDATE(), '09:00:00')
DO
BEGIN
  INSERT INTO Notifications (user_email, message, type, sent_at)
  SELECT
    f.user_email,
    CONCAT('You have an unpaid fine of ', f.amount, '. Reason: ', COALESCE(f.description, 'Library fine'),
           '. Please pay it to avoid further penalties.'),
    'FineReminder',
    NOW()
  FROM Fines f
  WHERE f.paid = 0
    AND f.created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND NOT EXISTS (
      SELECT 1
      FROM Notifications n
      WHERE n.user_email = f.user_email
        AND n.type = 'FineReminder'
        AND n.message LIKE CONCAT('%', f.amount, '%')
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    );
END//

CREATE EVENT cleanup_old_notifications
ON SCHEDULE EVERY 1 MONTH
STARTS TIMESTAMP(CURDATE(), '02:00:00')
DO
BEGIN
  DELETE FROM Notifications
  WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END//

CREATE EVENT check_reservation_expiry
ON SCHEDULE EVERY 6 HOUR
DO
BEGIN
  INSERT INTO Notifications (user_email, message, type, sent_at)
  SELECT
    r.user_email,
    CONCAT('You missed your reservation pickup window for "', b.title, '". The book is now available for others.'),
    'ReservationMissed',
    NOW()
  FROM Reservations r
  JOIN Books b ON r.isbn = b.isbn
  WHERE r.status = 'Active'
    AND r.expires_at IS NOT NULL
    AND r.expires_at < NOW()
    AND NOT EXISTS (
      SELECT 1
      FROM Notifications n
      WHERE n.user_email = r.user_email
        AND n.type = 'ReservationMissed'
        AND n.message LIKE CONCAT('%', b.title, '%')
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    );

  UPDATE Reservations
  SET status = 'Expired'
  WHERE status = 'Active'
    AND expires_at IS NOT NULL
    AND expires_at < NOW();
END//

CREATE EVENT check_fine_limits
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '06:00:00')
DO
BEGIN
  INSERT INTO Notifications (user_email, message, type, sent_at)
  SELECT
    f.user_email,
    CONCAT('Your total unpaid fines (', SUM(f.amount), ' TK) have exceeded the limit. Please pay your fines to continue borrowing books.'),
    'FineLimitReached',
    NOW()
  FROM Fines f
  WHERE f.paid = 0
  GROUP BY f.user_email
  HAVING SUM(f.amount) >= 200
    AND NOT EXISTS (
      SELECT 1
      FROM Notifications n
      WHERE n.user_email = f.user_email
        AND n.type = 'FineLimitReached'
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    );
END//

CREATE EVENT cleanup_expired_borrow_requests
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
DO
BEGIN
  DELETE FROM Transaction_Requests
  WHERE status = 'Pending'
    AND request_date < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END//

CREATE PROCEDURE cleanup_expired_requests_now()
BEGIN
  DELETE FROM Transaction_Requests
  WHERE status = 'Pending'
    AND request_date < DATE_SUB(NOW(), INTERVAL 24 HOUR);

  SELECT ROW_COUNT() AS deleted_requests;
END//

DELIMITER ;

-- ============================================================
-- FINAL STATUS
-- ============================================================
SELECT 'Combined installer completed successfully' AS status;
SELECT 'Databases created: iit_shelf, iit_shelf_prereg, iit_shelf_auth_temp' AS info;
