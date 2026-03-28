<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';

// Log to a file for debugging
$logFile = __DIR__ . '/../../logs/update_book.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
file_put_contents($logFile, "\n\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($logFile, "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n", FILE_APPEND);
file_put_contents($logFile, "_POST: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($logFile, "_FILES: " . json_encode($_FILES) . "\n", FILE_APPEND);

$database = new Database();
$db = $database->getConnection();

// Handle both JSON and FormData requests
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isFormData = strpos($contentType, 'multipart/form-data') !== false || strpos($contentType, 'application/x-www-form-urlencoded') !== false;

if ($isFormData) {
    // FormData request - get data from $_POST
    $payload = (object) $_POST;
    error_log('UPDATE_BOOK FORMDATA: ' . json_encode($_POST));
    
    // Handle file uploads
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/covers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $payload->pic_path = '/api/uploads/covers/' . $filename;
        }
    }
    
    // Handle PDF file upload
    file_put_contents($logFile, "Checking for PDF file upload\n", FILE_APPEND);
    file_put_contents($logFile, "_FILES: " . json_encode($_FILES) . "\n", FILE_APPEND);
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/pdfs/';
        file_put_contents($logFile, "Upload dir: $uploadDir\n", FILE_APPEND);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = uniqid() . '_' . basename($_FILES['pdf']['name']);
        $targetPath = $uploadDir . $filename;
        file_put_contents($logFile, "Target path: $targetPath\n", FILE_APPEND);
        file_put_contents($logFile, "Temp file: " . $_FILES['pdf']['tmp_name'] . "\n", FILE_APPEND);
        file_put_contents($logFile, "Temp file exists: " . (file_exists($_FILES['pdf']['tmp_name']) ? 'YES' : 'NO') . "\n", FILE_APPEND);
        
        if (move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
            $payload->pdf_url = '/api/uploads/pdfs/' . $filename;
            file_put_contents($logFile, "PDF uploaded successfully to: " . $payload->pdf_url . "\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "Failed to move PDF file. Error: " . error_get_last()['message'] . "\n", FILE_APPEND);
        }
    } else if (isset($_FILES['pdf'])) {
        file_put_contents($logFile, "PDF upload error code: " . $_FILES['pdf']['error'] . "\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "No PDF file in request\n", FILE_APPEND);
    }
    
    // Parse course_ids if it's a JSON string
    if (isset($payload->course_ids) && is_string($payload->course_ids)) {
        $payload->course_ids = json_decode($payload->course_ids);
    }
    if (isset($payload->copy_ids) && is_string($payload->copy_ids)) {
        $payload->copy_ids = json_decode($payload->copy_ids);
    }
    if (isset($payload->copy_locations) && is_string($payload->copy_locations)) {
        $payload->copy_locations = json_decode($payload->copy_locations);
    }
} else {
    // JSON request
    $rawInput = file_get_contents('php://input');
    error_log('UPDATE_BOOK RAW INPUT: ' . $rawInput);
    $payload = json_decode($rawInput) ?: new stdClass();
}

error_log('UPDATE_BOOK PAYLOAD: ' . json_encode($payload));

// Require an identifier. Prefer ISBN since the books table has ISBN as primary key.
if (empty($payload->isbn)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ISBN is required',
    ]);
    exit;
}

$fields = [];
$params = [':where_isbn' => $payload->isbn];

$allowed = [
    'title',
    'author',
    'isbn',
    'category',
    'publisher',
    'publication_year',
    'edition',
    'description',
    'pic_path',
];

foreach ($allowed as $key) {
    if (isset($payload->$key)) {
        $fields[] = "$key = :$key";
        $params[":$key"] = $payload->$key;
    }
}

// Optional PDF URL (stored in Digital_Resources)
$pdfUrl = null;
if (isset($payload->pdf_url) && !empty($payload->pdf_url)) {
    $candidateUrl = trim($payload->pdf_url);
    // Reject local file paths (security + invalid data)
    if (!preg_match('#^(/home|/Users|C:|D:|/tmp)#i', $candidateUrl)) {
        $pdfUrl = $candidateUrl;
        file_put_contents($logFile, "pdfUrl set to: $pdfUrl\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "Rejected local path pdf_url: $candidateUrl\n", FILE_APPEND);
    }
} else {
    file_put_contents($logFile, "No pdf_url in payload\n", FILE_APPEND);
}

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

$uploadedBy = null;
if (!empty($payload->uploaded_by)) {
    $uploadedBy = trim((string)$payload->uploaded_by);
} elseif (!empty($payload->user_email)) {
    $uploadedBy = trim((string)$payload->user_email);
}

// Update Books table fields if any are provided
if (!empty($fields)) {
    $query = 'UPDATE Books SET ' . implode(', ', $fields) . ' WHERE isbn = :where_isbn';
    $stmt = $db->prepare($query);

    try {
        $stmt->execute($params);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to update book: ' . $e->getMessage(),
        ]);
        exit;
    }
}

