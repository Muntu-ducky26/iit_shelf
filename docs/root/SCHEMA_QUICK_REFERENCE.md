# Database Schema Quick Reference - Team Version

## Table Structure Overview

### 👥 Authentication & Users

**Temp_User_Verification**
```sql
email VARCHAR(150) [PK with purpose]
otp_code VARCHAR(20)
purpose ENUM('EmailVerification','PasswordReset')
created_at DATETIME
expires_at DATETIME
```

**Users**
```sql
email VARCHAR(150) [PK]
name VARCHAR(150)
password_hash VARCHAR(255)
role ENUM('Student','Teacher','Librarian','Director')
contact VARCHAR(20)  -- renamed from 'phone'
created_at DATETIME
last_login DATETIME
```

**Students**
```sql
email VARCHAR(150) [PK, FK→Users]
roll VARCHAR(50) [UNIQUE]
session VARCHAR(50)
```

**Teachers**
```sql
email VARCHAR(150) [PK, FK→Users]
designation VARCHAR(120)
```

---

### 📚 Books & Inventory

**Books**
```sql
isbn VARCHAR(30) [PK]
title VARCHAR(255)
author VARCHAR(255)
category VARCHAR(120)
publisher VARCHAR(255)
publication_year YEAR
edition VARCHAR(50)
description TEXT
pic_path VARCHAR(500)
-- NO copies_total, copies_available (calc from Book_Copies)
-- NO language, keywords, is_deleted, timestamps
```

**Book_Copies**
```sql
copy_id VARCHAR(60) [PK]
isbn VARCHAR(30) [FK→Books]
shelf_id INT [FK→Shelves]
compartment_no INT
subcompartment_no INT
status ENUM('Available','Borrowed','Reserved','Lost','Discarded')
condition_note VARCHAR(255)
-- NO is_deleted, timestamps
```

**Shelves**
```sql
shelf_id INT [PK, AUTO_INCREMENT]
total_compartments INT
total_subcompartments INT
is_deleted BOOLEAN
```

**Digital_Resources**
```sql
resource_id INT [PK, AUTO_INCREMENT]
isbn VARCHAR(30) [FK→Books]
file_name VARCHAR(255)
file_path VARCHAR(500)
resource_type ENUM('PDF','E-Book','Other')
uploaded_by VARCHAR(150) [FK→Users]
uploaded_at DATETIME
```

---

### 📖 Courses

**Courses**
```sql
course_id VARCHAR(50) [PK]
course_name VARCHAR(200)
semester VARCHAR(50)
```

**Book_Courses**
```sql
isbn VARCHAR(30) [PK, FK→Books]
course_id VARCHAR(50) [PK, FK→Courses]
```

**Course_Prerequisites**
```sql
course_id VARCHAR(50) [PK, FK→Courses]
prerequisite_course_id VARCHAR(50) [PK, FK→Courses]
```

---

### 🔄 Transactions & Circulation

**Transaction_Requests**
```sql
request_id INT [PK, AUTO_INCREMENT]
isbn VARCHAR(30) [FK→Books]
requester_email VARCHAR(150) [FK→Users]
request_date DATETIME
status ENUM('Pending','Approved','Rejected')
reviewed_by VARCHAR(150) [FK→Users]
reviewed_at DATETIME
```

**Approved_Transactions**
```sql
transaction_id INT [PK, AUTO_INCREMENT]
request_id INT [FK→Transaction_Requests] -- REQUIRED!
copy_id VARCHAR(60) [FK→Book_Copies]
issue_date DATE
due_date DATE
return_date DATE
status ENUM('Borrowed','Returned','Overdue','Lost')
-- NO user_email, isbn, issued_by (get via JOINs)
-- NO created_at, updated_at
```

**Reservations**
```sql
reservation_id INT [PK, AUTO_INCREMENT]
isbn VARCHAR(30) [FK→Books]
user_email VARCHAR(150) [FK→Users]
queue_position INT
status ENUM('Active','Cancelled','Completed')
created_at DATETIME
notified_at DATETIME
expires_at DATETIME
```

---

### 💰 Fines & Payments

**Fines**
```sql
fine_id INT [PK, AUTO_INCREMENT]
transaction_id INT [FK→Approved_Transactions]
user_email VARCHAR(150) [FK→Users]
amount DECIMAL(10,2)
description VARCHAR(255)
paid BOOLEAN
payment_date DATETIME
-- NO fine_type, days_overdue, timestamps
```

**Payments**
```sql
payment_id INT [PK, AUTO_INCREMENT]
amount DECIMAL(10,2)
status ENUM('Pending','Completed','Failed','Refunded')
gateway_txn_id VARCHAR(100)
paid_at DATETIME
-- NO fine_id, user_email, payment_method, currency, etc.
```

