# reject_borrow_request.php

## Overview
- Source file: backend\api\librarian\reject_borrow_request.php
- Category: Librarian API
- Purpose: Handles librarian operation: reject borrow request.

## What Is Done In This File
- Implements backend logic for reject borrow request.
- Handles request validation, data processing, and response output.
- Returns endpoint/script output consumed by frontend or internal flows.

## Related Includes (Quick Scan)
- include_once '../../config/database.php';
- include_once '../lib/notification_helpers.php';
- echo json_encode(['success'=>false,'message'=>'request_id is required']);

## Notes
- This is a concise generated note. Use source code for exact implementation details.
