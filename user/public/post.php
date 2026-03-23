<?php
// ============================================================
// PHP LOGIC — unchanged from original
// ============================================================
include '../shared/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

$post = null;
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = sanitize_input($_GET['slug']);
    $stmt = $conn->prepare("SELECT * FROM posts WHERE slug = ? AND COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved' LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ? AND COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved' LIMIT 1");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    echo "<div class='alert alert-error'>Invalid post identifier.</div>";
    include '../shared/footer.php';
    exit;
}

if (!$post) {
    echo "<div class='alert alert-error'>Post not found.</div>";
    include '../shared/footer.php';
    exit;
}

$post_id = (int)$post['id'];

if (!isset($_SESSION['viewed_posts'])) $_SESSION['viewed_posts'] = [];
if (!in_array($post_id, $_SESSION['viewed_posts'])) {
    $inc = $conn->prepare("UPDATE posts SET view_count = view_count + 1 WHERE id = ?");
    $inc->bind_param("i", $post_id);
    $inc->execute();
    $inc->close();
    $_SESSION['viewed_posts'][] = $post_id;
}

$likesQuery = $conn->prepare("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
$likesQuery->bind_param("i", $post_id);
$likesQuery->execute();
$likesResult = $likesQuery->get_result()->fetch_assoc();
$likesQuery->close();
$likes = $likesResult['total'];

$user_ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$userLiked = false;
$likeCheck = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND ip_address = ?");
$likeCheck->bind_param("is", $post_id, $user_ip);
$likeCheck->execute();
if ($likeCheck->get_result()->num_rows > 0) $userLiked = true;
$likeCheck->close();

$commentsQuery = $conn->prepare("SELECT * FROM comments WHERE post_id = ? ORDER BY created_at DESC");
$commentsQuery->bind_param("i", $post_id);
$commentsQuery->execute();
$comments = $commentsQuery->get_result();
$commentsCount = $comments->num_rows;

$relatedQuery = $conn->prepare("SELECT id, title, image, content, created_at FROM posts WHERE id != ? AND COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved' ORDER BY created_at DESC LIMIT 6");
$relatedQuery->bind_param("i", $post_id);
$relatedQuery->execute();
$relatedPosts = $relatedQuery->get_result();

if (is_logged_in()) {
    $user_id = get_user_id();
    $mark_read_stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND reference_type = 'post' AND reference_id = ? AND read_at IS NULL");
    $mark_read_stmt->bind_param("ii", $user_id, $post_id);
    $mark_read_stmt->execute();
    $mark_read_stmt->close();
}

include '../shared/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Libre+Franklin:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ─── TOKENS ────────────────────────────────────────────── */
:root {
    --ink:        #18110c;
    --ink-soft:   #3d3028;
    --ink-muted:  #9a8a7e;
    --ink-faint:  #c5b8b0;
    --page:       #f9f6f1;
    --paper:      #ffffff;
    --rule:       #e2d9d0;
    --rule-soft:  #ede8e2;
    --accent:     #b8521e;
    --accent-lt:  #f5e8e0;
    --accent-warm:#d9916a;

    --max-w:      1360px;
    --gutter:     1.5rem;
    --col-text:   var(--max-w);
    --col-wide:   var(--max-w);

    --ease-out-expo: cubic-bezier(.16,1,.3,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
    font-family: 'Libre Franklin', sans-serif;
    background: var(--page);
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
    min-height: 100vh;
}

a { color: inherit; text-decoration: none; }
img { display: block; max-width: 100%; }
button { font-family: inherit; cursor: pointer; }


/* ══════════════════════════════════════════════════════════
   HERO — full-bleed image, no card, no border
══════════════════════════════════════════════════════════ */
.hero {
    position: relative;
    width: 100%;
    max-height: 68vh;
    min-height: 340px;
    overflow: hidden;
    animation: fadeIn .7s var(--ease-out-expo) both;
}

.hero-img {
    width: 100%;
    height: 68vh;
    min-height: 340px;
    object-fit: cover;
    display: block;
    transform: scale(1.03);
    animation: heroZoom 8s ease-out both;
}

@keyframes heroZoom {
    from { transform: scale(1.06); }
    to   { transform: scale(1.0); }
}

/* Subtle gradient at bottom to bleed into page */
.hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to bottom,
        transparent 50%,
        rgba(249,246,241,.85) 90%,
        var(--page) 100%
    );
    pointer-events: none;
}


