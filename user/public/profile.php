<?php
session_start();
require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        switch ($_POST['action']) {
            case 'update_profile':
                $username = trim($_POST['username']);
                $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                $bio = trim($_POST['bio']);
                $website = filter_var($_POST['website'], FILTER_VALIDATE_URL) ?: '';
                $twitter  = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['social_twitter']);
                $facebook = preg_replace('/[^a-zA-Z0-9.]/', '', $_POST['social_facebook']);
                $linkedin = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['social_linkedin']);

                if (strlen($username) < 3) {
                    $error = 'Username must be at least 3 characters';
                } elseif (!$email) {
                    $error = 'Invalid email address';
                } else {
                    $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                    $check->bind_param("ssi", $username, $email, $user_id);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $error = 'Username or email already taken';
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, bio = ?, website = ?, social_twitter = ?, social_facebook = ?, social_linkedin = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("sssssssi", $username, $email, $bio, $website, $twitter, $facebook, $linkedin, $user_id);
                        if ($stmt->execute()) {
                            $_SESSION['username'] = $username;
                            $message = 'Profile updated successfully!';
                        } else {
                            $error = 'Failed to update profile';
                        }
                    }
                }
                break;

            case 'update_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);

                    if (!in_array($mime, $allowed)) {
                        $error = 'Invalid image format. Use JPG, PNG, GIF, or WebP.';
                    } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                        $error = 'Image too large. Max 2MB allowed.';
                    } else {
                        $ext      = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                        $upload_path = 'uploads/avatars/' . $filename;

                        if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0755, true);

                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                            $stmt->bind_param("si", $upload_path, $user_id);
                            $stmt->execute();
                            $message = 'Avatar updated!';
                        } else {
                            $error = 'Failed to upload image';
                        }
                    }
                }
                break;

            case 'update_password':
                $current = $_POST['current_password'];
                $new     = $_POST['new_password'];
                $confirm = $_POST['confirm_password'];

                if ($new !== $confirm) {
                    $error = 'New passwords do not match';
                } elseif (strlen($new) < 8) {
                    $error = 'Password must be at least 8 characters';
                } else {
                    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $u = $stmt->get_result()->fetch_assoc();

                    if (!password_verify($current, $u['password_hash'])) {
                        $error = 'Current password is incorrect';
                    } else {
                        $hash = password_hash($new, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->bind_param("si", $hash, $user_id);
                        $stmt->execute();
                        $message = 'Password changed successfully!';
                    }
                }
                break;

            case 'delete_account':
                if ($_POST['confirm_delete'] === 'DELETE') {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    session_destroy();
                    header('Location: index.php?deleted=1');
                    exit;
                } else {
                    $error = 'Please type DELETE to confirm';
                }
                break;
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get stats
$bookmarks_count = $conn->query("SELECT COUNT(*) as c FROM bookmarks WHERE user_id = $user_id")->fetch_assoc()['c'];
$comments_count  = $conn->query("SELECT COUNT(*) as c FROM comments  WHERE user_id = $user_id")->fetch_assoc()['c'];
$followers_count = $conn->query("SELECT COUNT(*) as c FROM follows   WHERE following_id = $user_id")->fetch_assoc()['c'];

$csrf_token = generate_csrf_token();

$page_title = 'My Profile - ' . SITE_NAME;
include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════ */
:root {
  --bg:           #f5f2ed;
  --bg-warm:      #ede9e1;
  --bg-warmer:    #e5e0d6;
  --ink:          #18160f;
  --ink-mid:      #4a4540;
  --ink-light:    #7a7570;
  --rule:         #d4cfc7;
  --rule-light:   #e8e4dc;
  --accent:       #c8641a;
  --accent-dim:   #f0dece;
  --sky:          #1a5fc8;
  --sky-dim:      #dce9f8;
  --green:        #1a7a4a;
  --green-dim:    #d2edd9;
  --amber:        #b8860b;
  --amber-dim:    #f5edcc;
  --red:          #b03030;
  --red-dim:      #f8e0e0;
  --purple:       #6b3fa0;
  --purple-dim:   #ede6f8;

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;

  --max-w:    1100px;
  --gutter:   1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.profile-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO
═══════════════════════════════════════════ */
.profile-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}
.profile-hero::before {
  content: 'Profile.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(4.5rem, 14vw, 11rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.035);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}
.profile-hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}
.hero-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  position: relative;
  z-index: 1;
  animation: slideUp .6s ease both;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.4);
  padding: .28rem .8rem;
  border-radius: 2px;
  margin-bottom: 1rem;
}
.hero-avatar-row {
  display: flex;
  align-items: flex-end;
  gap: 1.5rem;
  flex-wrap: wrap;
}
.hero-avatar {
  width: 80px; height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(255,255,255,.15);
  flex-shrink: 0;
}
.hero-avatar-placeholder {
  width: 80px; height: 80px;
  border-radius: 50%;
  background: rgba(255,255,255,.08);
  border: 3px solid rgba(255,255,255,.15);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif);
  font-size: 2rem;
  font-weight: 700;
  color: rgba(255,255,255,.4);
  flex-shrink: 0;
}
.hero-text {}
.hero-headline {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 4.5vw, 3rem);
  font-weight: 700;
  line-height: 1.1;
  color: #fff;
  margin-bottom: .3rem;
}
.hero-headline em { font-style: italic; color: rgba(255,255,255,.45); }
.hero-sub {
  font-size: .85rem;
  color: rgba(255,255,255,.4);
  font-weight: 300;
}
.hero-stats {
  display: flex;
  gap: 2.5rem;
  flex-wrap: wrap;
  margin-top: 2rem;
}
.hero-stat-num {
  font-family: var(--font-serif);
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
  display: block;
}
.hero-stat-label {
  font-size: .65rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.35);
  display: block;
  margin-top: .25rem;
}

