<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_path = $_SERVER['PHP_SELF'] ?? '';
$is_super_admin_section = strpos($current_path, '/super_admin/') !== false;
$login_url = $is_super_admin_section ? '../super_admin/login.php' : 'register.php';
$logout_url = $is_super_admin_section ? '../super_admin/logout.php' : 'logout.php';
$admin_link_prefix = $is_super_admin_section ? '../admin/' : '';

if (!isset($_SESSION['admin'])) {
    header('Location: ' . $login_url);
    exit();
}

$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$admin_name   = (string)($_SESSION['admin'] ?? 'Admin');
$admin_role   = (string)($_SESSION['admin_role'] ?? 'admin');
$role_labels  = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'editor'      => 'Editor',
];
$admin_role_label = $role_labels[$admin_role] ?? 'Admin';

function is_super_admin(): bool {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

$active_map = [
    'dashboard.php'          => 'dashboard.php',
    'add_post.php'           => 'add_post.php',
    'edit_post.php'          => 'add_post.php',
    'delete_post.php'        => 'add_post.php',
    'comments.php'           => 'comments.php',
    'safespeak.php'          => 'safespeak.php',
    'opportunities.php'      => 'opportunities.php',
    'opportunity_edit.php'   => 'opportunities.php',
    'opportunity_delete.php' => 'opportunities.php',
    'marketplace.php'        => 'marketplace.php',
    'marketplace_edit.php'   => 'marketplace.php',
    'marketplace_delete.php' => 'marketplace.php',
    'events.php'             => 'events.php',
    'event_edit.php'         => 'events.php',
    'event_delete.php'       => 'events.php',
    'super_admin.php'        => 'users.php',
    'users.php'              => 'users.php',
    'analytics.php'          => 'analytics.php',
    'profile.php'            => 'profile.php',
];
if (!empty($super_admin_active_map) && $is_super_admin_section) {
    $active_map = $super_admin_active_map;
}
$active_nav = $active_map[$current_page] ?? $current_page;

$page_title_map = [
    'dashboard.php'          => 'Dashboard',
    'add_post.php'           => 'Posts',
    'edit_post.php'          => 'Edit Post',
    'delete_post.php'        => 'Posts',
    'comments.php'           => 'Comments',
    'safespeak.php'          => 'SafeSpeak Reports',
    'opportunities.php'      => 'Opportunities',
    'opportunity_edit.php'   => 'Edit Opportunity',
    'opportunity_delete.php' => 'Opportunities',
    'marketplace.php'        => 'Marketplace',
    'marketplace_edit.php'   => 'Edit Listing',
    'marketplace_delete.php' => 'Marketplace',
    'events.php'             => 'Events',
    'event_edit.php'         => 'Edit Event',
    'event_delete.php'       => 'Events',
    'super_admin.php'        => 'Users & Admins',
    'users.php'              => 'Users & Admins',
    'analytics.php'          => 'Analytics',
    'profile.php'            => 'Profile',
];
if (!empty($super_admin_title_map) && $is_super_admin_section) {
    $page_title_map = $super_admin_title_map;
}
$topbar_title = $page_title_map[$current_page] ?? 'Admin Panel';

$topbar_subtitles = [
    'dashboard.php'     => 'Overview & analytics',
    'add_post.php'      => 'Create & manage posts',
    'comments.php'      => 'Moderate discussions',
    'safespeak.php'     => 'Review anonymous reports',
    'opportunities.php' => 'Manage listings',
    'marketplace.php'   => 'Product listings',
    'events.php'        => 'Campus events',
    'super_admin.php'   => 'Admin governance',
    'users.php'         => 'Admin governance',
    'analytics.php'     => 'Platform metrics',
    'profile.php'       => 'Manage your account details',
];
if (!empty($super_admin_subtitle_map) && $is_super_admin_section) {
    $topbar_subtitles = $super_admin_subtitle_map;
}
$topbar_subtitle = $topbar_subtitles[$current_page] ?? 'Manage your platform';

if (isset($topbar_title_override)) {
    $topbar_title = $topbar_title_override;
}
if (isset($topbar_subtitle_override)) {
    $topbar_subtitle = $topbar_subtitle_override;
}

$nav_items = [
    ['href' => 'dashboard.php',    'label' => 'Dashboard',    'icon' => 'gauge'],
    ['href' => 'add_post.php',     'label' => 'Posts',        'icon' => 'edit'],
    ['href' => 'comments.php',     'label' => 'Comments',     'icon' => 'message'],
    ['href' => 'safespeak.php',    'label' => 'SafeSpeak',    'icon' => 'shield'],
    ['href' => 'opportunities.php','label' => 'Opportunities','icon' => 'briefcase'],
    ['href' => 'marketplace.php',  'label' => 'Marketplace',  'icon' => 'store'],
    ['href' => 'events.php',       'label' => 'Events',       'icon' => 'calendar'],
    ['href' => 'profile.php',      'label' => 'Profile',      'icon' => 'user'],
];
if ($is_super_admin_section && !empty($super_admin_nav_override)) {
    $nav_items = $super_admin_nav_override;
} elseif ($is_super_admin_section) {
    $nav_items = [
        ['href' => '../super_admin/dashboard.php', 'label' => 'Dashboard',      'icon' => 'gauge'],
        ['href' => 'add_post.php',                 'label' => 'Posts',          'icon' => 'edit'],
        ['href' => 'comments.php',                 'label' => 'Comments',       'icon' => 'message'],
        ['href' => '../super_admin/users.php',     'label' => 'Users & Admins', 'icon' => 'crown'],
        ['href' => '../super_admin/analytics.php', 'label' => 'Analytics',      'icon' => 'chart'],
    ];
}

/* SVG icon helper — keeps Font Awesome dependency-free */
function sb_icon(string $name): string {
    $icons = [
        'gauge'     => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 2c2.09 0 4 .76 5.46 2l-1.42 1.42C14.95 6.54 13.52 6 12 6s-2.95.54-4.04 1.42L6.54 6C7.99 4.76 9.9 4 12 4zm0 16c-4.41 0-8-3.59-8-8 0-2.09.8-3.99 2.1-5.42l1.42 1.42C6.59 9.05 6 10.46 6 12c0 3.31 2.69 6 6 6s6-2.69 6-6c0-1.54-.59-2.95-1.54-4l1.42-1.42C19.2 8.01 20 9.91 20 12c0 4.41-3.59 8-8 8zm1-8.41l-3-3-1.41 1.41 4.41 4.41 6.41-6.41-1.41-1.41L13 11.59z" fill="currentColor"/>',
        'edit'      => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/>',
        'message'   => '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z" fill="currentColor"/>',
        'shield'    => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12V11c0 4.52-3.13 8.74-7 9.93-3.87-1.19-7-5.41-7-9.93V6.3l7-3.12zM11 7v2H9v2h2v2h2v-2h2v-2h-2V7h-2z" fill="currentColor"/>',
        'briefcase' => '<path d="M10 2h4a2 2 0 012 2v2h2a2 2 0 012 2v11a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h2V4a2 2 0 012-2zm4 4V4h-4v2h4zM4 8v11h16V8H4zm7 7v-2H9v-2h2V9h2v2h2v2h-2v2h-2z" fill="currentColor"/>',
        'store'     => '<path d="M2 6h20v2H2V6zm2 3h16l-1.5 9H5.5L4 9zm3 2v5h2v-5H7zm4 0v5h2v-5h-2zm4 0v5h2v-5h-2z" fill="currentColor"/>',
        'calendar'  => '<path d="M19 4h-1V2h-2v2H8V2H6v2H5C3.9 4 3 4.9 3 6v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM5 7V6h14v1H5zm2 4h10v2H7v-2zm0 4h7v2H7v-2z" fill="currentColor"/>',
        'crown'     => '<path d="M3 18h18v2H3v-2zm0-12l4 3 5-6 5 6 4-3-2 9H5L3 6z" fill="currentColor"/>',
        'chart'     => '<path d="M4 19h16v2H2V3h2v16zm4-2H6v-6h2v6zm5 0h-2V7h2v10zm5 0h-2v-4h2v4z" fill="currentColor"/>',
        'checklist' => '<path d="M9 7h11v2H9V7zm0 4h11v2H9v-2zm0 4h11v2H9v-2zM4.5 8.5L3 7l1.06-1.06 1.44 1.44L8.94 3.94 10 5 4.5 10.5zM4.5 16.5L3 15l1.06-1.06 1.44 1.44 2.44-2.44L10 14l-5.5 5.5z" fill="currentColor"/>',
        'user'      => '<path d="M12 12c2.76 0 5-2.69 5-6s-2.24-6-5-6-5 2.69-5 6 2.24 6 5 6zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5z" fill="currentColor"/>',
        'logout'    => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z" fill="currentColor"/>',
    ];
    $d = $icons[$name] ?? $icons['edit'];
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' . $d . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($topbar_title); ?> — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    /* ═══════════════════════════════════════════════════════════
       DESIGN TOKENS — exact match with opportunities.php
    ═══════════════════════════════════════════════════════════ */
    :root {
        /* palette */
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

        /* typography */
        --font-serif: 'Cormorant Garamond', Georgia, serif;
        --font-sans:  'Outfit', sans-serif;

        /* sidebar */
        --sb-w:       256px;
        --sb-w-col:   64px;

        /* misc */
        --transition: 200ms cubic-bezier(.4,0,.2,1);
        --radius:     4px;
        --radius-md:  6px;
        --shadow:     0 1px 3px rgba(24,22,15,.06), 0 4px 16px rgba(24,22,15,.06);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ─── BASE ─── */
    body.admin-shell {
        min-height: 100vh;
        background: var(--bg);
        color: var(--ink);
        font-family: var(--font-sans);
        font-size: 14px;
        line-height: 1.6;
    }

    /* ═══════════════════════════════════════════════════════════
       LAYOUT
    ═══════════════════════════════════════════════════════════ */
    .admin-app {
        display: flex;
        min-height: 100vh;
    }

    /* ═══════════════════════════════════════════════════════════
       SIDEBAR — dark ink panel, same as auth & hero
    ═══════════════════════════════════════════════════════════ */
    .admin-sidebar {
        width: var(--sb-w);
        flex-shrink: 0;
        background: var(--ink);
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow: hidden;
        z-index: 200;
        transition: width var(--transition);
    }

    /* dot-grid texture — matches hero overlay */
    .admin-sidebar::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
        background-size: 22px 22px;
        pointer-events: none;
        z-index: 0;
    }

    /* serif watermark — same as auth panels */
    .admin-sidebar::before {
        content: 'Adm.';
        position: absolute;
        right: -1rem;
        bottom: -1.5rem;
        font-family: var(--font-serif);
        font-size: 9rem;
        font-weight: 700;
        font-style: italic;
        color: rgba(255,255,255,.028);
        line-height: 1;
        pointer-events: none;
        user-select: none;
        z-index: 0;
    }

    .sb-inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 0 10px;
        overflow: hidden;
    }

    /* ─── BRAND ─── */
    .sb-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 20px 10px 16px;
        border-bottom: 1px solid rgba(255,255,255,.07);
        margin-bottom: 6px;
        flex-shrink: 0;
        min-width: 0;
    }

    /* eyebrow dot — matches live-dot */
    .sb-logo-wrap {
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 3px;
    }

    .sb-brand-dot {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: var(--accent);
        box-shadow: 0 0 6px var(--accent);
        animation: blink 2s infinite;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

    .sb-brand-text {
        overflow: hidden;
        min-width: 0;
        flex: 1;
        transition: opacity var(--transition), width var(--transition);
    }

    .sb-brand-name {
        font-family: var(--font-serif);
        font-size: 1.15rem;
        font-weight: 700;
        font-style: italic;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.1;
        letter-spacing: -.01em;
    }

    .sb-brand-sub {
        font-size: .6rem;
        font-weight: 600;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: rgba(255,255,255,.28);
        white-space: nowrap;
        margin-top: 2px;
    }

    /* collapse button */
    .sb-collapse-btn {
        margin-left: auto;
        flex-shrink: 0;
        width: 26px; height: 26px;
        border-radius: 3px;
        border: 1px solid rgba(255,255,255,.1);
        background: transparent;
        color: rgba(255,255,255,.3);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: all var(--transition);
    }
    .sb-collapse-btn:hover {
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.7);
        border-color: rgba(255,255,255,.18);
    }
    .sb-collapse-btn svg { width: 10px; height: 10px; }

    .sb-collapse-wrap {
        padding: 8px 12px 4px;
    }

    .sb-collapse-wrap .sb-collapse-btn {
        margin-left: 0;
    }

    /* ─── SECTION LABEL ─── */
    .sb-section-label {
        font-size: .6rem;
        font-weight: 600;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: rgba(255,255,255,.2);
        padding: 14px 12px 6px;
        white-space: nowrap;
        overflow: hidden;
        transition: opacity var(--transition);
    }

    /* ─── NAV ─── */
    .sb-nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 4px 0 8px;
        scrollbar-width: none;
    }
    .sb-nav::-webkit-scrollbar { display: none; }

    .sb-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 3px;
        color: rgba(255,255,255,.38);
        text-decoration: none;
        font-size: .845rem;
        font-weight: 500;
        white-space: nowrap;
        position: relative;
        overflow: hidden;
        transition: all var(--transition);
        margin-bottom: 1px;
    }

    /* left accent bar (same as .opp-row::before) */
    .sb-link::before {
        content: '';
        position: absolute;
        left: 0; top: 6px; bottom: 6px;
        width: 2px;
        border-radius: 0 2px 2px 0;
        background: transparent;
        transition: background var(--transition);
    }

    .sb-link:hover {
        color: rgba(255,255,255,.78);
        background: rgba(255,255,255,.055);
        transform: translateX(2px);
    }

    .sb-link.active {
        color: #fff;
        background: rgba(255,255,255,.08);
        font-weight: 600;
    }

    .sb-link.active::before {
        background: var(--accent);
    }

    .sb-icon {
        width: 16px; height: 16px;
        flex-shrink: 0;
        opacity: .6;
        transition: opacity var(--transition);
    }
    .sb-link:hover  .sb-icon { opacity: .9; }
    .sb-link.active .sb-icon { opacity: 1; }

    .sb-label {
        transition: opacity var(--transition);
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
    }

    /* ─── FOOTER ─── */
    .sb-footer {
        border-top: 1px solid rgba(255,255,255,.07);
        padding: 10px 0 12px;
        flex-shrink: 0;
    }

    .sb-user-card {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 12px;
        border-radius: 3px;
        border: 1px solid rgba(255,255,255,.07);
        background: rgba(255,255,255,.035);
        margin-bottom: 4px;
        min-width: 0;
        overflow: hidden;
        transition: background var(--transition);
    }
    .sb-user-card:hover { background: rgba(255,255,255,.065); }

    .sb-avatar {
        width: 30px; height: 30px;
        border-radius: 3px;
        background: var(--accent);
        display: flex; align-items: center; justify-content: center;
        font-family: var(--font-serif);
        font-size: .95rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
        letter-spacing: -.01em;
    }

    .sb-user-info { overflow: hidden; flex: 1; min-width: 0; transition: opacity var(--transition); }

    .sb-user-name {
        font-size: .82rem;
        font-weight: 600;
        color: rgba(255,255,255,.78);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sb-user-role {
        font-size: .65rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(255,255,255,.22);
        white-space: nowrap;
    }

    /* ─── COLLAPSED ─── */
    body.admin-sidebar-collapsed .admin-sidebar { width: var(--sb-w-col); }

    body.admin-sidebar-collapsed .sb-brand-text,
    body.admin-sidebar-collapsed .sb-section-label,
    body.admin-sidebar-collapsed .sb-label,
    body.admin-sidebar-collapsed .sb-user-info {
        opacity: 0; width: 0; overflow: hidden; pointer-events: none;
    }

    body.admin-sidebar-collapsed .sb-brand        { justify-content: center; padding-right: 10px; }
    body.admin-sidebar-collapsed .sb-collapse-btn { margin-left: 0; }
    body.admin-sidebar-collapsed .sb-link         { justify-content: center; padding: 10px 0; gap: 0; color: rgba(255,255,255,.78); }
    body.admin-sidebar-collapsed .sb-link::before { display: none; }
    body.admin-sidebar-collapsed .sb-user-card    { justify-content: center; padding: 9px 0; gap: 0; }

    body.admin-sidebar-collapsed .sb-link .sb-icon,
    body.admin-sidebar-collapsed .sb-user-card .sb-avatar {
        opacity: 1;
    }

    body.admin-sidebar-collapsed .sb-link .sb-icon {
        width: 18px;
        height: 18px;
        margin: 0 auto;
    }

    body.admin-sidebar-collapsed .sb-link:hover { transform: none; color: #fff; }

    /* tooltip in collapsed mode */
    body.admin-sidebar-collapsed .sb-link[data-tooltip]::after {
        content: attr(data-tooltip);
        position: absolute;
        left: calc(100% + 10px);
        top: 50%;
        transform: translateY(-50%);
        background: var(--ink);
        color: rgba(255,255,255,.85);
        font-size: .75rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 3px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        border: 1px solid rgba(255,255,255,.1);
        transition: opacity .15s;
        z-index: 999;
    }
    body.admin-sidebar-collapsed .sb-link:hover[data-tooltip]::after { opacity: 1; }

    /* ═══════════════════════════════════════════════════════════
       MAIN CONTENT AREA
    ═══════════════════════════════════════════════════════════ */
    .admin-main {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    /* ═══════════════════════════════════════════════════════════
       TOPBAR — sticky, warm bg, same as opp-toolbar
    ═══════════════════════════════════════════════════════════ */
    .admin-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0 1.5rem;
        height: 58px;
        background: var(--bg);
        border-bottom: 1px solid var(--rule);
        backdrop-filter: blur(8px);
        flex-shrink: 0;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    /* mobile toggle — same style as opp filter-toggle */
    .sidebar-toggle {
        width: 34px; height: 34px;
        border: 1.5px solid var(--rule);
        border-radius: 3px;
        background: var(--bg);
        color: var(--ink-mid);
        display: none;
        align-items: center; justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: all var(--transition);
    }
    .sidebar-toggle:hover { border-color: var(--ink); background: var(--bg-warm); }
    .sidebar-toggle svg { width: 14px; height: 14px; }

    /* vertical divider */
    .topbar-divider {
        width: 1px;
        height: 20px;
        background: var(--rule);
        flex-shrink: 0;
    }

    /* page info */
    .topbar-page-info { min-width: 0; }

    .topbar-eyebrow {
        font-size: .6rem;
        font-weight: 600;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--ink-light);
        line-height: 1;
        margin-bottom: 2px;
    }

    .topbar-title {
        font-family: var(--font-serif);
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--ink);
        line-height: 1;
        letter-spacing: -.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* right side */
    .topbar-right {
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-shrink: 0;
    }

    /* admin chip — same styling as hero-eyebrow */
    .topbar-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .72rem;
        font-weight: 500;
        color: var(--ink-mid);
        border: 1.5px solid var(--rule);
        border-radius: 99px;
        padding: .3rem .85rem;
        background: var(--bg);
        white-space: nowrap;
    }

    .chip-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--green);
        box-shadow: 0 0 5px rgba(26,122,74,.5);
        animation: blink 2.5s infinite;
        flex-shrink: 0;
    }

    /* logout link — matches btn-ghost */
    .topbar-logout {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .78rem;
        font-weight: 600;
        color: var(--red);
        text-decoration: none;
        border: 1.5px solid rgba(176,48,48,.25);
        border-radius: 3px;
        padding: .38rem .85rem;
        background: transparent;
        transition: all var(--transition);
        white-space: nowrap;
    }
    .topbar-logout:hover {
        background: var(--red-dim);
        border-color: rgba(176,48,48,.45);
        color: var(--red);
    }
    .topbar-logout svg { width: 12px; height: 12px; }

    /* ═══════════════════════════════════════════════════════════
       CONTENT WRAPPER
    ═══════════════════════════════════════════════════════════ */
    .admin-content {
        padding: 2rem 1.75rem;
        flex: 1;
    }

    /* ═══════════════════════════════════════════════════════════
       BOOTSTRAP OVERRIDES — reskinned to match design system
    ═══════════════════════════════════════════════════════════ */
    .card {
        background: #fff;
        border: 1px solid var(--rule-light);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow);
    }

    .card-header {
        background: var(--bg-warm);
        border-bottom: 1px solid var(--rule);
        border-radius: var(--radius-md) var(--radius-md) 0 0 !important;
        font-family: var(--font-serif);
        font-size: 1rem;
        font-weight: 700;
        color: var(--ink);
        padding: .9rem 1.25rem;
        letter-spacing: -.01em;
    }

    .card-body { padding: 1.25rem; }

    .table > :not(caption) > * > * {
        padding: 10px 14px;
        vertical-align: middle;
        border-bottom-color: var(--rule-light);
    }

    .table thead th {
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--ink-light);
        border-bottom: 1.5px solid var(--rule) !important;
        background: var(--bg-warm);
    }

    .table tbody tr { transition: background var(--transition); }
    .table tbody tr:hover { background: var(--bg); }

    .form-control, .form-select {
        border: 1.5px solid var(--rule);
        border-radius: 3px;
        padding: .65rem .9rem;
        font-size: .875rem;
        font-family: var(--font-sans);
        color: var(--ink);
        background: #fff;
        box-shadow: none;
        transition: border-color var(--transition), box-shadow var(--transition);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--ink);
        box-shadow: 0 0 0 3px rgba(24,22,15,.06);
    }
    .form-control::placeholder { color: var(--ink-light); }

    .form-label {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--ink-mid);
        margin-bottom: .45rem;
    }

    /* btn-primary → ink dark */
    .btn-primary {
        background: var(--ink);
        border: none;
        color: #fff;
        border-radius: 3px;
        font-family: var(--font-sans);
        font-size: .845rem;
        font-weight: 600;
        padding: .6rem 1.25rem;
        transition: all var(--transition);
    }
    .btn-primary:hover, .btn-primary:focus {
        background: #2c2a22;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(24,22,15,.22);
        border: none;
    }
    .btn-primary:active { transform: scale(.98); }

    /* btn-secondary → ghost */
    .btn-secondary, .btn-outline-secondary {
        background: transparent;
        border: 1.5px solid var(--rule);
        color: var(--ink-mid);
        border-radius: 3px;
        font-family: var(--font-sans);
        font-size: .845rem;
        font-weight: 500;
        padding: .6rem 1.25rem;
        transition: all var(--transition);
    }
    .btn-secondary:hover, .btn-outline-secondary:hover {
        border-color: var(--ink-mid);
        background: var(--bg-warm);
        color: var(--ink);
    }

    /* btn-danger */
    .btn-danger {
        background: var(--red);
        border: none;
        border-radius: 3px;
        font-family: var(--font-sans);
        font-size: .845rem;
        font-weight: 600;
        padding: .6rem 1.25rem;
        color: #fff;
        transition: all var(--transition);
    }
    .btn-danger:hover { background: #922020; transform: translateY(-1px); }

    /* btn-sm override */
    .btn-sm {
        padding: .38rem .8rem;
        font-size: .78rem;
    }

    /* alerts */
    .alert {
        border-radius: 3px;
        font-size: .845rem;
        border: 1px solid transparent;
    }
    .alert-success { background: var(--green-dim); color: var(--green); border-color: rgba(26,122,74,.2); }
    .alert-danger  { background: var(--red-dim);   color: var(--red);   border-color: rgba(176,48,48,.2); }
    .alert-warning { background: var(--amber-dim); color: var(--amber); border-color: rgba(184,134,11,.2); }
    .alert-info    { background: var(--sky-dim);   color: var(--sky);   border-color: rgba(26,95,200,.2); }

    /* badges */
    .badge {
        font-family: var(--font-sans);
        font-size: .62rem;
        font-weight: 600;
        letter-spacing: .06em;
        border-radius: 2px;
        padding: .2rem .55rem;
    }
    .badge.bg-success { background: var(--green-dim) !important; color: var(--green); }
    .badge.bg-danger  { background: var(--red-dim)   !important; color: var(--red); }
    .badge.bg-warning { background: var(--amber-dim) !important; color: var(--amber); }
    .badge.bg-info    { background: var(--sky-dim)   !important; color: var(--sky); }
    .badge.bg-primary { background: var(--accent-dim)!important; color: var(--accent); }
    .badge.bg-secondary { background: var(--bg-warm) !important; color: var(--ink-light); border: 1px solid var(--rule); }

    /* ═══════════════════════════════════════════════════════════
       BACKDROP (mobile)
    ═══════════════════════════════════════════════════════════ */
    .admin-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(24,22,15,.45);
        z-index: 190;
        opacity: 0;
        pointer-events: none;
        transition: opacity .22s ease;
        backdrop-filter: blur(2px);
    }

    /* ═══════════════════════════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════════════════════════ */
    @media (max-width: 992px) {
        .admin-sidebar {
            position: fixed;
            left: 0; top: 0;
            height: 100%;
            transform: translateX(-110%);
            transition: transform .26s cubic-bezier(.4,0,.2,1), width var(--transition);
        }
        body.admin-sidebar-open .admin-sidebar {
            transform: translateX(0);
        }
        body.admin-sidebar-open .admin-backdrop {
            opacity: 1;
            pointer-events: auto;
        }
        .sidebar-toggle { display: inline-flex; }

        /* reset collapsed on mobile */
        body.admin-sidebar-collapsed .admin-sidebar { width: var(--sb-w); }
        body.admin-sidebar-collapsed .sb-brand-text,
        body.admin-sidebar-collapsed .sb-section-label,
        body.admin-sidebar-collapsed .sb-label,
        body.admin-sidebar-collapsed .sb-user-info {
            opacity: 1; width: auto; pointer-events: auto;
        }
        body.admin-sidebar-collapsed .sb-brand     { justify-content: flex-start; }
        body.admin-sidebar-collapsed .sb-link      { justify-content: flex-start; padding: 9px 12px; }
        body.admin-sidebar-collapsed .sb-link::before { display: block; }
        body.admin-sidebar-collapsed .sb-user-card { justify-content: flex-start; padding: 10px 12px; }
        body.admin-sidebar-collapsed .sb-link[data-tooltip]::after { display: none; }

        .admin-content { padding: 1.25rem 1rem; }
        .admin-topbar  { padding: 0 1rem; }
    }

    @media (max-width: 576px) {
        .topbar-chip .chip-label { display: none; }
    }

    /* ── GLOBAL ADMIN RESPONSIVE HELPERS ── */
    .admin-content img,
    .admin-content video,
    .admin-content canvas {
        max-width: 100%;
        height: auto;
    }

    .admin-content .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 768px) {
        .admin-topbar {
            height: auto;
            padding: 0.75rem 1rem;
            flex-wrap: wrap;
        }
        .topbar-left { flex: 1 1 100%; }
        .topbar-right { width: 100%; justify-content: space-between; }
        .admin-content { padding: 1rem; }
    }
    </style>
