# get_user_transactions.php

## Overview
- Source file: backend\api\borrow\get_user_transactions.php
- Category: Borrow/Return API
- Purpose: Handles borrow workflow action: get user transactions.

## What Is Done In This File
- Implements backend logic for get user transactions.
- Handles request validation, data processing, and response output.
- Returns endpoint/script output consumed by frontend or internal flows.

## Related Includes (Quick Scan)
- require_once '../../config/database.php';
- require_once '../lib/request_cleanup.php';
- 'message' => 'Email is required.'
- // Include both Borrowed and Overdue books when fetching borrowed books

## Notes
- This is a concise generated note. Use source code for exact implementation details.
