<?php
include 'includes/header.php';
include '../shared/db_connect.php';
include '../shared/functions.php';

$categories = [];
$catResult = $conn->query("SELECT * FROM categories ORDER BY name");
if ($catResult) {
    $categories = $catResult->fetch_all(MYSQLI_ASSOC);
}

function validate_category_id(mysqli $conn, ?int $category_id, ?string &$error): ?int {
    if (!$category_id) { $error = 'Please select a category.'; return null; }
    $check = $conn->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    if (!$check) { $error = 'Unable to validate category.'; return null; }
    $check->bind_param("i", $category_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $check->close(); return $category_id; }
    $check->close();
    $error = 'Selected category does not exist.';
    return null;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = ''; $success = '';

if (!$id) { echo "Invalid post ID"; exit; }

$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$post) { echo "Post not found"; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $excerpt     = trim($_POST['excerpt']);
    $content     = trim($_POST['content']);
    $visible     = isset($_POST['visible']) ? 1 : 0;
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $category_id = validate_category_id($conn, $category_id, $error);

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "../assets/images/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
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
            $stmt->bind_param("sssssii", $title, $excerpt, $content, $imagePath, $visible, $category_id, $id);
        } else {
            $stmt = $conn->prepare("UPDATE posts SET title=?, excerpt=?, content=?, visible=?, category_id=? WHERE id=?");
            $stmt->bind_param("sssiii", $title, $excerpt, $content, $visible, $category_id, $id);
        }
        if ($stmt->execute()) {
            $success = 'Post updated successfully!';
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->bind_param("i", $id);
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

$postStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM posts");
$postStmt->execute(); $postCount = $postStmt->get_result()->fetch_assoc()['cnt']; $postStmt->close();
$commentStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments");
$commentStmt->execute(); $commentCount = $commentStmt->get_result()->fetch_assoc()['cnt']; $commentStmt->close();
$pendingStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments WHERE approved = 0");
$pendingStmt->execute(); $pendingCount = $pendingStmt->get_result()->fetch_assoc()['cnt']; $pendingStmt->close();
?>

<style>
/* ═══════════════════════════════════════════
   POST FORM — tokens from admin-header.php
   (edit_post uses same styles as add_post)
═══════════════════════════════════════════ */

.pf-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.75rem;
  animation: pfIn .45s ease both;
}
@keyframes pfIn {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}

.pf-breadcrumb {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .72rem;
  color: var(--ink-light);
  margin-bottom: .6rem;
}
.pf-breadcrumb a {
  color: var(--sky);
  text-decoration: none;
  font-weight: 500;
  transition: color var(--transition);
}
.pf-breadcrumb a:hover { color: var(--ink); }
.pf-breadcrumb svg { width: 10px; height: 10px; opacity: .5; }

.pf-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .6rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .24rem .72rem;
  border-radius: 2px;
  margin-bottom: .6rem;
}

.pf-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.06;
  letter-spacing: -.015em;
  margin-bottom: .25rem;
}
.pf-title em { font-style: italic; color: var(--ink-light); }

.pf-sub {
  font-size: .845rem;
  color: var(--ink-light);
  font-weight: 300;
}

/* post ID badge */
.pf-id-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .08em;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  background: var(--bg-warm);
  padding: .28rem .75rem;
  border-radius: 2px;
  align-self: flex-start;
  margin-top: .5rem;
  white-space: nowrap;
}

/* ── MINI STAT STRIP ── */
.pf-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .85rem;
  margin-bottom: 1.75rem;
  animation: pfIn .45s ease .05s both;
}

.pf-stat {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1rem 1.1rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  text-decoration: none;
  color: inherit;
  box-shadow: var(--shadow);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  position: relative;
  overflow: hidden;
}
.pf-stat:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}
.pf-stat::before {
  content: '';
  position: absolute;
  left: 0; top: 6px; bottom: 6px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.pf-stat--posts::before    { background: var(--sky); }
.pf-stat--comments::before { background: var(--green); }
.pf-stat--pending::before  { background: var(--accent); }

.pf-stat-icon {
  width: 34px; height: 34px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.pf-stat-icon svg { width: 15px; height: 15px; }
.pf-stat--posts    .pf-stat-icon { background: var(--sky-dim);    color: var(--sky); }
.pf-stat--comments .pf-stat-icon { background: var(--green-dim);  color: var(--green); }
.pf-stat--pending  .pf-stat-icon { background: var(--accent-dim); color: var(--accent); }

.pf-stat-label {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  line-height: 1;
  margin-bottom: .25rem;
}
.pf-stat-val {
  font-family: var(--font-serif);
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  letter-spacing: -.02em;
}

/* ── ALERTS ── */
.pf-alert {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  padding: .85rem 1rem;
  border-radius: 3px;
  font-size: .825rem;
  line-height: 1.55;
  margin-bottom: 1.4rem;
  animation: pfIn .3s ease both;
}
.pf-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .15rem; }
.pf-alert--error   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(176,48,48,.18); }
.pf-alert--success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(26,122,74,.18); }

