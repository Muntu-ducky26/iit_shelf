# Pre-Registration Database Setup - Quick Start

## What Changed?

✅ **Pre-registration tables moved to separate database:**
- Old location: `iit_shelf.PreReg_*` tables
- New location: `iit_shelf_prereg.PreReg_*` tables

✅ **Teachers table enhanced:**
- Added `designation` column (VARCHAR 100, NOT NULL)
- Stores teacher positions (e.g., "Assistant Professor", "Lecturer")

✅ **Backend updated:**
- `backend/config/db_pre_registration.php` now points to `iit_shelf_prereg`
- All queries work seamlessly with new database

## Setup Instructions (2 Steps)

### Step 1: Run SQL Setup Script
Open **DBeaver** → Connect to MariaDB → Run this file:
```
backend/setup_prereg_database.sql
```

This script will:
1. Create `iit_shelf_prereg` database
2. Create all 4 pre-registration tables
3. Grant permissions to `iit_user`
4. Migrate any existing data

### Step 2: Verify
Check tables were created:
```sql
SHOW DATABASES LIKE 'iit_shelf_prereg';
USE iit_shelf_prereg;
SHOW TABLES;
DESC PreReg_Teachers;  -- Verify designation column exists
```

## Pre-Registration Tables

### PreReg_Students
```
- email (unique key)
- roll
- full_name
- contact
- session
```

### PreReg_Teachers (with designation)
```
- email (unique key)
- designation ← NEW COLUMN
- full_name
- contact
```

### PreReg_Librarians
```
- email (unique key)
- full_name
- contact
```

### PreReg_Directors
```
- email (unique key)
- full_name
- contact
```

## Sample Pre-Registration Data

Insert test data for registration testing:

```sql
USE iit_shelf_prereg;

-- Add test students
INSERT INTO PreReg_Students (email, roll, full_name, contact, session)
VALUES 
('student1@iit.edu', 'CSE-001', 'Karim Abdullah', '01700000001', '2023-2024'),
('student2@iit.edu', 'CSE-002', 'Fatima Rahman', '01700000002', '2023-2024');

-- Add test teachers (with designation)
INSERT INTO PreReg_Teachers (email, designation, full_name, contact)
VALUES 
('teacher1@iit.edu', 'Assistant Professor', 'Dr. Ahmed Khan', '01700000101'),
('teacher2@iit.edu', 'Lecturer', 'Dr. Farhana Haque', '01700000102');

-- Add test librarians
INSERT INTO PreReg_Librarians (email, full_name, contact)
VALUES 
('librarian1@iit.edu', 'Mohammad Karim', '01700000201');

-- Add test directors
INSERT INTO PreReg_Directors (email, full_name, contact)
VALUES 
('director1@iit.edu', 'Dr. Hassan Ahmed', '01700000301');
```

## Registration Flow

1. **User enters email** → System queries `iit_shelf_prereg.PreReg_*` tables
2. **OTP sent** → Pre-registered data retrieved (includes designation for teachers)
3. **OTP verified** → Name and phone auto-populated
4. **Set password** → Registration completes, user added to main `iit_shelf` database

## Testing

### Test Student Registration
```
Email: student1@iit.edu
Expected: Full name "Karim Abdullah" appears after OTP verification
```

### Test Teacher Registration
```
Email: teacher1@iit.edu
Expected: Full name "Dr. Ahmed Khan" with designation "Assistant Professor"
```

## Files Updated

- ✅ `backend/config/db_pre_registration.php` - Points to iit_shelf_prereg
- ✅ `backend/setup_prereg_database.sql` - Database setup script
- ✅ `PREREG_DATABASE_SETUP.md` - Detailed documentation
- ✅ Backend code - Already supports designation column

## Troubleshooting

**Q: Database connection error**
- A: Run SQL script with admin account to create database and grant permissions

**Q: "Unknown database 'iit_shelf_prereg'"**
- A: Run the setup_prereg_database.sql script in DBeaver

**Q: Permission denied error**
- A: Check that GRANT PRIVILEGES statement was executed in SQL script

**Q: Designation not showing for teachers**
- A: Verify teacher email has designation value in PreReg_Teachers table

## Architecture

```
Database Separation:
┌─────────────────────┐         ┌──────────────────────┐
│  iit_shelf (Main)   │         │ iit_shelf_prereg     │
├─────────────────────┤         ├──────────────────────┤
│ users               │ ←─ ─ ─ ← │ PreReg_Students      │
│ roles               │         │ PreReg_Teachers      │
│ students            │         │ PreReg_Librarians    │
│ teachers            │         │ PreReg_Directors     │
│ librarians          │         └──────────────────────┘
│ directors           │
│ books               │
│ shelves             │
│ borrow_requests     │
│ reports             │
└─────────────────────┘

Registration validates against PreReg_* tables
User data stored in main iit_shelf database
```

---
**Status**: ✅ Ready to use
**Database**: iit_shelf_prereg
**Tables**: 4 (Students, Teachers, Librarians, Directors)
**Date**: January 9, 2026
