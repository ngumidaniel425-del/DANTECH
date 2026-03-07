<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

header('Content-Type: application/json');

// 1. Database Connection
$connection_string = getenv('DATABASE_URL'); 
$conn = pg_connect($connection_string);

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Connection failed"]);
    exit();
}

// 2. Initialize Inputs
$type = isset($_GET['type']) ? $_GET['type'] : '';
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

// 3. Logic Router
if ($type == "attendance" || $type == "attendance_upload") {
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : [];
    foreach ($attendance_list as $row) {
        $query = "INSERT INTO attendance_sync (student_adm, student_name, class_name, lesson_name, period_type, attendance_date) 
                  VALUES ($1, $2, $3, $4, $5, $6) 
                  ON CONFLICT DO NOTHING";
        pg_query_params($conn, $query, array($row['student_adm'], $row['student_name'], $row['class_name'], $row['lesson_name'], $row['period_type'], $row['date']));
    }
    echo json_encode(["status" => "success"]);

} elseif ($type == "add_class") {
    $class_name = isset($data['class_name']) ? $data['class_name'] : '';
    if (!empty($class_name)) {
        $query = "INSERT INTO classes (class_name) VALUES ($1) ON CONFLICT (class_name) DO NOTHING";
        $result = pg_query_params($conn, $query, array($class_name));
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Class name empty"]);
    }

} elseif ($type == "add_lesson") {
    $teacher_id = isset($data['teacher_id']) ? $data['teacher_id'] : 0;
    $lesson_name = isset($data['lesson_name']) ? $data['lesson_name'] : '';
    $school_name = isset($data['school_name']) ? $data['school_name'] : '';

    if (!empty($lesson_name)) {
        // Corrected ON CONFLICT to match your Postgres unique constraints
        $query = "INSERT INTO lessons (teacher_id, lesson_name, school_name) 
                  VALUES ($1, $2, $3) 
                  ON CONFLICT (lesson_name) 
                  DO UPDATE SET school_name = EXCLUDED.school_name, teacher_id = EXCLUDED.teacher_id";
        
        $result = pg_query_params($conn, $query, array($teacher_id, $lesson_name, $school_name));
        echo json_encode(["status" => $result ? "success" : "error", "message" => "Lesson processed"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Missing lesson name"]);
    }

} elseif ($type == "register_student") {
    // FIXED: Removed the double "ON ON" and aligned conflict with (admission, school_name)
    $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) 
              VALUES ($1, $2, $3, $4) 
              ON CONFLICT (admission, school_name) 
              DO UPDATE SET fullname = EXCLUDED.fullname";
    $result = pg_query_params($conn, $query, array($data['admission'], $data['fullname'], $data['class_name'], $data['school_name']));
    echo json_encode(["status" => $result ? "success" : "error"]);

} elseif ($type == "upload_all") {
    $success = true;
    $students = isset($data['students']) ? $data['students'] : [];
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : [];

    // A. Handle Students
    foreach ($students as $s) {
        $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) 
                  VALUES ($1, $2, $3, $4) 
                  ON CONFLICT (admission, school_name) 
                  DO UPDATE SET fullname = EXCLUDED.fullname";
        if (!pg_query_params($conn, $query, array($s['admission'], $s['fullname'], $s['class_name'], $s['school_name']))) {
            $success = false;
        }
    }

    // B. Handle Attendance
    foreach ($attendance_list as $row) {
        $query = "INSERT INTO attendance_sync (student_adm, student_name, class_name, lesson_name, period_type, attendance_date) 
                  VALUES ($1, $2, $3, $4, $5, $6) 
                  ON CONFLICT DO NOTHING";
        if (!pg_query_params($conn, $query, array($row['student_adm'], $row['student_name'], $row['class_name'], $row['lesson_name'], $row['period_type'], $row['date']))) {
            $success = false;
        }
    }

    echo json_encode(["status" => $success ? "success" : "error", "message" => $success ? "Data synced" : "Sync partial failure"]);

} elseif ($type == 'fetch_master_data') {
    $classes = pg_fetch_all(pg_query($conn, "SELECT * FROM classes"));
    $lessons = pg_fetch_all(pg_query($conn, "SELECT * FROM lessons"));
    
    echo json_encode([
        "classes" => $classes ? $classes : [],
        "lessons" => $lessons ? $lessons : []
    ]);

} elseif ($type == 'debug_view') {
    $tables = ['classes', 'lessons', 'attendance_sync', 'students_master'];
    $debug_output = [];
    foreach ($tables as $table) {
        $result = pg_query($conn, "SELECT * FROM $table");
        $debug_output[$table] = $result ? pg_fetch_all($result) : "No data";
    }
    echo json_encode($debug_output, JSON_PRETTY_PRINT);

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request type"]);
}

pg_close($conn);
?>
