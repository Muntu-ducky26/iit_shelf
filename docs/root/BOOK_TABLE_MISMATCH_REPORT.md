# Database & Frontend Add Book Field Mismatch Report

## Database Books Table Fields (Team Schema)
```
isbn (PRIMARY KEY)
title
author
category
publisher
publication_year
edition
description
pic_path
```

## Frontend add_book.dart Form Fields
```
title
isbn
author
category
quantity (NOT in database)
availableQuantity (NOT in database)
shelfId (NOT in database)
pdfUrl (NOT in database - goes to digital_resources)
description
course (NOT in Books - should be in Book_Courses)
```

## Backend add_book.php Expects
```
FROM DATABASE FIELDS:
- isbn
- title
- author
- category
- publisher
- publication_year
- edition
- description
- pic_path

ADDITIONAL FIELDS (handled separately):
- course_id (links to Book_Courses table)
- copies_total (used to create entries in Book_Copies table)
- shelf_id (stored in Book_Copies)
- compartment_no (stored in Book_Copies)
- subcompartment_no (stored in Book_Copies)
- condition_note (stored in Book_Copies)
```

## Key Mismatches

### ❌ Fields in Frontend but NOT in Books table:
1. **quantity** → Should use `copies_total` and create Book_Copies records
2. **availableQuantity** → Handled automatically (copies_total = available initially)
3. **shelfId** → Should go to Book_Copies.shelf_id, not Books table
4. **pdfUrl** → Should go to Digital_Resources table, not Books table

### ❌ Fields in Database but NOT in Frontend:
1. **publisher** - Missing from add_book.dart form
2. **publication_year** - Missing from add_book.dart form
3. **edition** - Missing from add_book.dart form

### ❌ Course Handling Mismatch:
- Frontend: Sends `courseId` in BookPayload
- Database: Uses separate `Book_Courses` junction table
- Backend: Correctly handles this linking

### ❌ Book Copies Handling Mismatch:
- Frontend: No fields for shelf_id, compartment_no, subcompartment_no
- Database: Book_Copies table requires these for physical copy tracking
- Backend: Has fields but frontend doesn't collect them

## Required Fixes

### Option 1: Update Frontend to Match Database (Recommended)
1. Add fields for: publisher, publication_year, edition
2. Rename `quantity` to `copiesToal` or better UI labeling
3. Add fields for: shelf_id, compartment_no, subcompartment_no
4. Remove `availableQuantity` (calculated automatically)
5. Move `pdfUrl` to a separate Digital Resources form
6. Keep `courseId` as is (backend handles it correctly)

### Option 2: Update Backend to Match Frontend
1. Ignore publisher, publication_year, edition if not provided
2. Store quantity/availableQuantity in Books table (schema change needed)
3. Handle shelf/compartment info separately
4. Handle PDF separately

### Option 3: Hybrid Approach
- Make publisher, publication_year, edition optional
- Use existing quantity handling but clarify it's for Book_Copies
- Keep shelf/compartment as optional
- PDF should definitely be separate
