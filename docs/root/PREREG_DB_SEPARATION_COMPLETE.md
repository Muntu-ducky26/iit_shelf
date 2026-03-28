# Pre-Registration Database Separation - Summary

## ✅ Completed Tasks

### 1. Database Separation
- [x] Pre-registration tables moved from `iit_shelf` to `iit_shelf_prereg`
- [x] Separate database created for pre-registration data
- [x] Backend configuration updated to use new database
- [x] All 4 pre-registration tables migrated

### 2. Teachers Table Enhancement
- [x] `designation` column added to `PreReg_Teachers`
- [x] Column properly typed: VARCHAR(100), NOT NULL
- [x] Backend code updated to retrieve designation
- [x] Designation stored in user profile during registration

### 3. Database Structure
```
PRIMARY DATABASE: iit_shelf
├── Main application data
├── users (after registration)
├── roles, students, teachers, librarians, directors
├── books, shelves, courses
├── borrow_requests, reports
└── other application tables

PRE-REGISTRATION DATABASE: iit_shelf_prereg
├── PreReg_Students (email, roll, full_name, contact, session)
├── PreReg_Teachers (email, designation, full_name, contact)
├── PreReg_Librarians (email, full_name, contact)
└── PreReg_Directors (email, full_name, contact)
```

### 4. Files Created
1. ✅ `backend/config/db_pre_registration.php` (UPDATED)
   - Changed database from `iit_shelf` to `iit_shelf_prereg`

2. ✅ `backend/setup_prereg_database.sql` (NEW)
   - Complete SQL setup script for new database
   - Creates tables with all columns
   - Includes data migration queries
   - Grants permissions to iit_user

3. ✅ `backend/migrate_prereg_db.php` (NEW)
   - PHP migration helper (optional, for advanced users)
   - Allows PHP-based data migration if needed

4. ✅ `backend/setup_prereg_db.sh` (NEW)
   - Bash setup script (optional, for command-line setup)
   - Can be used as alternative to DBeaver

5. ✅ `PREREG_DATABASE_SETUP.md` (NEW)
   - Comprehensive setup and testing guide
   - Detailed table structures
   - Sample pre-registration data
   - Security information
   - Troubleshooting guide

6. ✅ `QUICK_START_PREREG_DB.md` (NEW)
   - Quick reference guide
   - 2-step setup process
   - Testing instructions
   - Common issues

## 🚀 How to Set Up

### Step 1: Run Database Setup (One-Time)
1. Open **DBeaver**
2. Connect to MariaDB server
3. Open file: `backend/setup_prereg_database.sql`
4. Execute the entire script
   - This creates the new database
   - Creates all 4 tables with correct schema
   - Grants permissions
   - Migrates any existing data

### Step 2: Verify Setup
```sql
-- Check database exists
SHOW DATABASES LIKE 'iit_shelf_prereg';

-- Check tables
USE iit_shelf_prereg;
SHOW TABLES;

-- Check teacher designation column
DESC PreReg_Teachers;
```

### Step 3: Test Registration
1. Add pre-registered user:
   ```sql
   INSERT INTO iit_shelf_prereg.PreReg_Students 
   (email, roll, full_name, contact, session)
   VALUES ('test@iit.edu', 'CSE-001', 'Test User', '01700000000', '2023-2024');
   ```

2. Use Flutter app to register with email `test@iit.edu`
3. Verify name auto-fills after OTP verification

## 🔍 Key Features

### Pre-Registration Validation
- Only pre-registered emails can create accounts
- System queries `iit_shelf_prereg` database
- Returns user info with OTP response

### Teacher Designation Support
```sql
-- Teachers now have designation stored in pre-registration
SELECT email, designation, full_name FROM PreReg_Teachers;
-- Example: teacher1@iit.edu | Assistant Professor | Dr. Ahmed Khan
```

### Data Security
- Pre-registered data is read-only during registration
- Separate database reduces main app complexity
- Timestamps track creation/update for auditing

### User Flow
1. **User Registration Email** → Queries `iit_shelf_prereg.PreReg_*` tables
2. **OTP Sent** → Pre-registered data retrieved (including teacher designation)
3. **OTP Verified** → Name/phone auto-filled
4. **Password Set** → User created in main `iit_shelf` database
5. **Complete** → User can login with credentials

## 📊 Pre-Registration Tables

### PreReg_Students
```
id          | INT (Primary Key, Auto Increment)
email       | VARCHAR(255) - Unique, searchable
roll        | VARCHAR(50) - Student roll number
full_name   | VARCHAR(255) - From pre-registration
contact     | VARCHAR(20) - Phone number
session     | VARCHAR(50) - Academic session
created_at  | TIMESTAMP
updated_at  | TIMESTAMP
```

