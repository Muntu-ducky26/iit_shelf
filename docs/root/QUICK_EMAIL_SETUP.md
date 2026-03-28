# Email Implementation - Quick Reference

## Current Status
✅ SMTP email system fully implemented (no external dependencies)
✅ OTP logging to file works
✅ Ready to connect to real email provider

## What You Need to Do

### Option 1: Mailtrap (Easiest for Testing)
```bash
# 1. Sign up at https://mailtrap.io (free)
# 2. Get your SMTP credentials from dashboard
# 3. Update this file with your credentials:
vim /mnt/academics/iit_shelf_test/backend/api/config/mail.php

# 4. Change these lines:
'username' => 'your-mailtrap-username',  # Copy from Mailtrap dashboard
'password' => 'your-mailtrap-password',  # Copy from Mailtrap dashboard

# 5. Save and test:
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@gmail.com"}'

# 6. Check Mailtrap inbox for the email
```

### Option 2: Gmail
```bash
# 1. Create app-specific password: https://myaccount.google.com/apppasswords
# 2. Edit mail.php:
vim /mnt/academics/iit_shelf_test/backend/api/config/mail.php

# 3. Change driver to 'gmail' and add:
'username' => 'your-email@gmail.com',
'password' => 'app-specific-password-from-google',

# 4. Test same way as above
```

### Option 3: SendGrid
```bash
# 1. Sign up at https://sendgrid.com
# 2. Create API key
# 3. Edit mail.php to use 'sendgrid' driver
# 4. Set SENDGRID_API_KEY environment variable
```

## System Files

| File | Purpose |
|------|---------|
| `/backend/api/lib/simple_smtp_mailer.php` | Raw SMTP implementation (no deps) |
| `/backend/api/lib/mail_service.php` | High-level email API |
| `/backend/api/config/mail.php` | Configuration (update your credentials here) |
| `/backend/EMAIL_SETUP.md` | Detailed setup guide |
| `/tmp/iit_shelf_otp.log` | Development OTP log (view with: `tail -f /tmp/iit_shelf_otp.log`) |

## How It Works

1. **User registers** → requests OTP
2. **Backend generates OTP** → logs to `/tmp/iit_shelf_otp.log`
3. **Backend sends email** → via SimpleSMTPMailer → your SMTP provider
4. **Provider delivers** → email appears in user's inbox
5. **User verifies OTP** → continues registration

## Testing

```bash
# View OTP log
tail -f /tmp/iit_shelf_otp.log

# Send test OTP
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"your-test-email@gmail.com"}'

# Run full test (all 8 auth steps)
cd /backend && ./test_complete_flow.sh
```

## Key Points

- ✅ No composer or external PHP packages needed
- ✅ Works with any SMTP provider
- ✅ Production-ready SMTP implementation
- ✅ All code in `/backend/api/` - easy to modify
- ✅ Email templates in `mail_service.php` - easy to customize

## Next Step

**Get Mailtrap credentials** (5 minutes) and update `/backend/api/config/mail.php` with your username/password.

Once done, run the test - emails will appear in your Mailtrap inbox!
