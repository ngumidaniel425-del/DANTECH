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

// 3. Extract Context (School & Department)
// Checked in both JSON body (POST) and URL (GET)
$school = isset($data['school_name']) ? $data['school_name'] : (isset($_GET['school_name']) ? $_GET['school_name'] : '');
$dept = isset($data['department']) ? $data['department'] : (isset($_GET['department']) ? $_GET['department'] : '');

// 4. Logic Router
if ($type == "register_teacher") {
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $query = "INSERT INTO teachers (fullname, email, phone, password, school_name, department) VALUES ($1, $2, $3, $4, $5, $6)";
    $result = pg_query_params($conn, $query, array($data['fullname'], $data['email'], $data['phone'], $password, $data['school_name'], $data['department']));
    echo json_encode(["status" => $result ? "success" : "error"]);

} elseif ($type == "login") {
    $query = "SELECT * FROM teachers WHERE email = $1";
    $result = pg_query_params($conn, $query, array($data['email']));
    $user = pg_fetch_assoc($result);
    if ($user && password_verify($data['password'], $user['password'])) {
        echo json_encode([
            "status" => "success",
            "teacher_id" => $user['id'],
            "teacher_name" => $user['fullname'],
            "school_name" => $user['school_name'],
            "department" => $user['department'],
            "phone" => $user['phone']
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    }

} elseif ($type == "add_class") {
    // RECTIFIED: Class is now isolated to the Department
    $query = "INSERT INTO classes (class_name, school_name, department) VALUES ($1, $2, $3) ON CONFLICT DO NOTHING";
    $result = pg_query_params($conn, $query, array($data['class_name'], $school, $dept));
    echo json_encode(["status" => "success"]);

} elseif ($type == "add_lesson") {
    // RECTIFIED: Lesson is bound to the specific Class and Department
    $query = "INSERT INTO lessons (lesson_name, class_name, school_name, department) VALUES ($1, $2, $3, $4) ON CONFLICT DO NOTHING";
    $result = pg_query_params($conn, $query, array($data['lesson_name'], $data['class_name'], $school, $dept));
    echo json_encode(["status" => "success"]);

} elseif ($type == "upload_all") {
    $success = true;
    $students = isset($data['students']) ? $data['students'] : [];
    $attendance = isset($data['attendance_data']) ? $data['attendance_data'] : [];

    foreach ($students as $s) {
        $q = "INSERT INTO students_master (admission, fullname, class_name, school_name, department) 
              VALUES ($1, $2, $3, $4, $5) ON CONFLICT (admission, school_name) DO UPDATE SET fullname=EXCLUDED.fullname, class_name=EXCLUDED.class_name";
        if (!pg_query_params($conn, $q, array($s['admission'], $s['fullname'], $s['class_name'], $school, $dept))) $success = false;
    }

    foreach ($attendance as $row) {
        $q = "INSERT INTO attendance_sync (student_adm, class_name, lesson_name, period_type, attendance_date, student_name, school_name, department) 
              VALUES ($1, $2, $3, $4, $5, $6, $7, $8) ON CONFLICT DO NOTHING";
        if (!pg_query_params($conn, $q, array($row['adm'], $row['class'], $row['lesson'], $row['period'], $row['date'], $row['name'], $school, $dept))) $success = false;
    }
    echo json_encode(["status" => $success ? "success" : "error"]);

} elseif ($type == 'fetch_master_data') {
    // RECTIFIED: Fetch ONLY data belonging to this specific School and Department
    $classes = pg_fetch_all(pg_query_params($conn, "SELECT class_name FROM classes WHERE school_name = $1 AND department = $2", array($school, $dept)));
    $lessons = pg_fetch_all(pg_query_params($conn, "SELECT lesson_name, class_name FROM lessons WHERE school_name = $1 AND department = $2", array($school, $dept)));
    $students = pg_fetch_all(pg_query_params($conn, "SELECT admission, fullname, class_name FROM students_master WHERE school_name = $1 AND department = $2", array($school, $dept)));
    
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