/* ═══════════════════════════════════════════
   TOOLBAR / TABS
═══════════════════════════════════════════ */
.profile-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.profile-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--gutter);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.profile-tabs {
  display: flex;
  overflow-x: auto;
  scrollbar-width: none;
}
.profile-tabs::-webkit-scrollbar { display: none; }
.profile-tab {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .85rem 1.1rem;
  font-size: .82rem;
  font-weight: 500;
  font-family: var(--font-sans);
  color: var(--ink-light);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: color var(--transition), border-color var(--transition);
  margin-bottom: -1px;
}
.profile-tab:hover { color: var(--ink-mid); }
.profile-tab.active {
  color: var(--ink);
  border-bottom-color: var(--ink);
  font-weight: 600;
}
.profile-tab svg { width: 13px; height: 13px; }

/* ═══════════════════════════════════════════
   BODY
═══════════════════════════════════════════ */
.profile-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 2rem;
  align-items: start;
}
@media (max-width: 768px) {
  .profile-body { grid-template-columns: 1fr; }
  .profile-sidebar { position: static; top: auto; }
  .profile-body > main { order: 1; }
  .profile-body > .profile-sidebar { order: 2; }
}
@media (max-width: 640px) {
  .profile-hero { padding: 2.5rem var(--gutter) 2rem; }
  .hero-avatar-row { flex-direction: column; align-items: flex-start; }
  .hero-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .hero-stat-num { font-size: 1.5rem; }
  .profile-body { gap: 1.25rem; padding: 1.5rem var(--gutter) 0; }
  .profile-sidebar { padding: 1.25rem; }
  .sidebar-avatar { align-items: flex-start; text-align: left; }
  .profile-toolbar-inner { padding: 0 var(--gutter); }
  .profile-tab { padding: .65rem .85rem; font-size: .78rem; }
  .form-card { padding: 1.25rem; }
  .form-row { grid-template-columns: 1fr; }
  .btn { width: 100%; justify-content: center; }
  .section-sep { margin: 1rem 0 .75rem; }
}
@media (max-width: 420px) {
  .hero-stats { grid-template-columns: 1fr; }
  .hero-sub { max-width: 100%; }
}

