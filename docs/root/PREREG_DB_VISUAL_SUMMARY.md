# Pre-Registration Database Setup - Visual Summary

## Architecture Diagram

```
╔════════════════════════════════════════════════════════════════════╗
║                       IIT Shelf System                             ║
╠════════════════════════════════════════════════════════════════════╣
║                                                                    ║
║  ┌──────────────────────────────┐    ┌──────────────────────────┐ ║
║  │   PRIMARY DATABASE           │    │ PRE-REGISTRATION DB      │ ║
║  │   iit_shelf                  │    │ iit_shelf_prereg         │ ║
║  ├──────────────────────────────┤    ├──────────────────────────┤ ║
║  │                              │    │                          │ ║
║  │ • users                      │◄───┤ • PreReg_Students       │ ║
║  │ • roles                      │    │ • PreReg_Teachers       │ ║
║  │ • students                   │    │ • PreReg_Librarians     │ ║
║  │ • teachers                   │    │ • PreReg_Directors      │ ║
║  │ • librarians                 │    │                          │ ║
║  │ • directors                  │    │ (Email, Name, Contact,  │ ║
║  │ • books                      │    │  Session/Roll/Desig)    │ ║
║  │ • shelves                    │    │                          │ ║
║  │ • courses                    │    └──────────────────────────┘ ║
║  │ • borrow_requests            │                                  ║
║  │ • reports                    │                                  ║
║  │                              │                                  ║
║  └──────────────────────────────┘                                  ║
║                                                                    ║
╚════════════════════════════════════════════════════════════════════╝

User Registration Flow:
━━━━━━━━━━━━━━━━━━━━━━

1. User enters email
   ↓
2. System queries iit_shelf_prereg for pre-registration
   ├─ Checks PreReg_Students (email, roll, full_name, contact, session)
   ├─ Checks PreReg_Teachers (email, designation, full_name, contact)
   ├─ Checks PreReg_Librarians (email, full_name, contact)
   └─ Checks PreReg_Directors (email, full_name, contact)
   ↓
3. If found: OTP sent with user info
   ↓
4. User verifies OTP
   ↓
5. Name & Phone auto-filled from pre-registration (read-only)
   ↓
6. User sets password
   ↓
7. User created in iit_shelf.users table
   ↓
8. Registration complete!
```

## Table Structure Comparison

### Before (All in iit_shelf)
```
┌─────────────────────────────────────────┐
│         DATABASE: iit_shelf              │
├─────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ │
│ │ PreReg_Students                     │ │
│ │ PreReg_Teachers (no designation)    │ │
│ │ PreReg_Librarians                   │ │
│ │ PreReg_Directors                    │ │
│ └─────────────────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │
│ │ users, books, shelves, reports...   │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### After (Separated)
```
┌───────────────────────────────┐  ┌──────────────────────────┐
│  DATABASE: iit_shelf          │  │ DATABASE: iit_shelf_prereg│
├───────────────────────────────┤  ├──────────────────────────┤
│ ┌─────────────────────────────┤  │ ┌──────────────────────┐ │
│ │ users                       │  │ │ PreReg_Students      │ │
│ │ books, shelves, reports     │  │ │ PreReg_Teachers ✨   │ │
│ │ courses, borrow_requests    │  │ │ PreReg_Librarians    │ │
│ │ ... other app tables        │  │ │ PreReg_Directors     │ │
│ └─────────────────────────────┘  │ └──────────────────────┘ │
│                                   │                          │
│     MAIN APPLICATION DB           │    PRE-REG DB ONLY       │
└───────────────────────────────────┴──────────────────────────┘
                ↑                              ↑
         Stores active users         Stores pre-registration
         and application data        data for validation
```

## PreReg_Teachers Enhancement

```
BEFORE: No designation column
┌────────────┬──────────────┬───────────┬─────────┐
│ email      │ full_name    │ contact   │ (missing)
├────────────┼──────────────┼───────────┼─────────┤
│ teacher1@  │ Dr. Ahmed    │ 017000001 │
│ iit.edu    │ Khan         │ 01        │ ❌
└────────────┴──────────────┴───────────┴─────────┘

AFTER: Now has designation column
┌────────────┬──────────────┬──────────────────────┬─────────┐
│ email      │ full_name    │ designation          │ contact │
├────────────┼──────────────┼──────────────────────┼─────────┤
│ teacher1@  │ Dr. Ahmed    │ Assistant Professor  │ 017000001
│ iit.edu    │ Khan         │                      │ 01      │
│            │              │                      │         │
│ teacher2@  │ Dr. Farhana  │ Lecturer             │ 017000002
│ iit.edu    │ Haque        │                      │ 02      │
│            │              │                      │         │
│ teacher3@  │ Dr. Mahmud   │ Professor            │ 017000003
│ iit.edu    │ Hasan        │                      │ 03      │
└────────────┴──────────────┴──────────────────────┴─────────┘

✨ Now stores: Professor, Associate Prof, Assistant Prof,
              Lecturer, Assistant Lecturer, etc.
