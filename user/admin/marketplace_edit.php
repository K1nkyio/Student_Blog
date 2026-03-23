<?php
include 'includes/header.php';
include '../shared/db_connect.php';

$createSql = "CREATE TABLE IF NOT EXISTS marketplace_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    item_condition VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    stock_status VARCHAR(50) DEFAULT NULL,
    variant VARCHAR(100) DEFAULT NULL,
    delivery_estimate VARCHAR(100) DEFAULT NULL,
    contact VARCHAR(255) DEFAULT NULL,
    link VARCHAR(500) DEFAULT NULL,
    description TEXT NOT NULL,
    features TEXT DEFAULT NULL,
    specifications TEXT DEFAULT NULL,
    materials TEXT DEFAULT NULL,
    usage_instructions TEXT DEFAULT NULL,
    review_proof TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

$conn->query("CREATE TABLE IF NOT EXISTS marketplace_item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES marketplace_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'item_condition'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN item_condition VARCHAR(50) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'contact'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN contact VARCHAR(255) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'link'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN link VARCHAR(500) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'stock_status'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN stock_status VARCHAR(50) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'variant'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN variant VARCHAR(100) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'delivery_estimate'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN delivery_estimate VARCHAR(100) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'features'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN features TEXT DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'specifications'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN specifications TEXT DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'materials'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN materials TEXT DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'usage_instructions'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN usage_instructions TEXT DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'review_proof'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN review_proof TEXT DEFAULT NULL");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

$stmt = $conn->prepare("SELECT * FROM marketplace_items WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: marketplace.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $item_condition = trim($_POST['item_condition'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $stock_status = trim($_POST['stock_status'] ?? '');
    $variant = trim($_POST['variant'] ?? '');
    $delivery_estimate = trim($_POST['delivery_estimate'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $materials = trim($_POST['materials'] ?? '');
    $usage_instructions = trim($_POST['usage_instructions'] ?? '');
    $review_proof = trim($_POST['review_proof'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($title === '' || $category === '' || $description === '') {
        $error = 'Title, category, and description are required.';
    } else {
        $stmt = $conn->prepare("UPDATE marketplace_items SET title = ?, category = ?, price = ?, item_condition = ?, location = ?, stock_status = ?, variant = ?, delivery_estimate = ?, contact = ?, link = ?, description = ?, features = ?, specifications = ?, materials = ?, usage_instructions = ?, review_proof = ?, status = ? WHERE id = ?");
        $priceParam = $price === '' ? null : $price;
        $stmt->bind_param(
            "ssdssssssssssssssi",
            $title,
            $category,
            $priceParam,
            $item_condition,
            $location,
            $stock_status,
            $variant,
            $delivery_estimate,
            $contact,
            $link,
            $description,
            $features,
            $specifications,
            $materials,
            $usage_instructions,
            $review_proof,
            $status,
            $id
        );
        if ($stmt->execute()) {
            if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $uploadDir = realpath(__DIR__ . '/../public') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'marketplace';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                foreach ($_FILES['images']['name'] as $index => $name) {
                    $tmpName = $_FILES['images']['tmp_name'][$index] ?? '';
                    $type = $_FILES['images']['type'][$index] ?? '';
                    if ($tmpName === '' || !is_uploaded_file($tmpName) || !in_array($type, $allowed, true)) {
                        continue;
                    }
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $safeName = uniqid('marketplace_', true) . '.' . $ext;
                    $target = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
                    if (move_uploaded_file($tmpName, $target)) {
                        $relativePath = 'uploads/marketplace/' . $safeName;
                        $imgStmt = $conn->prepare("INSERT INTO marketplace_item_images (item_id, image_path) VALUES (?, ?)");
                        $imgStmt->bind_param("is", $id, $relativePath);
                        $imgStmt->execute();
                        $imgStmt->close();
                    }
                }
            }
            $success = 'Marketplace item updated.';
        } else {
            $error = 'Failed to update marketplace item.';
        }
        $stmt->close();
        $item = array_merge($item, [
            'title' => $title,
            'category' => $category,
            'price' => $priceParam,
            'item_condition' => $item_condition,
            'location' => $location,
            'stock_status' => $stock_status,
            'variant' => $variant,
            'delivery_estimate' => $delivery_estimate,
            'contact' => $contact,
            'link' => $link,
            'description' => $description,
            'features' => $features,
            'specifications' => $specifications,
            'materials' => $materials,
            'usage_instructions' => $usage_instructions,
            'review_proof' => $review_proof,
            'status' => $status
        ]);
    }
}
?>

<div class="container-fluid py-4">
    <div class="mb-3">
        <h2 class="mb-1">Edit Marketplace Item</h2>
        <p class="text-muted mb-0">Update marketplace details.</p>
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
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($item['category']); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo htmlspecialchars($item['price'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condition</label>
                    <input type="text" name="item_condition" class="form-control" value="<?php echo htmlspecialchars($item['item_condition'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stock Status</label>
                    <input type="text" name="stock_status" class="form-control" value="<?php echo htmlspecialchars($item['stock_status'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Variant</label>
                    <input type="text" name="variant" class="form-control" value="<?php echo htmlspecialchars($item['variant'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Delivery Estimate</label>
                    <input type="text" name="delivery_estimate" class="form-control" value="<?php echo htmlspecialchars($item['delivery_estimate'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($item['contact'] ?? ''); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Link</label>
                    <input type="url" name="link" class="form-control" value="<?php echo htmlspecialchars($item['link'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Add More Images</label>
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Features</label>
                    <textarea name="features" class="form-control" rows="3"><?php echo htmlspecialchars($item['features'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Specifications</label>
                    <textarea name="specifications" class="form-control" rows="3"><?php echo htmlspecialchars($item['specifications'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Materials</label>
                    <textarea name="materials" class="form-control" rows="3"><?php echo htmlspecialchars($item['materials'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Usage Instructions</label>
                    <textarea name="usage_instructions" class="form-control" rows="3"><?php echo htmlspecialchars($item['usage_instructions'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Review Proof</label>
                    <textarea name="review_proof" class="form-control" rows="3"><?php echo htmlspecialchars($item['review_proof'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                    <a class="btn btn-outline-secondary" href="marketplace.php">Back to Marketplace</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