/* ── Flash ── */
.flash-wrap {
  max-width: var(--max-w);
  margin: 1.5rem auto 0;
  padding: 0 var(--gutter);
}
.flash-msg {
  padding: .85rem 1.25rem;
  border-radius: 4px;
  font-size: .875rem;
  font-weight: 500;
  border-left: 3px solid;
  font-family: var(--font-sans);
}
.flash-msg.success { background: var(--green-dim); color: var(--green); border-color: var(--green); }
.flash-msg.error   { background: var(--red-dim);   color: var(--red);   border-color: var(--red); }

/* ── Sidebar ── */
.profile-sidebar {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  padding: 1.75rem;
  position: sticky;
  top: 4.5rem;
}
.sidebar-avatar {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--rule-light);
  margin-bottom: 1.5rem;
}
.sidebar-avatar img,
.sidebar-avatar-placeholder {
  width: 88px; height: 88px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 1rem;
  border: 3px solid var(--rule-light);
}
.sidebar-avatar-placeholder {
  background: var(--bg-warm);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif);
  font-size: 2.2rem;
  font-weight: 700;
  color: var(--ink-light);
}
.upload-label {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: .04em;
  color: var(--ink-light);
  border: 1.5px dashed var(--rule);
  border-radius: 3px;
  padding: .35rem .8rem;
  cursor: pointer;
  transition: all var(--transition);
  font-family: var(--font-sans);
}
.upload-label:hover { border-color: var(--ink-mid); color: var(--ink-mid); background: var(--bg-warm); }
.upload-label input { display: none; }
.upload-label svg { width: 12px; height: 12px; }
.sidebar-name {
  font-family: var(--font-serif);
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--ink);
  margin-top: .75rem;
  margin-bottom: .25rem;
}
.sidebar-role {
  display: inline-block;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  background: var(--ink);
  color: #fff;
  padding: .22rem .7rem;
  border-radius: 2px;
}
.sidebar-since {
  font-size: .78rem;
  color: var(--ink-light);
  margin-top: .4rem;
}

/* sidebar quick-links */
.sidebar-links { display: flex; flex-direction: column; gap: .25rem; }
.sidebar-link {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .6rem .75rem;
  font-size: .82rem;
  font-weight: 500;
  color: var(--ink-mid);
  text-decoration: none;
  border-radius: 4px;
  transition: all var(--transition);
  font-family: var(--font-sans);
}
.sidebar-link:hover { background: var(--bg-warm); color: var(--ink); }
.sidebar-link svg { width: 13px; height: 13px; opacity: .6; flex-shrink: 0; }

/* ── Tab pane ── */
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ── Cards ── */
.form-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  padding: 1.75rem;
  margin-bottom: 1.5rem;
}
.card-heading {
  display: flex;
  align-items: center;
  gap: .6rem;
  font-family: var(--font-serif);
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--ink);
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--rule-light);
  margin-bottom: 1.5rem;
}
.card-heading svg { width: 15px; height: 15px; opacity: .5; }

/* danger card */
.form-card.danger {
  border-color: rgba(176,48,48,.3);
  background: #fdf8f8;
}
.form-card.danger .card-heading { color: var(--red); }

/* ── Form elements ── */
.form-group { margin-bottom: 1.25rem; }
.form-label {
  display: block;
  font-size: .78rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .5rem;
  font-family: var(--font-sans);
}
.form-input {
  width: 100%;
  padding: .65rem .9rem;
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  font-size: .9rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  transition: border-color var(--transition), background var(--transition);
}
.form-input:focus {
  outline: none;
  border-color: var(--ink);
  background: #fff;
}
.form-input.textarea {
  min-height: 100px;
  resize: vertical;
}
.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}
@media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }
.form-hint {
  font-size: .75rem;
  color: var(--ink-light);
  margin-top: .3rem;
}

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 600;
  padding: .6rem 1.25rem;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  transition: all var(--transition);
  text-decoration: none;
  line-height: 1;
}
.btn:active { transform: scale(.97); }
.btn svg { width: 12px; height: 12px; }

