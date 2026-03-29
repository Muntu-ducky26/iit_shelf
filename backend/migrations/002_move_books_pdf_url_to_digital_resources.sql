-- Migrate legacy Books.pdf_url into Digital_Resources.file_path (PDF),
-- then remove Books.pdf_url column.

INSERT INTO Digital_Resources (isbn, file_name, file_path, resource_type, uploaded_by)
SELECT
  b.isbn,
  CONCAT(b.isbn, '.pdf') AS file_name,
  b.pdf_url AS file_path,
  'PDF' AS resource_type,
  NULL AS uploaded_by
FROM Books b
WHERE b.pdf_url IS NOT NULL
  AND TRIM(b.pdf_url) <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM Digital_Resources dr
    WHERE dr.isbn = b.isbn
      AND dr.resource_type = 'PDF'
  );

ALTER TABLE Books
DROP COLUMN pdf_url;

