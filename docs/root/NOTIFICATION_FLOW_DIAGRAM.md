# Notification System - Complete Flow Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         NOTIFICATION SYSTEM                          │
└─────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  TRIGGERS (When notifications are created)                            │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  1. REAL-TIME TRIGGERS (Immediate)                                    │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  a) Librarian approves borrow request                          │  │
│  │     → notifyBorrowRequestApproved()                            │  │
│  │     → "Your borrow request for '[Title]' has been approved"    │  │
│  │                                                                 │  │
│  │  b) Librarian processes return                                 │  │
│  │     → notifyReturnRequestApproved()                            │  │
│  │     → "Your return of '[Title]' has been processed"            │  │
│  │                                                                 │  │
│  │  c) Director approves book addition                            │  │
│  │     → notifyAdditionRequestApproved()                          │  │
│  │     → "Your request to add '[Title]' has been approved"        │  │
│  │                                                                 │  │
│  │  d) Reserved book becomes available                            │  │
│  │     → ensureReservationWindow() detects book returned          │  │
│  │     → notifyReservedBookAvailable()                            │  │
│  │     → "Your reserved book '[Title]' is available! Borrow in    │  │
│  │        12 hours"                                                │  │
│  │                                                                 │  │
│  │  e) Queue position changes                                     │  │
│  │     → renumberQueuePositions() after someone removed           │  │
│  │     → notifyQueuePositionChanges()                             │  │
│  │     → "You are now #X in the queue for '[Title]'"             │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│  2. SCHEDULED TRIGGERS (Automated)                                    │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  a) Due date approaching (MySQL Event - Daily 8 AM)           │  │
│  │     → send_due_date_reminders event runs                       │  │
│  │     → Finds books due within 24 hours                          │  │
│  │     → notifyDueDateApproaching() for each                      │  │
│  │     → "Reminder: '[Title]' is due tomorrow"                    │  │
│  │                                                                 │  │
│  │  b) Unpaid fines (MySQL Event - Every 3 days, 9 AM)           │  │
│  │     → send_fine_reminders event runs                           │  │
│  │     → Finds users with unpaid fines                            │  │
│  │     → notifyUnpaidFine() for each                              │  │
│  │     → "You have an unpaid fine of $X.XX"                       │  │
│  └────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  DATABASE (Storage)                                                    │
├───────────────────────────────────────────────────────────────────────┤
│  Table: Notifications                                                  │
│  ┌─────────────────┬──────────────────────────────────────────────┐  │
│  │ notification_id │ Auto-increment primary key                   │  │
│  │ user_email      │ Who receives the notification                │  │
│  │ message         │ Notification text                            │  │
│  │ type            │ Enum: BorrowRequestApproved,                 │  │
│  │                 │       ReturnRequestApproved,                 │  │
│  │                 │       ReservedBookAvailable,                 │  │
│  │                 │       ReservationQueueUpdate,                │  │
│  │                 │       DueDateReminder,                       │  │
│  │                 │       FineReminder,                          │  │
│  │                 │       AdditionRequestApproved,               │  │
│  │                 │       PaymentConfirmation,                   │  │
│  │                 │       System                                 │  │
│  │ sent_at         │ Timestamp (default: CURRENT_TIMESTAMP)       │  │
│  └─────────────────┴──────────────────────────────────────────────┘  │
│                                                                        │
│  Indexes:                                                              │
│  - idx_user_email (for fast user lookup)                              │
│  - idx_sent_at (for chronological sorting)                            │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  BACKEND API (PHP)                                                     │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  1. GET /auth/get_notifications.php                                   │
│     Parameters: email, limit (optional, default: 50)                  │
│     Returns: Array of notifications ordered by sent_at DESC           │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ SELECT * FROM Notifications                                  │ │
│     │ WHERE user_email = ?                                         │ │
│     │ ORDER BY sent_at DESC                                        │ │
│     │ LIMIT ?                                                      │ │
│     └──────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  2. POST /auth/send_notifications.php                                 │
│     Parameters: type (due_dates | fines | all)                        │
│     Purpose: Manual trigger for testing/cron                          │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ if (type === 'due_dates' || type === 'all'):                │ │
│     │     sendDueDateReminders($pdo)                               │ │
│     │                                                              │ │
│     │ if (type === 'fines' || type === 'all'):                    │ │
│     │     sendFineReminders($pdo)                                  │ │
│     └──────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  3. Helper Functions (lib/notification_helpers.php)                   │
│     - createNotification($pdo, $email, $message, $type)               │
│     - notifyBorrowRequestApproved(...)                                │
│     - notifyReturnRequestApproved(...)                                │
│     - notifyAdditionRequestApproved(...)                              │
│     - notifyReservedBookAvailable(...)                                │
│     - notifyReservationQueueUpdate(...)                               │
│     - notifyDueDateApproaching(...)                                   │
│     - notifyUnpaidFine(...)                                           │
│     - sendDueDateReminders($pdo)                                      │
│     - sendFineReminders($pdo)                                         │
│     - getUserNotifications($pdo, $email, $limit)                      │
│     - notifyQueuePositionChanges($pdo, $isbn)                         │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  MYSQL EVENTS (Automation)                                             │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Event Scheduler: ON                                                   │
│                                                                        │
│  1. send_due_date_reminders                                           │
│     Schedule: EVERY 1 DAY at 08:00:00                                 │
│     Action: Find books due within 24 hours → Create notifications     │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ INSERT INTO Notifications (user_email, message, type)        │ │
│     │ SELECT requester_email,                                      │ │
│     │        CONCAT('Reminder: "', title, '" is due tomorrow'),    │ │
│     │        'DueDateReminder'                                     │ │
│     │ FROM Approved_Transactions                                   │ │
│     │ WHERE due_date BETWEEN NOW() AND NOW() + 24 HOURS            │ │
│     └──────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  2. send_fine_reminders                                               │
│     Schedule: EVERY 3 DAY at 09:00:00                                 │
│     Action: Find unpaid fines → Create notifications                  │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ INSERT INTO Notifications (user_email, message, type)        │ │
│     │ SELECT requester_email,                                      │ │
│     │        CONCAT('Unpaid fine: $', fine_amount),                │ │
│     │        'FineReminder'                                        │ │
│     │ FROM Fines                                                   │ │
│     │ WHERE payment_status = 'Unpaid'                              │ │
│     └──────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  3. cleanup_old_notifications                                         │
│     Schedule: EVERY 1 MONTH at 02:00:00                              │
│     Action: Delete notifications older than 90 days                   │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ DELETE FROM Notifications                                    │ │
│     │ WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)            │ │
│     └──────────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  FRONTEND (Flutter)                                                    │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  1. BookService.getNotifications(email, limit)                        │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ http.get('/auth/get_notifications.php?email=...&limit=...')  │ │
│     │ → Returns List<dynamic> of notification objects              │ │
│     └──────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  2. NotificationsPage Widget (lib/notifications.dart)                 │
│     ┌──────────────────────────────────────────────────────────────┐ │
│     │ initState():                                                 │ │
│     │   → loadNotifications()                                      │ │
│     │                                                              │ │
│     │ loadNotifications():                                         │ │
│     │   → email = AuthService.getCurrentUserEmail()                │ │
│     │   → data = await BookService.getNotifications(email)         │ │
│     │   → Transform data into NotificationItem objects             │ │
│     │   → setState() to update UI                                  │ │
│     │                                                              │ │
│     │ UI Components:                                               │ │
│     │   - AppBar with refresh button                              │ │
│     │   - Loading spinner (while isLoading)                       │ │
│     │   - Empty state (if notifications.isEmpty)                  │ │
│     │   - RefreshIndicator (pull-to-refresh)                      │ │
│     │   - ListView of notification cards                          │ │
│     │                                                              │ │
│     │ Notification Card:                                           │ │
│     │   - Icon (based on type)                                     │ │
│     │   - Color (based on type)                                    │ │
│     │   - Message text                                             │ │
│     │   - Relative timestamp (2h ago, 3d ago, etc.)               │ │
│     └──────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  3. Icon & Color Mapping:                                             │
│     BorrowRequestApproved     → ✅ Green (check_circle)              │
│     ReturnRequestApproved     → ✅ Green (check_circle)              │
│     AdditionRequestApproved   → ✅ Green (library_add_check)         │
│     ReservedBookAvailable     → 🔔 Orange (notification_important)   │
│     DueDateReminder           → 🔔 Orange (schedule)                 │
│     FineReminder              → ⚠️ Red (warning)                     │
│     ReservationQueueUpdate    → 🔄 Teal (update)                     │
│     System                    → 🔔 Grey (notifications)              │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  USER FLOW                                                             │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  1. User logs into app (Student/Teacher/Director)                     │
│  2. User navigates to Notifications page                              │
│  3. NotificationsPage loads:                                          │
│     a) Shows loading spinner                                          │
│     b) Calls BookService.getNotifications(userEmail)                  │
│     c) Backend fetches from Notifications table                       │
│     d) Displays notifications with icons, colors, timestamps          │
│  4. User can:                                                          │
│     - Pull down to refresh (RefreshIndicator)                         │
│     - Tap refresh icon in AppBar                                      │
│     - Read notification messages                                      │
│     - See color-coded priorities (green/orange/red)                   │
│  5. New notifications appear:                                         │
│     - When librarian/director performs action                         │
│     - When scheduled events run (8 AM, every 3 days)                  │
│     - When reservation system updates                                 │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│  COMPLETE EXAMPLE: Book Due Date Notification                         │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Day 1 (Jan 1): Student borrows "Database Systems" (due Jan 3)        │
│     → Record created in Approved_Transactions                         │
│     → status = 'Borrowed', due_date = '2025-01-03'                    │
│                                                                        │
│  Day 2 (Jan 2, 8:00 AM): MySQL event runs                             │
│     → send_due_date_reminders event executes                          │
│     → Finds "Database Systems" due within 24 hours                    │
│     → Creates notification:                                           │
│        {                                                               │
│          user_email: 'student@example.com',                           │
│          message: 'Reminder: "Database Systems" is due tomorrow',     │
│          type: 'DueDateReminder',                                     │
│          sent_at: '2025-01-02 08:00:00'                               │
│        }                                                               │
│                                                                        │
│  Day 2 (Jan 2, 9:00 AM): Student opens app                            │
│     → Goes to Notifications page                                      │
│     → Flutter calls BookService.getNotifications()                    │
│     → Backend returns notification                                    │
│     → UI displays:                                                    │
│        🔔 [Orange] "Reminder: Database Systems is due tomorrow"       │
│            Timestamp: 1h ago                                          │
│                                                                        │
│  Day 3 (Jan 3): Student returns book on time                          │
│     → No fine charged                                                 │
│                                                                        │
│  Day 92 (April 3, 2:00 AM): Cleanup event runs                        │
│     → cleanup_old_notifications event executes                        │
│     → Deletes notification (90+ days old)                             │
└───────────────────────────────────────────────────────────────────────┘

```

## Summary

**Data Flow:**
1. **Trigger** (User action or scheduled event) 
2. **Helper Function** (notification_helpers.php creates notification)
3. **Database** (INSERT into Notifications table)
4. **API** (GET /auth/get_notifications.php retrieves)
5. **Flutter** (BookService.getNotifications() fetches)
6. **UI** (NotificationsPage displays with icons/colors)

**All notification types covered:**
✅ Borrow/Return/Addition approvals (Real-time)
✅ Reservation updates (Real-time)
✅ Due date reminders (Daily 8 AM)
✅ Fine reminders (Every 3 days, 9 AM)
✅ Queue position changes (Real-time)
