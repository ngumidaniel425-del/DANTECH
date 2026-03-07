<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
header('Content-Type: application/json');

$connection_string = getenv('DATABASE_URL'); 
$conn = pg_connect($connection_string);

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . pg_last_error()]);
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

// --- 1. ATTENDANCE UPLOAD ---
if ($type == "attendance" || $type == "attendance_upload") {
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : [];
    foreach ($attendance_list as $row) {
        $query = "INSERT INTO attendance_sync (student_adm, student_name, class_name, lesson_name, period_type, attendance_date) VALUES ($1, $2, $3, $4, $5, $6)";
        pg_query_params($conn, $query, array($row['student_adm'], $row['student_name'], $row['class_name'], $row['lesson_name'], $row['period_type'], $row['date']));
    }
    echo json_encode(["status" => "success"]);

// --- 2. STUDENT REGISTRATION ---
} elseif ($type == "add_student" || $type == "register_student") {
    $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) VALUES ($1, $2, $3, $4) ON CONFLICT (admission, school_name) DO UPDATE SET fullname = EXCLUDED.fullname";
    $result = pg_query_params($conn, $query, array($data['admission'], $data['fullname'], $data['class_name'], $data['school_name']));
    echo json_encode(["status" => $result ? "success" : "error"]);

// --- 3. FETCH ALL ---
} elseif ($type == 'fetch_students') {
    $school = isset($_GET['school']) ? $_GET['school'] : '';
    $result = pg_query_params($conn, "SELECT admission, fullname, class_name, school_name FROM students_master WHERE school_name = $1", array($school));
    $students = [];
    while($row = pg_fetch_assoc($result)) { $students[] = $row; }
    echo json_encode($students);
}

pg_close($conn);
?>
