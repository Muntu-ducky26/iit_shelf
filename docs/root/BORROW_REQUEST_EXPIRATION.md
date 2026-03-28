# 24-Hour Borrow Request Expiration - Implementation Summary

## Overview
All borrow requests from every role (Student, Teacher, Librarian, Director) are now automatically valid for only 24 hours. After 24 hours, requests are automatically removed from the database.

## Implementation Details

### 1. **Automatic Cleanup (MySQL Event)**
- **Event Name:** `cleanup_expired_borrow_requests`
- **Schedule:** Runs every 1 hour
- **Action:** Deletes all pending borrow requests older than 24 hours
- **Status:** Enabled and running

```sql
-- Check event status
SELECT event_name, status, last_executed 
FROM information_schema.events 
WHERE event_name = 'cleanup_expired_borrow_requests';
```

### 2. **Runtime Cleanup (PHP)**
Added automatic cleanup at request time to the following endpoints:
- `/api/borrow/request_borrow.php` - Before creating new request
- `/api/librarian/get_requests.php` - Before displaying requests
- `/api/borrow/get_user_transactions.php` - Before fetching user transactions
- `/api/librarian/dashboard_stats.php` - Before calculating stats

**Helper Function:** `/api/lib/request_cleanup.php`
```php
cleanupExpiredBorrowRequests($db);
```

### 3. **Manual Cleanup (Stored Procedure)**
For manual/on-demand cleanup:
```sql
CALL cleanup_expired_requests_now();
```

## User Interface Updates

### Student/Teacher/Director View (`my_books.dart`)
- Pending requests now show expiration countdown
- Display format:
  - "Expires in 23h 45m" (blue color when > 6 hours)
  - "Expires in 5h 30m" (orange color when < 6 hours)
  - "Expires in 45m" (red color when < 1 hour)
  - "Request expired" (red color when expired)

### Librarian View (`librarian_requests.dart`)
- Borrow request cards show expiration time
- Expired requests highlighted with red border
- Warning icon for expired requests
- Expiration countdown visible in the card header

## Backend API Changes

### `/api/borrow/get_user_transactions.php`
Now returns pending requests with expiration info:
```json
{
  "type": "pending",
  "request_id": 123,
  "title": "Book Title",
  "request_date": "2026-01-09 15:30:09",
  "expires_in_hours": 23,
  "expires_in_minutes": 45,
  "is_expired": false
}
```

### `/api/librarian/get_requests.php`
Enhanced borrow requests with:
- `hours_old`: Hours since request was made
- `expires_in_hours`: Hours until expiration
- `expires_in_minutes`: Minutes remaining (in the current hour)
- `is_expired`: Boolean flag

## Testing

### Run the Test Script
```bash
cd /mnt/academics/iit_shelf_test/backend
./test_request_expiration.sh
```

### Manual Testing Steps
1. **Create a request:**
   ```bash
   curl -X POST http://localhost:8000/borrow/request_borrow.php \
     -H "Content-Type: application/json" \
     -d '{"user_email":"test@example.com","isbn":"978-0-201-53174-9"}'
   ```

2. **Check expiration time:**
   ```sql
   SELECT request_id, request_date, 
          TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(request_date, INTERVAL 24 HOUR)) as minutes_left
   FROM Transaction_Requests 
   WHERE status = 'Pending';
   ```

3. **Force expire a request (for testing):**
   ```sql
   UPDATE Transaction_Requests 
   SET request_date = DATE_SUB(NOW(), INTERVAL 25 HOUR)
   WHERE request_id = X;
   ```

4. **Run manual cleanup:**
   ```sql
   CALL cleanup_expired_requests_now();
   ```

## Key Features

✅ **Automatic:** Requests expire without manual intervention
✅ **Universal:** Applies to all user roles equally  
✅ **Real-time:** Runtime cleanup ensures immediate consistency
✅ **Visible:** Users see countdown timers on pending requests
✅ **Fail-safe:** Multiple cleanup mechanisms (event + runtime + manual)
✅ **Performance:** Indexed queries and efficient deletion

## Database Changes

### Table: `Transaction_Requests`
- No schema changes required
- Uses existing `request_date` column with `CURRENT_TIMESTAMP` default
- Cleanup logic: `WHERE status = 'Pending' AND request_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)`

### New Objects
1. **Event:** `cleanup_expired_borrow_requests` (runs hourly)
2. **Procedure:** `cleanup_expired_requests_now()` (manual cleanup)
3. **PHP Helper:** `request_cleanup.php` (runtime cleanup functions)

## Files Modified

### Backend
- `/backend/database/setup_request_expiration.sql` - Event & procedure setup
- `/backend/api/lib/request_cleanup.php` - Cleanup helper functions
- `/backend/api/borrow/request_borrow.php` - Added runtime cleanup
- `/backend/api/borrow/get_user_transactions.php` - Added pending requests & cleanup
- `/backend/api/librarian/get_requests.php` - Added expiration info & cleanup
- `/backend/api/librarian/dashboard_stats.php` - Added cleanup

### Frontend
- `/lib/my_books.dart` - Display expiration countdown for pending requests
- `/lib/librarian_requests.dart` - Show expiration info with visual indicators

### Testing
- `/backend/test_request_expiration.sh` - Comprehensive test script

## Configuration

All timing is centralized in the SQL logic:
- **Expiration Time:** 24 hours (hardcoded in queries)
- **Cleanup Frequency:** Every 1 hour (MySQL event)
- **Grace Period:** None (exact 24-hour cutoff)

To change expiration time, modify `INTERVAL 24 HOUR` in:
1. `setup_request_expiration.sql` (event and procedure)
2. `request_cleanup.php` (PHP helper)
3. All SQL queries checking expiration

## Monitoring

### Check Event Execution
```sql
SELECT event_name, last_executed, status
FROM information_schema.events 
WHERE event_schema = 'iit_shelf';
```

### View Cleanup Statistics
```sql
SELECT 
    COUNT(*) as total_pending,
    SUM(CASE WHEN TIMESTAMPDIFF(HOUR, request_date, NOW()) >= 24 THEN 1 ELSE 0 END) as expired,
    SUM(CASE WHEN TIMESTAMPDIFF(HOUR, request_date, NOW()) < 24 THEN 1 ELSE 0 END) as valid
FROM Transaction_Requests
WHERE status = 'Pending';
```

## Troubleshooting

**Q: Event not running?**
```sql
-- Check event scheduler is enabled
SHOW VARIABLES LIKE 'event_scheduler';

-- Enable if needed (requires SUPER privilege)
SET GLOBAL event_scheduler = ON;
```

**Q: Requests not expiring?**
- Check event status and last execution time
- Run manual cleanup: `CALL cleanup_expired_requests_now();`
- Verify runtime cleanup is being called (check PHP error logs)

**Q: Performance concerns?**
- Cleanup queries use indexed columns (`status`, `request_date`)
- DELETE operations are efficient (typically < 100ms)
- Event runs hourly, not continuously

## Future Enhancements

Potential improvements:
1. Configurable expiration time per role
2. Email notifications before expiration
3. Extension requests (ask for more time)
4. Dashboard widget showing expiring requests
5. Cleanup audit log
