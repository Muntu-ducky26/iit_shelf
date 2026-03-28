# Bug Fixes: PDF Viewing & Cover Image Upload

## Issues Fixed

### 1. PDF Viewing Error ✅
**Problem:** When viewing PDFs from addition requests, the browser showed:
```
{"success":false,"message":"Endpoint not found: uploads/pdfs/file-example_PDF_1MB_1767961840_6960f4f034037.pdf"}
```

**Root Cause:** The `serve_image.php` file only supported image mime types (jpg, png, gif, webp) but not PDFs.

**Solution:** Added PDF mime type support to `serve_image.php`:
```php
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',  // ← Added this line
];
```

**Verification:**
```bash
curl -I "http://localhost:8000/serve_image.php?path=uploads/pdfs/filename.pdf"
# Returns: Content-Type: application/pdf
```

---

### 2. Cover Image Upload - "Simulated" Message ✅
**Problem:** When clicking the upload image button in the request book page, it showed:
```
"Image upload simulated - integrate with file picker"
```

**Root Cause:** The `_pickImage()` method was a placeholder/stub implementation that didn't actually use the file picker.

**Solution:** Implemented actual cover image upload functionality:

#### a) Updated `_pickImage()` Method
**File:** `lib/request_book_details.dart`

Changed from simulated to actual file picker:
```dart
Future<void> _pickImage() async {
  try {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.image,
    );

    if (result != null && result.files.single.path != null) {
      setState(() {
        _selectedCoverImage = File(result.files.single.path!);
        _imageUploaded = true;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Cover image selected: ${result.files.single.name}'),
          backgroundColor: Colors.green,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  } catch (e) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Error picking image: $e'),
        backgroundColor: Colors.redAccent,
        duration: const Duration(seconds: 2),
      ),
    );
  }
}
```

#### b) Created Backend Upload Endpoint
**File:** `backend/api/books/upload_cover_image.php` (NEW)

Features:
- Validates file type (jpeg, png, gif, webp only)
- Validates file size (max 5MB)
- Generates unique filename with timestamp and uniqid
- Stores in `uploads/covers/` directory
- Returns path: `uploads/covers/sanitized-name_timestamp_uniqueid.ext`

Endpoint: `POST http://localhost:8000/books/upload_cover_image.php`

Request: Multipart form-data with `image` field

Response:
```json
{
  "success": true,
  "message": "Cover image uploaded successfully",
  "path": "uploads/covers/book-cover_1234567890_abc123.jpg"
}
```

#### c) Added Upload Method to Service
**File:** `lib/book_service.dart`

```dart
static Future<ApiResponse> uploadCoverImage(File imageFile) async {
  final uri = Uri.parse('$_baseUrl/books/upload_cover_image.php');
  try {
    final request = http.MultipartRequest('POST', uri);
    final file = await http.MultipartFile.fromPath('image', imageFile.path);
    request.files.add(file);

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    final decoded = response.body.isNotEmpty
        ? jsonDecode(response.body) as Map<String, dynamic>
        : <String, dynamic>{};
    final ok =
        decoded['success'] == true ||
        (response.statusCode >= 200 && response.statusCode < 300);
    final message = decoded['message'] as String? ?? 'Request failed';
    return ApiResponse(ok: ok, message: message, data: decoded);
  } catch (e) {
    return ApiResponse(ok: false, message: 'Network error: $e', data: {});
  }
}
```

#### d) Updated Request Submission Logic
**File:** `lib/request_book_details.dart` - `_sendRequest()` method

Now uploads cover image before submitting the request:
```dart
// Upload cover image if selected
String? coverImagePath;
if (_selectedCoverImage != null) {
  final uploadRes = await BookService.uploadCoverImage(_selectedCoverImage!);
  if (uploadRes.ok && uploadRes.data['path'] != null) {
    coverImagePath = uploadRes.data['path'];
  } else {
    // Show error and return
    return;
  }
}

// Then upload PDF and submit request with picPath parameter
final res = await BookService.requestAddition(
  title: _titleController.text.trim(),
  author: _authorController.text.trim(),
  isbn: _isbnController.text.trim(),
  pdfPath: pdfPath,
  picPath: coverImagePath,  // ← Cover image path included
);
```

