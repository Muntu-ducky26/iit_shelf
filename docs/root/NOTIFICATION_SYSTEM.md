# Notification System Implementation

## Overview
Comprehensive notification system for the IIT Shelf library management system. Users receive real-time notifications for important events related to book requests, reservations, due dates, and fines.

## Features

### 1. Notification Types
The system supports the following notification types:

1. **BorrowRequestApproved** - When a borrow request is approved by librarian
2. **ReturnRequestApproved** - When a return is processed by librarian
3. **ReservedBookAvailable** - When a reserved book becomes available (12-hour window)
4. **ReservationQueueUpdate** - When queue position changes
5. **DueDateReminder** - When book due date is within 1 day
6. **FineReminder** - For unpaid fines (sent every 3 days)
7. **AdditionRequestApproved** - When a book addition request is approved

### 2. Automated Notifications
MySQL Event Scheduler runs the following tasks:

- **Due Date Reminders**: Daily at 8:00 AM
  - Notifies users with books due within 24 hours
  
- **Fine Reminders**: Every 3 days at 9:00 AM
  - Notifies users with unpaid fines
  
- **Cleanup**: Monthly at 2:00 AM
  - Removes notifications older than 90 days

### 3. Real-time Notifications
Triggered immediately when:
- Librarian approves/rejects borrow request
- Librarian processes a return
- Book becomes available for #1 in reservation queue
- Queue position changes after someone fails to borrow
- Director approves book addition request

## Database Schema

### Notifications Table
```sql
CREATE TABLE Notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('BorrowRequestApproved', 'ReturnRequestApproved', 
              'ReservedBookAvailable', 'ReservationQueueUpdate',
              'DueDateReminder', 'FineReminder', 'AdditionRequestApproved',
              'PaymentConfirmation', 'System') DEFAULT 'System',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_email (user_email),
    INDEX idx_sent_at (sent_at)
);
```

## Backend Implementation

### Core Files

#### 1. `backend/api/lib/notification_helpers.php`
Central notification management with helper functions:

**Key Functions:**
- `createNotification($pdo, $email, $message, $type)` - Create a notification
- `notifyBorrowRequestApproved($pdo, $email, $isbn, $bookTitle)` - Borrow approval
- `notifyReturnRequestApproved($pdo, $email, $isbn, $bookTitle)` - Return confirmation
- `notifyAdditionRequestApproved($pdo, $email, $bookTitle)` - Addition approval
- `notifyReservedBookAvailable($pdo, $email, $isbn, $bookTitle)` - 12h window notification
- `notifyReservationQueueUpdate($pdo, $email, $isbn, $bookTitle, $position)` - Queue update
- `notifyDueDateApproaching($pdo, $email, $isbn, $bookTitle, $dueDate)` - Due date reminder
- `notifyUnpaidFine($pdo, $email, $fineAmount)` - Fine reminder
- `sendDueDateReminders($pdo)` - Send all due date reminders
- `sendFineReminders($pdo)` - Send all fine reminders
- `getUserNotifications($pdo, $email, $limit)` - Get user's notifications
- `notifyQueuePositionChanges($pdo, $isbn)` - Notify all queue members of position changes

#### 2. `backend/database/setup_notification_events.sql`
MySQL events for automated notifications:
- `send_due_date_reminders` - Daily at 8 AM
- `send_fine_reminders` - Every 3 days at 9 AM
- `cleanup_old_notifications` - Monthly cleanup

**Setup:**
```bash
mysql -u root -proot iit_shelf < backend/database/setup_notification_events.sql
```

#### 3. `backend/api/auth/get_notifications.php`
API endpoint to retrieve user notifications:

**Endpoint:** `GET /auth/get_notifications.php`

