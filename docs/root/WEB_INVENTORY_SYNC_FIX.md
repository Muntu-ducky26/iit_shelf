# Web Frontend Inventory Sync Fix

## Issue
The librarian inventory page in the web frontend was not displaying the TOTAL, BORROWED, and AVAILABLE inventory data from the database. The table columns showed the data but it was not syncing/populating.

## Root Cause
The backend API (`/api/books/get_books.php`) was returning inventory data with the following field names:
- `copies_available` - Number of available copies
- `copies_total` - Total number of physical copies
- ~~`copies_borrowed`~~ - **This field was missing!**

However, the frontend was trying to display:
- `book.available`
- `book.totalCopies` 
- `book.borrowed`

This mismatch meant the values were undefined and not displaying.

## Solution

### 1. Backend Fix: `/mnt/academics/iit_shelf_test/backend/api/books/get_books.php`

**Added borrowed count query:**
```php
// Count borrowed copies
$borrowedStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn AND status = "Borrowed"');
$borrowedStmt->execute([':isbn' => $row['isbn']]);
$copiesBorrowed = (int)$borrowedStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
```

**Updated response array to include:**
```php
'copies_borrowed' => $copiesBorrowed,
```

### 2. Frontend Fix: `/mnt/academics/iit_shelf_test/web frontend/src/pages/LibrarianInventory.jsx`

**Mapped API fields to frontend expected fields:**
```jsx
const booksData = data.books || data.data || []

// Map backend fields to frontend expected fields
const mappedBooks = booksData.map(book => ({
  ...book,
  available: book.copies_available || 0,
  totalCopies: book.copies_total || 0,
  borrowed: book.copies_borrowed || 0
}))

setBooks(mappedBooks)
```

## Data Flow
1. Backend fetches all books from `Books` table
2. For each book, queries `Book_Copies` table to count:
   - Total copies (all statuses)
   - Available copies (status = "Available")
   - Borrowed copies (status = "Borrowed")
3. Returns data with fields: `copies_total`, `copies_available`, `copies_borrowed`
4. Frontend maps these to: `totalCopies`, `available`, `borrowed`
5. UI displays in the inventory table under columns: Total, Borrowed, Available

## Display Locations
The inventory data now displays correctly in:
- **Table View**: Three columns show Total, Borrowed, and Available counts per book
- **Card View**: Shows the same stats when viewing books as cards

## Testing
To verify the fix:
1. Start the backend server: `cd /mnt/academics/iit_shelf_test/backend && ./start_server.sh`
2. Visit the web frontend librarian inventory page
3. Verify that the TOTAL, BORROWED, and AVAILABLE columns are now populated with actual numbers from the database
4. The counts should update correctly as books are borrowed/returned

## Files Modified
- ✅ `/mnt/academics/iit_shelf_test/backend/api/books/get_books.php` - Added borrowed count
- ✅ `/mnt/academics/iit_shelf_test/web frontend/src/pages/LibrarianInventory.jsx` - Added field mapping
