# Borrow Request Workflow - Librarian Approval Required

## Overview
The borrow functionality has been updated to require **librarian approval** before a book is actually borrowed. Users can now only submit borrow requests, which must be reviewed and approved by a librarian.

## Updated Workflow

### User Side (Book Detail Page)

#### 1. Submit Borrow Request
When a user clicks **"Request to Borrow"** button:

**Frontend:** [lib/book_detail.dart](lib/book_detail.dart)
```dart
Future<void> _borrow(BuildContext context) async {
  // Shows loading dialog
  final result = await BookService.borrowBook(isbn: widget.isbn!);
  // Shows success/error message
}
```

**API Call:** [lib/book_service.dart](lib/book_service.dart)
```dart
static Future<ApiResponse> borrowBook({required String isbn}) async {
  final email = AuthService.getCurrentUserEmail();
  return _post('borrow/request_borrow.php', {
    'isbn': isbn,
    if (email != null) 'user_email': email,
  });
}
```

**Backend:** [backend/api/borrow/request_borrow.php](backend/api/borrow/request_borrow.php)
```php
// Creates a PENDING request (not approved yet)
INSERT INTO Transaction_Requests (
    isbn, requester_email, request_date, status
) VALUES (
    :isbn, :requester_email, NOW(), "Pending"
)
```

**Response:**
```json
{
  "success": true,
  "message": "Borrow request submitted successfully. Waiting for librarian approval.",
  "request_id": 123
}
```

**UI Feedback:**
- ✅ Success: "Borrow request submitted successfully. Waiting for librarian approval."
- ❌ Already pending: "You already have a pending request for this book"
- ❌ Already borrowed: "You have already borrowed this book"

### Librarian Side (Requests Page)

#### 2. View Pending Requests
Librarians see all pending borrow requests in the **Requests → Borrow** tab:

**Frontend:** [lib/librarian_requests.dart](lib/librarian_requests.dart)
- Fetches requests via `GET /librarian/get_requests.php?type=borrow`
- Displays user info, book details, and timestamp
- Shows "Approve" button for each request

**Backend:** [backend/api/librarian/get_requests.php](backend/api/librarian/get_requests.php)
```sql
SELECT tr.request_id, tr.isbn, tr.request_date, 
       u.name, u.email, b.title
FROM Transaction_Requests tr
JOIN Users u ON u.email = tr.requester_email
JOIN Books b ON b.isbn = tr.isbn
WHERE tr.status = 'Pending'
ORDER BY tr.request_date DESC
```

#### 3. Approve Borrow Request
When librarian clicks **"Approve"**:

**Backend:** [backend/api/librarian/approve_borrow_request.php](backend/api/librarian/approve_borrow_request.php)

**Process:**
1. Validates request exists and is pending
2. Checks user role and sets loan period:
   - Student: 7 days
   - Teacher: 15 days
   - Librarian/Director: 30 days
3. Finds available book copy
4. Updates `Transaction_Requests.status = 'Approved'`
5. Creates `Approved_Transactions` record
6. Updates `Book_Copies.status = 'Borrowed'`

**SQL Operations:**
```sql
-- 1. Update request status
UPDATE Transaction_Requests 
SET status = "Approved" 
WHERE request_id = :rid

-- 2. Create approved transaction
INSERT INTO Approved_Transactions (
    request_id, copy_id, issue_date, due_date, status
) VALUES (
    :rid, :cid, NOW(), DATE_ADD(NOW(), INTERVAL :days DAY), "Borrowed"
)

-- 3. Update book copy status
UPDATE Book_Copies 
SET status = "Borrowed" 
WHERE copy_id = :cid
```

**Response:**
```json
{
  "success": true,
  "message": "Request approved",
  "copy_id": "0078815967-001"
}
```

## Database Schema

### Transaction_Requests Table
```sql
CREATE TABLE Transaction_Requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  requester_email VARCHAR(150) NOT NULL,
  request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  reviewed_by VARCHAR(150),
  reviewed_at DATETIME
)
```

**Status Values:**
- `Pending` - Waiting for librarian approval (shown in Borrow tab)
- `Approved` - Request approved and book borrowed
- `Rejected` - Request denied by librarian

