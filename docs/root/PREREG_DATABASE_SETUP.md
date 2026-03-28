# Pre-Registration Database Migration Guide

## Overview
The pre-registration tables have been moved to a separate database `iit_shelf_prereg` to improve data organization and security.

**Changes:**
- ✅ Pre-registration tables now in separate database: `iit_shelf_prereg`
- ✅ Teachers table includes `designation` column
- ✅ Backend config updated to use new database
- ✅ All 4 pre-registration tables migrated with data

## Pre-Registration Tables Structure

### Table: PreReg_Students (iit_shelf_prereg)
```
Columns:
- id (INT, Primary Key, Auto Increment)
- email (VARCHAR 255, UNIQUE NOT NULL)
- roll (VARCHAR 50, NOT NULL)
- full_name (VARCHAR 255, NOT NULL)
- contact (VARCHAR 20, nullable)
- session (VARCHAR 50, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

Index: idx_email on email
```

### Table: PreReg_Teachers (iit_shelf_prereg)
```
Columns:
- id (INT, Primary Key, Auto Increment)
- email (VARCHAR 255, UNIQUE NOT NULL)
- designation (VARCHAR 100, NOT NULL) ← NEW COLUMN
- full_name (VARCHAR 255, NOT NULL)
- contact (VARCHAR 20, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

Index: idx_email on email
```

### Table: PreReg_Librarians (iit_shelf_prereg)
```
Columns:
- id (INT, Primary Key, Auto Increment)
- email (VARCHAR 255, UNIQUE NOT NULL)
- full_name (VARCHAR 255, NOT NULL)
- contact (VARCHAR 20, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

Index: idx_email on email
```

### Table: PreReg_Directors (iit_shelf_prereg)
```
Columns:
- id (INT, Primary Key, Auto Increment)
- email (VARCHAR 255, UNIQUE NOT NULL)
- full_name (VARCHAR 255, NOT NULL)
- contact (VARCHAR 20, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

Index: idx_email on email
```

## Setup Instructions

### Step 1: Create the Database with DBeaver

1. Open DBeaver
2. Connect to your MariaDB server (localhost:3306)
3. Run the SQL script: `backend/setup_prereg_database.sql`
   - This creates the new database
   - Sets up all 4 tables
   - Migrates existing data from old location
   - Grants privileges to iit_user

### Step 2: Verify Database Connection

The backend is already configured to use the new database:
- File: `backend/config/db_pre_registration.php`
- Database: `iit_shelf_prereg`
- User: `iit_user`
- Password: `iit_password`

### Step 3: Verify Data Migration

After running the SQL script, check the data was migrated:

```sql
-- Check PreReg_Students
SELECT COUNT(*) as total FROM iit_shelf_prereg.PreReg_Students;

-- Check PreReg_Teachers (with designation)
SELECT email, designation, full_name FROM iit_shelf_prereg.PreReg_Teachers;

-- Check PreReg_Librarians
SELECT COUNT(*) as total FROM iit_shelf_prereg.PreReg_Librarians;

-- Check PreReg_Directors
SELECT COUNT(*) as total FROM iit_shelf_prereg.PreReg_Directors;
```

## Backend Code Changes

### File: `backend/config/db_pre_registration.php`
```php
class PreRegistrationDatabase {
    private $host = 'localhost';
    private $db_name = 'iit_shelf_prereg';  // ← Changed from 'iit_shelf'
    private $username = 'iit_user';
    private $password = 'iit_password';
    // ... rest of code
}
```

### File: `backend/api/auth/send_register_otp.php`
This file already expects the designation column in PreReg_Teachers:
```php
$stmt = $preDb->prepare("SELECT email, designation, full_name, contact FROM PreReg_Teachers WHERE email = :email");
```

The designation is now properly handled in the separate database.

## Testing Pre-Registration Flow

### Test Case 1: Student Registration
1. Pre-register a student in `iit_shelf_prereg.PreReg_Students`
2. Use Flutter app to register with that student's email
3. OTP is sent
4. After OTP verification, name and phone are auto-filled from pre-registration
5. Set password to complete registration

### Test Case 2: Teacher Registration with Designation
1. Pre-register a teacher in `iit_shelf_prereg.PreReg_Teachers`:
   ```sql
   INSERT INTO PreReg_Teachers (email, designation, full_name, contact)
   VALUES ('teacher1@iit.edu', 'Assistant Professor', 'Dr. Ahmed Khan', '01700000001');
   ```