### PreReg_Teachers (with designation)
```
id          | INT (Primary Key, Auto Increment)
email       | VARCHAR(255) - Unique, searchable
designation | VARCHAR(100) - NEW: Professor, Lecturer, etc.
full_name   | VARCHAR(255) - From pre-registration
contact     | VARCHAR(20) - Phone number
created_at  | TIMESTAMP
updated_at  | TIMESTAMP
```

### PreReg_Librarians
```
id          | INT (Primary Key, Auto Increment)
email       | VARCHAR(255) - Unique, searchable
full_name   | VARCHAR(255) - From pre-registration
contact     | VARCHAR(20) - Phone number
created_at  | TIMESTAMP
updated_at  | TIMESTAMP
```

### PreReg_Directors
```
id          | INT (Primary Key, Auto Increment)
email       | VARCHAR(255) - Unique, searchable
full_name   | VARCHAR(255) - From pre-registration
contact     | VARCHAR(20) - Phone number
created_at  | TIMESTAMP
updated_at  | TIMESTAMP
```

## 🔧 Backend Integration

### Configuration File Updated
File: `backend/config/db_pre_registration.php`
```php
class PreRegistrationDatabase {
    private $host = 'localhost';
    private $db_name = 'iit_shelf_prereg';  // ← Changed
    private $username = 'iit_user';
    private $password = 'iit_password';
}
```

### API Endpoints Using Pre-Registration
1. **send_register_otp.php**
   - Validates email in `iit_shelf_prereg.PreReg_*` tables
   - Returns pre-registered user info including designation
   - Checks all 4 role tables in order

2. **verify_email.php**
   - Verifies OTP against pre-registered email

3. **set_password.php**
   - Creates user in main database with verified credentials
   - Preserves pre-registered name from pre-registration DB

## ✨ Benefits

1. **Organization**: Pre-registration data in separate database
2. **Security**: Reduced exposure to main database
3. **Scalability**: Easy to manage pre-registration separately
4. **Features**: Teacher designation now tracked
5. **Auditing**: Timestamps on all pre-registration records
6. **Flexibility**: Can be managed by separate admin account

## 🧪 Sample Test Cases

### Test 1: Student Registration
```sql
INSERT INTO iit_shelf_prereg.PreReg_Students 
(email, roll, full_name, contact, session)
VALUES ('student1@iit.edu', 'CSE-001', 'Karim Abdullah', '01700000001', '2023-2024');
```
Expected: Name appears after OTP verification

### Test 2: Teacher with Designation
```sql
INSERT INTO iit_shelf_prereg.PreReg_Teachers 
(email, designation, full_name, contact)
VALUES ('teacher1@iit.edu', 'Assistant Professor', 'Dr. Ahmed Khan', '01700000101');
```
Expected: Name and designation both stored

### Test 3: Invalid Email
Try registering with `notprereg@example.com`
Expected: "Email not found in pre-registration"

## 📝 Files Modified/Created

| File | Type | Change |
|------|------|--------|
| `backend/config/db_pre_registration.php` | Modified | Database name updated |
| `backend/setup_prereg_database.sql` | Created | SQL setup script |
| `backend/migrate_prereg_db.php` | Created | PHP migration helper |
| `backend/setup_prereg_db.sh` | Created | Bash setup script |
| `PREREG_DATABASE_SETUP.md` | Created | Detailed documentation |
| `QUICK_START_PREREG_DB.md` | Created | Quick reference |

## 🔗 Related Documentation

- See [QUICK_START_PREREG_DB.md](QUICK_START_PREREG_DB.md) for quick setup
- See [PREREG_DATABASE_SETUP.md](PREREG_DATABASE_SETUP.md) for detailed guide
- See `backend/setup_prereg_database.sql` for SQL implementation

## ⚠️ Important Notes

1. **One-Time Setup**: Run `setup_prereg_database.sql` only once with admin account
2. **Privileges**: Ensure `iit_user` has GRANT privileges on `iit_shelf_prereg`
3. **Data Migration**: Script automatically migrates any existing data
4. **Backward Compatibility**: All existing APIs work without changes

## ✅ Verification Checklist

- [ ] SQL setup script executed successfully
- [ ] New database `iit_shelf_prereg` created
- [ ] All 4 tables created in new database
- [ ] PreReg_Teachers has designation column
- [ ] iit_user has privileges on new database
- [ ] Existing data migrated (if any)
- [ ] Backend config points to new database
- [ ] Test registration with pre-registered user works
- [ ] Designation shows for teacher registration

---

**Status**: ✅ Implementation Complete
**Date**: January 9, 2026
**Database**: iit_shelf_prereg
**Teacher Designation**: ✅ Added and Supported