#### e) Created Upload Directory
```bash
mkdir -p backend/uploads/covers
chmod 755 backend/uploads/covers
```

---

## Files Modified

### Backend
1. ✅ `backend/serve_image.php` - Added PDF mime type support
2. ✅ `backend/api/books/upload_cover_image.php` - NEW file for cover image uploads
3. ✅ `backend/uploads/covers/` - NEW directory for cover images

### Frontend
1. ✅ `lib/request_book_details.dart`:
   - Added `_selectedCoverImage` state variable
   - Replaced simulated `_pickImage()` with actual file picker
   - Updated `_sendRequest()` to upload cover image before request submission

2. ✅ `lib/book_service.dart`:
   - Added `uploadCoverImage()` method

---

## Testing Guide

### Test PDF Viewing
1. Navigate to Librarian > Addition Requests
2. Click "View Request" on a pending request
3. Click "View PDF" button
4. **Expected:** PDF opens in browser/external app (not "Endpoint not found" error)

### Test Cover Image Upload
1. Navigate to Request Book Details page
2. Fill in book title (required)
3. Upload a PDF file
4. Click the **upload image button** (camera icon)
5. **Expected:** File picker opens for image selection
6. Select a JPG/PNG image
7. **Expected:** Green toast "Cover image selected: filename.jpg" (not "simulated" message)
8. Click "Request to add"
9. **Expected:** Both PDF and cover image uploaded, success toast shown

### Verify in Database
```sql
-- Check cover image path saved in request
SELECT request_id, title, pic_path, pdf_path 
FROM Requests 
WHERE status = 'Pending' 
ORDER BY request_id DESC LIMIT 5;
```

### Verify Files on Disk
```bash
# Check uploaded cover images
ls -lh backend/uploads/covers/

# Check uploaded PDFs
ls -lh backend/uploads/pdfs/

# Test serve_image.php with PDF
curl -I "http://localhost:8000/serve_image.php?path=uploads/pdfs/filename.pdf"
# Should return: Content-Type: application/pdf

# Test serve_image.php with cover image
curl -I "http://localhost:8000/serve_image.php?path=uploads/covers/filename.jpg"
# Should return: Content-Type: image/jpeg
```

---

## Technical Details

### File Type Validation
**Cover Images:**
- Allowed: JPEG, PNG, GIF, WebP
- Max size: 5MB
- MIME type validation using `finfo_file()`

**PDFs:**
- Allowed: PDF only
- Max size: 50MB
- MIME type validation

### Unique Filename Generation
Format: `{sanitized-name}_{timestamp}_{uniqid}.{ext}`

Example: `book-cover_1736436720_6776e0b0e6d8a.jpg`

Components:
- `book-cover`: Original filename sanitized (only a-z, A-Z, 0-9, _, -)
- `1736436720`: Unix timestamp
- `6776e0b0e6d8a`: Unique ID from PHP's `uniqid()`
- `.jpg`: Original file extension

### Security
✅ Path traversal prevention: `str_replace(['..', '\\', "\0"], '', $path)`
✅ Directory restriction: Only `uploads/` directory allowed
✅ MIME type validation: Prevents renaming attacks
✅ File size limits: 5MB for images, 50MB for PDFs
✅ Extension whitelist: Only allowed types accepted

---

## Summary

Both issues are now **fully resolved**:

1. ✅ **PDF Viewing**: PDFs from addition requests can now be viewed in the browser
2. ✅ **Cover Image Upload**: Users can select and upload actual cover images when requesting books

The system now properly handles both PDF documents and cover images throughout the request workflow, from submission through approval to library display.
