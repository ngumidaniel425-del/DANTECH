<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
header('Content-Type: application/json');

$connection_string = getenv('DATABASE_URL'); 
$conn = pg_connect($connection_string);

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Connection failed"]);
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

// --- 2. STUDENT REGISTRATION (SINGLE) ---
} elseif ($type == "register_student") {
    $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) VALUES ($1, $2, $3, $4) ON CONFLICT (admission, class_name) DO UPDATE SET fullname = EXCLUDED.fullname";
    $result = pg_query_params($conn, $query, array($data['admission'], $data['fullname'], $data['class_name'], $data['school_name']));
    echo json_encode(["status" => $result ? "success" : "error"]);

// --- 3. BULK STUDENT UPLOAD ---
} elseif ($type == "upload_all") {
    $students = isset($data['students']) ? $data['students'] : [];
    foreach ($students as $s) {
        $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) VALUES ($1, $2, $3, $4) ON CONFLICT (admission, class_name) DO UPDATE SET fullname = EXCLUDED.fullname";
        pg_query_params($conn, $query, array($s['admission'], $s['fullname'], $s['class_name'], $s['school_name']));
    }
    echo json_encode(["status" => "success"]);

// --- 4. FETCH MASTER DATA ---
} elseif ($type == 'fetch_master_data') {
    $classes = pg_fetch_all(pg_query($conn, "SELECT * FROM classes"));
    $lessons = pg_fetch_all(pg_query($conn, "SELECT * FROM lessons"));
    
    echo json_encode([
        "classes" => $classes ? $classes : [],
        "lessons" => $lessons ? $lessons : []
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request type"]);
}

pg_close($conn);
?>