/* ── FORM CARD ── */
.pf-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
  animation: pfIn .45s ease .1s both;
}
.pf-card-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  gap: .65rem;
}
.pf-card-head-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}
.pf-card-head svg { width: 14px; height: 14px; color: var(--ink-light); }
.pf-card-body { padding: 1.75rem; }

.pf-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.pf-field { margin-bottom: 1.4rem; }
.pf-field:last-child { margin-bottom: 0; }

.pf-label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .45rem;
}
.pf-label-hint {
  display: block;
  font-size: .72rem;
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
  color: var(--ink-light);
  margin-top: 2px;
}

.pf-input, .pf-select, .pf-textarea {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .7rem .9rem;
  font-size: .875rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
  -webkit-appearance: none;
}
.pf-input::placeholder,
.pf-textarea::placeholder { color: var(--ink-light); }
.pf-input:focus, .pf-select:focus, .pf-textarea:focus {
  border-color: var(--ink);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}

.pf-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237a7570'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .9rem center;
  background-color: var(--bg);
  padding-right: 2.5rem;
  cursor: pointer;
}

.pf-textarea {
  min-height: 240px;
  resize: vertical;
  line-height: 1.65;
}

.pf-hint {
  display: flex;
  align-items: flex-start;
  gap: .5rem;
  margin-top: .55rem;
  padding: .65rem .85rem;
  background: var(--sky-dim);
  border: 1px solid rgba(26,95,200,.15);
  border-radius: 3px;
  font-size: .78rem;
  color: var(--sky);
  line-height: 1.5;
}
.pf-hint svg { width: 12px; height: 12px; flex-shrink: 0; margin-top: .1rem; }

/* current image preview */
.pf-image-preview {
  background: var(--bg-warm);
  border: 1px solid var(--rule);
  border-radius: 3px;
  padding: 1.1rem;
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .65rem;
}
.pf-image-preview-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
}
.pf-image-preview img {
  max-width: 280px;
  max-height: 160px;
  object-fit: cover;
  border-radius: 2px;
  border: 1px solid var(--rule);
}

/* image upload */
.pf-upload-wrap { position: relative; }
.pf-upload-input { position: absolute; left: -9999px; }

.pf-upload-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  padding: 2rem 1.5rem;
  border: 1.5px dashed var(--rule);
  border-radius: 3px;
  cursor: pointer;
  transition: all var(--transition);
  background: var(--bg);
  color: var(--ink-light);
  text-align: center;
}
.pf-upload-label:hover {
  border-color: var(--ink-mid);
  background: var(--bg-warm);
  color: var(--ink-mid);
}
.pf-upload-icon  { font-size: 1.75rem; line-height: 1; }
.pf-upload-text  { font-size: .82rem; font-weight: 500; }
.pf-upload-hint  { font-size: .72rem; color: var(--ink-light); }

.pf-upload-name {
  display: none;
  margin-top: .55rem;
  font-size: .78rem;
  color: var(--green);
  font-weight: 500;
  padding: .4rem .8rem;
  background: var(--green-dim);
  border-radius: 2px;
  border: 1px solid rgba(26,122,74,.2);
}

/* toggle */
.pf-toggle-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
  background: var(--bg);
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  cursor: pointer;
  transition: all var(--transition);
  user-select: none;
}
.pf-toggle-wrap:hover {
  background: var(--bg-warm);
  border-color: var(--ink-mid);
}
.pf-toggle-info {}
.pf-toggle-title { font-size: .845rem; font-weight: 600; color: var(--ink); }
.pf-toggle-desc  { font-size: .72rem; color: var(--ink-light); margin-top: 1px; }

.pf-toggle-input { display: none; }
.pf-toggle-track {
  width: 44px; height: 26px;
  border-radius: 99px;
  border: 1.5px solid var(--rule);
  background: var(--bg-warmer);
  position: relative;
  flex-shrink: 0;
  transition: all var(--transition);
}
.pf-toggle-track::after {
  content: '';
  position: absolute;
  width: 18px; height: 18px;
  border-radius: 50%;
  background: var(--ink-light);
  top: 2px; left: 2px;
  transition: all var(--transition);
  box-shadow: 0 1px 3px rgba(24,22,15,.18);
}
.pf-toggle-input:checked ~ .pf-toggle-track {
  background: var(--green-dim);
  border-color: rgba(26,122,74,.3);
}
.pf-toggle-input:checked ~ .pf-toggle-track::after {
  background: var(--green);
  transform: translateX(18px);
}