### Approved_Transactions Table
```sql
CREATE TABLE Approved_Transactions (
  transaction_id INT PRIMARY KEY AUTO_INCREMENT,
  request_id INT NOT NULL,
  copy_id VARCHAR(60) NOT NULL,
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE,
  status ENUM('Borrowed','Returned','Overdue','Lost') DEFAULT 'Borrowed'
)
```

## API Endpoints

### POST /borrow/request_borrow.php
Submit a new borrow request (user side).

**Request:**
```json
{
  "isbn": "0078815967",
  "user_email": "student@iit.ac.lk"
}
```

**Validations:**
- ✓ User exists
- ✓ Book exists
- ✓ No pending request for same book
- ✓ User hasn't already borrowed this book

**Response (Success):**
```json
{
  "success": true,
  "message": "Borrow request submitted successfully. Waiting for librarian approval.",
  "request_id": 123
}
```

### GET /librarian/get_requests.php?type=borrow
Fetch all pending borrow requests (librarian side).

**Response:**
```json
{
  "success": true,
  "count": 2,
  "items": [
    {
      "request_id": "123",
      "name": "John Doe",
      "email": "john.doe@example.com",
      "title": "Teach yourself C",
      "isbn": "0078815967",
      "request_date": "2026-01-07 14:30:00"
    }
  ]
}
```

### POST /librarian/approve_borrow_request.php
Approve a borrow request (librarian side).

**Request:**
```json
{
  "request_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "message": "Request approved",
  "copy_id": "0078815967-001"
}
```

## UI Changes

### Before (Direct Borrow)
```
[Borrow Book] → Book immediately borrowed
```

### After (Request + Approval)
```
[Request to Borrow] → Creates pending request → Librarian approves → Book borrowed
```

### Button Label
**Changed:** "Borrow Book" → **"Request to Borrow"**
- Location: [lib/book_detail.dart](lib/book_detail.dart) line 357

## Testing

### 1. Test the complete workflow:
```bash
cd /mnt/academics/iit_shelf_test/backend
./test_borrow_workflow.sh
```

### 2. Manual testing steps:

**As a User:**
1. Navigate to any book detail page
2. Click "Request to Borrow"
3. Should see: "Borrow request submitted successfully. Waiting for librarian approval."

**As a Librarian:**
1. Go to Requests page (bottom navigation)
2. Click "Borrow" tab
3. Should see the pending request
4. Click "Approve"
5. Request disappears from Borrow tab
6. Book status changes to "Borrowed"

### 3. Verify database:
```sql
-- Check pending requests
SELECT * FROM Transaction_Requests WHERE status = 'Pending';

-- Check approved transactions
SELECT * FROM Approved_Transactions WHERE status = 'Borrowed';

-- Check book copy status
SELECT * FROM Book_Copies WHERE status = 'Borrowed';
```

## Error Handling

### User Side Errors:
| Error | Message |
|-------|---------|
| Already has pending request | "You already have a pending request for this book" |
| Already borrowed | "You have already borrowed this book" |
| Book not found | "Book not found" |
| User not found | "User not found" |

### Librarian Side Errors:
| Error | Message |
|-------|---------|
| Request not found | "Pending request not found" |
| No available copies | "No available copies for this book" |
| Invalid request_id | "request_id is required" |

## Files Modified

1. **NEW:** `/backend/api/borrow/request_borrow.php` - Endpoint to create pending requests
2. **Modified:** `/lib/book_service.dart` - Changed `borrowBook()` to call `request_borrow.php`
3. **Modified:** `/lib/book_detail.dart` - Button label changed to "Request to Borrow"
4. **Existing:** `/backend/api/librarian/approve_borrow_request.php` - Approves requests
5. **Existing:** `/backend/api/librarian/get_requests.php` - Lists pending requests
6. **Existing:** `/lib/librarian_requests.dart` - Librarian requests UI

## Summary

✅ Users can only **request** to borrow books (not immediate)
✅ All requests go to `Transaction_Requests` with `status = 'Pending'`
✅ Librarians see requests in **Requests → Borrow** tab
✅ Librarians must approve before book is borrowed
✅ Validations prevent duplicate requests
✅ Loan period based on user role
✅ Complete database integration
✅ Proper error handling and user feedback

The system now enforces a proper request-approval workflow for borrowing books.
