# Pre-Registration Database Schema

## Overview
The pre-registration database (`pre_registration_database`) contains pre-populated user information that must exist before users can register in the system. It contains role-specific tables for Students, Teachers, Librarians, and Directors.

## Database Name
- **Database**: `pre_registration_database`
- **Credentials**: 
  - Host: localhost
  - User: root
  - Password: (empty)

---

## Tables in Pre-Registration Database

### 1. **student_info** Table
Stores pre-registered student information

**Columns:**
| Column Name | Data Type | Constraints | Description |
|------------|-----------|------------|-------------|
| email | varchar(255) | PRIMARY KEY | Student email address |
| roll | varchar(15) | UNIQUE NOT NULL | Student roll/registration number |
| full_name | varchar(100) | NOT NULL | Full name of student |
| contact | varchar(15) | - | Contact number (phone) |
| session | varchar(10) | - | Academic session (e.g., "2023-2024") |

**Example:**
```sql
INSERT INTO student_info (email, roll, full_name, contact, session) 
VALUES ('student@iit.edu', 'CS001', 'Ahmed Hassan', '+8801234567890', '2023-2024');
```

---

### 2. **teacher_info** Table
Stores pre-registered teacher information

**Columns:**
| Column Name | Data Type | Constraints | Description |
|------------|-----------|------------|-------------|
| email | varchar(255) | PRIMARY KEY | Teacher email address |
| designation | varchar(100) | NOT NULL | Job title/designation |
| full_name | varchar(100) | NOT NULL | Full name of teacher |
| contact | varchar(15) | - | Contact number (phone) |

**Example:**
```sql
INSERT INTO teacher_info (email, designation, full_name, contact) 
VALUES ('teacher@iit.edu', 'Associate Professor', 'Dr. Fatima Khan', '+8801234567890');
```

---

### 3. **librarian_info** Table
Stores pre-registered librarian information

**Columns:**
| Column Name | Data Type | Constraints | Description |
|------------|-----------|------------|-------------|
| email | varchar(255) | PRIMARY KEY | Librarian email address |
| full_name | varchar(100) | NOT NULL | Full name of librarian |
| contact | varchar(15) | - | Contact number (phone) |

**Example:**
```sql
INSERT INTO librarian_info (email, full_name, contact) 
VALUES ('librarian@iit.edu', 'Ayesha Siddiqui', '+8801234567890');
```

---

### 4. **director_info** Table (Inferred)
Stores pre-registered director information

**Columns:**
| Column Name | Data Type | Constraints | Description |
|------------|-----------|------------|-------------|
| email | varchar(255) | PRIMARY KEY | Director email address |
| full_name | varchar(100) | NOT NULL | Full name of director |
| contact | varchar(15) | - | Contact number (phone) |

**Example:**
```sql
INSERT INTO director_info (email, full_name, contact) 
VALUES ('director@iit.edu', 'Prof. Muhammad Ali', '+8801234567890');
```

---

## Registration Flow Using Pre-Registration Database

### Step 1: User Enters Email During Registration
- User provides email in Flutter app's registration form

### Step 2: Email Validation Against Pre-Registration Database
The backend checks if email exists in:
1. `student_info` table → Role = "Student"
2. `teacher_info` table → Role = "Teacher"  
3. `librarian_info` table → Role = "Librarian"
4. `director_info` table → Role = "Director"

Reference: `/backend/check_email_role.php`

### Step 3: Pre-Registration Data Retrieved
If email found, the system retrieves:
- Full name
- Contact number
- Role-specific info (roll, designation, session, etc.)

### Step 4: User Completes Registration
- User receives OTP verification
- User sets password
- Account created in main `users` table with:
  - Email
  - Name (from pre-registration)
  - Phone (from pre-registration)
  - Role (from pre-registration)
  - Password hash (new)

---

## Database Connection in Backend

**File**: `/backend/db_pre_registration.php`

```php
<?php
class PreRegistrationDatabase {
    private $host = 'localhost';
    private $db_name = 'pre_registration_database';
    private $username = 'root';
    private $password = '';
    
    public function connect() {
        // Returns PDO connection to pre_registration_database
    }
}
?>
```

---

## Usage in Backend APIs

### 1. Email Role Check
**File**: `/backend/check_email_role.php`

