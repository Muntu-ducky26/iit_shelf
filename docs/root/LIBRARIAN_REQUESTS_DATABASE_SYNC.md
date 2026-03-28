# Librarian Requests - Database Sync Documentation

## ✅ Status: FULLY SYNCED WITH DATABASE

The librarian requests feature is completely integrated with the MySQL database. All requests are fetched from and stored in the database in real-time.

## Database Schema

### 1. **Transaction_Requests** (Borrow Requests)
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
- Stores all borrow requests from users
- Status: `Pending` requests appear in Borrow tab
- On approval: Creates entry in `Approved_Transactions`

### 2. **Approved_Transactions** (Return Requests)
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
- Stores all approved borrow transactions
- Status: `Borrowed` transactions appear in Return tab
- Tracks due dates and calculates fines

### 3. **Reservations** (Reserve Queue)
```sql
CREATE TABLE Reservations (
  reservation_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  queue_position INT NOT NULL,
  status ENUM('Active','Fulfilled','Cancelled','Expired') DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)
```
- Tracks book reservations/queue
- Status: `Active` reservations appear in Reserve tab
- Maintains queue position

### 4. **Requests** (Addition Requests)
```sql
CREATE TABLE Requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(500) NOT NULL,
  isbn VARCHAR(30),
  requester_identifier VARCHAR(150),
  description TEXT,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  approved_by VARCHAR(150),
  approved_at DATETIME
)
```
- Stores requests to add new books
- Status: `Pending` requests appear in Addition tab

## API Endpoints

### Fetch Requests: `GET /librarian/get_requests.php`

**Parameters:**
- `type`: `borrow`, `return`, `reserve`, or `addition`
- `search`: (optional) Filter by name, email, title, or ISBN

**Examples:**
```bash
# Get all pending borrow requests
curl "http://localhost:8000/librarian/get_requests.php?type=borrow"

# Get return requests (currently borrowed books)
curl "http://localhost:8000/librarian/get_requests.php?type=return"

# Search in borrow requests
curl "http://localhost:8000/librarian/get_requests.php?type=borrow&search=john"
```

**Response:**
```json
{
  "success": true,
  "count": 1,
  "items": [
    {
      "request_id": "123",
      "name": "John Doe",
      "email": "john.doe@example.com",
      "title": "Database Management",
      "isbn": "234612AB",
      "request_date": "2026-01-07 10:30:00"
    }
  ]
}
```

### Approve Borrow Request: `POST /librarian/approve_borrow_request.php`

**Request:**
```json
{
  "request_id": 123
}
```

**Process:**
1. Validates request exists and is pending
2. Finds available book copy
3. Updates `Transaction_Requests` status to `Approved`
4. Creates entry in `Approved_Transactions`
5. Updates book copy status to `Borrowed`
6. Sets due date based on user role:
   - Student: 7 days
   - Teacher: 15 days
   - Librarian/Director: 30 days

**Response:**
```json
{
  "success": true,
  "message": "Request approved",
  "copy_id": "234612AB-001"
}
```

## Flutter Integration

### File: `lib/librarian_requests.dart`

#### Key Functions:

```dart
// Fetch all request types from database
Future<List<Map<String, dynamic>>> _fetch(String type, String search) async {
  final uri = Uri.parse('$_baseUrl/librarian/get_requests.php')
    .replace(queryParameters: {
      'type': type,
      if (search.isNotEmpty) 'search': search
    });
  final resp = await http.get(uri);
  // Parse and return items from database
}

// Approve borrow request - updates database
Future<void> _approveBorrow(int requestId) async {
  final uri = Uri.parse('$_baseUrl/librarian/approve_borrow_request.php');
  final resp = await http.post(uri, 
    headers: {'Content-Type': 'application/json'},
    body: json.encode({'request_id': requestId})
  );
  // Reload data from database
  await _loadAll();
}
```

#### Data Flow:
1. **Page Load** → `_loadAll()` → Fetches all 4 request types from database
2. **User Searches** → Re-fetches filtered data from database
3. **Approve Action** → Updates database → Re-fetches to reflect changes

### UI Components:

#### Tabs:
1. **Borrow Tab** - Shows pending `Transaction_Requests`
2. **Return Tab** - Shows borrowed `Approved_Transactions` 
3. **Reserve Tab** - Shows active `Reservations`
4. **Addition Tab** - Shows pending `Requests`

#### Request Cards:
- Display user info (name, email)
- Book details (title, ISBN)
- Timestamps (relative time ago)
- Action buttons (Approve, View)

## Testing

Run the sync test to verify database connection:

```bash
cd /mnt/academics/iit_shelf_test/backend
php api/librarian/test_requests_sync.php
```

**Expected Output:**
```
=== Testing Librarian Requests Database Sync ===

1. Checking Transaction_Requests table...
   Pending borrow requests: 0

2. Checking Approved_Transactions table...
   Currently borrowed (return requests): 3

3. Checking Reservations table...
   Active reservations: 0

4. Checking Requests table...
   Pending addition requests: 0
```

## Current Status

✅ **Borrow Requests**: Synced with `Transaction_Requests` table
✅ **Return Requests**: Synced with `Approved_Transactions` table  
✅ **Reserve Requests**: Synced with `Reservations` table
✅ **Addition Requests**: Synced with `Requests` table

All CRUD operations are performed directly on the database with no mock data or hardcoded values.

## Example User Flow

### Approving a Borrow Request:

1. **User submits request** → Creates row in `Transaction_Requests` (status: Pending)
2. **Librarian sees request** → Flutter app queries database via `get_requests.php?type=borrow`
3. **Librarian taps Approve** → Calls `approve_borrow_request.php`
4. **Backend processes**:
   - Finds available book copy
   - Updates `Transaction_Requests.status = 'Approved'`
   - Creates `Approved_Transactions` record
   - Updates `Book_Copies.status = 'Borrowed'`
5. **UI updates** → Re-fetches from database, request disappears from Borrow tab

### Fine Calculation (Returns):

```sql
-- Calculated in real-time from database
GREATEST(DATEDIFF(NOW(), due_date), 0) AS days_overdue
```

- Days overdue × BDT 5 = Late fine
- Displayed in Return tab
- Updated on every page load

## Database Relationships

```
Transaction_Requests
    ↓ (creates on approval)
Approved_Transactions
    ↓ (references)
Book_Copies → Books

Reservations → Books
    ↓ (references)
Users

Requests (Addition)
    ↓ (references)
Users
```

## Summary

The librarian requests system is **fully database-driven** with no frontend state persistence or mock data. Every request visible in the UI exists in the MySQL database and all actions directly modify database records. The system provides real-time synchronization between the Flutter frontend and PHP backend through REST APIs.
