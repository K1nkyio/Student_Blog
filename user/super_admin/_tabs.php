<?php
$active_tab = $active_tab ?? '';
$tabs = [
    [
        'key' => 'dashboard',
        'title' => 'Dashboard',
        'desc' => 'Super admin overview and key alerts.',
        'meta' => 'dashboard.php',
        'href' => 'dashboard.php',
    ],
    [
        'key' => 'posts',
        'title' => 'Posts',
        'desc' => 'Manage all posts across the platform.',
        'meta' => 'post_management.php',
        'href' => 'post_management.php',
    ],
    [
        'key' => 'review',
        'title' => 'Review',
        'desc' => 'Approve, reject, or delete submitted posts before they go live.',
        'meta' => 'review.php',
        'href' => 'review.php',
    ],
    [
        'key' => 'comments',
        'title' => 'Comments',
        'desc' => 'Review, approve, reject, and delete comments across all platform sections.',
        'meta' => 'comments.php',
        'href' => 'comments.php',
    ],
    [
        'key' => 'safespeak',
        'title' => 'SafeSpeak',
        'desc' => 'Review anonymous reports, update statuses, and track critical safety issues.',
        'meta' => 'safespeak.php',
        'href' => 'safespeak.php',
    ],
    [
        'key' => 'users',
        'title' => 'Users & Admins',
        'desc' => 'Approve, audit, and manage admin and user access.',
        'meta' => 'users.php',
        'href' => 'users.php',
    ],
    [
        'key' => 'analytics',
        'title' => 'Analytics',
        'desc' => 'Platform performance and engagement metrics.',
        'meta' => 'analytics.php',
        'href' => 'analytics.php',
    ],
    [
        'key' => 'profile',
        'title' => 'Profile',
        'desc' => 'View and update your super admin account information.',
        'meta' => 'profile.php',
        'href' => 'profile.php',
    ],
];
?>

<div class="sa-tabs">
  <?php foreach ($tabs as $tab): ?>
    <a class="sa-tab <?php echo $active_tab === $tab['key'] ? 'active' : ''; ?>"
       href="<?php echo htmlspecialchars($tab['href']); ?>">
      <div class="sa-tab-title"><?php echo htmlspecialchars($tab['title']); ?></div>
      <div class="sa-tab-desc"><?php echo htmlspecialchars($tab['desc']); ?></div>
      <div class="sa-tab-meta"><?php echo htmlspecialchars($tab['meta']); ?></div>
    </a>
  <?php endforeach; ?>
</div>
