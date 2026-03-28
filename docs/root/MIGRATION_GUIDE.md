# Database Migration Guide - Team Schema Alignment

## Overview
This guide documents the migration from the original Flutter app database schema to the team's unified database schema for web and mobile platforms.

**Migration Date:** January 6, 2026  
**Purpose:** Align Flutter app backend with web team's database structure

---

## Migration Files Created

### 1. **schema_team_migration.sql**
- Fresh database schema matching team specifications
- Clean setup with sample data
- Use this for new deployments

### 2. **migration_to_team_schema.sql**
- Migration script for existing databases
- Preserves existing data during conversion
- Includes table renaming and field mapping

---

## Key Schema Changes

### Table Name Changes
All table names changed from `lowercase_with_underscores` to `PascalCase`:

| Old Name | New Name |
|----------|----------|
| `temp_user_verification` | `Temp_User_Verification` |
| `users` | `Users` |
| `students` | `Students` |
| `teachers` | `Teachers` |
| `courses` | `Courses` |
| `course_prerequisites` | `Course_Prerequisites` |
| `shelves` | `Shelves` |
| `books` | `Books` |
| `book_courses` | `Book_Courses` |
| `book_copies` | `Book_Copies` |
| `digital_resources` | `Digital_Resources` |
| `transaction_requests` | `Transaction_Requests` |
| `approved_transactions` | `Approved_Transactions` |
| `reservations` | `Reservations` |
| `fines` | `Fines` |
| `payments` | `Payments` |
| `reports` | `Reports` |
| `notifications` | `Notifications` |
| `requests` | `Requests` |

### Field Changes by Table

#### **Users Table**
**Removed Fields:**
- `phone` → Changed to `contact`
- `is_active`
- `email_verified_at` (verification removed from flow)
- `updated_at`

**Retained Fields:**
- `email` (PK)
- `name`
- `password_hash`
- `role`
- `contact` (renamed from phone)
- `created_at`
- `last_login`

#### **Books Table**
**Removed Fields:**
- `language`
- `keywords`
- `copies_total`
- `copies_available`
- `is_deleted`
- `created_at`
- `updated_at`

**Note:** Copy counts now calculated dynamically from `Book_Copies` table

#### **Book_Copies Table**
**Removed Fields:**
- `is_deleted`
- `created_at`
- `updated_at`

#### **Approved_Transactions Table**
**Removed Fields:**
- `user_email` (now fetched via JOIN with Transaction_Requests)
- `isbn` (now fetched via JOIN with Book_Copies)
- `issued_by`
- `created_at`
- `updated_at`

**Important:** `request_id` is now required (NOT NULL) - all transactions must have a request

#### **Fines Table**
**Removed Fields:**
- `fine_type`
- `days_overdue`
- `created_at`
- `updated_at`

#### **Payments Table**
**Removed Fields:**
- `fine_id` (now in junction table `fine_payment`)
- `user_email`
- `payment_method`
- `currency`
- `gateway_payload`
- `created_at`
- `updated_at`

**New Structure:**
- Many-to-many relationship between Fines and Payments via `fine_payment` table

---

## API Changes

### Authentication APIs Updated
All authentication endpoints now use `Users` and `Temp_User_Verification` tables:

✅ **register.php**
- Uses `contact` instead of `phone`
- Removes `email_verified_at` workflow
- Simplified registration flow

✅ **login.php**
- Removes email verification check
- Uses `Users` table

✅ **verify_email.php**
- Uses `Temp_User_Verification` table
- Removes `email_verified_at` update

✅ **reset_password.php**
- Uses `Users` and `Temp_User_Verification`
- Removes `updated_at` field

✅ **get_profile.php**
- Returns `contact` instead of `phone`
- Removes `is_active`, `email_verified_at` fields

✅ **upload_profile_image.php**
- Uses `Users` table
- Removes `updated_at` field

### Book Management APIs Updated

✅ **add_book.php**
- Uses `Books` and `Book_Copies` tables (PascalCase)
- Removes: `language`, `keywords`, `copies_total`, `copies_available`, `is_deleted`, timestamps

✅ **get_books.php**
- Uses `Books` table
- Dynamically calculates `copies_available` from `Book_Copies`
- Removes unsupported fields from response

✅ **update_book.php**
- Uses `Books` table
- Only allows updating: title, author, isbn, category, publisher, publication_year, edition, description, pic_path

✅ **delete_book.php**
- Uses `Books` and `Book_Copies` tables
- Marks book copies as "Discarded"

✅ **reserve_book.php**
- Uses `Books` and `Reservations` tables
- Removes `is_deleted` checks

✅ **cancel_reservation.php**
- Uses `Reservations` table

✅ **upload_pdf.php**
- Uses `Digital_Resources` table
- Now uses `isbn` instead of `book_id`
- Stores as resource record, not book field

### Transaction APIs Updated

✅ **borrow_book.php**
- Creates entry in `Transaction_Requests` first
- Then creates `Approved_Transactions` record
- Uses `Book_Copies` table
- Removes book-level copy count updates (calculated dynamically)

