<?php
header('Content-Type: application/json');

// --- 1. DATABASE CONNECTION (InfinityFree) ---
$host = "sql112.infinityfree.com"; 
$db_user = "if0_41260155"; 
$db_pass = "170dantech21218"; 
$db_name = "if0_41260155_smartscan"; 

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// --- 2. RETRIEVE PARAMETERS ---
// We need class, date, and now LESSON to be accurate for Version 2
$class = isset($_GET['class_name']) ? $_GET['class_name'] : '';
$date  = isset($_GET['date']) ? $_GET['date'] : '';
$lesson = isset($_GET['lesson_name']) ? $_GET['lesson_name'] : '';

if (empty($class) || empty($date)) {
    echo json_encode(["error" => "Missing parameters: class_name and date are required"]);
    exit();
}

// --- 3. FETCH ATTENDANCE ---
// We join with the students table so the app gets the student NAMES, not just admission numbers
$sql = "SELECT a.student_adm, s.fullname, a.lesson_name, a.attendance_date 
        FROM attendance a 
        LEFT JOIN students s ON a.student_adm = s.admission 
        WHERE a.class_name = ? AND a.attendance_date = ?";

// If a specific lesson is requested, filter by it too
if (!empty($lesson)) {
    $sql .= " AND a.lesson_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $class, $date, $lesson);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $class, $date);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = array();
while($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

// --- 4. RETURN DATA ---
echo json_encode($rows);

$stmt->close();
$conn->close();
?>