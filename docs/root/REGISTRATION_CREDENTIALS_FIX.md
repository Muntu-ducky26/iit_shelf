# Registration Credentials Display Fix

## Problem
The registration form was displaying pre-registered credentials (Full Name and Phone Number) **immediately after OTP send**, before the user had verified the OTP. This violated the intended security flow.

**Expected Flow:**
1. User enters email
2. User clicks "Send OTP"
3. Fields show empty (waiting for OTP verification)
4. User enters OTP code
5. User clicks "Verify OTP"
6. **ONLY AFTER verification** - Full Name and Phone Number are populated from pre-registration data (read-only)
7. User sets password

**Actual Behavior (Before Fix):**
- Steps 1-2 worked correctly
- Step 3 was broken - Full Name and Phone were already filled after OTP send, before user entered OTP

## Root Cause
In `lib/register.dart`, the `_sendOtp()` method was directly populating the TextEditingControllers with pre-registered data:

```dart
if (_preRegName != null && _preRegName!.isNotEmpty) {
  _nameController.text = _preRegName!;  // ← Problem: Sets immediately
}
if (_preRegPhone != null && _preRegPhone!.isNotEmpty) {
  _phoneController.text = _preRegPhone!;  // ← Problem: Sets immediately
}
```

## Solution
Moved credential population from `_sendOtp()` to `_verifyOtp()`:

### Changes in `_sendOtp()`:
```dart
Future<void> _sendOtp() async {
  // ... validation and API call ...
  if (res.ok) {
    // Store pre-reg data but DON'T fill controllers yet
    // Controllers will be filled AFTER OTP verification
    _preRegName = res.name;
    _preRegPhone = res.phone;
    _preRegRole = res.role;
    _startResendTimer();  // ← Removed controller.text assignments
  }
}
```

### Changes in `_verifyOtp()`:
```dart
Future<void> _verifyOtp() async {
  // ... OTP validation ...
  final res = await AuthService.verifyEmailOtp(...);
  setState(() {
    _otpVerified = res.ok;
    // Fill credentials ONLY AFTER OTP verification succeeds
    if (_otpVerified) {
      if (_preRegName != null && _preRegName!.isNotEmpty) {
        _nameController.text = _preRegName!;  // ← Now fills AFTER verification
      }
      if (_preRegPhone != null && _preRegPhone!.isNotEmpty) {
        _phoneController.text = _preRegPhone!;  // ← Now fills AFTER verification
      }
    }
  });
}
```

## Files Modified
- `lib/register.dart`:
  - Line 105-129: Modified `_sendOtp()` - removed credential population
  - Line 131-156: Modified `_verifyOtp()` - added credential population

## Testing the Fix

### Manual Test Steps:
1. Run the Flutter app
2. Navigate to Registration page
3. Enter a pre-registered email (e.g., `student3@iit.edu`)
4. Click "Send OTP"
5. **Verify**: Full Name and Phone Number fields should be EMPTY (showing placeholder "Auto-filled after OTP verification")
6. Enter the 6-digit OTP code received via email
7. Click "Verify OTP"
8. **Verify**: Full Name and Phone Number are NOW populated with pre-registered data and are read-only
9. Enter password and confirm password
10. Click "Set Password"
11. Account registration completes

### Pre-registered Test Users:
- Student: `student3@iit.edu` → Full Name: "Karim Abdullah" or "Fatima Rahman"
- Teacher: `teacher1@iit.edu` → Full Name: (check pre-registration table)
- Librarian: `librarian1@iit.edu` → Full Name: (check pre-registration table)
- Director: `director1@iit.edu` → Full Name: (check pre-registration table)

## Security Implications
This fix ensures:
1. **Unverified emails cannot see pre-registered data** - Only after OTP verification are credentials displayed
2. **Pre-registered data is protected** - Data is retrieved from backend but displayed only after verification
3. **Sequential verification flow** - Users must verify OTP before proceeding to password setup
4. **Data integrity** - Read-only fields prevent users from modifying pre-registered information

## Field States Throughout Registration Flow

| Step | Email | OTP | Full Name | Phone | Password |
|------|-------|-----|-----------|-------|----------|
| Initial | Editable | Disabled | Empty, Read-only | Empty, Read-only | Disabled |
| After Send OTP | Disabled | Editable | Empty, Read-only | Empty, Read-only | Disabled |
| After Verify OTP | Disabled | Disabled | Filled, Read-only | Filled, Read-only | Editable |
| After Set Password | Disabled | Disabled | Filled, Read-only | Filled, Read-only | Disabled |

## Backward Compatibility
- No breaking changes to API contracts
- No database schema changes required
- No changes to backend authentication flow
- Only UI timing adjustment in Flutter frontend

## Related Files
- Backend: `backend/api/auth/send_register_otp.php` (unchanged)
- Backend: `backend/api/auth/verify_email.php` (unchanged)
- Backend: `backend/api/auth/set_password.php` (unchanged)
- Frontend: `lib/auth_service.dart` (unchanged)
- Pre-registration schema: PreReg_Students, PreReg_Teachers, PreReg_Librarians, PreReg_Directors
