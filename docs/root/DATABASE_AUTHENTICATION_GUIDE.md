# User Authentication Database Implementation - Complete Guide

## ✅ Changes Made

### 1. Database Schema
Added `profile_image` column to `users` table:
```sql
ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER phone;
```

**Updated users table structure:**
- email (PK)
- name
- password_hash
- role (Student/Teacher/Librarian/Director)
- phone
- **profile_image** ← NEW
- is_active
- email_verified_at
- last_login
- created_at
- updated_at

### 2. Registration Flow (3-Step Process)

#### Step 1: Send Registration OTP
- **Endpoint:** POST `/auth/send_register_otp.php`
- **Input:** email
- **Creates:** Unverified user account in database
- **Returns:** Success message (OTP logged to file for testing)

#### Step 2: Verify Email OTP
- **Endpoint:** POST `/auth/verify_email.php`
- **Input:** email, otp
- **Updates:** Sets `email_verified_at` timestamp
- **Security:** Brute force protection (5 attempts = 15 min lockout)
- **Returns:** Success message

#### Step 3: Set Password
- **Endpoint:** POST `/auth/set_password.php`
- **Input:** email, new_password, name, phone, role
- **Updates:** 
  - Sets password_hash (bcrypt, cost 12)
  - Stores full name
  - Stores phone number
  - Stores role (defaults to 'Student')
- **Returns:** Success message

### 3. Flutter App Updates

**New Fields Added:**
- Full Name field (displayed after email)
- Phone field (existing)
- Password field
- Confirm Password field

**Flow:**
1. User enters email → Click "Send OTP"
2. User receives email with 6-digit OTP
3. User enters OTP → Click "Verify OTP"
4. Password fields unlock
5. User enters name, phone, password → Click "Set Password"
6. Account created in database
7. Redirect to login

### 4. Updated Code Files

**Backend:**
- `/backend/api/auth/set_password.php` - Updated to save name, phone, role
- `/backend/api/config/mail.php` - Gmail SMTP configured
- `/backend/api/lib/mail_service.php` - Sends real emails
- `/backend/api/lib/simple_smtp_mailer.php` - Pure SMTP client

**Frontend:**
- `/lib/register.dart` - Added name field, updated flow
- `/lib/auth_service.dart` - Updated setPasswordAfterVerification method

---

## Database User Account Structure

After registration, users table contains:

```
email              : tamaltm64@gmail.com
name               : Tamal User Name
password_hash      : $2y$12$encrypted_hash...
role               : Student
phone              : +8801XXXXXXXXXX
profile_image      : NULL (ready for future profile picture upload)
is_active          : 1
email_verified_at  : 2026-01-04 XX:XX:XX
last_login         : NULL (set on first login)
created_at         : 2026-01-04 XX:XX:XX
updated_at         : 2026-01-04 XX:XX:XX
```

---

## Login Process

**Endpoint:** POST `/auth/login.php`
**Input:** email, password
**Process:**
1. Check if user exists
2. Verify email is verified (email_verified_at is set)
3. Verify password hash matches
4. Update last_login timestamp
5. Return user role and token

**Returns:**
```json
{
  "success": true,
  "message": "Login successful",
  "role": "Student",
  "token": "demo-token"
}
```

---

## Password Reset Process

**Step 1:** Send reset OTP
- **Endpoint:** POST `/auth/send_reset_otp.php`
- **Input:** email
- **Requirement:** Email must be verified
- **Returns:** OTP sent message

**Step 2:** Verify reset OTP
- **Endpoint:** POST `/auth/verify_reset_otp.php`
- **Input:** email, otp
- **Returns:** Verification status

**Step 3:** Reset password
- **Endpoint:** POST `/auth/reset_password.php`
- **Input:** email, new_password
- **Updates:** password_hash
- **Returns:** Success message

---

## Testing the Complete Flow

### Quick Test (Manual)

```bash
# 1. Send registration OTP
curl -X POST http://localhost:8000/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"newtestuser@gmail.com"}'

# 2. Check OTP log
tail -1 /tmp/iit_shelf_otp.log

# 3. Verify OTP (copy code from log)
curl -X POST http://localhost:8000/auth/verify_email.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"newtestuser@gmail.com","otp":"123456"}'

# 4. Set password with user data
curl -X POST http://localhost:8000/auth/set_password.php \
  -H 'Content-Type: application/json' \
  -d '{
    "email":"newtestuser@gmail.com",
    "new_password":"TestPass123!",
    "name":"New User",
    "phone":"+8801234567890",
    "role":"Student"
  }'

# 5. Check database
mariadb -e "USE iit_shelf; SELECT email, name, phone, role, email_verified_at FROM users WHERE email='newtestuser@gmail.com';"

# 6. Login test
curl -X POST http://localhost:8000/auth/login.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"newtestuser@gmail.com","password":"TestPass123!"}'
```

### Full Integration Test (Flutter App)

1. Open Flutter app
2. Click "Create Account"
3. Enter email → Send OTP
4. Check Gmail inbox
5. Enter OTP → Verify
6. Enter full name (e.g., "John Doe")
7. Enter phone (e.g., "+8801234567890")
8. Enter password → Set Password
9. Click "Back to Login"
10. Enter email and password → Login
11. ✅ Should see dashboard

