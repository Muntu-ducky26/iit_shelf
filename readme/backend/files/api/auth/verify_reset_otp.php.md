# verify_reset_otp.php

## Overview
- Source file: backend\api\auth\verify_reset_otp.php
- Category: Authentication API
- Purpose: Handles auth-related action: verify reset otp.

## What Is Done In This File
- Implements backend logic for verify reset otp.
- Handles request validation, data processing, and response output.
- Returns endpoint/script output consumed by frontend or internal flows.

## Related Includes (Quick Scan)
- require_once '../../config/database.php';
- require_once '../lib/auth_helpers.php';
- require_once '../lib/otp_attempt_tracker.php';
- 'message' => 'Email and OTP are required.',

## Notes
- This is a concise generated note. Use source code for exact implementation details.
