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

// 3. Identify the School (Crucial for segregation)
// We look for school_name in the URL parameters or the JSON body
$school = isset($_GET['school_name']) ? $_GET['school_name'] : (isset($data['school_name']) ? $data['school_name'] : '');

if (empty($school) && $type != 'register_teacher') {
    echo json_encode(["status" => "error", "message" => "School name is required for sync"]);
    exit();
}

// 4. Logic Router
if ($type == "attendance" || $type == "attendance_upload") {
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : [];
    $success = true;
    
    foreach ($attendance_list as $row) {
        // RECTIFIED: Added school_name to ensure unique attendance per school
        $query = "INSERT INTO attendance_sync (student_adm, class_name, lesson_name, period_type, attendance_date, student_name, school_name) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7) 
                  ON CONFLICT (student_adm, class_name, lesson_name, attendance_date, school_name) DO NOTHING";
        
        $params = array(
            $row['adm'],    
            $row['class'],  
            $row['lesson'], 
            $row['period'], 
            $row['date'],   
            isset($row['name']) ? $row['name'] : 'Unknown',
            $school
        );

        if (!pg_query_params($conn, $query, $params)) { $success = false; }
    }
    echo json_encode(["status" => $success ? "success" : "error"]);

} elseif ($type == "add_class") {
    $class_name = isset($data['class_name']) ? $data['class_name'] : '';
    if (!empty($class_name)) {
        // RECTIFIED: Classes are now unique to each school
        $query = "INSERT INTO classes (class_name, school_name) VALUES ($1, $2) ON CONFLICT (class_name, school_name) DO NOTHING";
        $result = pg_query_params($conn, $query, array($class_name, $school));
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Class name empty"]);
    }

} elseif ($type == "add_lesson") {
    $lesson_name = isset($data['lesson_name']) ? $data['lesson_name'] : '';
    $class_name = isset($data['class_name']) ? $data['class_name'] : '';

    if (!empty($lesson_name) && !empty($class_name)) {
        $query = "INSERT INTO lessons (lesson_name, class_name, school_name) 
                  VALUES ($1, $2, $3) 
                  ON CONFLICT (lesson_name, class_name, school_name) DO NOTHING";
        $result = pg_query_params($conn, $query, array($lesson_name, $class_name, $school));
        echo json_encode(["status" => $result ? "success" : "error"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
    }

} elseif ($type == "register_student") {
    $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) 
              VALUES ($1, $2, $3, $4) 
              ON CONFLICT (admission, school_name) 
              DO UPDATE SET fullname = EXCLUDED.fullname, class_name = EXCLUDED.class_name";
    $result = pg_query_params($conn, $query, array($data['admission'], $data['fullname'], $data['class_name'], $school));
    echo json_encode(["status" => $result ? "success" : "error"]);

} elseif ($type == "upload_all") {
    $success = true;
    $students = isset($data['students']) ? $data['students'] : [];
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : [];
    $classes = isset($data['classes']) ? $data['classes'] : [];
    $lessons = isset($data['lessons']) ? $data['lessons'] : [];

    // All loops updated to force the current school context
    foreach ($students as $s) {
        $query = "INSERT INTO students_master (admission, fullname, class_name, school_name) 
                  VALUES ($1, $2, $3, $4) 
                  ON CONFLICT (admission, school_name) 
                  DO UPDATE SET fullname = EXCLUDED.fullname, class_name = EXCLUDED.class_name";
        if (!pg_query_params($conn, $query, array($s['admission'], $s['fullname'], $s['class_name'], $school))) $success = false;
    }

    foreach ($attendance_list as $row) {
        $query = "INSERT INTO attendance_sync (student_adm, class_name, lesson_name, period_type, attendance_date, student_name, school_name) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7) 
                  ON CONFLICT (student_adm, class_name, lesson_name, attendance_date, school_name) DO NOTHING";
        $params = array($row['adm'], $row['class'], $row['lesson'], $row['period'], $row['date'], $row['name'], $school);
        if (!pg_query_params($conn, $query, $params)) $success = false;
    }

    foreach ($classes as $c) {
        pg_query_params($conn, "INSERT INTO classes (class_name, school_name) VALUES ($1, $2) ON CONFLICT (class_name, school_name) DO NOTHING", array($c['class_name'], $school));
    }

    foreach ($lessons as $l) {
        pg_query_params($conn, "INSERT INTO lessons (lesson_name, class_name, school_name) VALUES ($1, $2, $3) ON CONFLICT (lesson_name, class_name, school_name) DO NOTHING", array($l['lesson_name'], $l['class_name'], $school));
    }

    echo json_encode(["status" => $success ? "success" : "error"]);

} elseif ($type == 'fetch_master_data') {
    // RECTIFIED: Only fetch data that belongs to THIS school
    $classes = pg_fetch_all(pg_query_params($conn, "SELECT class_name FROM classes WHERE school_name = $1", array($school)));
    $lessons = pg_fetch_all(pg_query_params($conn, "SELECT lesson_name, class_name FROM lessons WHERE school_name = $1", array($school)));
    $students = pg_fetch_all(pg_query_params($conn, "SELECT admission, fullname, class_name FROM students_master WHERE school_name = $1", array($school)));
    
    echo json_encode([
        "classes" => $classes ?: [], 
        "lessons" => $lessons ?: [], 
        "students" => $students ?: []
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request type"]);
}

pg_close($conn);
?>