try {
    $db->beginTransaction();
    
    // Handle course_ids update via Book_Courses junction table
    // Support both single course_id and array of course_ids
    if (isset($payload->course_id) || isset($payload->course_ids)) {
        $courseIds = [];
        
        // Handle single course_id (backward compatibility)
        if (isset($payload->course_id)) {
            if (is_array($payload->course_id)) {
                foreach ($payload->course_id as $cid) {
                    $trimmed = trim($cid);
                    if ($trimmed !== '' && $trimmed !== 'NONE') {
                        $courseIds[] = $trimmed;
                    }
                }
            } else {
                $trimmed = trim($payload->course_id);
                if ($trimmed !== '' && $trimmed !== 'NONE') {
                    $courseIds[] = $trimmed;
                }
            }
        }
        
        // Handle course_ids array
        if (isset($payload->course_ids) && is_array($payload->course_ids)) {
            foreach ($payload->course_ids as $cid) {
                $trimmed = trim($cid);
                if ($trimmed !== '' && $trimmed !== 'NONE') {
                    $courseIds[] = $trimmed;
                }
            }
        }
        
        $courseIds = array_values(array_unique($courseIds)); // Remove duplicates
        
        if (!empty($courseIds)) {
            // Verify all courses exist
            foreach ($courseIds as $courseId) {
                $courseCheck = $db->prepare('SELECT course_id FROM Courses WHERE course_id = :course_id');
                $courseCheck->execute([':course_id' => $courseId]);
                
                if ($courseCheck->fetchColumn() === false) {
                    $db->rollBack();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Course not found for course_id: ' . $courseId,
                    ]);
                    exit;
                }
            }
            
            // Delete existing course associations for this book
            $deleteStmt = $db->prepare('DELETE FROM Book_Courses WHERE isbn = :isbn');
            $deleteStmt->execute([':isbn' => $payload->isbn]);

            // Insert new course associations
            $insertStmt = $db->prepare('INSERT INTO Book_Courses (isbn, course_id) VALUES (:isbn, :course_id)');
            foreach ($courseIds as $courseId) {
                $insertStmt->execute([
                    ':isbn' => $payload->isbn,
                    ':course_id' => $courseId
                ]);
            }
        }
    }

    // Handle physical copies if provided
    if (isset($payload->copies_total) && $payload->copies_total > 0) {
        error_log('UPDATE_BOOK: Handling copies - copies_total: ' . $payload->copies_total);
        
        // Get list of copy IDs being sent
        $providedCopyIds = [];
        if (isset($payload->copy_ids) && is_array($payload->copy_ids)) {
            $providedCopyIds = array_values(array_filter(array_map('trim', $payload->copy_ids), 'strlen'));
            error_log('UPDATE_BOOK: Copy IDs provided: ' . json_encode($providedCopyIds));
        }

        // Handle copy locations
        $copyLocations = [];
        if (isset($payload->copy_locations) && is_array($payload->copy_locations)) {
            $copyLocations = $payload->copy_locations;
        }

        // Fetch existing copies
        $existingStmt = $db->prepare('SELECT copy_id FROM Book_Copies WHERE isbn = :isbn');
        $existingStmt->execute([':isbn' => $payload->isbn]);
        $existingCopyIds = array_map(function($row) {
            return $row['copy_id'];
        }, $existingStmt->fetchAll(PDO::FETCH_ASSOC));

        // Find new copies (not in existing list)
        $newCopyIds = array_diff($providedCopyIds, $existingCopyIds);
        
        error_log('UPDATE_BOOK: Existing copies: ' . json_encode($existingCopyIds));
        error_log('UPDATE_BOOK: New copies to add: ' . json_encode($newCopyIds));

        // Update shelf locations and condition notes for all provided copies
        $updateStmt = $db->prepare('
            UPDATE Book_Copies 
            SET shelf_id = :shelf_id, 
                compartment_no = :compartment_no, 
                subcompartment_no = :subcompartment_no,
                condition_note = :condition_note
            WHERE isbn = :isbn AND copy_id = :copy_id
        ');

        foreach ($providedCopyIds as $index => $copyId) {
            $location = $copyLocations[$index] ?? null;
            $shelfId = null;
            $compartmentNo = null;
            $subcompartmentNo = null;
            $conditionNote = null;

            if ($location) {
                // Handle both array and object formats
                if (is_array($location)) {
                    $shelfId = isset($location['shelf_id']) ? (int)$location['shelf_id'] : null;
                    $compartmentNo = isset($location['compartment_no']) ? (int)$location['compartment_no'] : null;
                    $subcompartmentNo = isset($location['subcompartment_no']) ? (int)$location['subcompartment_no'] : null;
                    $conditionNote = $location['condition_note'] ?? null;
                } else {
                    $shelfId = isset($location->shelf_id) ? (int)$location->shelf_id : null;
                    $compartmentNo = isset($location->compartment_no) ? (int)$location->compartment_no : null;
                    $subcompartmentNo = isset($location->subcompartment_no) ? (int)$location->subcompartment_no : null;
                    $conditionNote = $location->condition_note ?? null;
                }
            }

            // Also check for global condition note
            if (!$conditionNote && isset($payload->condition_note)) {
                $conditionNote = $payload->condition_note;
            }

            error_log('UPDATE_BOOK: Updating copy ' . $copyId . ' with shelf=' . ($shelfId ?? 'null') . ', condition=' . ($conditionNote ?? 'null'));
            
            $updateStmt->execute([
                ':isbn' => $payload->isbn,
                ':copy_id' => $copyId,
                ':shelf_id' => $shelfId,
                ':compartment_no' => $compartmentNo,
                ':subcompartment_no' => $subcompartmentNo,
                ':condition_note' => $conditionNote,
            ]);
        }

        // Insert only NEW copies
        if (!empty($newCopyIds)) {
            $copyInsert = $db->prepare('INSERT INTO Book_Copies (
                copy_id, isbn, shelf_id, compartment_no, subcompartment_no, status, condition_note
            ) VALUES (
                :copy_id, :isbn, :shelf_id, :compartment_no, :subcompartment_no, "Available", :condition_note
            )');

            foreach ($newCopyIds as $copyId) {
                $index = array_search($copyId, $providedCopyIds);
                $location = $copyLocations[$index] ?? null;
                $shelfId = null;
                $compartmentNo = null;
                $subcompartmentNo = null;
                $conditionNote = null;

                if ($location) {
                    if (is_array($location)) {
                        $shelfId = isset($location['shelf_id']) ? (int)$location['shelf_id'] : null;
                        $compartmentNo = isset($location['compartment_no']) ? (int)$location['compartment_no'] : null;
                        $subcompartmentNo = isset($location['subcompartment_no']) ? (int)$location['subcompartment_no'] : null;
                        $conditionNote = $location['condition_note'] ?? null;
                    } else {
                        $shelfId = isset($location->shelf_id) ? (int)$location->shelf_id : null;
                        $compartmentNo = isset($location->compartment_no) ? (int)$location->compartment_no : null;
                        $subcompartmentNo = isset($location->subcompartment_no) ? (int)$location->subcompartment_no : null;
                        $conditionNote = $location->condition_note ?? null;
                    }
                }

                if (!$conditionNote && isset($payload->condition_note)) {
                    $conditionNote = $payload->condition_note;
                }

                error_log('UPDATE_BOOK: Inserting new copy ' . $copyId . ' for ISBN ' . $payload->isbn);
                
                $copyInsert->execute([
                    ':copy_id' => $copyId,
                    ':isbn' => $payload->isbn,
                    ':shelf_id' => $shelfId,
                    ':compartment_no' => $compartmentNo,
                    ':subcompartment_no' => $subcompartmentNo,
                    ':condition_note' => $conditionNote,
                ]);
            }
        }
    }

    // Upsert PDF into Digital_Resources if provided
    if (!empty($pdfUrl)) {
        $fileName = basename(parse_url($pdfUrl, PHP_URL_PATH) ?: $pdfUrl);
        if ($fileName === '' || $fileName === '.' || $fileName === '/') {
            $fileName = $payload->isbn . '.pdf';
        }
        // remove existing PDF for isbn
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

    $db->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Book updated successfully',
    ]);
} catch (Exception $e) {
    $db->rollBack();
    error_log('UPDATE_BOOK ERROR: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update book: ' . $e->getMessage(),
    ]);
}
?>
