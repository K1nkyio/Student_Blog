<?php
// Super Admin header wrapper - supplies custom nav + titles.
$force_super_admin_header = true;
$super_admin_nav_override = [
    ['href' => 'dashboard.php', 'label' => 'Dashboard',      'icon' => 'gauge'],
    ['href' => 'post_management.php', 'label' => 'Posts',          'icon' => 'edit'],
    ['href' => 'review.php', 'label' => 'Review',        'icon' => 'checklist'],
    ['href' => 'comments.php',  'label' => 'Comments',       'icon' => 'message'],
    ['href' => 'safespeak.php', 'label' => 'SafeSpeak',      'icon' => 'shield'],
    ['href' => 'users.php',     'label' => 'Users & Admins', 'icon' => 'crown'],
    ['href' => 'analytics.php', 'label' => 'Analytics',      'icon' => 'chart'],
    ['href' => 'profile.php', 'label' => 'Profile',       'icon' => 'user'],
];
$super_admin_active_map = [
    'dashboard.php' => 'dashboard.php',
    'post_management.php'  => 'post_management.php',
    'edit_post.php' => 'post_management.php',
    'delete_post.php' => 'post_management.php',
    'review.php' => 'review.php',
    'comments.php'  => 'comments.php',
    'safespeak.php' => 'safespeak.php',
    'users.php'     => 'users.php',
    'analytics.php' => 'analytics.php',
    'profile.php' => 'profile.php',
];
$super_admin_title_map = [
    'dashboard.php' => 'Super Admin Dashboard',
    'post_management.php'  => 'Posts',
    'edit_post.php' => 'Edit Post',
    'delete_post.php' => 'Posts',
    'review.php' => 'Post Review',
    'comments.php'  => 'Comments',
    'safespeak.php' => 'SafeSpeak Reports',
    'users.php'     => 'Users & Admins',
    'analytics.php' => 'Analytics',
    'profile.php' => 'Profile',
];
$super_admin_subtitle_map = [
    'dashboard.php' => 'Approvals, oversight, and platform health',
    'post_management.php'  => 'Edit and manage posts',
    'edit_post.php' => 'Update and refine a post',
    'delete_post.php' => 'Manage posts',
    'review.php' => 'Approve, reject, or remove submitted content',
    'comments.php'  => 'Moderate discussions and responses',
    'safespeak.php' => 'Review anonymous safety reports across the platform',
    'users.php'     => 'Admin governance and access control',
    'analytics.php' => 'Platform performance and engagement metrics',
    'profile.php' => 'Manage your super admin account details',
];

include __DIR__ . '/../../admin/includes/header.php';