.pf-divider {
  height: 1px;
  background: var(--rule-light);
  margin: 1.75rem 0;
}

.pf-actions {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
  align-items: center;
  padding-top: 1.5rem;
  border-top: 1px solid var(--rule-light);
}

.pf-btn-submit {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .75rem 1.75rem;
  background: var(--ink);
  color: #fff;
  border: none;
  border-radius: 3px;
  font-family: var(--font-sans);
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition);
  line-height: 1;
}
.pf-btn-submit:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.pf-btn-submit:active   { transform: scale(.98); }
.pf-btn-submit:disabled { background: var(--rule); color: var(--ink-light); cursor: not-allowed; transform: none; }
.pf-btn-submit svg { width: 13px; height: 13px; }

.pf-btn-cancel {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .75rem 1.25rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  color: var(--ink-mid);
  font-family: var(--font-sans);
  font-size: .875rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition);
  line-height: 1;
}
.pf-btn-cancel:hover { border-color: var(--ink-mid); background: var(--bg-warm); color: var(--ink); }
.pf-btn-cancel svg { width: 12px; height: 12px; }

@media (max-width: 640px) {
  .pf-stats { grid-template-columns: 1fr; }
  .pf-row-2 { grid-template-columns: 1fr; }
  .pf-actions { flex-direction: column; align-items: stretch; }
  .pf-btn-submit, .pf-btn-cancel { justify-content: center; width: 100%; }
}
</style>

<!-- ══ HEADING ══ -->
<div class="pf-heading">
  <div>
    <div class="pf-breadcrumb">
      <a href="dashboard.php">Dashboard</a>
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>Edit Post</span>
    </div>
    <div class="pf-eyebrow">Editing</div>
    <h1 class="pf-title">Edit<em> Post</em></h1>
    <p class="pf-sub">Update and refine your content.</p>
  </div>
  <div class="pf-id-badge">
    <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="12" height="12" rx="2"/><path d="M5 8h6M5 5.5h4M5 10.5h3" stroke-linecap="round"/></svg>
    Post #<?php echo (int)$id; ?>
  </div>
</div>

<!-- ══ MINI STATS ══ -->
<div class="pf-stats">
  <a href="dashboard.php" class="pf-stat pf-stat--posts">
    <div class="pf-stat-icon">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zm-9-4h6v2H9v-2zm0-4h6v2H9v-2zm0-4h4v2H9V8z"/></svg>
    </div>
    <div>
      <div class="pf-stat-label">Total Posts</div>
      <div class="pf-stat-val"><?php echo $postCount; ?></div>
    </div>
  </a>
  <a href="comments.php" class="pf-stat pf-stat--comments">
    <div class="pf-stat-icon">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
    </div>
    <div>
      <div class="pf-stat-label">Comments</div>
      <div class="pf-stat-val"><?php echo $commentCount; ?></div>
    </div>
  </a>
  <a href="comments.php?filter=pending" class="pf-stat pf-stat--pending">
    <div class="pf-stat-icon">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
    </div>
    <div>
      <div class="pf-stat-label">Pending</div>
      <div class="pf-stat-val"><?php echo $pendingCount; ?></div>
    </div>
  </a>
</div>

<!-- ══ ALERTS ══ -->
<?php if ($error): ?>
<div class="pf-alert pf-alert--error">
  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="pf-alert pf-alert--success">
  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
  <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<!-- ══ FORM CARD ══ -->
