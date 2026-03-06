<?php
// --- SMART SCAN REGISTRY MASTER CONFIG ---
// Hosted on InfinityFree for Dantyz

$host = "sql112.infinityfree.com"; 
$user = "if0_41260155";             
$pass = "170dantech21218";           
$dbname = "if0_41260155_smartscan";  

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Set Charset to UTF-8 to handle student names with special characters
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
    // Return JSON error so the Android app doesn't crash on a raw HTML error
    header('Content-Type: application/json');
    die(json_encode([
        "status" => "error", 
        "message" => "Database Connection Failed: " . $conn->connect_error
    ]));
}

// Success! The connection is now live for any file that includes this.
?>