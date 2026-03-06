<?php
    <?php
// Force allow cross-origin and disable some bot-checks if possible
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// If the request is OPTIONS (pre-flight), exit early
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

header('Content-Type: application/json');
// ... rest of your connection code ...
header('Content-Type: application/json');

// --- DATABASE CONNECTION (InfinityFree) ---
$host = "sql112.infinityfree.com"; 
$db_user = "if0_41260155"; 
$db_pass = "170dantech21218"; 
$db_name = "if0_41260155_smartscan"; 

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed"]));
}

// Check if type is set
if (!isset($_GET['type'])) {
    echo json_encode(["status" => "error", "message" => "No request type"]);
    exit();
}

$type = $_GET['type'];
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

// --- 1. SINGLE SCAN UPLOAD ---
if ($type == "single_upload") {
    $stmt = $conn->prepare("INSERT IGNORE INTO attendance_sync (student_adm, student_name, class_name, lesson_name, date, time) VALUES (?, ?, ?, ?, CURDATE(), CURTIME())");
    $stmt->bind_param("ssss", $data['admission'], $data['fullname'], $data['class_name'], $data['lesson_name']);
    if ($stmt->execute()) echo json_encode(["status" => "success"]);
    else echo json_encode(["status" => "error", "message" => $stmt->error]);
    $stmt->close();

// --- 2. BULK ATTENDANCE UPLOAD ---
} elseif ($type == "attendance" || $type == "attendance_upload") {
    $attendance_list = isset($data['attendance_data']) ? $data['attendance_data'] : $data;
    if (!empty($attendance_list)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO attendance_sync (student_adm, student_name, class_name, lesson_name, date, time) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($attendance_list as $row) {
            $stmt->bind_param("ssssss", $row['student_adm'], $row['student_name'], $row['class_name'], $row['lesson_name'], $row['date'], $row['time']);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(["status" => "success"]);
    }

// --- 3. STUDENT REGISTRATION ---
} elseif ($type == "add_student" || $type == "register_student") {
    $adm = isset($data['admission']) ? $data['admission'] : $_POST['admission'];
    $name = isset($data['fullname']) ? $data['fullname'] : $_POST['fullname'];
    $class = isset($data['class_name']) ? $data['class_name'] : $_POST['class_name'];
    $school = isset($data['school_name']) ? $data['school_name'] : $_POST['school_name'];

    $stmt = $conn->prepare("INSERT INTO students_master (admission, fullname, class_name, school_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE fullname=?, class_name=?");
    $stmt->bind_param("ssssss", $adm, $name, $class, $school, $name, $class);
    if ($stmt->execute()) echo json_encode(["status" => "success"]);
    else echo json_encode(["status" => "error", "message" => $stmt->error]);
    $stmt->close();

// --- 4. FETCH ALL (Dashboard Sync) ---
// This matches DashboardActivity: fetch_all&school=...
} elseif ($type == 'fetch_all' || $type == 'fetch_students') {
    $school = isset($_GET['school']) ? $_GET['school'] : '';
    
    // We fetch from students_master to update the local Registry
    $result = $conn->query("SELECT admission, fullname, class_name, school_name FROM students_master WHERE school_name = '$school'");
    
    $students = [];
    while($row = $result->fetch_assoc()) { 
        $students[] = $row; 
    }
    // Return the array directly as JsonArrayRequest expects it
    echo json_encode($students);

// --- 5. FETCH CLASSES / LESSONS ---
} elseif ($type == 'fetch_classes') {
    $school = $_GET['school'];
    $result = $conn->query("SELECT DISTINCT class_name FROM classes WHERE school_name = '$school'");
    $classes = [];
    while($row = $result->fetch_assoc()) { $classes[] = $row; }
    echo json_encode($classes);

} elseif ($type == 'fetch_lessons') {
    $school = $_GET['school'];
    $result = $conn->query("SELECT DISTINCT lesson_name FROM lessons WHERE school_name = '$school'");
    $lessons = [];
    while($row = $result->fetch_assoc()) { $lessons[] = $row; }
    echo json_encode($lessons);
}

$conn->close();
?>