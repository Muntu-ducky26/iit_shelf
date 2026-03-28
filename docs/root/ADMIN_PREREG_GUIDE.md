# Admin Guide: Managing Pre-Registered Users

## Overview
Only users pre-registered by the administrator can create accounts in the IIT Shelf system. This ensures controlled access and institutional data integrity.

---

## Pre-Registration Tables

All pre-registration data is stored in the `iit_shelf` database with `PreReg_` prefix:

| Table Name | Purpose | Columns |
|-----------|---------|---------|
| **PreReg_Students** | Student pre-registration | email, roll, full_name, contact, session |
| **PreReg_Teachers** | Teacher pre-registration | email, designation, full_name, contact |
| **PreReg_Librarians** | Librarian pre-registration | email, full_name, contact |
| **PreReg_Directors** | Director pre-registration | email, full_name, contact |

---

## How It Works

### Registration Flow
1. User enters email in registration form
2. System checks if email exists in **any** PreReg table
3. **If found**: Registration allowed, user info auto-filled
4. **If not found**: Registration blocked with error message

### What Gets Auto-Filled
- **Full Name**: From pre-registration record
- **Role**: Determined by which table contains the email
- **Contact/Phone**: From pre-registration record

User only needs to provide:
- **Password** (new)
- **OTP verification** (sent to email)

---

## Admin Commands

### 1. Add New Pre-Registered Student

```sql
INSERT INTO PreReg_Students (email, roll, full_name, contact, session)
VALUES ('newstudent@iit.edu', 'CS2024001', 'Student Full Name', '+8801712345678', '2024-2025');
```

### 2. Add New Pre-Registered Teacher

```sql
INSERT INTO PreReg_Teachers (email, designation, full_name, contact)
VALUES ('newteacher@iit.edu', 'Assistant Professor', 'Dr. Teacher Name', '+8801812345678');
```

### 3. Add New Pre-Registered Librarian

```sql
INSERT INTO PreReg_Librarians (email, full_name, contact)
VALUES ('newlibrarian@iit.edu', 'Librarian Full Name', '+8801912345678');
```

### 4. Add New Pre-Registered Director

```sql
INSERT INTO PreReg_Directors (email, full_name, contact)
VALUES ('newdirector@iit.edu', 'Prof. Director Name', '+8801612345678');
```

---

## Bulk Import Examples

### Import Multiple Students from CSV

```sql
-- Prepare data in CSV format: email, roll, full_name, contact, session
LOAD DATA LOCAL INFILE '/path/to/students.csv'
INTO TABLE PreReg_Students
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(email, roll, full_name, contact, session);
```

### Import from Excel/Spreadsheet
1. Export to CSV format
2. Use LOAD DATA INFILE or INSERT statements
3. Or use phpMyAdmin import feature

---

## View Commands

### View All Pre-Registered Users (All Roles)

```sql
SELECT 'Student' as role, email, full_name, contact FROM PreReg_Students
UNION ALL
SELECT 'Teacher' as role, email, full_name, contact FROM PreReg_Teachers
UNION ALL
SELECT 'Librarian' as role, email, full_name, contact FROM PreReg_Librarians
UNION ALL
SELECT 'Director' as role, email, full_name, contact FROM PreReg_Directors
ORDER BY role, email;
```

### View Students Only

```sql
SELECT * FROM PreReg_Students ORDER BY roll;
```

### Check Specific Email

```sql
SELECT 'Student' as role, email, full_name FROM PreReg_Students WHERE email = 'student@iit.edu'
UNION
SELECT 'Teacher' as role, email, full_name FROM PreReg_Teachers WHERE email = 'student@iit.edu'
UNION
SELECT 'Librarian' as role, email, full_name FROM PreReg_Librarians WHERE email = 'student@iit.edu'
UNION
SELECT 'Director' as role, email, full_name FROM PreReg_Directors WHERE email = 'student@iit.edu';
```

### Count Pre-Registered Users

```sql
SELECT 
  (SELECT COUNT(*) FROM PreReg_Students) as students,
  (SELECT COUNT(*) FROM PreReg_Teachers) as teachers,
  (SELECT COUNT(*) FROM PreReg_Librarians) as librarians,
  (SELECT COUNT(*) FROM PreReg_Directors) as directors,
  (SELECT COUNT(*) FROM PreReg_Students) + 
  (SELECT COUNT(*) FROM PreReg_Teachers) + 
  (SELECT COUNT(*) FROM PreReg_Librarians) + 
  (SELECT COUNT(*) FROM PreReg_Directors) as total;
```