✅ **return_book.php**
- Uses `Approved_Transactions` and `Transaction_Requests` with JOIN
- Uses `Book_Copies` table
- Updates `Fines` table (simplified structure)
- Removes `fine_type` and `days_overdue` fields

---

## Migration Steps

### For New Deployment (Fresh Install)

```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE iit_shelf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run team schema
mysql -u root -p iit_shelf < backend/database/schema_team_migration.sql

# 3. Verify
mysql -u root -p iit_shelf -e "SHOW TABLES;"
```

### For Existing Database (Migration)

```bash
# 1. BACKUP YOUR DATABASE FIRST!
mysqldump -u root -p iit_shelf > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Run migration script
mysql -u root -p iit_shelf < backend/database/migration_to_team_schema.sql

# 3. Verify migration
mysql -u root -p iit_shelf -e "SELECT COUNT(*) FROM Users;"
mysql -u root -p iit_shelf -e "SELECT COUNT(*) FROM Books;"
mysql -u root -p iit_shelf -e "SELECT COUNT(*) FROM Book_Copies;"
```

### Post-Migration Verification

```bash
# Test authentication
curl -X POST http://localhost:8000/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@iit.edu","password":"test123","name":"Test User","role":"Student"}'

# Test login
curl -X POST http://localhost:8000/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@iit.edu","password":"test123"}'

# Test book listing
curl http://localhost:8000/api/books/get_books.php
```

---

## Breaking Changes & Compatibility Notes

### ⚠️ Breaking Changes

1. **Email Verification Flow Removed**
   - Old: Users had `email_verified_at` field and verification was enforced
   - New: Verification flow simplified, field removed
   - **Impact:** If your app relies on email verification status, update UI logic

2. **Book Copy Counts**
   - Old: Stored in `books.copies_total` and `copies_available`
   - New: Calculated dynamically from `Book_Copies` table
   - **Impact:** Queries that read these fields need updating

3. **Transaction User Lookup**
   - Old: `approved_transactions.user_email` directly available
   - New: Requires JOIN with `Transaction_Requests.requester_email`
   - **Impact:** Queries showing transaction history need JOIN updates

4. **Phone → Contact**
   - Old: `users.phone`
   - New: `Users.contact`
   - **Impact:** Update all references in Flutter app

5. **Fine Structure**
   - Old: `fines.fine_type`, `fines.days_overdue`
   - New: Description only, type removed
   - **Impact:** Fine reporting queries need updates

6. **Payments Many-to-Many**
   - Old: `payments.fine_id` (one-to-one)
   - New: `fine_payment` junction table (many-to-many)
   - **Impact:** Payment queries need junction table JOIN

### 📱 Flutter App Updates Needed

1. **Auth Service (`lib/auth_service.dart`)**
   - Update field names: `phone` → `contact`
   - Remove `emailVerified` checks if present
   - Update profile model

2. **Book Models**
   - Remove `copiesTotal`, `copiesAvailable` from model
   - Add dynamic calculation or fetch from API

3. **Transaction Models**
   - Update to handle new transaction flow
   - Request → Approved Transaction relationship

4. **API Endpoints**
   - All backend API endpoints already updated
   - Test each endpoint after migration

---

## Rollback Plan

If migration fails or issues arise:

```bash
# Restore from backup
mysql -u root -p iit_shelf < backup_YYYYMMDD_HHMMSS.sql

# Revert code changes
git checkout HEAD -- backend/api/
git checkout HEAD -- backend/config/
```

---

## Testing Checklist

- [ ] Database migration runs without errors
- [ ] All tables created with correct structure
- [ ] Sample data inserted successfully
- [ ] User registration works
- [ ] User login works
- [ ] Book listing works
- [ ] Book addition works
- [ ] Book borrowing creates transaction request and approved transaction
- [ ] Book return works and calculates fines
- [ ] Reservations work
- [ ] Profile image upload works
- [ ] PDF resource upload works
- [ ] All foreign key constraints work correctly

---

## Support & Troubleshooting

### Common Issues

**Issue:** Foreign key constraint errors during migration
```sql
-- Temporarily disable checks
SET FOREIGN_KEY_CHECKS = 0;
-- Run migration
-- Re-enable checks
SET FOREIGN_KEY_CHECKS = 1;
```

**Issue:** Case sensitivity with table names
- MySQL on Linux is case-sensitive by default
- Ensure exact casing: `Users` not `users`

**Issue:** Missing columns in API responses
- Update frontend models to match new schema
- Remove references to deleted fields

---

## Future Considerations

1. **Add Indexes** - Consider adding indexes on frequently queried fields
2. **Caching** - Implement caching for book availability counts
3. **Audit Trail** - Consider adding created_at/updated_at if audit trail needed
4. **Soft Deletes** - If soft delete needed, use `Shelves.is_deleted` pattern consistently

---

## Contact

For migration issues or questions:
- Review this guide
- Check backend/database/ for SQL scripts
- Test with sample data first
- Keep backups before production migration

**Last Updated:** January 6, 2026
