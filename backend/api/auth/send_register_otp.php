<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/db_pre_registration.php';
require_once '../lib/auth_helpers.php';
require_once '../lib/mail_service.php';

$database = new Database();
$db = $database->getConnection();

$input = json_input();
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$phone = $input['phone'] ?? '';
$role = $input['role'] ?? 'Student';
$name = $input['name'] ?? '';

if ($email === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email is required.',
    ]);
}

// STEP 1: Validate email exists in pre-registration database (REQUIRED)
$preRegDatabase = new PreRegistrationDatabase();
$preDb = $preRegDatabase->connect();

if (!$preDb) {
    respond(500, [
        'success' => false,
        'message' => 'Unable to validate pre-registration. Please contact administrator.',
    ]);
}

$userInfo = null;
$preRegRole = null;

// Check Student table
$stmt = $preDb->prepare("SELECT email, roll, full_name, contact, session FROM PreReg_Students WHERE email = :email");
$stmt->execute([':email' => $email]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    $preRegRole = 'Student';
    $userInfo = [
        'email' => $student['email'],
        'full_name' => $student['full_name'],
        'contact' => $student['contact'],
        'roll' => $student['roll'],
        'session' => $student['session']
    ];
}

// Check Teacher table if not student
if (!$preRegRole) {
    $stmt = $preDb->prepare("SELECT email, designation, full_name, contact FROM PreReg_Teachers WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        $preRegRole = 'Teacher';
        $userInfo = [
            'email' => $teacher['email'],
            'full_name' => $teacher['full_name'],
            'contact' => $teacher['contact'],
            'designation' => $teacher['designation']
        ];
    }
}

// Check Librarian table if not student or teacher
if (!$preRegRole) {
    $stmt = $preDb->prepare("SELECT email, full_name, contact FROM PreReg_Librarians WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $librarian = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($librarian) {
        $preRegRole = 'Librarian';
        $userInfo = [
            'email' => $librarian['email'],
            'full_name' => $librarian['full_name'],
            'contact' => $librarian['contact']
        ];
    }
}

// Check Director table if not student, teacher, or librarian
if (!$preRegRole) {
    $stmt = $preDb->prepare("SELECT email, full_name, contact FROM PreReg_Directors WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $director = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($director) {
        $preRegRole = 'Director';
        $userInfo = [
            'email' => $director['email'],
            'full_name' => $director['full_name'],
            'contact' => $director['contact']
        ];
    }
}

// If email not found in ANY pre-registration table, reject registration
if (!$preRegRole || !$userInfo) {
    respond(403, [
        'success' => false,
        'message' => 'This email is not pre-registered. Only authorized users can create accounts. Please contact the administrator.',
    ]);
}

// Override role and name from pre-registration data
$role = $preRegRole;
$name = $userInfo['full_name'] ?? '';
$phone = $userInfo['contact'] ?? $phone;

// Check existing user in main database
$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    respond(400, [
        'success' => false,
        'message' => 'Account already exists. Please sign in.',
    ]);
}

// Create provisional account now (as requested flow): email is registered in Users
// immediately after pre-registration validation, with role assigned.
try {
    $db->beginTransaction();

    $placeholderHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
    $ins = $db->prepare('
        INSERT INTO Users (email, name, password_hash, role, contact, created_at)
        VALUES (:email, :name, :password_hash, :role, :contact, NOW())
    ');
    $ins->execute([
        ':email' => $email,
        ':name' => $userInfo['full_name'],
        ':password_hash' => $placeholderHash,
        ':role' => $preRegRole,
        ':contact' => $userInfo['contact'] ?? '',
    ]);

    if ($preRegRole === 'Student' && isset($userInfo['roll'], $userInfo['session'])) {
        $stuIns = $db->prepare('
            INSERT INTO Students (email, roll, session)
            VALUES (:email, :roll, :session)
        ');
        $stuIns->execute([
            ':email' => $email,
            ':roll' => $userInfo['roll'],
            ':session' => $userInfo['session'],
        ]);
    } elseif ($preRegRole === 'Teacher' && isset($userInfo['designation'])) {
        $tchIns = $db->prepare('
            INSERT INTO Teachers (email, designation)
            VALUES (:email, :designation)
        ');
        $tchIns->execute([
            ':email' => $email,
            ':designation' => $userInfo['designation'],
        ]);
    }

    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, [
        'success' => false,
        'message' => 'Failed to register account. Please try again.',
    ]);
}

$otpResult = issue_otp($email, 'EmailVerification');
if (!$otpResult['ok']) {
    $wait = $otpResult['wait'] ?? 60;
    respond(429, [
        'success' => false,
        'message' => "Please wait ${wait}s before requesting another code.",
        'retry_after' => $wait,
    ]);
}

// Send verification email
$emailSent = MailService::sendVerificationEmail($email, $otpResult['otp']);

respond(200, [
    'success' => true, // Always succeed in dev mode so user can proceed
    'message' => 'Verification code sent to your email. (Check backend/logs/iit_shelf_otp.log in local development)',
    'email' => $email,
    'role' => $role,
    'user_info' => $userInfo,
    'otp' => $otpResult['otp'], // Return for development testing
]);