Validates email and returns role:
```
POST /check_email_role.php
{
  "email": "student@iit.edu"
}

Response:
{
  "success": true,
  "role": "Student",
  "user_info": {
    "email": "student@iit.edu",
    "full_name": "Ahmed Hassan",
    "contact": "+8801234567890",
    "roll": "CS001",
    "session": "2023-2024"
  }
}
```

### 2. Registration
**File**: `/backend/register.php`

Checks pre-registration database during registration:
- Verifies email exists in correct role table
- Retrieves full name and contact
- Creates user in main database with pre-filled data

### 3. User Profile Retrieval
**File**: `/backend/get_user_profile.php`

Falls back to pre-registration database if contact not found in main database

---

## Key Differences from Main Database

| Aspect | Pre-Registration DB | Main Database (iit_shelf) |
|--------|-------------------|--------------------------|
| **Purpose** | Pre-populated user data | Active system data |
| **Tables** | student_info, teacher_info, librarian_info, director_info | users, Students, Teachers, Librarians, Directors |
| **When Used** | During registration validation | After registration complete |
| **Email Verification** | Not required | Required (email_verified_at) |
| **Password** | Not stored | Stored as password_hash |
| **Contact Source** | Primary source | Updated by user |

---

## SQL Queries Reference

### Create Pre-Registration Tables

```sql
CREATE DATABASE IF NOT EXISTS pre_registration_database;
USE pre_registration_database;

-- Student Information
CREATE TABLE IF NOT EXISTS student_info (
  email varchar(255) NOT NULL PRIMARY KEY,
  roll varchar(15) UNIQUE NOT NULL,
  full_name varchar(100),
  contact varchar(15),
  session varchar(10)
);

-- Teacher Information
CREATE TABLE IF NOT EXISTS teacher_info (
  email varchar(255) NOT NULL PRIMARY KEY,
  designation varchar(100) NOT NULL,
  full_name varchar(100),
  contact varchar(15)
);

-- Librarian Information
CREATE TABLE IF NOT EXISTS librarian_info (
  email varchar(255) NOT NULL PRIMARY KEY,
  full_name varchar(100),
  contact varchar(15)
);

-- Director Information
CREATE TABLE IF NOT EXISTS director_info (
  email varchar(255) NOT NULL PRIMARY KEY,
  full_name varchar(100),
  contact varchar(15)
);
```

### View All Pre-Registered Users

```sql
SELECT 'Student' as role, email, full_name, contact FROM student_info
UNION ALL
SELECT 'Teacher' as role, email, full_name, contact FROM teacher_info
UNION ALL
SELECT 'Librarian' as role, email, full_name, contact FROM librarian_info
UNION ALL
SELECT 'Director' as role, email, full_name, contact FROM director_info;
```

### Check if Email is Pre-Registered

```sql
-- Check Student
SELECT email, 'Student' as role FROM student_info WHERE email = 'student@iit.edu';

-- Check Teacher
SELECT email, 'Teacher' as role FROM teacher_info WHERE email = 'teacher@iit.edu';

-- Check All
SELECT email, 'Student' as role FROM student_info WHERE email = 'user@iit.edu'
UNION
SELECT email, 'Teacher' as role FROM teacher_info WHERE email = 'user@iit.edu'
UNION
SELECT email, 'Librarian' as role FROM librarian_info WHERE email = 'user@iit.edu'
UNION
SELECT email, 'Director' as role FROM director_info WHERE email = 'user@iit.edu';
```

---

## File References

| File | Purpose |
|------|---------|
| `/backend/db_pre_registration.php` | Database connection class |
| `/backend/check_email_role.php` | Validates email and retrieves role |
| `/backend/register.php` | Registration with pre-reg verification |
| `/backend/get_user_profile.php` | Falls back to pre-reg data if needed |
| `/backend/cleanup_duplicate_table.sql` | Maintenance script |

---

## Important Notes

1. **Pre-Registration Required**: Users CANNOT register with emails not in pre-registration database
2. **Email is Primary Key**: Each email is unique across all pre-registration tables
3. **Role Determination**: Email's role is determined by which table contains it
4. **Data Immutability**: Pre-registration data provides baseline for account creation
5. **Contact Backup**: If contact not in main database, system retrieves from pre-registration
6. **No Password Storage**: Pre-registration database does NOT store passwords

---

## Version History

- **Created**: January 9, 2026
- **Database Source**: `pre_registration_database` (separate from `iit_shelf`)
- **Status**: Active and in use for registration flow
