# Finding Mailtrap SMTP Credentials

## Updated Mailtrap Interface (2026)

Mailtrap now shows API tokens by default, but SMTP credentials are still available!

### Method 1: Find SMTP Credentials in Mailtrap

1. **Go to Mailtrap.io** and sign in
2. **Click "Email Testing"** in the left sidebar (not "Email Sending")
3. **Select or create an inbox**
4. **Click "SMTP Settings"** tab (or "Show Credentials" button)
5. You should see:
   ```
   Host: smtp.mailtrap.io (or sandbox.smtp.mailtrap.io)
   Port: 2525 (or 25, 465, 587)
   Username: [your-username]
   Password: [your-password]
   Auth: PLAIN or LOGIN
   TLS: Optional
   ```

### Where to Look
- **Email Testing** → **Inboxes** → **[Your Inbox]** → **SMTP Settings**
- OR look for "Integrations" tab → Select your language → See credentials

### Alternative: Use Sandbox Credentials
If you see `sandbox.smtp.mailtrap.io`, use:
- Host: `sandbox.smtp.mailtrap.io`
- Port: `2525`
- Username: (shown in interface)
- Password: (shown in interface)

---

## Method 2: Use Gmail Instead (Simpler)

If Mailtrap is confusing, Gmail is easier:

### Step 1: Generate App Password
1. Go to https://myaccount.google.com/apppasswords
2. Sign in to your Google account
3. Select "Mail" and your device
4. Click "Generate"
5. Copy the 16-character password (spaces removed)

### Step 2: Update Configuration
Edit `/backend/api/config/mail.php`:

```php
// Change this at the top of the file:
$driver = getenv('MAIL_DRIVER') ?: 'gmail';  // Changed from 'mailtrap' to 'gmail'
```

Then find the Gmail section and update:
```php
case 'gmail':
    return [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',        // ← Your Gmail address
        'password' => 'your-app-specific-password',  // ← The 16-char password from step 1
        'encryption' => 'tls',
        'from_email' => 'your-email@gmail.com',
        'from_name' => 'IIT Shelf Library'
    ];
```

### Step 3: Test
```bash
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"your-email@gmail.com"}'

# Check your Gmail inbox - OTP email should arrive in 5-10 seconds
```

---

## Method 3: Environment Variables (No File Editing)

Instead of editing mail.php, you can set environment variables:

### For Mailtrap:
```bash
export MAIL_DRIVER=mailtrap
export MAILTRAP_USERNAME=your_username_from_mailtrap
export MAILTRAP_PASSWORD=your_password_from_mailtrap
```

### For Gmail:
```bash
export MAIL_DRIVER=gmail
export GMAIL_USERNAME=your-email@gmail.com
export GMAIL_PASSWORD=your-16-char-app-password
```

Then restart your PHP server:
```bash
cd /mnt/academics/iit_shelf_test
php -S localhost:8000 -t backend/api
```

---

## Quick Test Without Credentials

You can test the system is working by checking the OTP log (emails won't be sent but OTPs are logged):

```bash
# Send OTP
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com"}'

# Check log file - OTP should appear here
tail /tmp/iit_shelf_otp.log
```

You'll see: `[2026-01-04 XX:XX:XX] EMAIL: test@example.com | OTP: 123456 | PURPOSE: EmailVerification`

This means the system is working - you just need to add email credentials for actual delivery.

---

## Recommendation

**Easiest for testing:** Use **Gmail** (Method 2 above)
- Takes 5 minutes
- No new account signup
- Emails go to your real inbox
- Easy to test

**Best for production:** Use **SendGrid** or **AWS SES**
- More reliable
- Better delivery rates
- No Gmail rate limits

Let me know which method you prefer and I can help configure it!