**fine_payment** (junction table)
```sql
fine_id INT [PK, FK→Fines]
payment_id INT [PK, FK→Payments]
```

---

### 📊 Reports & Notifications

**Reports**
```sql
report_id INT [PK, AUTO_INCREMENT]
type ENUM('MostBorrowed','Overdue','FinesCollected','MostRequested')
generated_by VARCHAR(150) [FK→Users]
generated_at DATETIME
```

**Notifications**
```sql
notification_id INT [PK, AUTO_INCREMENT]
user_email VARCHAR(150) [FK→Users]
message TEXT
type ENUM('DueDateReminder','ReservedBookAvailable','PaymentConfirmation','System')
sent_at DATETIME
```

**Requests** (User book requests)
```sql
request_id INT [PK, AUTO_INCREMENT]
requester_identifier VARCHAR(150) [FK→Users]
isbn VARCHAR(30) [FK→Books]
title VARCHAR(255)
author VARCHAR(255)
pdf_path VARCHAR(500)
category VARCHAR(120)
publisher VARCHAR(255)
publication_year YEAR
edition VARCHAR(50)
description TEXT
pic_path VARCHAR(500)
status ENUM('Pending','Approved','Rejected')
approved_by VARCHAR(150) [FK→Users]
approved_at DATETIME
```

---

## Common Queries

### Get User Profile
```sql
SELECT u.*, s.roll, s.session 
FROM Users u
LEFT JOIN Students s ON u.email = s.email
WHERE u.email = 'user@example.com';
```

### Get Available Books with Copy Count
```sql
SELECT b.*, 
  (SELECT COUNT(*) FROM Book_Copies WHERE isbn = b.isbn AND status = 'Available') as copies_available
FROM Books b;
```

### Get User's Active Borrows
```sql
SELECT at.*, tr.requester_email, bc.isbn, bk.title
FROM Approved_Transactions at
JOIN Transaction_Requests tr ON at.request_id = tr.request_id
JOIN Book_Copies bc ON at.copy_id = bc.copy_id
JOIN Books bk ON bc.isbn = bk.isbn
WHERE tr.requester_email = 'user@example.com' 
  AND at.status = 'Borrowed';
```

### Calculate Overdue Fines
```sql
SELECT at.transaction_id,
  DATEDIFF(CURDATE(), at.due_date) as days_overdue,
  DATEDIFF(CURDATE(), at.due_date) * 5 as fine_amount
FROM Approved_Transactions at
WHERE at.status = 'Borrowed' 
  AND at.due_date < CURDATE();
```

### Get Book with Course Info
```sql
SELECT b.*, c.course_name, c.semester
FROM Books b
LEFT JOIN Book_Courses bc ON b.isbn = bc.isbn
LEFT JOIN Courses c ON bc.course_id = c.course_id
WHERE b.isbn = '978-0-13-110362-7';
```

---

## Field Mapping Reference

| Old Field | New Field | Notes |
|-----------|-----------|-------|
| `users.phone` | `Users.contact` | Renamed |
| `users.email_verified_at` | (removed) | Verification simplified |
| `users.is_active` | (removed) | Not in team schema |
| `users.updated_at` | (removed) | No auto timestamps |
| `books.copies_total` | (calculated) | Count from Book_Copies |
| `books.copies_available` | (calculated) | Count WHERE status='Available' |
| `books.language` | (removed) | Not in team schema |
| `books.keywords` | (removed) | Not in team schema |
| `approved_transactions.user_email` | `Transaction_Requests.requester_email` | Via JOIN |
| `approved_transactions.isbn` | `Book_Copies.isbn` | Via JOIN |
| `fines.fine_type` | (removed) | Use description only |
| `fines.days_overdue` | (calculated) | Calculate on query |

---

## Key Differences from Old Schema

1. ✅ **Table names are PascalCase** (Users, not users)
2. ✅ **No email verification workflow** (email_verified_at removed)
3. ✅ **Book copy counts are calculated** (not stored in Books table)
4. ✅ **Transactions require requests** (request_id is NOT NULL)
5. ✅ **Fines-Payments is many-to-many** (via fine_payment junction)
6. ✅ **Minimal timestamps** (only where necessary)
7. ✅ **User email from transaction via JOIN** (not stored redundantly)

---

## Migration Commands

### Fresh Install
```bash
mysql -u iit_user -p iit_shelf < backend/database/schema_team_migration.sql
```

### Migrate Existing DB
```bash
# Backup first!
mysqldump -u iit_user -p iit_shelf > backup.sql

# Then migrate
mysql -u iit_user -p iit_shelf < backend/database/migration_to_team_schema.sql
```

---

**Last Updated:** January 6, 2026  
**Schema Version:** Team Unified Schema v1.0
