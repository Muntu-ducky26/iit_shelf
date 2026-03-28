# Fix Details

This file records the exact fixes applied, with code snippets.

## 1. Shelf API vs `Shelves` table mismatch

### Problem

The API used `compartment` and `subcompartment`, but the table actually uses:

- `total_compartments`
- `total_subcompartments`

### Fixed file

[`backend/api/librarian/manage_shelves.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/librarian/manage_shelves.php)

### Fixed snippet

```php
$stmt = $pdo->query("
    SELECT
        shelf_id,
        total_compartments,
        total_subcompartments,
        is_deleted
    FROM Shelves
    WHERE is_deleted = 0
    ORDER BY shelf_id ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$shelves = array_map(function ($row) {
    return [
        'shelf_id' => (int)$row['shelf_id'],
        'compartment' => (int)$row['total_compartments'],
        'subcompartment' => (int)$row['total_subcompartments'],
        'total_compartments' => (int)$row['total_compartments'],
        'total_subcompartments' => (int)$row['total_subcompartments'],
        'is_deleted' => (int)$row['is_deleted'],
    ];
}, $rows);
```

```php
$stmt = $pdo->prepare("
    INSERT INTO Shelves (shelf_id, total_compartments, total_subcompartments, is_deleted)
    VALUES (:shelf_id, :compartment, :subcompartment, 0)
");
```

```php
$stmt = $pdo->prepare("
    UPDATE Shelves
    SET total_compartments = :compartment,
        total_subcompartments = :subcompartment
    WHERE shelf_id = :shelf_id AND is_deleted = 0
");
```

## 2. `add_book.php` copy-location object/array bug

### Problem

JSON requests were decoded as objects, but the code accessed copy locations like arrays, causing:

`Cannot use object of type stdClass as array`

### Fixed file

[`backend/api/books/add_book.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/books/add_book.php)

### Fixed snippet

```php
if ($location) {
    if (is_array($location)) {
        $shelfId = $location['shelf_id'] ?? null;
        $compartmentNo = $location['compartment_no'] ?? null;
        $subcompartmentNo = $location['subcompartment_no'] ?? null;
    } else {
        $shelfId = $location->shelf_id ?? null;
        $compartmentNo = $location->compartment_no ?? null;
        $subcompartmentNo = $location->subcompartment_no ?? null;
    }
}
```

## 3. `add_book.php` PDF insert mismatch with `Digital_Resources`

### Problem

The backend inserted only `isbn`, `resource_type`, and `file_path`, but the table requires `file_name` too.

### Fixed snippet

```php
$uploadedBy = null;
if (!empty($data->uploaded_by)) {
    $uploadedBy = trim((string)$data->uploaded_by);
} elseif (!empty($data->user_email)) {
    $uploadedBy = trim((string)$data->user_email);
}
```

```php
if (!empty($pdfUrl)) {
    $fileName = basename(parse_url($pdfUrl, PHP_URL_PATH) ?: $pdfUrl);
    if ($fileName === '' || $fileName === '.' || $fileName === '/') {
        $fileName = $data->isbn . '.pdf';
    }

    $pdfStmt = $db->prepare('INSERT INTO Digital_Resources (isbn, file_name, file_path, resource_type, uploaded_by)
        VALUES (:isbn, :file_name, :file_path, "PDF", :uploaded_by)');
    $pdfStmt->execute([
        ':isbn' => $data->isbn,
        ':file_name' => $fileName,
        ':file_path' => $pdfUrl,
        ':uploaded_by' => $uploadedBy,
    ]);
}
```

## 4. `update_book.php` did not handle copy JSON fields correctly

### Problem

When copy-related fields arrived as strings in form-data, they were not decoded.

### Fixed file

[`backend/api/books/update_book.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/books/update_book.php)

### Fixed snippet

```php
if (isset($payload->course_ids) && is_string($payload->course_ids)) {
    $payload->course_ids = json_decode($payload->course_ids);
}
if (isset($payload->copy_ids) && is_string($payload->copy_ids)) {
    $payload->copy_ids = json_decode($payload->copy_ids);
}
if (isset($payload->copy_locations) && is_string($payload->copy_locations)) {
    $payload->copy_locations = json_decode($payload->copy_locations);
}
```

## 5. `update_book.php` rejected valid copy-only updates

### Problem

The old validation only allowed normal book fields, `course_id`, or `pdf_url`, so copy updates could be rejected as:

`No fields provided to update`

### Fixed snippet

```php
if (
    empty($fields) &&
    empty($payload->course_id) &&
    empty($payload->course_ids) &&
    empty($payload->copies_total) &&
    empty($payload->copy_ids) &&
    empty($payload->copy_locations) &&
    empty($pdfUrl)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No fields provided to update',
    ]);
    exit;
}
```

## 6. `update_book.php` PDF insert/update mismatch

### Problem

`Digital_Resources` writes needed a valid file name and uploader.

### Fixed snippet

```php
$uploadedBy = null;
if (!empty($payload->uploaded_by)) {
    $uploadedBy = trim((string)$payload->uploaded_by);
} elseif (!empty($payload->user_email)) {
    $uploadedBy = trim((string)$payload->user_email);
}
```

```php
if (!empty($pdfUrl)) {
    $fileName = basename(parse_url($pdfUrl, PHP_URL_PATH) ?: $pdfUrl);
    if ($fileName === '' || $fileName === '.' || $fileName === '/') {
        $fileName = $payload->isbn . '.pdf';
    }
    $delStmt = $db->prepare('DELETE FROM Digital_Resources WHERE isbn = :isbn AND resource_type = "PDF"');
    $delStmt->execute([':isbn' => $payload->isbn]);

    $insStmt = $db->prepare('INSERT INTO Digital_Resources (isbn, file_name, file_path, resource_type, uploaded_by) VALUES (:isbn, :name, :path, "PDF", :uploaded_by)');
    $insStmt->execute([
        ':isbn' => $payload->isbn,
        ':name' => $fileName,
        ':path' => $pdfUrl,
        ':uploaded_by' => $uploadedBy,
    ]);
}
```

## 7. `get_book_copies.php` selected a missing column

### Problem

The endpoint selected `bc.created_at`, but `Book_Copies` does not have that column.

### Fixed file

[`backend/api/books/get_book_copies.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/books/get_book_copies.php)

### Fixed snippet

```php
$stmt = $db->prepare('
    SELECT
        bc.copy_id,
        bc.isbn,
        bc.shelf_id,
        bc.compartment_no,
        bc.subcompartment_no,
        bc.status,
        bc.condition_note
    FROM Book_Copies bc
    WHERE bc.isbn = :isbn
    ORDER BY bc.copy_id
');
```

## 8. `get_profile.php` selected a missing column

### Problem

The endpoint selected `profile_image`, but `Users` does not have that column.

### Fixed file

[`backend/api/auth/get_profile.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/auth/get_profile.php)

### Fixed snippet

```php
$stmt = $db->prepare('
    SELECT email, name, contact, role, created_at, last_login
    FROM Users
    WHERE email = :email
');
```

```php
'profile_image' => null,
```

## 9. Librarian role lookup mismatch

### Problem

The DB stores roles like `Librarian`, but some helper queries used `role = 'librarian'`.

### Fixed file

[`backend/api/lib/enhanced_notification_helpers.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/lib/enhanced_notification_helpers.php)

### Fixed snippet

```php
$stmt = $db->prepare("SELECT email FROM Users WHERE LOWER(role) = 'librarian'");
```

This was applied in all librarian notification helper functions.

## 10. `request_return.php` input handling and status matching

### Problem

The return request endpoint was too strict and could fail even when the transaction existed.

### Fixed file

[`backend/api/borrow/request_return.php`](/home/alvee/Desktop/iit_shelf_test/backend/api/borrow/request_return.php)

### Fixed snippet

```php
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$transactionId = isset($input['transaction_id']) ? (int)$input['transaction_id'] : 0;
$userEmail = isset($input['user_email']) ? strtolower(trim($input['user_email'])) : '';
```

```php
$stmt = $db->prepare("SELECT at.transaction_id
  FROM Approved_Transactions at
  JOIN Transaction_Requests tr ON tr.request_id = at.request_id
  WHERE at.transaction_id = :tid
    AND LOWER(tr.requester_email) = :email
    AND at.status IN ('Borrowed', 'Overdue')");
```

## 11. Schema update for `Book_Copies.status`

### Problem

The backend uses `Unavailable`, but the schema did not allow it.

### Fixed file

[`backend/database/schema_team_migration.sql`](/home/alvee/Desktop/iit_shelf_test/backend/database/schema_team_migration.sql)

### Fixed snippet

```sql
status ENUM('Available','Borrowed','Reserved','Unavailable','Lost','Discarded') DEFAULT 'Available',
```

## 12. Schema update for `Notifications.type`

### Problem

The backend emits several notification types that were not in the schema enum.

### Fixed snippet

```sql
type ENUM(
  'DueDateReminder',
  'ReservedBookAvailable',
  'PaymentConfirmation',
  'BorrowRequestApproved',
  'ReturnRequestApproved',
  'AdditionRequestApproved',
  'ReservationQueueUpdate',
  'FineReminder',
  'BorrowRequestPending',
  'ReturnRequestPending',
  'BookAdded',
  'InventoryUpdate',
  'UserTransaction',
  'ReportGenerated',
  'FineLimitReached',
  'System'
) DEFAULT 'System',
```

## Verified After Fix

These flows were tested successfully after the changes:

1. Login with `librarian@iit.edu`
2. Profile fetch
3. Shelf list/add/update
4. Add book with copies and PDF metadata
5. Update book with copy changes and PDF metadata
6. Toggle book availability to `Unavailable`
7. List copies for a book
8. Submit return request and create `ReturnRequestPending` notification
