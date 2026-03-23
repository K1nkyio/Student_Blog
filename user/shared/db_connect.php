<?php
$host = "localhost";
$user = "root";       // default XAMPP username
$pass = "";           // default XAMPP password is blank
$dbname = "student_blog";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_init();
    if ($conn === false) {
        throw new Exception("Failed to initialize MySQLi");
    }

    // Avoid long hangs when MySQL is down or not responding.
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    mysqli_real_connect($conn, $host, $user, $pass, $dbname);

    ensure_posts_review_schema($conn);
} catch (Throwable $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(503);
    die("Database connection failed. Please check that MySQL is running and try again.");
}

function ensure_posts_review_schema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM posts");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = true;
    }

    $alter = [];
    if (!isset($columns['review_status'])) {
        $alter[] = "ADD COLUMN review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER visible";
    }
    if (!isset($columns['review_notes'])) {
        $alter[] = "ADD COLUMN review_notes TEXT DEFAULT NULL AFTER review_status";
    }
    if (!isset($columns['reviewed_by'])) {
        $alter[] = "ADD COLUMN reviewed_by INT(11) DEFAULT NULL AFTER review_notes";
    }
    if (!isset($columns['reviewed_at'])) {
        $alter[] = "ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER reviewed_by";
    }

    if (!empty($alter)) {
        $conn->query("ALTER TABLE posts " . implode(', ', $alter));
    }
}
?>
