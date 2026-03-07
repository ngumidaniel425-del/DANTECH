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

// --- 3. BULK UPLOAD (Attendance AND Students) ---
} elseif ($type == "upload_all") {
    $success = true;

    // A. Handle Students
    $students = isset($data['students']) ? $data['students'] : [];
    foreach ($students as $s) {
        $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) 
                  VALUES ($1, $2, $3, $4) 
                  ON CONFLICT (admission, class_name) 
                  DO UPDATE SET fullname = EXCLUDED.fullname";
        if (!pg_query_params($conn, $query, array($s['admission'], $s['fullname'], $s['class_name'], $s['school_name']))) {
            $success = false;
        }
    }

    // B. Handle Attendance (This was missing in your upload_all block!)
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : [];
    foreach ($attendance_list as $row) {
        $query = "INSERT INTO attendance_sync (student_adm, student_name, class_name, lesson_name, period_type, attendance_date) 
                  VALUES ($1, $2, $3, $4, $5, $6)
                  ON CONFLICT DO NOTHING"; // Prevent duplicates if same record sent twice
        if (!pg_query_params($conn, $query, array($row['student_adm'], $row['student_name'], $row['class_name'], $row['lesson_name'], $row['period_type'], $row['date']))) {
            $success = false;
        }
    }

    if ($success) {
        echo json_encode(["status" => "success", "message" => "All data synced successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Some data failed to save"]);
    }

// --- 4. FETCH MASTER DATA ---
} elseif ($type == 'fetch_master_data') {
    $classes = pg_fetch_all(pg_query($conn, "SELECT * FROM classes"));
    $lessons = pg_fetch_all(pg_query($conn, "SELECT * FROM lessons"));
    
    echo json_encode([
        "classes" => $classes ? $classes : [],
        "lessons" => $lessons ? $lessons : []
    ]);
    // ... after the fetch_master_data block ...

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
