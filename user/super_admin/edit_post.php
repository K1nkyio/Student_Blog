<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$topbar_title_override = 'Edit Post';
$topbar_subtitle_override = 'Update and publish content';

include 'includes/header.php';
include '../shared/db_connect.php';
include '../shared/functions.php';

$categories = [];
$catResult = $conn->query("SELECT * FROM categories ORDER BY name");
if ($catResult) {
    $categories = $catResult->fetch_all(MYSQLI_ASSOC);
}

function validate_category_id(mysqli $conn, ?int $category_id, ?string &$error): ?int {
    if (!$category_id) {
        $error = 'Please select a category.';
        return null;
    }
    $check = $conn->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    if (!$check) {
        $error = 'Unable to validate category.';
        return null;
    }
    $check->bind_param('i', $category_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        return $category_id;
    }
    $check->close();
    $error = 'Selected category does not exist.';
    return null;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';
$post = null;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$post) {
        $error = 'Post not found.';
    }
} else {
    $error = 'Invalid post ID.';
}

if ($post && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $excerpt     = trim($_POST['excerpt'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $visible     = isset($_POST['visible']) ? 1 : 0;
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $category_id = validate_category_id($conn, $category_id, $error);

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "../assets/images/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $fileName   = time() . '_' . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = 'assets/images/' . $fileName;
        } else {
            $error = 'Image upload failed.';
        }
    }

    if (!$error) {
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE posts SET title=?, excerpt=?, content=?, image=?, visible=?, category_id=? WHERE id=?");
            $stmt->bind_param('sssssii', $title, $excerpt, $content, $imagePath, $visible, $category_id, $id);
        } else {
            $stmt = $conn->prepare("UPDATE posts SET title=?, excerpt=?, content=?, visible=?, category_id=? WHERE id=?");
            $stmt->bind_param('sssiii', $title, $excerpt, $content, $visible, $category_id, $id);
        }
        if ($stmt->execute()) {
            $success = 'Post updated successfully.';
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $post = $stmt->get_result()->fetch_assoc();
            if (($post['review_status'] ?? 'approved') === 'approved') {
                notify_users_about_post($id, $title, $post['author_id'], true);
            }
            $stmt->close();
        } else {
            $error = $stmt->error;
        }
    }
}

$postCount = 0;
$commentCount = 0;
$pendingCount = 0;
$postStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM posts");
if ($postStmt) {
    $postStmt->execute();
    $postCount = (int)($postStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $postStmt->close();
}
$commentStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments");
if ($commentStmt) {
    $commentStmt->execute();
    $commentCount = (int)($commentStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $commentStmt->close();
}
$pendingStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments WHERE approved = 0");
if ($pendingStmt) {
    $pendingStmt->execute();
    $pendingCount = (int)($pendingStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $pendingStmt->close();
}
?>

<style>
.sa-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.75rem 1.5rem 3rem;
}
.sa-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}
.sa-kicker {
  font-size: .65rem;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .25rem .7rem;
  border-radius: 2px;
  display: inline-block;
  margin-bottom: .5rem;
}
.sa-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  margin: 0 0 .35rem;
  color: var(--ink);
}
.sa-sub {
  color: var(--ink-light);
  font-size: .9rem;
}
.sa-id {
  font-size: .7rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .35rem .7rem;
  border-radius: 2px;
  background: var(--bg-warm);
}

.sa-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: .9rem;
  margin-bottom: 1.5rem;
}
.sa-stat {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1rem;
  text-decoration: none;
  color: inherit;
  box-shadow: var(--shadow);
  transition: box-shadow var(--transition), transform var(--transition);
}
.sa-stat:hover {
  transform: translateY(-2px);
  box-shadow: 0 3px 10px rgba(0,0,0,.08);
}
.sa-stat-label {
  font-size: .65rem;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .35rem;
}
.sa-stat-val {
  font-family: var(--font-serif);
  font-size: 1.6rem;
  color: var(--ink);
}

.sa-alert {
  padding: .8rem 1rem;
  border-radius: 3px;
  margin-bottom: 1rem;
  font-size: .9rem;
}
.sa-alert-error {
  background: var(--red-dim);
  color: var(--red);
  border: 1px solid rgba(176,48,48,.2);
}
.sa-alert-success {
  background: var(--green-dim);
  color: var(--green);
  border: 1px solid rgba(26,122,74,.2);
}

