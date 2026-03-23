<?php
/**
 * Blog System - Categories Page
 * Displays all categories with post counts and statistics
 */

session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

// ============================================================================
// FETCH CATEGORIES DATA
// ============================================================================

// Get all categories with post counts and latest post info
$categories_sql = "SELECT
    c.id,
    c.name,
    c.slug,
    c.description,
    COUNT(p.id) as post_count,
    MAX(p.created_at) as latest_post_date,
    (
        SELECT GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ')
        FROM tags t
        INNER JOIN post_tags pt ON t.id = pt.tag_id
        INNER JOIN posts p2 ON pt.post_id = p2.id
        WHERE p2.category_id = c.id
          AND COALESCE(p2.visible, 1) = 1
          AND COALESCE(p2.review_status, 'approved') = 'approved'
        GROUP BY c.id
        LIMIT 3
    ) as popular_tags
    FROM categories c
    LEFT JOIN posts p ON c.id = p.category_id AND COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
    GROUP BY c.id, c.name, c.slug, c.description
    ORDER BY post_count DESC, c.name ASC";

$categories_stmt = $conn->prepare($categories_sql);
$categories_stmt->execute();
$categories = $categories_stmt->get_result();

// Get total categories count
$total_categories = $categories->num_rows;

// ============================================================================
// FETCH SIDEBAR DATA
// ============================================================================

// Get categories for sidebar (reuse from above)
// Reset pointer for sidebar
$categories->data_seek(0);