/* ══════════════════════════════════════════════════════════
   ARTICLE — open, no box
══════════════════════════════════════════════════════════ */
.article-wrap {
    max-width: var(--col-text);
    margin: 0 auto;
    padding: 0 var(--gutter);
}

/* Category label */
.post-eyebrow {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-top: 2.75rem;
    margin-bottom: 1.25rem;
    animation: riseIn .55s .05s var(--ease-out-expo) both;
}

.eyebrow-rule {
    width: 32px;
    height: 2px;
    background: var(--accent);
    border-radius: 2px;
}

.eyebrow-label {
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--accent);
}

/* Title */
.post-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(2rem, 5.5vw, 3.4rem);
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -.025em;
    color: var(--ink);
    margin-bottom: 1.5rem;
    animation: riseIn .55s .1s var(--ease-out-expo) both;
}

/* Meta row */
.post-meta {
    display: flex;
    align-items: center;
    gap: 1.75rem;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid var(--rule);
    margin-bottom: 2.25rem;
    animation: riseIn .55s .15s var(--ease-out-expo) both;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .78rem;
    font-weight: 500;
    color: var(--ink-muted);
    letter-spacing: .02em;
}

.meta-item i { font-size: .7rem; opacity: .8; }

.meta-dot {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: var(--ink-faint);
}

/* Body copy */
.post-content {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(1.125rem, 2.5vw, 1.28rem);
    line-height: 1.9;
    color: var(--ink-soft);
    text-align: justify;
    text-justify: inter-word;
    hyphens: auto;
    animation: riseIn .55s .2s var(--ease-out-expo) both;
}

.post-content p + p { margin-top: 1.4em; }

.post-content * { max-width: 100%; }

.post-content img,
.post-content video,
.post-content iframe {
    display: block;
    max-width: 100%;
    height: auto;
}

.post-content {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.post-content pre,
.post-content code {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.post-content pre {
    overflow-x: auto;
    padding: 1rem;
    background: var(--paper);
    border: 1px solid var(--rule-soft);
    border-radius: 6px;
}


/* ── ACTION BAR — inline, minimal ──────────────────────── */
.action-bar {
    display: flex;
    align-items: center;
    gap: .875rem;
    margin-top: 2.75rem;
    padding-top: 1.75rem;
    border-top: 1px solid var(--rule);
    animation: riseIn .55s .25s var(--ease-out-expo) both;
}

.btn-like {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem 1.25rem;
    border-radius: 30px;
    border: 1.5px solid var(--rule);
    background: transparent;
    color: var(--ink-soft);
    font-size: .8rem;
    font-weight: 600;
    letter-spacing: .03em;
    transition: border-color .2s, color .2s, background .2s, transform .15s;
}

.btn-like:hover {
    border-color: #e55a5a;
    color: #e55a5a;
    transform: translateY(-1px);
}

.btn-like.liked {
    border-color: #e55a5a;
    background: #fff0f0;
    color: #e55a5a;
}

.heart-icon { font-size: .95rem; transition: transform .2s; }
.btn-like:hover .heart-icon,
.btn-like.liked .heart-icon { transform: scale(1.3); }

.btn-bookmark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1.5px solid var(--rule);
    background: transparent;
    color: var(--ink-muted);
    font-size: .85rem;
    transition: border-color .2s, color .2s, background .2s, transform .15s;
}

.btn-bookmark:hover {
    border-color: var(--accent);
    color: var(--accent);
    transform: translateY(-1px);
}

.btn-bookmark.bookmarked {
    border-color: var(--accent);
    background: var(--accent-lt);
    color: var(--accent);
}


/* ══════════════════════════════════════════════════════════
   COMMENTS — open section, no card
══════════════════════════════════════════════════════════ */
.comments-section {
    max-width: var(--col-text);
    margin: 4.5rem auto 0;
    padding: 0 var(--gutter);
    border-top: 2px solid var(--ink);
    padding-top: 2.75rem;
    animation: riseIn .55s .3s var(--ease-out-expo) both;
}

.section-heading {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.7rem;
    font-weight: 700;
    color: var(--ink);
    display: flex;
    align-items: baseline;
    gap: .75rem;
    margin-bottom: 2rem;
}

.count-tag {
    font-family: 'Libre Franklin', sans-serif;
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--accent);
    background: var(--accent-lt);
    padding: .2rem .55rem;
    border-radius: 4px;
    vertical-align: middle;
    position: relative;
    top: -2px;
}