</head>
<body class="admin-shell">

<div class="admin-backdrop" id="adminBackdrop"></div>

<div class="admin-app">

    <!-- ═══════════════════ SIDEBAR ═══════════════════ -->
    <aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="Admin navigation">
        <div class="sb-inner">

            <!-- Brand -->
            <div class="sb-brand">
                <div class="sb-logo-wrap">
                    <span class="sb-brand-dot"></span>
                </div>
                <div class="sb-brand-text">
                    <div class="sb-brand-name">StudentBlog</div>
                    <div class="sb-brand-sub">Admin Console</div>
                </div>
            </div>

            <!-- Nav -->
            <nav class="sb-nav">
                <div class="sb-section-label">Navigation</div>

                <?php foreach ($nav_items as $item):
                    $is_active = ($active_nav === $item['href'] || $active_nav === basename($item['href']));
                    $href = $item['href'];
                    if ($is_super_admin_section && empty($super_admin_nav_override) && strpos($href, '../super_admin/') !== 0) {
                        $href = $admin_link_prefix . $href;
                    }
                ?>
                <a class="sb-link <?php echo $is_active ? 'active' : ''; ?>"
                   href="<?php echo htmlspecialchars($href); ?>"
                   data-tooltip="<?php echo htmlspecialchars($item['label']); ?>">
                    <span class="sb-icon"><?php echo sb_icon($item['icon']); ?></span>
                    <span class="sb-label"><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
                <?php endforeach; ?>

                <div class="sb-collapse-wrap">
                    <button class="sb-collapse-btn" id="sidebarCollapse" type="button" aria-label="Collapse sidebar">
                        <svg id="collapseIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </nav>

            <!-- Footer -->
            <div class="sb-footer">
                <div class="sb-user-card">
                    <div class="sb-avatar">
                        <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                    </div>
                    <div class="sb-user-info">
                        <div class="sb-user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                        <div class="sb-user-role"><?php echo htmlspecialchars($admin_role_label); ?></div>
                    </div>
                </div>
                <a class="sb-link" href="<?php echo htmlspecialchars($logout_url); ?>" data-tooltip="Logout">
                    <span class="sb-icon"><?php echo sb_icon('logout'); ?></span>
                    <span class="sb-label">Logout</span>
                </a>
            </div>

        </div>
    </aside>

    <!-- ═══════════════════ MAIN ═══════════════════ -->
    <div class="admin-main">

        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Open navigation">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M2 4h12M2 8h12M2 12h12" stroke-linecap="round"/>
                    </svg>
                </button>
                <div class="topbar-divider"></div>
                <div class="topbar-page-info">
                    <div class="topbar-eyebrow"><?php echo htmlspecialchars($topbar_subtitle); ?></div>
                    <h1 class="topbar-title"><?php echo htmlspecialchars($topbar_title); ?></h1>
                </div>
            </div>

            <div class="topbar-right">
                <div class="topbar-chip">
                    <span class="chip-dot"></span>
                    <span class="chip-label"><?php echo htmlspecialchars($admin_name . ' · ' . $admin_role_label); ?></span>
                </div>
                <a href="<?php echo htmlspecialchars($logout_url); ?>" class="topbar-logout">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                    Logout
                </a>
            </div>
        </header>

        <!-- Page content injected here -->
        <main class="admin-content">

