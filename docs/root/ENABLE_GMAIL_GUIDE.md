# Enable Gmail for Email Sending

## Why App Passwords Don't Work

You're seeing "Setting not available" because **2-Step Verification** isn't enabled on your Gmail account.

## Quick Fix (5 minutes):

### Step 1: Enable 2-Step Verification
1. Go to https://myaccount.google.com/security
2. Scroll to "How you sign in to Google"
3. Click **"2-Step Verification"**
4. Click **"Get Started"**
5. Follow the setup (add phone number)
6. Complete verification

### Step 2: Generate App Password
1. Go back to https://myaccount.google.com/apppasswords
2. Now it should work!
3. Select "Mail" and "Other (Custom name)"
4. Type "IIT Shelf"
5. Click Generate
6. Copy the 16-character password

### Step 3: Update Configuration
```bash
vim /mnt/academics/iit_shelf_test/backend/api/config/mail.php
```

Change line 9:
```php
$driver = getenv('MAIL_DRIVER') ?: 'gmail';  // Change 'mailtrap' to 'gmail'
```

Update Gmail section (around line 13):
```php
case 'gmail':
    return [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',     // Your Gmail
        'password' => 'xxxx xxxx xxxx xxxx',      // 16-char password
        'encryption' => 'tls',
        'from_email' => 'your-email@gmail.com',
        'from_name' => 'IIT Shelf Library'
    ];
```

### Step 4: Test
```bash
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"your-email@gmail.com"}'
```

Check your Gmail inbox!
