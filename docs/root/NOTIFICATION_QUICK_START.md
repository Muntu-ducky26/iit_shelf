# Notification System - Quick Start Guide

## ✅ Implementation Complete

The notification system is fully integrated and operational.

## What's Working

### 1. Database
- ✅ `Notifications` table created with all notification types
- ✅ Proper indexes for performance
- ✅ Enum types: BorrowRequestApproved, ReturnRequestApproved, ReservedBookAvailable, ReservationQueueUpdate, DueDateReminder, FineReminder, AdditionRequestApproved

### 2. Backend (PHP)
- ✅ `backend/api/lib/notification_helpers.php` - Core notification functions
- ✅ `backend/api/auth/get_notifications.php` - API to fetch notifications
- ✅ `backend/api/auth/send_notifications.php` - Manual trigger for testing
- ✅ Integration in approval endpoints (borrow, return, addition)
- ✅ Integration in reservation system (queue updates, 12h windows)

### 3. Automated Tasks (MySQL Events)
- ✅ `send_due_date_reminders` - Runs daily at 8 AM
- ✅ `send_fine_reminders` - Runs every 3 days at 9 AM
- ✅ `cleanup_old_notifications` - Runs monthly at 2 AM
- ✅ Event Scheduler: ENABLED

### 4. Frontend (Flutter)
- ✅ `lib/book_service.dart` - getNotifications() method added
- ✅ `lib/notifications.dart` - UI updated to display database notifications
- ✅ Pull-to-refresh functionality
- ✅ Color-coded notification types
- ✅ Relative timestamps
- ✅ Empty state handling
- ✅ Loading states

## Quick Test

### 1. Check API
```bash
curl "http://localhost:8000/auth/get_notifications.php?email=eusha@nstu.edu.bd&limit=10"
```

### 2. Create Test Notification
```bash
mysql -u iit_user -piit_password iit_shelf << 'EOF'
INSERT INTO Notifications (user_email, message, type) 
VALUES ('eusha@nstu.edu.bd', 'Test: Your request has been approved', 'BorrowRequestApproved');
EOF
```

### 3. Manual Trigger (Testing)
```bash
# Trigger due date reminders
curl -X POST http://localhost:8000/auth/send_notifications.php \
  -H "Content-Type: application/json" \
  -d '{"type": "due_dates"}'

# Trigger fine reminders
curl -X POST http://localhost:8000/auth/send_notifications.php \
  -H "Content-Type: application/json" \
  -d '{"type": "fines"}'
```

### 4. Check Events Status
```bash
mysql -u iit_user -piit_password iit_shelf -e "
SELECT EVENT_NAME, STATUS, LAST_EXECUTED, INTERVAL_VALUE, INTERVAL_FIELD 
FROM information_schema.EVENTS 
WHERE EVENT_SCHEMA = 'iit_shelf';
"
```

## Notification Triggers

### Real-time (Immediate)
1. **Borrow Request Approved** - When librarian approves borrow
2. **Return Request Approved** - When librarian processes return
3. **Addition Request Approved** - When director approves new book
4. **Reserved Book Available** - When #1 in queue gets 12h window
5. **Queue Position Update** - When someone moves up in reservation queue

### Scheduled (Automated)
6. **Due Date Reminder** - Daily at 8 AM (books due within 24 hours)
7. **Fine Reminder** - Every 3 days at 9 AM (unpaid fines)

## User Experience

### For Students/Teachers/Directors
1. Open app → Navigate to Notifications page
2. See all notifications with:
   - ✅ Green icons for approved requests
   - 🔔 Orange icons for urgent items (due dates, reserved books)
   - ⚠️ Red icons for fines
   - 🔄 Teal icons for queue updates
3. Pull down to refresh
4. Tap refresh icon to reload

### Notification Examples
- "Your borrow request for 'Database Systems' has been approved"
- "Your reserved book 'Operating Systems' is now available. Borrow within 12 hours"
- "Reminder: 'Computer Networks' is due tomorrow. Please return to avoid fines"
- "You are now #1 in the queue for 'Data Structures'"
- "You have an unpaid fine of $5.00. Please pay to continue borrowing"

## Files Modified/Created

### Backend
- ✅ `backend/api/lib/notification_helpers.php` (NEW)
- ✅ `backend/api/auth/get_notifications.php` (NEW)
- ✅ `backend/api/auth/send_notifications.php` (NEW)
- ✅ `backend/database/setup_notification_events.sql` (UPDATED)
- ✅ `backend/api/librarian/approve_borrow_request.php` (UPDATED)
- ✅ `backend/api/books/approve_request.php` (UPDATED)
- ✅ `backend/api/lib/reservation_helpers.php` (UPDATED)
- ✅ `backend/test_notifications.sh` (NEW)

### Frontend
- ✅ `lib/book_service.dart` (UPDATED - added getNotifications)
- ✅ `lib/notifications.dart` (UPDATED - database integration)

### Documentation
- ✅ `NOTIFICATION_SYSTEM.md` (NEW - comprehensive guide)
- ✅ `NOTIFICATION_QUICK_START.md` (NEW - this file)

## Monitoring

### View Recent Notifications
```sql
SELECT notification_id, user_email, message, type, sent_at 
FROM Notifications 
ORDER BY sent_at DESC 
LIMIT 10;
```

### Count by Type
```sql
SELECT type, COUNT(*) as count 
FROM Notifications 
GROUP BY type 
ORDER BY count DESC;
```

### Check Event Scheduler
```sql
SHOW VARIABLES LIKE 'event_scheduler';
-- Should show: ON
```

## Troubleshooting

### Issue: No notifications showing up
**Solution:** Check if test notifications exist
```bash
mysql -u iit_user -piit_password iit_shelf -e "SELECT COUNT(*) FROM Notifications;"
```

### Issue: Events not running
**Solution:** Enable event scheduler
```sql
SET GLOBAL event_scheduler = ON;
```

### Issue: API returns empty array
**Solution:** Check user email matches database records
```bash
curl "http://localhost:8000/auth/get_notifications.php?email=YOUR_EMAIL&limit=10"
```

## Next Steps (Optional Enhancements)

- [ ] Add push notifications (Firebase Cloud Messaging)
- [ ] Email notifications for critical events
- [ ] Mark notifications as read/unread
- [ ] Delete individual notifications
- [ ] Notification preferences/settings
- [ ] Badge counter on notification icon
- [ ] Filter notifications by type

## Summary

🎉 **All 5 notification triggers are working:**

1. ✅ Request approvals (borrow/return/addition) → Real-time
2. ✅ Reservation updates (queue + 12h window) → Real-time
3. ✅ Due date reminders (1 day before) → Automated daily
4. ✅ Addition approvals → Real-time
5. ✅ Fine reminders (every 3 days) → Automated

**Status:** Ready for production use!
