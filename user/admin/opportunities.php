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

$error = '';
$success = '';

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
        $stmt = $conn->prepare("INSERT INTO opportunities (title, type, organization, location, deadline, link, description, requirements, benefits, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $deadlineParam = $deadline === '' ? null : $deadline;
        $stmt->bind_param(
            "ssssssssss",
            $title,
            $type,
            $organization,
            $location,
            $deadlineParam,
            $link,
            $description,
            $requirements,
            $benefits,
            $status
        );
        if ($stmt->execute()) {
            $success = 'Opportunity created.';
        } else {
            $error = 'Failed to create opportunity.';
        }
        $stmt->close();
    }
}

$items = $conn->query("SELECT * FROM opportunities ORDER BY created_at DESC");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Opportunities</h2>
            <p class="text-muted mb-0">Create opportunities that match the student-facing opportunities board.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Add Opportunity</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Software Engineering Internship" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="internship">Internship</option>
                        <option value="attachment">Attachment</option>
                        <option value="job">Job</option>
                        <option value="scholarship">Scholarship</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Organization</label>
                    <input type="text" name="organization" class="form-control" placeholder="e.g. Zetech Labs">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Nairobi, Kenya / Remote">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="deadline" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Application Link</label>
                    <input type="url" name="link" class="form-control" placeholder="https://">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Summarize responsibilities, eligibility, and key details." required></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Requirements</label>
                    <textarea name="requirements" class="form-control" rows="4" placeholder="e.g. Resume, cover letter, transcript"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Benefits</label>
                    <textarea name="benefits" class="form-control" rows="4" placeholder="e.g. Mentorship, stipend, certificate"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Create Opportunity</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">All Opportunities</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Organization</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($items && $items->num_rows > 0): ?>
                    <?php while ($row = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['type'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($row['organization'] ?: '-'); ?></td>
                            <td><?php echo !empty($row['deadline']) ? htmlspecialchars(date('M d, Y', strtotime($row['deadline']))) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'active')); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="opportunity_edit.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" href="opportunity_delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this opportunity?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No opportunities yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