**Parameters:**
- `email` (required) - User email
- `limit` (optional, default: 50) - Max notifications to return

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "notification_id": 1,
      "message": "Your borrow request for 'Book Title' has been approved!",
      "type": "BorrowRequestApproved",
      "sent_at": "2025-01-08 10:30:00"
    }
  ]
}
```

#### 4. `backend/api/auth/send_notifications.php`
Manual trigger for sending notifications (for testing/cron):

**Endpoint:** `POST /auth/send_notifications.php`

**Parameters:**
- `type` (optional) - Notification type to send: `due_dates`, `fines`, or `all`

**Response:**
```json
{
  "status": "success",
  "message": "Notifications sent successfully",
  "details": {
    "due_dates_sent": 5,
    "fines_sent": 2
  }
}
```

### Integration Points

#### Borrow Request Approval
File: `backend/api/librarian/approve_borrow_request.php`
```php
require_once __DIR__ . '/../lib/notification_helpers.php';
notifyBorrowRequestApproved($pdo, $email, $isbn, $bookTitle);
```

#### Book Addition Approval
File: `backend/api/books/approve_request.php`
```php
require_once __DIR__ . '/../lib/notification_helpers.php';
notifyAdditionRequestApproved($pdo, $requesterEmail, $bookTitle);
```

#### Reservation Window Start
File: `backend/api/lib/reservation_helpers.php`
```php
require_once __DIR__ . '/notification_helpers.php';
notifyReservedBookAvailable($pdo, $email, $isbn, $bookTitle);
```

#### Queue Position Changes
File: `backend/api/lib/reservation_helpers.php`
```php
notifyQueuePositionChanges($pdo, $isbn);
```

## Frontend Implementation

### Flutter Integration

#### 1. `lib/book_service.dart`
Added `getNotifications` method to fetch notifications:

```dart
static Future<List<dynamic>> getNotifications(String email, {int limit = 50}) async {
  final response = await http.get(
    Uri.parse('$_baseUrl/auth/get_notifications.php?email=$email&limit=$limit'),
  );
  
  if (response.statusCode == 200) {
    final data = json.decode(response.body);
    if (data['status'] == 'success') {
      return data['data'] ?? [];
    }
  }
  return [];
}
```

#### 2. `lib/notifications.dart`
Updated notifications page to display database-synced notifications:

**Features:**
- Loading state with spinner
- Pull-to-refresh
- Manual refresh button
- Color-coded notification types
- Icon indicators for different types
- Relative timestamps (e.g., "2h ago", "3d ago")
- Empty state when no notifications

**Notification Icons & Colors:**
- ✅ Green: Approved requests (borrow/return/addition)
- 🔔 Orange: Urgent (reserved book available, due date)
- ⚠️ Red: Fines
- 🔄 Teal: Queue updates

## Testing

### Test Script
Run the comprehensive test:
```bash
cd /mnt/academics/iit_shelf_test/backend
./test_notifications.sh
```

**Tests:**
1. Fetch notifications for test user
2. Manually trigger due date reminders
3. Manually trigger fine reminders
4. Verify notifications after triggers
5. Check MySQL event scheduler status

### Manual Testing

#### Create Test Notification
```bash
mysql -u root -proot iit_shelf -e "
INSERT INTO Notifications (user_email, message, type) 
VALUES ('eusha@nstu.edu.bd', 'Test notification', 'System');
"
```

#### Trigger Due Date Reminders
```bash
curl -X POST http://localhost:8000/auth/send_notifications.php \
  -H "Content-Type: application/json" \
  -d '{"type": "due_dates"}'
