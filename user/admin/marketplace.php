<?php
include 'includes/header.php';
include '../shared/db_connect.php';

$createSql = "CREATE TABLE IF NOT EXISTS marketplace_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    listing_type VARCHAR(50) DEFAULT 'Product',
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    item_condition VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    phone_number VARCHAR(30) DEFAULT NULL,
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
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'listing_type'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN listing_type VARCHAR(50) DEFAULT 'Product'");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE 'phone_number'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE marketplace_items ADD COLUMN phone_number VARCHAR(30) DEFAULT NULL");
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $listing_type = trim($_POST['listing_type'] ?? 'Product');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $item_condition = trim($_POST['item_condition'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = 'active';

    if ($title === '' || $category === '' || $description === '' || $price === '' || $item_condition === '' || $contact === '') {
        $error = 'Title, description, price, condition, and contact are required.';
    } else {
        $imageCount = 0;
        if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
            foreach ($_FILES['images']['name'] as $name) {
                if (trim($name) !== '') {
                    $imageCount++;
                }
            }
        }
        if ($imageCount < 1) {
            $error = 'Please upload at least 1 image.';
        } else {
        $stmt = $conn->prepare("INSERT INTO marketplace_items (title, listing_type, category, price, item_condition, location, phone_number, contact, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $priceParam = $price === '' ? null : $price;
        $stmt->bind_param(
            "sssdsissss",
            $title,
            $listing_type,
            $category,
            $priceParam,
            $item_condition,
            $location,
            $phone_number,
            $contact,
            $description,
            $status
        );
        if ($stmt->execute()) {
            $itemId = $stmt->insert_id;
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
                        $imgStmt->bind_param("is", $itemId, $relativePath);
                        $imgStmt->execute();
                        $imgStmt->close();
                    }
                }
            }
            $success = 'Marketplace item created.';
        } else {
            $error = 'Failed to create marketplace item.';
        }
        $stmt->close();
        }
    }
}

$items = $conn->query("SELECT * FROM marketplace_items ORDER BY created_at DESC");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Marketplace</h2>
            <p class="text-muted mb-0">Buy, sell, and trade with fellow Zetech students. Find great deals on electronics, books, fashion, services, and accommodation.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Add Marketplace Item</div>
        <div class="card-body">
            <form method="POST" class="row g-3" enctype="multipart/form-data">
                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price (KES) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Listing Type *</label>
                    <select name="listing_type" class="form-select" required>
                        <option value="Product">Product</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category *</label>
                    <select name="category" class="form-select" required>
                        <option value="" selected disabled>Select category</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Books">Books</option>
                        <option value="Fashion">Fashion</option>
                        <option value="Accommodation">Accommodation</option>
                        <option value="Services">Services</option>
                        <option value="Sports">Sports</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g., Nairobi, Kenya">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" placeholder="e.g., +254712345678 or 0712345678">
                </div>
                <div class="col-12">
                    <label class="form-label">Condition *</label>
                    <input type="text" name="item_condition" class="form-control" placeholder="New, Used, Like new" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Contact *</label>
                    <input type="text" name="contact" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Product Images *</label>
                    <div class="border rounded-3 p-3 text-center bg-light" id="dropzone">
                        <div class="fw-semibold">Drag & drop images here</div>
                        <div class="text-muted small">or click to browse (JPG, PNG, WEBP). Multiple images allowed.</div>
                        <input type="file" name="images[]" id="imageInput" class="form-control mt-3" accept="image/*" multiple>
                        <div id="imageList" class="text-muted small mt-2"></div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Create Marketplace Item</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">All Marketplace Items</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($items && $items->num_rows > 0): ?>
                    <?php while ($row = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo $row['price'] !== null ? htmlspecialchars(number_format((float)$row['price'], 2)) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="marketplace_edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" href="marketplace_delete.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this marketplace item?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No marketplace items yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const dropzone = document.getElementById('dropzone');
    const imageInput = document.getElementById('imageInput');
    const imageList = document.getElementById('imageList');

    const renderFiles = () => {
        if (!imageInput.files.length) {
            imageList.textContent = '';
            return;
        }
        const names = Array.from(imageInput.files).map(file => file.name);
        imageList.textContent = names.join(', ');
    };

    dropzone.addEventListener('click', () => imageInput.click());
    imageInput.addEventListener('change', renderFiles);
    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('border-primary');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-primary'));
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('border-primary');
        if (event.dataTransfer.files.length) {
            imageInput.files = event.dataTransfer.files;
            renderFiles();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>