.sa-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.sa-card-head {
  padding: 1rem 1.4rem;
  border-bottom: 1px solid var(--rule);
  background: var(--bg-warm);
  font-weight: 700;
  font-family: var(--font-serif);
}
.sa-card-body {
  padding: 1.5rem;
}
.sa-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.2rem;
}
.sa-field { margin-bottom: 1.2rem; }
.sa-label {
  display: block;
  font-size: .7rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .4rem;
}
.sa-input, .sa-select, .sa-textarea {
  width: 100%;
  padding: .7rem .9rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: var(--bg);
  font-size: .9rem;
  font-family: var(--font-sans);
  color: var(--ink);
}
.sa-textarea { min-height: 140px; resize: vertical; line-height: 1.6; }
.sa-hint {
  font-size: .75rem;
  color: var(--ink-light);
  margin-top: .3rem;
}
.sa-image-preview {
  background: var(--bg-warm);
  border: 1px solid var(--rule);
  border-radius: 3px;
  padding: 1rem;
  text-align: center;
}
.sa-image-preview img {
  max-width: 260px;
  max-height: 160px;
  object-fit: cover;
  border-radius: 2px;
  border: 1px solid var(--rule);
}
.sa-actions {
  display: flex;
  gap: .8rem;
  flex-wrap: wrap;
  margin-top: 1.5rem;
  padding-top: 1.2rem;
  border-top: 1px solid var(--rule-light);
}
.sa-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  padding: .7rem 1.4rem;
  font-size: .9rem;
  border-radius: 3px;
  border: 1.5px solid transparent;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition);
}
.sa-btn-primary {
  background: var(--ink);
  color: #fff;
}
.sa-btn-primary:hover { background: #2c2a22; }
.sa-btn-ghost {
  background: transparent;
  border-color: var(--rule);
  color: var(--ink-mid);
}
.sa-btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); }

@media (max-width: 720px) {
  .sa-grid-2 { grid-template-columns: 1fr; }
  .sa-actions { flex-direction: column; align-items: stretch; }
}
</style>

<div class="sa-wrap">
  <div class="sa-head">
    <div>
      <span class="sa-kicker">Post editor</span>
      <h1 class="sa-title">Edit Post</h1>
      <p class="sa-sub">Update content, images, and visibility for this post.</p>
    </div>
    <div class="sa-id">Post #<?php echo (int)$id; ?></div>
  </div>

  <div class="sa-stats">
    <a class="sa-stat" href="post_management.php">
      <div class="sa-stat-label">Total Posts</div>
      <div class="sa-stat-val"><?php echo $postCount; ?></div>
    </a>
    <a class="sa-stat" href="comments.php">
      <div class="sa-stat-label">Comments</div>
      <div class="sa-stat-val"><?php echo $commentCount; ?></div>
    </a>
    <a class="sa-stat" href="comments.php?status=pending">
      <div class="sa-stat-label">Pending</div>
      <div class="sa-stat-val"><?php echo $pendingCount; ?></div>
    </a>
  </div>

  <?php if ($error): ?>
    <div class="sa-alert sa-alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="sa-alert sa-alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if ($post): ?>
  <div class="sa-card">
    <div class="sa-card-head">Post Details</div>
    <div class="sa-card-body">
      <form method="post" enctype="multipart/form-data">
        <div class="sa-grid-2">
          <div class="sa-field">
            <label class="sa-label" for="title">Title</label>
            <input id="title" name="title" type="text" class="sa-input"
                   value="<?php echo htmlspecialchars($post['title']); ?>" required>
          </div>
          <div class="sa-field">
            <label class="sa-label" for="category_id">Category</label>
            <select id="category_id" name="category_id" class="sa-select" required>
              <option value="">Select a category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>" <?php echo ($post['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($categories)): ?>
              <div class="sa-hint">No categories found. Create categories first.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="sa-field">
          <label class="sa-label" for="excerpt">Excerpt</label>
          <textarea id="excerpt" name="excerpt" class="sa-textarea" maxlength="500"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
          <div class="sa-hint">Short summary shown in post previews.</div>
        </div>

        <div class="sa-field">
          <label class="sa-label" for="content">Content</label>
          <textarea id="content" name="content" class="sa-textarea" required><?php echo htmlspecialchars($post['content']); ?></textarea>
        </div>

        <?php if (!empty($post['image'])): ?>
        <div class="sa-field">
          <label class="sa-label">Current Image</label>
          <div class="sa-image-preview">
            <img src="../<?php echo htmlspecialchars($post['image']); ?>" alt="Current post image">
          </div>
        </div>
        <?php endif; ?>

        <div class="sa-field">
          <label class="sa-label" for="image">Replace Image</label>
          <input id="image" name="image" type="file" class="sa-input" accept="image/*">
          <div class="sa-hint">Optional. Upload a new image to replace the current one.</div>
        </div>

        <div class="sa-field">
          <label class="sa-label" for="visible">Visibility</label>
          <label style="display:flex;align-items:center;gap:.6rem;">
            <input id="visible" name="visible" type="checkbox" <?php echo $post['visible'] ? 'checked' : ''; ?>>
            <span>Publish this post</span>
          </label>
        </div>

        <div class="sa-actions">
          <button type="submit" class="sa-btn sa-btn-primary">Save Changes</button>
          <a href="post_management.php" class="sa-btn sa-btn-ghost">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>
    <div class="sa-alert sa-alert-error">Unable to load this post. Return to post management.</div>
    <a href="post_management.php" class="sa-btn sa-btn-ghost">Back to Posts</a>
  <?php endif; ?>
</div>

<?php include '../admin/includes/footer.php'; ?>