```

#### Get User Notifications
```bash
curl "http://localhost:8000/auth/get_notifications.php?email=eusha@nstu.edu.bd&limit=10"
```

## Notification Flow Examples

### Example 1: Borrow Request Approved
1. Student requests to borrow a book
2. Librarian approves the request
3. `notifyBorrowRequestApproved()` called in `approve_borrow_request.php`
4. Notification inserted: "Your borrow request for '[Book Title]' has been approved!"
5. Student sees notification in Flutter app

### Example 2: Reserved Book Available
1. Book has 3 people in reservation queue
2. Physical copy returned to library
3. `ensureReservationWindow()` in `reservation_helpers.php` activates
4. #1 in queue gets 12-hour window
5. `notifyReservedBookAvailable()` called
6. Notification: "Your reserved book '[Book Title]' is now available! Borrow within 12 hours."

### Example 3: Due Date Approaching
1. MySQL event runs daily at 8 AM
2. `sendDueDateReminders()` finds books due within 24 hours
3. For each book: `notifyDueDateApproaching()` called
4. Notification: "Reminder: '[Book Title]' is due on [Date]. Please return to avoid fines."

### Example 4: Fine Reminder
1. MySQL event runs every 3 days at 9 AM
2. `sendFineReminders()` finds users with unpaid fines
3. For each user: `notifyUnpaidFine()` called
4. Notification: "You have an unpaid fine of $[Amount]. Please pay to continue borrowing."

### Example 5: Queue Position Update
1. #1 in queue fails to borrow within 12 hours
2. System removes #1 from queue
3. `renumberQueuePositions()` updates positions
4. `notifyQueuePositionChanges()` notifies all members
5. Notifications sent:
   - Old #2 (now #1): "You are now #1 in the queue for '[Book Title]'!"
   - Old #3 (now #2): "You are now #2 in the queue for '[Book Title]'"

## Monitoring

### Check Event Scheduler Status
```sql
SHOW VARIABLES LIKE 'event_scheduler';
-- Should return: ON
```

### View Active Events
```sql
SELECT EVENT_NAME, STATUS, LAST_EXECUTED, NEXT_EXECUTION_TIME
FROM information_schema.EVENTS 
WHERE EVENT_SCHEMA = 'iit_shelf';
```

### View Recent Notifications
```sql
SELECT * FROM Notifications 
ORDER BY sent_at DESC 
LIMIT 20;
```

### Count Notifications by Type
```sql
SELECT type, COUNT(*) as count 
FROM Notifications 
GROUP BY type 
ORDER BY count DESC;
```

## Maintenance

### Disable Notification Events (if needed)
```sql
ALTER EVENT send_due_date_reminders DISABLE;
ALTER EVENT send_fine_reminders DISABLE;
```

### Re-enable Events
```sql
ALTER EVENT send_due_date_reminders ENABLE;
ALTER EVENT send_fine_reminders ENABLE;
```

### Manual Cleanup
```sql
DELETE FROM Notifications 
WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Troubleshooting

### No Notifications Appearing?
1. Check event scheduler: `SHOW VARIABLES LIKE 'event_scheduler';`
2. Enable if OFF: `SET GLOBAL event_scheduler = ON;`
3. Check events exist: `SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'iit_shelf';`
4. Check notification table: `SELECT * FROM Notifications LIMIT 10;`

### Notifications Not Showing in Flutter?
1. Check API endpoint: `curl http://localhost:8000/auth/get_notifications.php?email=test@example.com`
2. Check network logs in Flutter app
3. Verify user email is correct
4. Check for CORS issues

### Events Not Running?
1. Verify MySQL event scheduler is ON
2. Check event status: `SHOW EVENTS;`
3. Check MySQL error log for event execution errors
4. Test functions manually: Call `sendDueDateReminders()` in PHP script

## Future Enhancements

Potential additions:
- Push notifications (Firebase Cloud Messaging)
- Email notifications for critical events
- In-app notification badge counter
- Mark notifications as read/unread
- Delete individual notifications
- Filter notifications by type
- Notification preferences/settings
- Digest emails (daily/weekly summary)

## Summary

The notification system is fully integrated with:
- ✅ Database table created
- ✅ Backend helpers implemented
- ✅ MySQL events scheduled
- ✅ API endpoints created
- ✅ Flutter UI updated
- ✅ All 5 notification triggers working:
  1. Request approvals (borrow/return/addition)
  2. Reservation updates (queue changes + 12h window)
  3. Due date reminders (1 day before)
  4. Fine reminders (every 3 days)
  5. Queue position changes

All roles (Student, Teacher, Director, Librarian) receive relevant notifications based on their actions and responsibilities.