---

## Update Commands

### Update Student Information

```sql
UPDATE PreReg_Students 
SET full_name = 'New Name', contact = '+8801700000000', session = '2024-2025'
WHERE email = 'student@iit.edu';
```

### Update Teacher Designation

```sql
UPDATE PreReg_Teachers 
SET designation = 'Professor', full_name = 'Dr. Updated Name'
WHERE email = 'teacher@iit.edu';
```

---

## Delete Commands

### Remove Pre-Registered User (Before Registration)

```sql
-- Delete student
DELETE FROM PreReg_Students WHERE email = 'student@iit.edu';

-- Delete teacher
DELETE FROM PreReg_Teachers WHERE email = 'teacher@iit.edu';

-- Delete librarian
DELETE FROM PreReg_Librarians WHERE email = 'librarian@iit.edu';

-- Delete director
DELETE FROM PreReg_Directors WHERE email = 'director@iit.edu';
```

**⚠️ Warning**: This only prevents NEW registrations. If user already registered, delete from `Users` table.

---

## Migration/Transfer Commands

### Find Users Who Registered vs Not Registered

```sql
-- Pre-registered but not yet registered
SELECT p.email, p.full_name, 'Not Registered' as status
FROM PreReg_Students p
LEFT JOIN Users u ON p.email = u.email
WHERE u.email IS NULL;

-- Registered users
SELECT p.email, p.full_name, u.email_verified_at, 'Registered' as status
FROM PreReg_Students p
INNER JOIN Users u ON p.email = u.email;
```

---

## Security Notes

### Email Uniqueness
- Each email can exist in **only ONE** PreReg table
- System checks all tables and uses the first match
- Email is PRIMARY KEY (automatically unique)

### Data Validation
- Email format validated at application level
- Phone format: +880 country code recommended
- Roll numbers must be unique per student

### Access Control
- Only you (admin) can modify PreReg tables
- Regular users **cannot** add themselves
- Database credentials protected in backend config

---

## Troubleshooting

### User Says "Email not pre-registered"
1. Check if email exists:
   ```sql
   SELECT * FROM PreReg_Students WHERE email = 'user@example.com';
   ```
2. Verify email spelling (case-insensitive but spaces matter)
3. Add user if legitimate:
   ```sql
   INSERT INTO PreReg_Students (email, roll, full_name, contact, session)
   VALUES ('user@example.com', 'ROLL', 'Full Name', '+880XXXXXXXXXX', 'SESSION');
   ```

### User Already Exists Error
```sql
-- Check main database
SELECT email, role, email_verified_at FROM Users WHERE email = 'user@example.com';
```

If they forgot password, use password reset flow.

---

## Quick Admin Tasks

### Start of Semester: Bulk Add Students
1. Get student list from admissions office (CSV format)
2. Import to PreReg_Students table
3. Students can now register

### New Faculty Member
```sql
INSERT INTO PreReg_Teachers (email, designation, full_name, contact)
VALUES ('newfaculty@iit.edu', 'Lecturer', 'Dr. Faculty Name', '+8801800000000');
```

### Graduation Cleanup (End of Semester)
```sql
-- View graduating students
SELECT * FROM PreReg_Students WHERE session = '2019-2020';

-- Keep pre-reg data or delete if needed
-- (Users table keeps their account history)
```

---

## Backup Recommendations

### Export Pre-Registration Data
```bash
mysqldump -u iit_user -piit_password iit_shelf \
  PreReg_Students PreReg_Teachers PreReg_Librarians PreReg_Directors \
  > prereg_backup_$(date +%Y%m%d).sql
```

### Restore Pre-Registration Data
```bash
mysql -u iit_user -piit_password iit_shelf < prereg_backup_20260109.sql
```

---

## Contact for Issues

If you encounter issues with pre-registration system:
1. Check database connection: `mysql -u iit_user -piit_password iit_shelf`
2. Verify tables exist: `SHOW TABLES LIKE 'PreReg%';`
3. Check backend logs: `/tmp/iit_shelf_otp.log`
4. Test with known email: `curl -X POST http://localhost:8000/api/auth/send_register_otp.php -H "Content-Type: application/json" -d '{"email":"test@iit.edu"}'`

---

**Last Updated**: January 9, 2026
**Database**: iit_shelf (tables: PreReg_Students, PreReg_Teachers, PreReg_Librarians, PreReg_Directors)
**Access Level**: Administrator Only
