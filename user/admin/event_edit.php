<?php
include 'includes/header.php';
include '../shared/db_connect.php';

$createSql = "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    event_date DATE DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    organizer VARCHAR(255) DEFAULT NULL,
    registration_link VARCHAR(500) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'event_date'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN event_date DATE DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'status'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'image'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN image VARCHAR(255) DEFAULT NULL");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

$stmt = $conn->prepare("SELECT *, COALESCE(status, 'active') AS status FROM events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: events.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $registration_link = trim($_POST['registration_link'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $imagePath = null;

    if ($title === '' || $event_date === '' || $description === '') {
        $error = 'Title, date, and description are required.';
    } else {
        if (!empty($_FILES['image']['name'])) {
            $targetDir = "../assets/images/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName   = time() . '_event_' . basename($_FILES['image']['name']);
            $targetFile = $targetDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imagePath = 'assets/images/' . $fileName;
            } else {
                $error = 'Image upload failed.';
            }
        }
    }

    if ($error === '') {
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE events SET title = ?, event_date = ?, location = ?, organizer = ?, registration_link = ?, image = ?, description = ?, status = ? WHERE id = ?");
            $stmt->bind_param(
                "ssssssssi",
                $title,
                $event_date,
                $location,
                $organizer,
                $registration_link,
                $imagePath,
                $description,
                $status,
                $id
            );
        } else {
            $stmt = $conn->prepare("UPDATE events SET title = ?, event_date = ?, location = ?, organizer = ?, registration_link = ?, description = ?, status = ? WHERE id = ?");
            $stmt->bind_param(
                "sssssssi",
                $title,
                $event_date,
                $location,
                $organizer,
                $registration_link,
                $description,
                $status,
                $id
            );
        }

        if ($stmt->execute()) {
            $success = 'Event updated.';
        } else {
            $error = 'Failed to update event.';
        }
        $stmt->close();
        $item = array_merge($item, [
            'title' => $title,
            'event_date' => $event_date,
            'location' => $location,
            'organizer' => $organizer,
            'registration_link' => $registration_link,
            'description' => $description,
            'status' => $status,
            'image' => $imagePath ?: ($item['image'] ?? null)
        ]);
    }
}
?>

<div class="container-fluid py-4">
    <div class="mb-3">
        <h2 class="mb-1">Edit Event</h2>
        <p class="text-muted mb-0">Update event details.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3" enctype="multipart/form-data">
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="event_date" class="form-control" value="<?php echo htmlspecialchars($item['event_date']); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Organizer</label>
                    <input type="text" name="organizer" class="form-control" value="<?php echo htmlspecialchars($item['organizer'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Registration Link</label>
                    <input type="url" name="registration_link" class="form-control" value="<?php echo htmlspecialchars($item['registration_link'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <?php if (!empty($item['image'])): ?>
                        <div class="mb-2">
                            <small class="text-muted d-block">Current image</small>
                            <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="Event image" style="max-width: 240px; border-radius: 6px; border: 1px solid #e5e7eb;">
                        </div>
                    <?php endif; ?>
                    <label class="form-label">Event Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                    <a class="btn btn-outline-secondary" href="events.php">Back to Events</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

