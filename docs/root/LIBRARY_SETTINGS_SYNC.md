# Library Contact Information - Database Integration

## Overview
Library contact information is now stored in the database and can be dynamically updated without modifying the app code.

## Database

### Table: Library_Settings
```sql
CREATE TABLE Library_Settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Default Settings
- `library_email`: library@nstu.edu.bd
- `library_phone`: +880 1234-567890
- `library_hours`: Mon-Fri: 9:00 AM - 5:00 PM
- `library_location`: Central Library, NSTU Campus

## Backend APIs

### 1. Get Library Settings
**Endpoint**: `GET /api/settings/get_library_settings.php`

**Response**:
```json
{
  "success": true,
  "settings": {
    "library_email": "library@nstu.edu.bd",
    "library_phone": "+880 1234-567890",
    "library_hours": "Mon-Fri: 9:00 AM - 5:00 PM",
    "library_location": "Central Library, NSTU Campus"
  }
}
```

### 2. Update Library Settings
**Endpoint**: `POST /api/settings/update_library_settings.php`

**Request Body**:
```json
{
  "settings": {
    "library_email": "newlibrary@nstu.edu.bd",
    "library_phone": "+880 1234-567890",
    "library_hours": "Mon-Sat: 8:00 AM - 6:00 PM",
    "library_location": "Central Library, NSTU Campus"
  }
}
```

**Response**:
```json
{
  "success": true,
  "message": "Successfully updated 4 settings"
}
```

## Flutter Implementation

### Service: LibrarySettingsService
**File**: `lib/library_settings_service.dart`

**Methods**:
- `fetchLibrarySettings()`: Fetches library contact information from database

**Model**:
```dart
class LibrarySettings {
  final String email;
  final String phone;
  final String hours;
  final String location;
}
```

### Updated Page: ContactLibrarianPage
**File**: `lib/contact_librarian.dart`

Now uses `FutureBuilder` to fetch settings from database instead of hardcoded values.

## How to Update Contact Information

### Option 1: Direct Database Update
```sql
UPDATE Library_Settings 
SET setting_value = 'newemail@nstu.edu.bd' 
WHERE setting_key = 'library_email';
```

### Option 2: API Update
```bash
curl -X POST http://localhost:8000/api/settings/update_library_settings.php \
  -H "Content-Type: application/json" \
  -d '{"settings": {"library_email": "newemail@nstu.edu.bd"}}'
```

### Option 3: Admin Panel (Future Enhancement)
A dedicated admin panel page can be created for librarians to update these settings through the UI.

## Files Created/Modified

### Created:
1. `backend/database/setup_library_settings.sql` - Database schema and initial data
2. `backend/api/settings/get_library_settings.php` - Fetch settings API
3. `backend/api/settings/update_library_settings.php` - Update settings API
4. `lib/library_settings_service.dart` - Flutter service for settings

### Modified:
1. `lib/contact_librarian.dart` - Now fetches from database using FutureBuilder

## Benefits
✅ **Dynamic Updates**: Contact information can be changed without redeploying the app
✅ **Centralized Storage**: Single source of truth in the database
✅ **Easy Management**: Simple SQL updates or API calls to change information
✅ **Scalable**: Easy to add new settings in the future
✅ **Fallback Values**: App has default values if database fetch fails

## Next Steps (Optional)
- Create an admin panel UI for librarians to update settings
- Add more configurable settings (fines per day, borrow limits, etc.)
- Add validation and authorization for update API
- Create audit log for setting changes
