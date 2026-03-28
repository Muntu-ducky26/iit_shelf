# 12-Hour Borrow Request Implementation

## Overview
Added support for queue position 1 users to borrow books for 12 hours instead of the standard duration. When a librarian approves a borrow request for a queue position 1 user, they now have the option to approve for a standard duration or for 12 hours only.

## Changes Made

### 1. Backend: `backend/api/librarian/approve_borrow_request.php`
- Added `duration_hours` parameter to the request payload
- Modified loan duration calculation to check if `duration_hours` is specified
- If 12 hours is requested, converts it to days (0.5 days) for the due date calculation
- Maintains backward compatibility (no duration_hours = use standard role-based duration)

**Key Changes:**
```php
$durationHours = isset($data->duration_hours) ? (int)$data->duration_hours : 0;

// If duration_hours is specified (e.g., 12 for queue position 1), use it
if ($durationHours > 0) {
    $loanDays = $durationHours / 24;  // Convert hours to days
} else {
    // Use standard role-based duration
}
```

### 2. Frontend: `lib/librarian_requests.dart`

#### Updated Card Widget
- `_buildBorrowRequestCard()` now accepts optional `onApprove12h` callback
- When `onApprove12h` is provided, displays two buttons side-by-side:
  - **"Approve"** (Blue) - Standard duration based on role
  - **"12 Hours"** (Orange) - 12-hour duration for queue position 1
- Single "Approve" button shown when no 12-hour option available

#### Updated Borrow Tab
- `_buildBorrowTab()` now detects queue position 1 requests
- Determines if user is at queue position 1 using `expires_in_hours` > 0
- Passes `onApprove12h` callback only for queue position 1 users

#### Updated Copy Selection Flow
- `_promptCopySelection()` now accepts `duration12h` parameter
- Passes duration parameter to `_approveBorrowWithCopy()`
- Dialog title changes to "Select Copy (12 Hours)" when applicable

#### Updated Approval Method
- `_approveBorrowWithCopy()` now accepts `duration12h` parameter
- Includes `duration_hours: 12` in request body when 12-hour approval is selected
- Displays "(12 hours)" in success message for clarity

## User Experience Flow

### For Queue Position 1 Users:
1. Librarian sees the borrow request in the "Requests" tab
2. Request shows expiration timer (e.g., "Expires in 12h 0m")
3. Two action buttons appear:
   - **Approve** - Standard 7/15/30 day duration
   - **12 Hours** - Temporary 12-hour borrow window
4. Librarian selects a copy
5. Book is issued with appropriate due date

### For Other Users:
- Standard approval flow continues unchanged
- Single "Approve" button appears

## Testing Recommendations

1. **Test with Queue Position 1 User:**
   - Create a borrow request for a user at queue position 1
   - Verify "12 Hours" button appears in librarian interface
   - Click "12 Hours" and select a copy
   - Verify book due date is approximately 12 hours from now

2. **Test Standard Duration:**
   - Click "Approve" button for same request
   - Verify book due date follows standard role-based duration

3. **Test Non-Queue Users:**
   - Create requests for users not at queue position 1
   - Verify only "Approve" button is available
   - Standard approval flow should work normally

## Database Impact
- No schema changes required
- Due dates are calculated dynamically based on duration
- Existing data remains compatible

## Backward Compatibility
- API accepts optional `duration_hours` parameter
- If not provided, uses existing logic
- All existing integrations continue to work

## Files Modified
- `backend/api/librarian/approve_borrow_request.php`
- `lib/librarian_requests.dart`