// Get popular tags
$tags = $conn->query("SELECT t.id, t.name, t.slug, COUNT(p.id) as post_count
                      FROM tags t
                      LEFT JOIN post_tags pt ON t.id = pt.tag_id
                      LEFT JOIN posts p ON pt.post_id = p.id
                        AND COALESCE(p.visible, 1) = 1
                        AND COALESCE(p.review_status, 'approved') = 'approved'
                      GROUP BY t.id, t.name, t.slug
                      ORDER BY post_count DESC
                      LIMIT 20");

// Get active authors
$authors = $conn->query("SELECT u.id, u.username, COUNT(p.id) as post_count
                         FROM users u
                         INNER JOIN posts p ON u.id = p.author_id
                         WHERE COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
                         GROUP BY u.id, u.username
                         ORDER BY post_count DESC");

// ============================================================================
// USER SESSION & BOOKMARKS
// ============================================================================

$is_logged_in = is_logged_in();
$user_id = get_user_id();

// Get user's bookmarked posts
$bookmarked_posts = [];
if ($is_logged_in) {
    $bookmark_stmt = $conn->prepare("SELECT post_id FROM bookmarks WHERE user_id = ?");
    $bookmark_stmt->bind_param("i", $user_id);
    $bookmark_stmt->execute();
    $bookmark_result = $bookmark_stmt->get_result();
    while ($row = $bookmark_result->fetch_assoc()) {
        $bookmarked_posts[] = $row['post_id'];
    }
    $bookmark_stmt->close();
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// ============================================================================
// PAGE METADATA
// ============================================================================

$page_title = 'Categories - ' . SITE_NAME;
$meta_description = 'Browse all blog categories on ' . SITE_NAME . '. Discover topics and find posts that interest you most.';

// Include header
include '../shared/header.php';
?>

<style>
    :root {
        --primary: #4f46e5;
        --primary-dark: #4338ca;
        --secondary: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --dark: #1f2937;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --radius: 0.5rem;
        --radius-lg: 0.75rem;
        --transition: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--gray-50);
        color: var(--gray-800);
        line-height: 1.6;
    }

    .container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    /* Page Header */
    .page-header {
        text-align: center;
        margin-bottom: 3rem;
        padding: 2rem 0;
    }

    .page-header h1 {
        font-size: 2.5rem;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
        font-weight: 800;
    }

    .page-header h1 i {
        color: var(--primary);
        margin-right: 0.5rem;
    }

    .page-header p {
        font-size: 1.125rem;
        color: var(--gray-600);
    }

    /* Stats Section */
    .stats-section {
        background: var(--white);
        padding: 2rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
        text-align: center;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        margin-top: 1.5rem;
    }

    .stat-item {
        padding: 1.5rem;
        background: var(--gray-50);
        border-radius: var(--radius);
        border: 2px solid var(--gray-200);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 1rem;
        color: var(--gray-600);
        font-weight: 600;
    }

    /* Main Layout */
    .main-layout {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .main-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Categories Grid */
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
    }

    .category-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
    }

    .category-header {
        padding: 2rem 1.5rem 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: var(--white);
        position: relative;
    }

    .category-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }

    .category-name {
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .category-description {
        opacity: 0.9;
        font-size: 0.95rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .category-stats {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        opacity: 0.8;
    }

    .category-content {
        padding: 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .category-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        font-size: 0.875rem;
        color: var(--gray-600);
    }

    .post-count {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    .post-count i {
        color: var(--primary);
    }

    .latest-post {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        color: var(--gray-500);
    }

    .popular-tags {
        margin-bottom: 1rem;
    }

    .tag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .tag-item {
        background: var(--gray-100);
        color: var(--gray-700);
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .category-actions {
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid var(--gray-200);
    }

    .category-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
    }

    .category-link:hover {
        gap: 0.75rem;
    }

    /* Sidebar */
    .sidebar {
        position: sticky;
        top: 2rem;
    }

    .sidebar-widget {
        background: var(--white);
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
    }

    .widget-title {
        font-size: 1.125rem;
        margin-bottom: 1rem;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .widget-title i {
        color: var(--primary);
    }

    .widget-description {
        color: var(--gray-600);
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }

    .subscribe-form {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .subscribe-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius);
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .subscribe-input:focus {
        outline: none;
        border-color: var(--primary);
    }

    .subscribe-options {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .subscribe-options label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .category-link {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        color: var(--gray-700);
        text-decoration: none;
        border-bottom: 1px solid var(--gray-200);
        transition: var(--transition);
    }

    .category-link:last-child {
        border-bottom: none;
    }

    .category-link:hover {
        color: var(--primary);
        padding-left: 0.5rem;
    }

    .category-count {
        background: var(--gray-100);
        color: var(--gray-600);
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
    }

    .widget-empty {
        color: var(--gray-500);
        font-size: 0.875rem;
        text-align: center;
        padding: 1rem 0;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: var(--radius);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        justify-content: center;
    }

    .btn-primary {
        background: var(--primary);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-outline {
        background: transparent;
        color: var(--gray-700);
        border: 2px solid var(--gray-300);
    }

    .btn-outline:hover {
        background: var(--gray-100);
        border-color: var(--gray-400);
    }

    .btn-full {
        width: 100%;
    }

    /* No Categories */
    .no-categories {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
    }

    .no-categories i {
        font-size: 4rem;
        color: var(--gray-300);
        margin-bottom: 1rem;
    }

    .no-categories h2 {
        color: var(--gray-900);
        margin-bottom: 0.5rem;
    }

    .no-categories p {
        color: var(--gray-600);
        margin-bottom: 1.5rem;
    }

    /* Toast */
    .toast-container {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 10000;
    }

    .toast {
        background: var(--gray-900);
        color: var(--white);
        padding: 1rem 1.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow-xl);
        margin-bottom: 0.5rem;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .toast.success {
        background: var(--secondary);
    }

    .toast.error {
        background: var(--danger);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-header h1 {
            font-size: 2rem;
        }

        .categories-grid {
            grid-template-columns: 1fr;
        }

        .stat-number {
            font-size: 2rem;
        }

        .category-header {
            padding: 1.5rem 1rem 0.75rem;
        }

        .category-name {
            font-size: 1.25rem;
        }

        .toast-container {
            right: 1rem;
            left: 1rem;
        }
    }

    /* Category icons */
    .category-icon.folder { color: #fbbf24; }
    .category-icon.code { color: #10b981; }
    .category-icon.lightbulb { color: #f59e0b; }
    .category-icon.chart { color: #8b5cf6; }
    .category-icon.book { color: #06b6d4; }
    .category-icon.camera { color: #ec4899; }
    .category-icon.music { color: #84cc16; }
    .category-icon.gamepad { color: #f97316; }
    .category-icon.default { color: #6b7280; }
</style>

<div class="container">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-folder-open"></i> Categories</h1>
        <p>Explore all topics and find posts that interest you most</p>
    </div>

    <!-- Stats Section -->
    <div class="stats-section">
        <h2 style="color: var(--gray-900); margin-bottom: 0.5rem;">Blog Overview</h2>
        <p style="color: var(--gray-600); margin-bottom: 0;">Discover what our community is writing about</p>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?= $total_categories ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <?php
            $total_posts = $conn->query("SELECT COUNT(*) as count FROM posts WHERE COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved'")->fetch_assoc()['count'];
            $total_authors = $conn->query("SELECT COUNT(DISTINCT author_id) as count FROM posts WHERE COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved'")->fetch_assoc()['count'];
            $total_comments = $conn->query("SELECT COUNT(*) as count FROM comments WHERE approved = 1")->fetch_assoc()['count'];
            ?>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($total_posts) ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($total_authors) ?></div>
                <div class="stat-label">Authors</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($total_comments) ?></div>
                <div class="stat-label">Comments</div>
            </div>
        </div>
    </div>

    <!-- Main Layout: Categories Grid + Sidebar -->
    <div class="main-layout">
        <!-- Categories Section -->
        <div class="categories-section">
            <?php if ($categories->num_rows > 0): ?>
            <div class="categories-grid">
                <?php while ($category = $categories->fetch_assoc()):
                    // Determine category icon based on name
                    $icon_class = 'default';
                    $category_name_lower = strtolower($category['name']);
                    if (strpos($category_name_lower, 'tech') !== false || strpos($category_name_lower, 'programming') !== false) {
                        $icon_class = 'code';
                    } elseif (strpos($category_name_lower, 'tutorial') !== false || strpos($category_name_lower, 'guide') !== false) {
                        $icon_class = 'book';
                    } elseif (strpos($category_name_lower, 'news') !== false || strpos($category_name_lower, 'update') !== false) {
                        $icon_class = 'lightbulb';
                    } elseif (strpos($category_name_lower, 'data') !== false || strpos($category_name_lower, 'analytics') !== false) {
                        $icon_class = 'chart';
                    } elseif (strpos($category_name_lower, 'media') !== false || strpos($category_name_lower, 'photo') !== false) {
                        $icon_class = 'camera';
                    } elseif (strpos($category_name_lower, 'entertainment') !== false || strpos($category_name_lower, 'fun') !== false) {
                        $icon_class = 'music';
                    } elseif (strpos($category_name_lower, 'gaming') !== false) {
                        $icon_class = 'gamepad';
                    } elseif (strpos($category_name_lower, 'business') !== false || strpos($category_name_lower, 'career') !== false) {
                        $icon_class = 'chart';
                    }
                ?>
                <article class="category-card">
                    <!-- Category Header -->
                    <div class="category-header">
                        <div class="category-icon <?= $icon_class ?>">
                            <i class="fas fa-folder"></i>
                        </div>
                        <h2 class="category-name">
                            <?= htmlspecialchars($category['name']) ?>
                        </h2>
                        <?php if (!empty($category['description'])): ?>
                        <p class="category-description">
                            <?= htmlspecialchars($category['description']) ?>
                        </p>
                        <?php endif; ?>
                        <div class="category-stats">
                            <span><i class="fas fa-file-alt"></i> <?= number_format($category['post_count']) ?> posts</span>
                            <?php if ($category['latest_post_date']): ?>
                            <span><i class="fas fa-clock"></i> Updated <?= time_ago($category['latest_post_date']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Category Content -->
                    <div class="category-content">
                        <!-- Meta Information -->
                        <div class="category-meta">
                            <div class="post-count">
                                <i class="fas fa-newspaper"></i>
                                <?= number_format($category['post_count']) ?> article<?= $category['post_count'] !== 1 ? 's' : '' ?>
                            </div>
                            <?php if ($category['latest_post_date']): ?>
                            <div class="latest-post">
                                <i class="fas fa-calendar-alt"></i>
                                Latest: <?= date('M j, Y', strtotime($category['latest_post_date'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Popular Tags -->
                        <?php if (!empty($category['popular_tags'])): ?>
                        <div class="popular-tags">
                            <div class="tag-list">
                                <?php
                                $tags_array = explode(', ', $category['popular_tags']);
                                foreach (array_slice($tags_array, 0, 3) as $tag):
                                ?>
                                <span class="tag-item"><?= htmlspecialchars(trim($tag)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="category-actions">
                            <a href="index.php?category=<?= $category['id'] ?>" class="category-link">
                                <span>Browse Posts</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </article>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <!-- No Categories Found -->
            <div class="no-categories">
                <i class="fas fa-folder-open"></i>
                <h2>No categories yet</h2>
                <p>Categories will appear here once they're created by administrators.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> View All Posts
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Subscribe Widget -->
            <div class="sidebar-widget">
                <h3 class="widget-title">
                    <i class="fas fa-envelope"></i> Subscribe
                </h3>
                <p class="widget-description">
                    Get the latest posts delivered right to your inbox!
                </p>
                <form class="subscribe-form" id="subscribeForm" method="POST" action="ajax/subscribe.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="email" name="email" class="subscribe-input"
                           placeholder="Your email address" required>
                    <div class="subscribe-options">
                        <label>
                            <input type="checkbox" name="notify_posts" checked>
                            Notify me of new posts
                        </label>
                        <label>
                            <input type="checkbox" name="notify_weekly">
                            Weekly digest
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-paper-plane"></i> Subscribe
                    </button>
                </form>
            </div>

            <!-- Categories Widget -->
            <div class="sidebar-widget">
                <h3 class="widget-title">
                    <i class="fas fa-folder"></i> All Categories
                </h3>
                <?php
                $categories->data_seek(0);
                if ($categories->num_rows > 0):
                    while ($cat = $categories->fetch_assoc()):
                ?>
                <a href="index.php?category=<?= $cat['id'] ?>" class="category-link">
                    <span><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="category-count"><?= $cat['post_count'] ?></span>
                </a>
                <?php endwhile; else: ?>
                <p class="widget-empty">No categories yet.</p>
                <?php endif; ?>
            </div>

            <?php if ($is_logged_in): ?>
            <!-- Bookmarks Widget -->
            <div class="sidebar-widget">
                <h3 class="widget-title">
                    <i class="fas fa-bookmark"></i> Your Bookmarks
                </h3>
                <?php if (count($bookmarked_posts) > 0): ?>
                <p class="widget-description">
                    You have <?= count($bookmarked_posts) ?> saved post<?= count($bookmarked_posts) !== 1 ? 's' : '' ?>
                </p>
                <a href="bookmarks.php" class="btn btn-outline btn-full">
                    <i class="fas fa-eye"></i> View All Bookmarks
                </a>
                <?php else: ?>
                <p class="widget-empty">
                    No bookmarks yet. Click the <i class="far fa-bookmark"></i> icon on any post to save it for later!
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 3000);
}

// ============================================================================
// SUBSCRIBE FORM
// ============================================================================
document.getElementById('subscribeForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const button = this.querySelector('button[type="submit"]');
    const originalText = button.innerHTML;

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';

    fetch(this.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Successfully subscribed!', 'success');
            this.reset();
        } else {
            showToast(data.message || 'Subscription failed', 'error');
        }
    })
    .catch(error => {
        console.error('Subscribe error:', error);
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    });
});
</script>

<?php
$categories_stmt->close();
$conn->close();
include '../shared/footer.php';
?>