```

## Database Connection Flow

```
┌─────────────────────┐
│  Registration Form  │
│  (Flutter App)      │
└──────────┬──────────┘
           │ email
           ↓
┌──────────────────────────────────────────┐
│  send_register_otp.php                   │
│                                          │
│  Uses: PreRegistrationDatabase class     │
│  Points to: iit_shelf_prereg             │
│  User: iit_user                          │
│  Password: iit_password                  │
└──────────┬───────────────────────────────┘
           │ SQL Query
           ↓
┌─────────────────────────────────┐
│  iit_shelf_prereg Database      │
│                                 │
│  ✓ PreReg_Students              │
│  ✓ PreReg_Teachers (w/ desig)   │
│  ✓ PreReg_Librarians            │
│  ✓ PreReg_Directors             │
│                                 │
│  (Read-Only during registration)│
└─────────────────────────────────┘
           │ user data + otp
           ↓
┌──────────────────────┐
│  OTP Sent to Email   │
│  + User Info         │
└──────────────────────┘
           │
           ↓
┌──────────────────────┐
│  User Verifies OTP   │
│  Name Auto-fills     │
│  Sets Password       │
└────────┬─────────────┘
         │ verified data
         ↓
┌──────────────────────────┐
│  set_password.php        │
│                          │
│  Uses: Database (main)   │
│  Points to: iit_shelf    │
└────────┬─────────────────┘
         │ INSERT user
         ↓
┌──────────────────────────┐
│  iit_shelf.users         │
│  (User now registered)   │
└──────────────────────────┘
```

## Implementation Checklist

### Phase 1: Database Setup ✅
- [x] New database `iit_shelf_prereg` created
- [x] All 4 tables created with proper schema
- [x] PreReg_Teachers has designation column
- [x] Permissions granted to iit_user
- [x] Data migration (if existing records)

### Phase 2: Backend Configuration ✅
- [x] db_pre_registration.php updated
- [x] Database name changed to iit_shelf_prereg
- [x] Connection details verified
- [x] All auth endpoints using new database

### Phase 3: Code Updates ✅
- [x] send_register_otp.php queries new database
- [x] Designation column support added
- [x] Error handling for new database
- [x] Logging for troubleshooting

### Phase 4: Documentation ✅
- [x] Setup guide created
- [x] SQL script provided
- [x] Sample data included
- [x] Troubleshooting guide
- [x] Architecture documentation

## Step-by-Step Setup

```
STEP 1: Execute SQL Script
━━━━━━━━━━━━━━━━━━━━━━━━━
┌─────────────────────────┐
│ Open DBeaver            │
│ ↓                       │
│ Connect to MariaDB      │
│ ↓                       │
│ Run setup_prereg_       │
│    database.sql         │
│ ↓                       │
│ ✓ Database created      │
│ ✓ Tables created        │
│ ✓ Permissions granted   │
│ ✓ Data migrated         │
└─────────────────────────┘

STEP 2: Verify Tables
━━━━━━━━━━━━━━━━━━━━
mysql> USE iit_shelf_prereg;
mysql> SHOW TABLES;
mysql> DESC PreReg_Teachers;
       ↓
     ✓ All tables exist
     ✓ designation column present

STEP 3: Add Pre-Registration Data
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
mysql> INSERT INTO PreReg_Students ...
       ↓
     ✓ Test data added

STEP 4: Test Registration
━━━━━━━━━━━━━━━━━━━━━━━━━
Flutter App
  ↓ Enter email
  ↓ Send OTP
  ↓ Verify OTP → Name fills in ✓
  ↓ Set password
  ↓ Registration complete ✓
```

## Database Permissions

```
Database: iit_shelf_prereg
User: iit_user
Host: localhost

GRANT ALL PRIVILEGES ON iit_shelf_prereg.* 
TO 'iit_user'@'localhost' IDENTIFIED BY 'iit_password';

FLUSH PRIVILEGES;

Includes:
✓ SELECT (Read pre-registration data)
✓ INSERT (Create new records)
✓ UPDATE (Modify records)
✓ DELETE (Remove records)
```

## Key Points to Remember

1. **Separate Database**: Pre-registration data is NOT in iit_shelf
   - Pre-reg data → iit_shelf_prereg
   - Active users → iit_shelf

2. **Read-Only for Users**: During registration, user cannot modify pre-registered data
   - Fields are populated automatically
   - Fields are marked read-only

3. **Teacher Designation**: Now tracked in pre-registration
   - Stored in PreReg_Teachers.designation
   - Returned in OTP response
   - Available in user profile

4. **One-Time Setup**: Database setup only needed once
   - Run SQL script with admin access
   - Then users can register normally

5. **Backward Compatible**: No API changes
   - All endpoints work the same
   - New database is transparent to clients

---

**Implementation Date**: January 9, 2026
**Database Separation**: ✅ Complete
**Teacher Designation Support**: ✅ Complete
**Status**: Ready for Use