---

## Email Verification Security

### Brute Force Protection
- **Max Attempts:** 5 failed OTP verifications
- **Lockout Duration:** 15 minutes
- **Cooldown:** 60 seconds between OTP requests
- **OTP Expiry:** 5 minutes

### Audit Logging
All verification attempts logged to: `/tmp/iit_shelf_otp_attempts.log`

Example entry:
```
[2026-01-04 12:34:56] Email: user@example.com | Attempt: 1/5 | Status: failed
[2026-01-04 12:35:10] Email: user@example.com | Attempt: 2/5 | Status: failed
[2026-01-04 12:36:45] Email: user@example.com | LOCKED_OUT (15 minutes)
```

---

## Email Configuration

### Current Setup
- **Provider:** Gmail
- **Username:** tamaltm143@gmail.com
- **Email Type:** App-specific password
- **Config File:** `/backend/api/config/mail.php`

### To Change Provider

Edit `config/mail.php` and change line 14:
```php
$driver = getenv('MAIL_DRIVER') ?: 'gmail';  // Change to 'outlook', 'sendgrid', 'aws_ses', etc.
```

### Supported Providers
- ✅ Gmail
- ✅ Outlook
- ✅ SendGrid
- ✅ AWS SES
- ✅ Mailtrap
- ✅ Local mail server

---

## Database Queries Reference

### View all users
```sql
SELECT email, name, phone, role, email_verified_at, created_at FROM users;
```

### Check specific user
```sql
SELECT * FROM users WHERE email='user@example.com';
```

### View unverified users
```sql
SELECT email, created_at FROM users WHERE email_verified_at IS NULL;
```

### View pending OTPs
```sql
SELECT email, purpose, created_at FROM temp_user_verification;
```

### Reset a user's email verification (for testing)
```sql
UPDATE users SET email_verified_at=NULL WHERE email='user@example.com';
```

### Delete test user
```sql
DELETE FROM users WHERE email='test@example.com';
DELETE FROM temp_user_verification WHERE email='test@example.com';
```

---

## Security Features

✅ **Password Security**
- bcrypt hashing with cost 12
- Passwords never stored in plain text
- Password strength validation in UI

✅ **Email Verification**
- OTP required before account activation
- Brute force protection
- Cooldown between attempts
- Audit logging of all attempts

✅ **OTP Security**
- 6-digit random codes
- 5-minute expiration
- Purpose-based isolation (EmailVerification vs PasswordReset)
- Logged to file for development

✅ **Account Security**
- Email is primary identifier (unique)
- Active flag for account status
- Last login timestamp tracking
- Soft delete ready (is_active flag)

---

## Next Steps (Optional Future Work)

1. **Profile Picture Upload**
   - Store image path in `profile_image` column
   - Implement file upload endpoint
   - Add image resize/optimization

2. **JWT Tokens**
   - Replace stub "demo-token" with real JWT
   - Add token refresh mechanism
   - Implement token expiry

3. **Additional User Fields**
   - Department
   - Semester/Year
   - ID number
   - Address

4. **Role-Based Access Control**
   - Different dashboards per role
   - Permission-based features
   - Admin panel

5. **Email Templates**
   - Customizable email designs
   - Multilingual support
   - HTML email improvements

6. **Two-Factor Authentication**
   - SMS OTP option
   - Authenticator app support
   - Backup codes

---

## File Structure

```
/backend/
├── api/
│   ├── auth/
│   │   ├── send_register_otp.php       ✅ Sends OTP for registration
│   │   ├── verify_email.php            ✅ Verifies OTP, sets email_verified_at
│   │   ├── set_password.php            ✅ Sets password and user data
│   │   ├── send_reset_otp.php          ✅ Sends reset OTP
│   │   ├── verify_reset_otp.php        ✅ Verifies reset OTP
│   │   ├── reset_password.php          ✅ Resets password
│   │   └── login.php                   ✅ Authenticates user
│   ├── lib/
│   │   ├── simple_smtp_mailer.php      ✅ SMTP client
│   │   ├── mail_service.php            ✅ Email API
│   │   ├── auth_helpers.php            ✅ OTP helpers
│   │   └── otp_attempt_tracker.php     ✅ Brute force protection
│   ├── config/
│   │   └── mail.php                    ✅ Email configuration
│   └── database.php                    ✅ Database connection
├── database/
│   └── schema_auth_only.sql            ✅ 4-table schema
└── test scripts...

/web frontend/
├── src/                                ✅ Web frontend source
├── components/                         ✅ Shared UI components
├── lib/                                ✅ Frontend helpers
└── package.json                        ✅ Frontend package config
```

---

## Summary

✅ Database schema updated with profile_image  
✅ 3-step registration with OTP verification  
✅ User data saved on password set  
✅ Gmail email sending configured  
✅ Flutter UI updated with name field  
✅ Brute force protection active  
✅ Security audit logging in place  
✅ Full authentication flow tested and working  

**Status: Ready for Testing with Real User Accounts**
