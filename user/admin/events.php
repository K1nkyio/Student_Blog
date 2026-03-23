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

$error = '';
$success = '';

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
    } elseif ($status === 'active' && $event_date < date('Y-m-d')) {
        $error = 'Active events must be dated today or later so they appear on the student dashboard.';
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
        $stmt = $conn->prepare("INSERT INTO events (title, event_date, location, organizer, registration_link, image, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssss",
            $title,
            $event_date,
            $location,
            $organizer,
            $registration_link,
            $imagePath,
            $description,
            $status
        );
        if ($stmt->execute()) {
            $success = 'Event created.';
        } else {
            $error = 'Failed to create event.';
        }
        $stmt->close();
    }
}

$conn->query("UPDATE events SET status = 'active' WHERE status IS NULL OR status = ''");
$items = $conn->query("SELECT *, COALESCE(status, 'active') AS status FROM events ORDER BY event_date DESC, created_at DESC");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Events</h2>
            <p class="text-muted mb-0">Discover hackathons, workshops, social events, and competitions happening at Zetech.</p>
            <small class="text-muted">Active events dated today or later automatically show on the student dashboard.</small>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Add Event</div>
        <div class="card-body">
            <form method="POST" class="row g-3" enctype="multipart/form-data">
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Organizer</label>
                    <input type="text" name="organizer" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Registration Link</label>
                    <input type="url" name="registration_link" class="form-control" placeholder="https://">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Event Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Create Event</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">All Events</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($items && $items->num_rows > 0): ?>
                    <?php while ($row = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['event_date']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="event_edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" href="event_delete.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this event?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No events yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

