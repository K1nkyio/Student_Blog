<?php
include 'includes/header.php';
include '../shared/db_connect.php';

$createSql = "CREATE TABLE IF NOT EXISTS opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    organization VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    link VARCHAR(500) DEFAULT NULL,
    description TEXT NOT NULL,
    requirements TEXT DEFAULT NULL,
    benefits TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'type'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN type VARCHAR(50) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'deadline'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN deadline DATE DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'organization'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN organization VARCHAR(255) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'location'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN location VARCHAR(255) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'link'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN link VARCHAR(500) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'status'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'requirements'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN requirements TEXT DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'benefits'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN benefits TEXT DEFAULT NULL");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

$stmt = $conn->prepare("SELECT * FROM opportunities WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: opportunities.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($title === '' || $type === '' || $description === '') {
        $error = 'Title, type, and description are required.';
    } else {
        $stmt = $conn->prepare("UPDATE opportunities SET title = ?, type = ?, organization = ?, location = ?, deadline = ?, link = ?, description = ?, requirements = ?, benefits = ?, status = ? WHERE id = ?");
        $deadlineParam = $deadline === '' ? null : $deadline;
        $stmt->bind_param(
            "ssssssssssi",
            $title,
            $type,
            $organization,
            $location,
            $deadlineParam,
            $link,
            $description,
            $requirements,
            $benefits,
            $status,
            $id
        );
        if ($stmt->execute()) {
            $success = 'Opportunity updated.';
        } else {
            $error = 'Failed to update opportunity.';
        }
        $stmt->close();
        $item = array_merge($item, [
            'title' => $title,
            'type' => $type,
            'organization' => $organization,
            'location' => $location,
            'deadline' => $deadlineParam,
            'link' => $link,
            'description' => $description,
            'requirements' => $requirements,
            'benefits' => $benefits,
            'status' => $status
        ]);
    }
}
?>

<div class="container-fluid py-4">
    <div class="mb-3">
        <h2 class="mb-1">Edit Opportunity</h2>
        <p class="text-muted mb-0">Update opportunity details.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <?php
                        $types = ['internship','attachment','job','scholarship'];
                        foreach ($types as $t):
                        ?>
                            <option value="<?php echo $t; ?>" <?php echo $item['type'] === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Organization</label>
                    <input type="text" name="organization" class="form-control" value="<?php echo htmlspecialchars($item['organization'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="deadline" class="form-control" value="<?php echo htmlspecialchars($item['deadline'] ?? ''); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Link</label>
                    <input type="url" name="link" class="form-control" value="<?php echo htmlspecialchars($item['link'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Requirements</label>
                    <textarea name="requirements" class="form-control" rows="4"><?php echo htmlspecialchars($item['requirements'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Benefits</label>
                    <textarea name="benefits" class="form-control" rows="4"><?php echo htmlspecialchars($item['benefits'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                    <a class="btn btn-outline-secondary" href="opportunities.php">Back to Opportunities</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