<div class="pf-card">
  <div class="pf-card-head">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
    <span class="pf-card-head-title">Post Details</span>
  </div>
  <div class="pf-card-body">
    <form method="post" enctype="multipart/form-data" id="postForm">

      <!-- Title & Category -->
      <div class="pf-row-2">
        <div class="pf-field">
          <label class="pf-label" for="title">
            Title
            <span class="pf-label-hint">Update the headline for your post</span>
          </label>
          <input type="text" id="title" name="title" class="pf-input"
                 value="<?php echo htmlspecialchars($post['title']); ?>" required>
        </div>
        <div class="pf-field">
          <label class="pf-label" for="category_id">
            Category
            <span class="pf-label-hint">Choose the most relevant category</span>
          </label>
          <select id="category_id" name="category_id" class="pf-select" required>
            <option value="">— Select a category —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat['id']); ?>"
                <?php echo ($post['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['name']); ?>
              </option>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
              <option value="" disabled>No categories available</option>
            <?php endif; ?>
          </select>
          <?php if (empty($categories)): ?>
          <div class="pf-hint">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            No categories found. Please create categories first.
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Excerpt -->
      <div class="pf-field">
        <label class="pf-label" for="excerpt">
          Excerpt
          <span class="pf-label-hint">A brief summary shown in post previews</span>
        </label>
        <textarea id="excerpt" name="excerpt" class="pf-textarea" style="min-height:100px;"
                  maxlength="500"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
      </div>

      <!-- Content -->
      <div class="pf-field">
        <label class="pf-label" for="content">
          Content
          <span class="pf-label-hint">Edit your post content</span>
        </label>
        <textarea id="content" name="content" class="pf-textarea"
                  required><?php echo htmlspecialchars($post['content']); ?></textarea>
      </div>

      <!-- Current image -->
      <?php if (!empty($post['image'])): ?>
      <div class="pf-field">
        <label class="pf-label">Current Featured Image</label>
        <div class="pf-image-preview">
          <span class="pf-image-preview-label">Existing image</span>
          <img src="../<?php echo htmlspecialchars($post['image']); ?>" alt="Current post image">
        </div>
      </div>
      <?php endif; ?>

      <!-- Image upload -->
      <div class="pf-field">
        <label class="pf-label">
          <?php echo !empty($post['image']) ? 'Replace Image' : 'Featured Image'; ?>
          <span class="pf-label-hint">
            <?php echo !empty($post['image']) ? 'Upload a new image to replace the current one (optional)' : 'Optional — upload a cover image for your post'; ?>
          </span>
        </label>
        <div class="pf-upload-wrap">
          <input type="file" name="image" id="imageUpload" class="pf-upload-input" accept="image/*">
          <label for="imageUpload" class="pf-upload-label" id="uploadLabel">
            <span class="pf-upload-icon">🖼</span>
            <span class="pf-upload-text">Click to upload or drag &amp; drop</span>
            <span class="pf-upload-hint">PNG, JPG, WEBP — max 5 MB</span>
          </label>
          <div class="pf-upload-name" id="uploadName"></div>
        </div>
      </div>

      <div class="pf-divider"></div>

      <!-- Visibility toggle -->
      <div class="pf-field">
        <label class="pf-label">Visibility</label>
        <label class="pf-toggle-wrap" for="visible">
          <div class="pf-toggle-info">
            <div class="pf-toggle-title">Publish Post</div>
            <div class="pf-toggle-desc">Make this post visible to your audience</div>
          </div>
          <input type="checkbox" id="visible" name="visible" class="pf-toggle-input"
                 <?php echo $post['visible'] ? 'checked' : ''; ?>>
          <span class="pf-toggle-track"></span>
        </label>
      </div>

      <!-- Actions -->
      <div class="pf-actions">
        <button type="submit" class="pf-btn-submit" id="submitBtn">
          Save Changes
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <a href="dashboard.php" class="pf-btn-cancel">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/></svg>
          Cancel
        </a>
      </div>

    </form>
  </div>
</div>

<script>
(function () {
  const input      = document.getElementById('imageUpload');
  const uploadName = document.getElementById('uploadName');
  const uploadLabel= document.getElementById('uploadLabel');

  input?.addEventListener('change', function () {
    const name = this.files[0]?.name;
    if (name) {
      uploadName.textContent = '✓ ' + name;
      uploadName.style.display = 'block';
      uploadLabel.style.borderColor = 'var(--green)';
      uploadLabel.style.borderStyle = 'solid';
    } else {
      uploadName.style.display = 'none';
      uploadLabel.style.borderColor = '';
      uploadLabel.style.borderStyle = '';
    }
  });

  ['dragenter','dragover'].forEach(ev => {
    uploadLabel?.addEventListener(ev, e => {
      e.preventDefault();
      uploadLabel.style.borderColor = 'var(--ink)';
      uploadLabel.style.borderStyle = 'solid';
      uploadLabel.style.background  = 'var(--bg-warm)';
    });
  });
  ['dragleave','drop'].forEach(ev => {
    uploadLabel?.addEventListener(ev, e => {
      e.preventDefault();
      uploadLabel.style.borderColor = '';
      uploadLabel.style.borderStyle = '';
      uploadLabel.style.background  = '';
    });
  });

  document.getElementById('postForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<svg style="animation:spin .7s linear infinite" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9"/></svg> Saving…';
    }
  });

  const s = document.createElement('style');
  s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
})();
</script>

<?php include 'includes/footer.php'; ?>
