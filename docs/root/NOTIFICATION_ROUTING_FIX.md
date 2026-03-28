# Notification Routing Fix - Web Frontend

## Issue
When users clicked on book-related notifications (e.g., "Book Added to Library", "ISBN: 978-3-16-148410-0"), they were navigated to a non-existent route `/book-details/:isbn`, causing 404 errors and broken navigation.

## Root Cause
The web frontend routes had the correct path `/book/:id`, but the Notifications component was navigating to the incorrect path `/book-details/:isbn`.

## Solution
Updated the notification click handler in Notifications.jsx to use the correct route:

**File**: [web frontend/src/pages/Notifications.jsx](web%20frontend/src/pages/Notifications.jsx)

**Change**: Line 181
- **Before**: `navigate(\`/book-details/${encodeURIComponent(isbn)}\`)`
- **After**: `navigate(\`/book/${encodeURIComponent(isbn)}\`)`

## How It Works

1. **ISBN Extraction**: When a book-related notification is clicked, the ISBN is extracted from the notification message using the existing `extractISBN()` function.

2. **Route Navigation**: The extracted ISBN is passed as a route parameter to the `/book/:id` endpoint.

3. **BookDetails Component**: The BookDetails component receives the ISBN via `useParams()` and fetches book details from the API.

## Implementation Details

### Notification Types Handled
Book-related notifications that trigger this navigation:
- `BookAdded`
- `BookAvailable`
- `ReservedBookAvailable`
- `BookRejected`
- `BorrowApproved`
- `BorrowRejected`
- `ReturnApproved`
- `ReturnRejected`
- `ReserveAdded`
- `ReserveRejected`
- `AdditionRequestApproved`
- `InventoryUpdate`

### Payment Notifications
Payment-related notifications continue to navigate to `/payments`:
- `PaymentConfirmation`
- `FineReminder`
- `FineLimitReached`
- `FineNotification`

### Other Navigations
Notifications for other types (overdue, password changes, etc.) navigate to their respective pages using the existing switch-case logic.

## Testing

To verify the fix works:
1. Send a book-related notification from the backend with an ISBN in the message (e.g., "Book Available: ISBN: 978-3-16-148410-0")
2. Click the notification
3. Verify that you're navigated to `/book/978-3-16-148410-0` and the book details load correctly

## Files Modified
- [web frontend/src/pages/Notifications.jsx](web%20frontend/src/pages/Notifications.jsx#L181)

## Related Routes
- **Route Definition**: [web frontend/src/App.jsx](web%20frontend/src/App.jsx#L186) - `/book/:id` route
- **BookDetails Component**: [web frontend/src/pages/BookDetails.jsx](web%20frontend/src/pages/BookDetails.jsx#L13) - Uses `useParams()` to get ISBN