.btn-primary {
  background: var(--ink);
  color: #fff;
}
.btn-primary:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.btn-danger {
  background: var(--red);
  color: #fff;
}
.btn-danger:hover {
  background: #8f2020;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(176,48,48,.25);
}
.btn-ghost {
  background: transparent;
  color: var(--ink-mid);
  border: 1.5px solid var(--rule);
}
.btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); }

/* section divider */
.section-sep {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 600;
  color: var(--ink-light);
  margin: 1.75rem 0 1.25rem;
  padding-bottom: .5rem;
  border-bottom: 1px solid var(--rule-light);
  display: flex;
  align-items: center;
  gap: .5rem;
}
.section-sep svg { width: 13px; height: 13px; opacity: .5; }
</style>

<div class="profile-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="profile-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow">Your account</span>
      <div class="hero-avatar-row">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="" class="hero-avatar">
        <?php else: ?>
          <div class="hero-avatar-placeholder"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
        <?php endif; ?>
        <div class="hero-text">
          <h1 class="hero-headline"><?= htmlspecialchars($user['username']) ?><br><em>Settings</em></h1>
          <p class="hero-sub">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $bookmarks_count ?></span>
          <span class="hero-stat-label">Bookmarks</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $comments_count ?></span>
          <span class="hero-stat-label">Comments</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $followers_count ?></span>
          <span class="hero-stat-label">Followers</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ STICKY TABS ══════════ -->
  <div class="profile-toolbar">
    <div class="profile-toolbar-inner">
      <nav class="profile-tabs">
        <button class="profile-tab active" data-tab="profile">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="4.5" r="2.5"/><path d="M2 11.5c0-2.5 2.2-4.5 5-4.5s5 2 5 4.5" stroke-linecap="round"/></svg>
          Profile
        </button>
        <button class="profile-tab" data-tab="security">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="8" height="6" rx="1.5"/><path d="M5 6V4.5a2 2 0 014 0V6" stroke-linecap="round"/></svg>
          Security
        </button>
        <button class="profile-tab" data-tab="danger">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 2l5.5 9.5H1.5L7 2z" stroke-linejoin="round"/><path d="M7 6v2.5M7 10h.01" stroke-linecap="round"/></svg>
          Account
        </button>
      </nav>
    </div>
  </div>

  <!-- flash messages -->
  <?php if ($message || $error): ?>
  <div class="flash-wrap">
    <?php if ($message): ?>
      <div class="flash-msg success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="flash-msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ══════════ BODY ══════════ -->
  <div class="profile-body">

    <!-- ── Sidebar ── -->
    <aside class="profile-sidebar">
      <div class="sidebar-avatar">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
        <?php else: ?>
          <div class="sidebar-avatar-placeholder"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="update_avatar">
          <label class="upload-label">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="7" r="5.5"/><path d="M7 4.5v5M4.5 7h5" stroke-linecap="round"/></svg>
            Change photo
            <input type="file" name="avatar" accept="image/*" onchange="this.form.submit()">
          </label>
        </form>

        <div class="sidebar-name"><?= htmlspecialchars($user['username']) ?></div>
        <span class="sidebar-role"><?= htmlspecialchars($user['role']) ?></span>
        <div class="sidebar-since">Since <?= date('F Y', strtotime($user['created_at'])) ?></div>
      </div>

      <div class="sidebar-links">
        <a href="bookmarks.php" class="sidebar-link">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 2h8v10l-4-2.5L3 12V2z" stroke-linejoin="round"/></svg>
          My Bookmarks
        </a>
        <a href="my-comments.php" class="sidebar-link">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="10" height="8" rx="1.5"/><path d="M5 12l2-2h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          My Comments
        </a>
        <a href="notifications.php" class="sidebar-link">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 1.5a4 4 0 014 4v3l1 1.5H2L3 8.5v-3a4 4 0 014-4z"/><path d="M5.5 11.5a1.5 1.5 0 003 0" stroke-linecap="round"/></svg>
          Notifications
        </a>
      </div>
    </aside>

    <!-- ── Main panes ── -->
    <main>

      <!-- Profile Tab -->
      <div class="tab-pane active" id="tab-profile">
        <form method="POST" class="form-card">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="update_profile">

          <div class="card-heading">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="4.5" r="2.5"/><path d="M2 11.5c0-2.5 2.2-4.5 5-4.5s5 2 5 4.5" stroke-linecap="round"/></svg>
            Personal Information
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" required minlength="3">
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-input textarea" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Website</label>
            <input type="url" name="website" class="form-input" value="<?= htmlspecialchars($user['website'] ?? '') ?>" placeholder="https://yourwebsite.com">
          </div>

          <div class="section-sep">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="3" cy="7" r="1.5"/><circle cx="11" cy="7" r="1.5"/><path d="M4.5 7h5" stroke-linecap="round"/></svg>
            Social Links
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Twitter / X</label>
              <input type="text" name="social_twitter" class="form-input" value="<?= htmlspecialchars($user['social_twitter'] ?? '') ?>" placeholder="username">
            </div>
            <div class="form-group">
              <label class="form-label">Facebook</label>
              <input type="text" name="social_facebook" class="form-input" value="<?= htmlspecialchars($user['social_facebook'] ?? '') ?>" placeholder="username">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">LinkedIn</label>
            <input type="text" name="social_linkedin" class="form-input" value="<?= htmlspecialchars($user['social_linkedin'] ?? '') ?>" placeholder="username">
          </div>

          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 7l3 3L12 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Save Changes
          </button>
        </form>
      </div>

      <!-- Security Tab -->
      <div class="tab-pane" id="tab-security">
        <form method="POST" class="form-card">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="update_password">

          <div class="card-heading">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="8" height="6" rx="1.5"/><path d="M5 6V4.5a2 2 0 014 0V6" stroke-linecap="round"/></svg>
            Change Password
          </div>

          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-input" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-input" required minlength="8">
              <div class="form-hint">Minimum 8 characters</div>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-input" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="8" height="6" rx="1.5"/><path d="M5 6V4.5a2 2 0 014 0V6" stroke-linecap="round"/></svg>
            Update Password
          </button>
        </form>
      </div>

      <!-- Danger Zone Tab -->
      <div class="tab-pane" id="tab-danger">
        <form method="POST" class="form-card danger" onsubmit="return confirm('Are you sure? This cannot be undone!');">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="delete_account">

          <div class="card-heading">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 2l5.5 9.5H1.5L7 2z" stroke-linejoin="round"/><path d="M7 6v2.5M7 10h.01" stroke-linecap="round"/></svg>
            Danger Zone
          </div>

          <p style="font-size:.875rem; color:var(--ink-mid); line-height:1.7; margin-bottom:1.5rem;">
            Deleting your account will permanently remove all your data, including bookmarks, comments, and profile information. This action <strong>cannot be undone</strong>.
          </p>

          <div class="form-group">
            <label class="form-label">Type <strong>DELETE</strong> to confirm</label>
            <input type="text" name="confirm_delete" class="form-input" placeholder="DELETE" required>
          </div>

          <button type="submit" class="btn btn-danger">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 4h10M5 4V2.5h4V4M5.5 6.5v4M8.5 6.5v4M3 4l.8 7.5h6.4L11 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Delete My Account
          </button>
        </form>
      </div>

    </main>
  </div><!-- /.profile-body -->
</div><!-- /.profile-page -->

<script>
document.querySelectorAll('.profile-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
  });
});
</script>

<?php include '../shared/footer.php'; ?>