/* Comment form — minimal, open */
.comment-form {
    margin-bottom: 3rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.fg { margin-bottom: 1rem; }

label {
    display: block;
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-muted);
    margin-bottom: .4rem;
}

.field {
    width: 100%;
    padding: .7rem 0;
    border: none;
    border-bottom: 1.5px solid var(--rule);
    background: transparent;
    font-family: 'Libre Franklin', sans-serif;
    font-size: .9rem;
    color: var(--ink);
    transition: border-color .2s;
    outline: none;
}

.field:focus { border-color: var(--accent); }
.field::placeholder { color: var(--ink-faint); }

textarea.field {
    resize: none;
    min-height: 90px;
    line-height: 1.7;
    padding-top: .5rem;
}

.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: var(--ink);
    color: var(--page);
    border: none;
    padding: .7rem 2rem;
    border-radius: 2px;
    font-family: 'Libre Franklin', sans-serif;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-top: .5rem;
    transition: background .2s, transform .15s, letter-spacing .2s;
}

.btn-submit:hover {
    background: var(--accent);
    letter-spacing: .14em;
    transform: translateY(-1px);
}

.btn-submit:disabled { opacity: .45; cursor: not-allowed; transform: none !important; }

/* Divider between form and list */
.comments-divider {
    height: 1px;
    background: var(--rule-soft);
    margin: 0 0 2rem;
}

/* Comment list */
.comments-list { display: flex; flex-direction: column; }

.comment-item {
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--rule-soft);
    display: grid;
    grid-template-columns: 40px 1fr;
    gap: 1rem;
    animation: riseIn .4s var(--ease-out-expo) both;
}

.comment-item:first-child { border-top: 1px solid var(--rule-soft); }

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--accent-lt);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--accent);
    flex-shrink: 0;
    margin-top: 2px;
}

.comment-body-col {}

.comment-meta {
    display: flex;
    align-items: baseline;
    gap: .75rem;
    margin-bottom: .4rem;
}

.comment-author {
    font-size: .875rem;
    font-weight: 600;
    color: var(--ink);
}

.comment-date {
    font-size: .72rem;
    color: var(--ink-muted);
    letter-spacing: .02em;
}

.comment-text {
    font-size: .9rem;
    color: var(--ink-soft);
    line-height: 1.72;
}

.no-comments {
    padding: 3rem 0;
    text-align: center;
    color: var(--ink-muted);
    font-size: .9rem;
    font-style: italic;
}

.no-comments span { display: block; font-size: 2rem; margin-bottom: .6rem; }


/* ══════════════════════════════════════════════════════════
   RELATED POSTS
══════════════════════════════════════════════════════════ */
.related-section {
    max-width: var(--col-wide);
    margin: 5.5rem auto 0;
    padding: 0 var(--gutter) 5rem;
    animation: riseIn .55s .35s var(--ease-out-expo) both;
}

.related-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.related-header h2 {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--ink);
    white-space: nowrap;
}

.header-rule { flex: 1; height: 1px; background: var(--rule); }

/* Grid */
.posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 2rem;
}

.post-grid-card {
    display: flex;
    flex-direction: column;
    transition: transform .35s var(--ease-out-expo);
}

