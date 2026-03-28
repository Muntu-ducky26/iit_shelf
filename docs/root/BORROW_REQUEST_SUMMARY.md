# ✅ Borrow Request - Librarian Approval Implementation Complete

## What Changed?

### Before
```
User clicks "Borrow Book" → Book borrowed immediately ❌
```

### After  
```
User clicks "Request to Borrow" → Request created (Pending) → 
Librarian approves → Book borrowed ✅
```

## Visual Flow

```
┌─────────────────────────────────────────────────────────────┐
│                       USER SIDE                             │
│  (Book Detail Page - lib/book_detail.dart)                  │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Clicks "Request to Borrow"
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  API: POST /borrow/request_borrow.php                       │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Creates: Transaction_Requests                              │
│    - status: "Pending"                                      │
│    - requester_email: user@example.com                      │
│    - isbn: 0078815967                                       │
│    - request_date: NOW()                                    │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Returns success message
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  User sees:                                                 │
│  "Borrow request submitted successfully.                    │
│   Waiting for librarian approval."                          │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Request now pending...
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    LIBRARIAN SIDE                           │
│  (Requests Page → Borrow Tab)                               │
│  lib/librarian_requests.dart                                │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Fetches pending requests
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  API: GET /librarian/get_requests.php?type=borrow           │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Shows all requests with status = "Pending"                 │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Librarian sees request
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  ┌──────────────────────────────────────────────────┐      │
│  │ 🔵 John Doe                    2 hours ago       │      │
│  │    john.doe@example.com                          │      │
│  │                                                  │      │
│  │    Request to Borrow                             │      │
│  │    Book Name: Teach yourself C                   │      │
│  │    ISBN: 0078815967                              │      │
│  │                                                  │      │
│  │    [ Approve ]                                   │      │
│  └──────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Clicks "Approve"
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  API: POST /librarian/approve_borrow_request.php            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  1. Update: Transaction_Requests.status = "Approved"        │
│  2. Create: Approved_Transactions                           │
│     - issue_date: TODAY                                     │
│     - due_date: TODAY + 7/15/30 days (based on role)        │
│     - status: "Borrowed"                                    │
│  3. Update: Book_Copies.status = "Borrowed"                 │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ Success!
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  ✅ Book successfully borrowed                               │
│  - Request disappears from Borrow tab                       │
│  - Appears in Return tab (when due)                         │
│  - User can now see it in "My Books"                        │
└─────────────────────────────────────────────────────────────┘
```

## Key Files Changed

| File | Type | Change |
|------|------|--------|
| `backend/api/borrow/request_borrow.php` | NEW | Creates pending borrow requests |
| `lib/book_service.dart` | MODIFIED | Changed endpoint from `borrow_book.php` to `request_borrow.php` |
| `lib/book_detail.dart` | MODIFIED | Button text: "Borrow Book" → "Request to Borrow" |

## Database Tables Involved

### 1. Transaction_Requests
```sql
Status: "Pending" → User submits request
Status: "Approved" → Librarian approves
```

### 2. Approved_Transactions
```sql
Created when: Librarian approves request
Contains: issue_date, due_date, copy_id
```

### 3. Book_Copies
```sql
Status: "Available" → "Borrowed" (when approved)
```

## Testing

### Quick Test:
```bash
# 1. Start the backend server
cd /mnt/academics/iit_shelf_test/backend
php -S localhost:8000

# 2. In another terminal, run the workflow test
./test_borrow_workflow.sh
```

### Manual Test:
1. **As Student/User:**
   - Open any book detail page
   - Click "Request to Borrow"
   - Should see confirmation message

2. **As Librarian:**
   - Go to Requests page
   - Click "Borrow" tab
   - See the pending request
   - Click "Approve"
   - Request disappears

## User Messages

| Action | Message |
|--------|---------|
| ✅ Request submitted | "Borrow request submitted successfully. Waiting for librarian approval." |
| ❌ Duplicate request | "You already have a pending request for this book" |
| ❌ Already borrowed | "You have already borrowed this book" |
| ✅ Request approved | "Request approved" |

## Loan Periods (Auto-calculated)

| Role | Days | Due Date |
|------|------|----------|
| Student | 7 | +7 days |
| Teacher | 15 | +15 days |
| Librarian | 30 | +30 days |
| Director | 30 | +30 days |

## Validation Rules

### When Submitting Request:
- ✓ User must exist
- ✓ Book must exist
- ✓ No duplicate pending requests
- ✓ User hasn't already borrowed this book

### When Approving Request:
- ✓ Request must exist and be pending
- ✓ At least one book copy must be available
- ✓ Assigns specific copy to user

## API Endpoints Summary

```
POST /borrow/request_borrow.php
→ Creates pending request (User side)

GET /librarian/get_requests.php?type=borrow
→ Lists pending requests (Librarian side)

POST /librarian/approve_borrow_request.php
→ Approves request and borrows book (Librarian side)
```

## ✅ Implementation Status

- [x] Backend API endpoint created (`request_borrow.php`)
- [x] Flutter service updated (`book_service.dart`)
- [x] UI button text updated ("Request to Borrow")
- [x] Database integration (Transaction_Requests)
- [x] Validation logic (duplicates, already borrowed)
- [x] Librarian approval workflow (existing)
- [x] Error handling and user feedback
- [x] Test script created
- [x] Documentation complete

## Result

🎉 **Users can no longer borrow books directly. All borrow actions now require librarian approval through the request system!**
