<?php
// PDO Database connection for XAMPP (MySQL)
$db_host = 'localhost';
$db_name = 'restaurant_pos';
$db_user = 'root';
$db_pass = '';  // XAMPP default: empty password

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    // Return JSON for API calls, HTML for page calls
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    } else {
        die('<h2 style="color:red;font-family:sans-serif">Database connection failed. Make sure XAMPP MySQL is running and the database exists.<br>Run database.sql first.</h2>');
    }
    exit;
}