.post-grid-card:hover { transform: translateY(-5px); }

.card-img-wrap {
    overflow: hidden;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.post-grid-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
    transition: transform .55s var(--ease-out-expo);
}

.post-grid-card:hover .post-grid-image { transform: scale(1.04); }

.card-img-placeholder {
    width: 100%;
    height: 200px;
    background: var(--rule-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border-radius: 4px;
}

.post-grid-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--ink);
    line-height: 1.35;
    margin-bottom: .5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.post-grid-excerpt {
    font-size: .8rem;
    color: var(--ink-muted);
    line-height: 1.65;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: .875rem;
}

.post-grid-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: .75rem;
    border-top: 1px solid var(--rule-soft);
}

.post-grid-date {
    font-size: .72rem;
    color: var(--ink-muted);
    letter-spacing: .03em;
}

.read-more {
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--accent);
    display: flex;
    align-items: center;
    gap: .3rem;
    transition: gap .2s;
}

.post-grid-card:hover .read-more { gap: .55rem; }

.no-related {
    text-align: center;
    padding: 2.5rem;
    color: var(--ink-muted);
    font-style: italic;
}

/* ── ALERTS ──────────────────────────────────────────────── */
.alert {
    padding: .75rem 1rem;
    border-radius: 3px;
    font-size: .85rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    animation: slideRight .3s ease-out both;
}
.alert-success { background: #e8f5ee; color: #2d6a42; border-left: 3px solid #3d6b4a; }
.alert-error   { background: #fde8e8; color: #b91c1c; border-left: 3px solid #e55a5a; }
.alert-info    { background: #dbeafe; color: #1e40af; border-left: 3px solid #3b82f6; }

/* ── ANIMATIONS ──────────────────────────────────────────── */
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes riseIn {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes slideRight {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 640px) {
    .article-wrap,
    .comments-section { padding: 0 var(--gutter); }
    .related-section { padding: 0 var(--gutter) 4rem; }
    .form-grid { grid-template-columns: 1fr; }
    .posts-grid { grid-template-columns: 1fr; }
    .hero-img { height: 50vw; min-height: 220px; }
}

@media (max-width: 480px) {
    :root { --gutter: 1rem; }
}
</style>
</head>
<body>

<?php if ($post['image']): ?>
<!-- ── HERO ─────────────────────────────────────────────── -->
<div class="hero">
    <img class="hero-img"
         src="<?php echo htmlspecialchars(get_post_image_url($post['image'])); ?>"
         alt="<?php echo htmlspecialchars($post['title']); ?>">
</div>
<?php endif; ?>


<!-- ── ARTICLE ───────────────────────────────────────────── -->
<div class="article-wrap">
    <?php display_flash(); ?>

    <div class="post-eyebrow">
        <span class="eyebrow-rule"></span>
        <span class="eyebrow-label">Article</span>
    </div>

    <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>

    <div class="post-meta">
        <span class="meta-item">
            <i class="far fa-calendar-alt"></i>
            <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
        </span>

        <?php if (isset($post['view_count'])): ?>
        <span class="meta-dot"></span>
        <span class="meta-item">
            <i class="far fa-eye"></i>
            <?php echo number_format($post['view_count']); ?> views
        </span>
        <?php endif; ?>

        <span class="meta-dot"></span>
        <span class="meta-item" id="commentMeta">
            <i class="far fa-comment"></i>
            <span id="commentCountMeta"><?php echo $commentsCount; ?></span>
            <span id="commentCountLabel"><?php echo $commentsCount !== 1 ? 'comments' : 'comment'; ?></span>
        </span>
    </div>

    <div class="post-content">
        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
        <button type="button"
                class="btn-like <?php echo $userLiked ? 'liked' : ''; ?>"
                data-post-id="<?php echo $post_id; ?>">
            <span class="heart-icon"><?php echo $userLiked ? '❤️' : '🤍'; ?></span>
            <span class="like-count"><?php echo $likes; ?> <?php echo $likes === 1 ? 'Like' : 'Likes'; ?></span>
        </button>

        <?php if (is_logged_in()): ?>
        <button type="button"
                class="btn-bookmark <?php echo is_bookmarked($post_id) ? 'bookmarked' : ''; ?>"
                data-post-id="<?php echo $post_id; ?>"
                title="<?php echo is_bookmarked($post_id) ? 'Remove bookmark' : 'Bookmark this post'; ?>">
            <i class="fa<?php echo is_bookmarked($post_id) ? 's' : 'r'; ?> fa-bookmark"></i>
        </button>
        <?php endif; ?>
    </div>
</div>


<!-- ── COMMENTS ──────────────────────────────────────────── -->
<div class="comments-section">
    <h2 class="section-heading">
        Comments
        <span class="count-tag" id="commentCountTag"><?php echo $commentsCount; ?></span>
    </h2>

    <form method="POST" action="ajax/comment.php" class="comment-form ajax-comment-form" id="commentForm">
        <?php csrf_token_field(); ?>
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">

        <div class="form-grid">
            <div>
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="field"
                       placeholder="Your name" required
                       value="<?php echo htmlspecialchars($current_user['username'] ?? ($_POST['name'] ?? ''), ENT_QUOTES); ?>">
            </div>
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="field"
                       placeholder="your@email.com" required
                       value="<?php echo htmlspecialchars($current_user['email'] ?? ($_POST['email'] ?? ''), ENT_QUOTES); ?>">
            </div>
        </div>

        <div class="fg">
            <label for="comment_text">Comment</label>
            <textarea id="comment_text" name="comment_text" class="field"
                      placeholder="Share your thoughts…" required><?php echo htmlspecialchars($_POST['comment_text'] ?? '', ENT_QUOTES); ?></textarea>
        </div>

        <button type="submit" name="comment" class="btn-submit" id="submitBtn">
            <i class="far fa-paper-plane"></i> Post Comment
        </button>
    </form>

    <div class="comments-divider"></div>

    <div class="comments-list" id="commentsList">
        <?php if ($commentsCount > 0): ?>
            <?php while ($c = $comments->fetch_assoc()): ?>
            <div class="comment-item">
                <div class="comment-avatar"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div>
                <div class="comment-body-col">
                    <div class="comment-meta">
                        <span class="comment-author"><?php echo htmlspecialchars($c['name']); ?></span>
                        <span class="comment-date"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></span>
                    </div>
                    <div class="comment-text">
                        <?php echo nl2br(htmlspecialchars($c['comment'])); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-comments">
                <span>💬</span>
                No comments yet — be the first to share your thoughts!
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- ── RELATED POSTS ─────────────────────────────────────── -->
<div class="related-section">
    <div class="related-header">
        <h2>More to Read</h2>
        <div class="header-rule"></div>
    </div>

    <?php if ($relatedPosts->num_rows > 0): ?>
    <div class="posts-grid">
        <?php while ($rPost = $relatedPosts->fetch_assoc()): ?>
        <a href="post.php?id=<?php echo $rPost['id']; ?>" class="post-grid-card">
            <div class="card-img-wrap">
                <?php if ($rPost['image']): ?>
                    <img class="post-grid-image"
                         src="<?php echo htmlspecialchars(get_post_image_url($rPost['image'])); ?>"
                         alt="<?php echo htmlspecialchars($rPost['title']); ?>">
                <?php else: ?>
                    <div class="card-img-placeholder">📝</div>
                <?php endif; ?>
            </div>

            <div class="post-grid-content">
                <h3 class="post-grid-title"><?php echo htmlspecialchars($rPost['title']); ?></h3>
                <p class="post-grid-excerpt">
                    <?php
                        $excerpt = strip_tags($rPost['content']);
                        echo htmlspecialchars(substr($excerpt, 0, 130)) . (strlen($excerpt) > 130 ? '…' : '');
                    ?>
                </p>
                <div class="post-grid-footer">
                    <span class="post-grid-date"><?php echo date('M j, Y', strtotime($rPost['created_at'])); ?></span>
                    <span class="read-more">Read more <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></span>
                </div>
            </div>
        </a>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="no-related">No other posts available at the moment.</div>
    <?php endif; ?>
</div>

<script>
(function () {
    const likeBtn = document.querySelector('.btn-like');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    if (likeBtn) {
        likeBtn.addEventListener('click', async () => {
            if (!csrfToken) {
                alert('Session expired. Refresh and try again.');
                return;
            }
            const form = new FormData();
            form.append('post_id', likeBtn.dataset.postId);
            form.append('csrf_token', csrfToken);
            try {
                const res = await fetch('ajax/like.php', { method: 'POST', body: form });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Like failed');
                likeBtn.classList.toggle('liked', data.liked);
                const heart = likeBtn.querySelector('.heart-icon');
                if (heart) heart.textContent = data.liked ? '❤️' : '🤍';
                const count = likeBtn.querySelector('.like-count');
                if (count) {
                    count.textContent = `${data.likes} ${data.likes === 1 ? 'Like' : 'Likes'}`;
                }
            } catch (err) {
                alert(err.message || 'Network error');
            }
        });
    }

    const form = document.getElementById('commentForm');
    const list = document.getElementById('commentsList');
    const countTag = document.getElementById('commentCountTag');
    const countMeta = document.getElementById('commentCountMeta');
    const countLabel = document.getElementById('commentCountLabel');

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Comment failed');

                if (list) {
                    const empty = list.querySelector('.no-comments');
                    if (empty) empty.remove();

                    const item = document.createElement('div');
                    item.className = 'comment-item';

                    const avatar = document.createElement('div');
                    avatar.className = 'comment-avatar';
                    avatar.textContent = (data.comment.name || 'U').charAt(0).toUpperCase();

                    const body = document.createElement('div');
                    body.className = 'comment-body-col';

                    const meta = document.createElement('div');
                    meta.className = 'comment-meta';

                    const author = document.createElement('span');
                    author.className = 'comment-author';
                    author.textContent = data.comment.name || 'Anonymous';

                    const date = document.createElement('span');
                    date.className = 'comment-date';
                    date.textContent = data.comment.created_at || '';

                    const text = document.createElement('div');
                    text.className = 'comment-text';
                    text.innerHTML = (data.comment.comment || '').replace(/\n/g, '<br>');

                    meta.appendChild(author);
                    meta.appendChild(date);
                    body.appendChild(meta);
                    body.appendChild(text);
                    item.appendChild(avatar);
                    item.appendChild(body);

                    list.prepend(item);
                }

                const current = parseInt(countTag?.textContent || '0', 10) || 0;
                const next = current + 1;
                if (countTag) countTag.textContent = String(next);
                if (countMeta) countMeta.textContent = String(next);
                if (countLabel) countLabel.textContent = next === 1 ? 'comment' : 'comments';

                form.reset();
            } catch (err) {
                alert(err.message || 'Network error');
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    const bookmarkBtn = document.querySelector('.btn-bookmark');
    if (bookmarkBtn) {
        bookmarkBtn.addEventListener('click', async () => {
            const postId = bookmarkBtn.dataset.postId;
            const isBookmarked = bookmarkBtn.classList.contains('bookmarked');
            try {
                const res = await fetch('ajax/bookmark.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        post_id: postId,
                        action: isBookmarked ? 'remove' : 'add'
                    })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Bookmark failed');
                bookmarkBtn.classList.toggle('bookmarked', data.bookmarked);
                const icon = bookmarkBtn.querySelector('i');
                if (icon) {
                    icon.className = data.bookmarked ? 'fas fa-bookmark' : 'far fa-bookmark';
                }
                bookmarkBtn.title = data.bookmarked ? 'Remove bookmark' : 'Bookmark this post';
            } catch (err) {
                alert(err.message || 'Network error');
            }
        });
    }
})();
</script>


<?php
$commentsQuery->close();
$relatedQuery->close();
$conn->close();
include '../shared/footer.php';
?>