2. Use Flutter app to register with teacher's email
3. Designation is retrieved and stored in main `iit_shelf.users` table
4. Complete registration

### Sample Pre-Registration Data

#### Students
```sql
INSERT INTO PreReg_Students (email, roll, full_name, contact, session)
VALUES 
('student1@iit.edu', 'CSE-001', 'Karim Abdullah', '01700000001', '2023-2024'),
('student2@iit.edu', 'CSE-002', 'Fatima Rahman', '01700000002', '2023-2024'),
('student3@iit.edu', 'EEE-001', 'Shakib Ibne Rashid', '01886995292', '2023-2024');
```

#### Teachers
```sql
INSERT INTO PreReg_Teachers (email, designation, full_name, contact)
VALUES 
('teacher1@iit.edu', 'Assistant Professor', 'Dr. Ahmed Khan', '01700000101'),
('teacher2@iit.edu', 'Lecturer', 'Dr. Farhana Haque', '01700000102'),
('teacher3@iit.edu', 'Professor', 'Dr. Mahmud Hasan', '01700000103');
```

#### Librarians
```sql
INSERT INTO PreReg_Librarians (email, full_name, contact)
VALUES 
('librarian1@iit.edu', 'Mohammad Karim', '01700000201'),
('librarian2@iit.edu', 'Nasrin Akhter', '01700000202');
```

#### Directors
```sql
INSERT INTO PreReg_Directors (email, full_name, contact)
VALUES 
('director1@iit.edu', 'Dr. Hassan Ahmed', '01700000301'),
('director2@iit.edu', 'Dr. Shahin Azad', '01700000302');
```

## Database Architecture

```
Main Database: iit_shelf
├── users (all roles after registration)
├── roles
├── students
├── teachers
├── librarians
├── directors
├── books
├── shelves
├── borrow_requests
├── reports
└── ... (other tables)

Pre-Registration Database: iit_shelf_prereg
├── PreReg_Students (email, roll, full_name, contact, session)
├── PreReg_Teachers (email, designation, full_name, contact)  ← with designation
├── PreReg_Librarians (email, full_name, contact)
└── PreReg_Directors (email, full_name, contact)
```

## Security Benefits

1. **Separation of Concerns**: Pre-registration data is isolated in its own database
2. **Restricted Access**: Only admin can modify pre-registration data
3. **Data Protection**: Pre-registered data is read-only during user registration
4. **Audit Trail**: Timestamps track when records were created/updated

## Troubleshooting

### Error: "Unable to validate pre-registration"
- Check if `iit_shelf_prereg` database exists
- Verify `iit_user` has privileges on `iit_shelf_prereg`
- Check backend log files for connection errors

### Error: "Access denied for user 'iit_user'"
- Run the SQL script with root/admin account in DBeaver
- Ensure GRANT PRIVILEGES statement is executed
- Run FLUSH PRIVILEGES after granting

### Missing designation in registration
- Check if teacher email is in `iit_shelf_prereg.PreReg_Teachers`
- Verify designation column is populated in pre-registration table
- Check backend log for SQL errors

## Rollback Instructions (if needed)

If you need to move tables back to main database:

1. Update `backend/config/db_pre_registration.php` to use `iit_shelf`
2. Copy data back to main database tables
3. Drop `iit_shelf_prereg` database

```sql
-- Rollback script
INSERT INTO iit_shelf.PreReg_Students SELECT * FROM iit_shelf_prereg.PreReg_Students;
INSERT INTO iit_shelf.PreReg_Teachers SELECT * FROM iit_shelf_prereg.PreReg_Teachers;
INSERT INTO iit_shelf.PreReg_Librarians SELECT * FROM iit_shelf_prereg.PreReg_Librarians;
INSERT INTO iit_shelf.PreReg_Directors SELECT * FROM iit_shelf_prereg.PreReg_Directors;
DROP DATABASE iit_shelf_prereg;
```

## Files Modified

1. ✅ `backend/config/db_pre_registration.php` - Updated database name
2. ✅ `backend/setup_prereg_database.sql` - SQL setup script
3. ✅ `backend/migrate_prereg_db.php` - PHP migration helper (optional)
4. ✅ `backend/setup_prereg_db.sh` - Bash setup script (optional)

## Next Steps

1. **Run the SQL setup script** in DBeaker with root/admin account
2. **Verify database creation** and data migration
3. **Test registration flow** with pre-registered users
4. **Update admin documentation** with pre-registration procedures

---
**Date**: January 9, 2026
**Database**: iit_shelf_prereg
**Status**: Setup complete, ready for use
