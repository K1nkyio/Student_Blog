<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BLOG_SYSTEM')) {
    define('BLOG_SYSTEM', true);
}
require_once __DIR__ . '/functions.php';

$current_user = function_exists('get_logged_in_user') ? get_logged_in_user() : null;
$unread_count = $current_user ? get_unread_notifications_count() : 0;
$current_page = basename($_SERVER['PHP_SELF']);
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cart_count += (int)$qty;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= function_exists('generate_csrf_token') ? generate_csrf_token() : '' ?>">
    <meta name="description" content="<?= isset($meta_description) ? htmlspecialchars($meta_description) : SITE_NAME . ' - A space for students to learn about finance, innovation, technology, skills, and opportunities.' ?>">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($extra_head)) echo $extra_head; ?>
    <style>
        :root {
            --ink: #141414;
            --ink-muted: #6b6b6b;
            --ink-faint: #a8a8a8;
            --surface: #ffffff;
            --surface-raised: #fafaf9;
            --surface-hover: #f4f3f0;
            --border: #e8e6e1;
            --border-strong: #d0cec8;
            --accent: #2563eb;
            --accent-soft: #eff4ff;
            --accent-warm: #d97706;
            --danger: #dc2626;
            --success: #16a34a;
            --nav-h: 68px;
            --font-sans: 'DM Sans', system-ui, sans-serif;
            --font-serif: 'Instrument Serif', Georgia, serif;
            --r: 10px;
            --r-sm: 6px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
            --shadow-lg: 0 12px 40px rgba(0,0,0,0.12), 0 4px 14px rgba(0,0,0,0.06);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-sans);
            background: var(--surface-raised);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── NAV SHELL ── */
        .main-nav {
            position: sticky;
            top: 0;
            z-index: 200;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border-bottom: 1px solid var(--border);
            height: var(--nav-h);
        }

        .nav-container {
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 100%;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 2rem;
        }

        /* ── BRAND ── */
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
            color: var(--ink);
            flex-shrink: 0;
        }

        .nav-brand-icon {
            width: 34px;
            height: 34px;
            background: var(--ink);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.85rem;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .nav-brand:hover .nav-brand-icon {
            background: var(--accent);
            transform: rotate(-6deg) scale(1.05);
        }

        .nav-brand-text {
            font-family: var(--font-serif);
            font-size: 1.35rem;
            letter-spacing: -0.01em;
            color: var(--ink);
            line-height: 1;
        }

        .nav-brand-text em {
            font-style: italic;
            color: var(--accent);
        }

        /* ── CENTRE LINKS ── */
        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.9rem;
            text-decoration: none;
            color: var(--ink-muted);
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--r-sm);
            transition: color 0.15s ease, background 0.15s ease;
            white-space: nowrap;
        }

        .nav-link i {
            font-size: 0.8rem;
            opacity: 0.7;
            transition: opacity 0.15s;
        }

        .nav-link:hover {
            color: var(--ink);
            background: var(--surface-hover);
        }

        .nav-link:hover i { opacity: 1; }

        .nav-link.active {
            color: var(--accent);
            background: var(--accent-soft);
            font-weight: 600;
        }

        .nav-link.active i { opacity: 1; color: var(--accent); }
        .nav-link.mobile-only { display: none; }
        .nav-link.nav-link-auth {
            justify-content: center;
            border: 1px solid var(--border);
            margin-top: 0.35rem;
        }
        .nav-link.nav-link-auth:hover { border-color: var(--border-strong); }
        .nav-link.nav-link-auth--solid {
            background: var(--ink);
            color: #fff;
        }
        .nav-link.nav-link-auth--solid:hover {
            background: #2a2a2a;
            color: #fff;
        }

        /* ── ACTIONS ── */
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        /* Icon button (cart, bookmarks, bell) */
        .nav-icon-btn {
            position: relative;
            width: 38px;
            height: 38px;
            border-radius: var(--r-sm);
            background: transparent;
            color: var(--ink-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: color 0.15s, background 0.15s;
            border: none;
            cursor: pointer;
        }

        .nav-icon-btn:hover {
            color: var(--ink);
            background: var(--surface-hover);
        }

        .nav-badge {
            position: absolute;
            top: 3px;
            right: 3px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            background: var(--danger);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--surface);
            line-height: 1;
        }

        /* Divider between icon cluster and user */
        .nav-divider {
            width: 1px;
            height: 22px;
            background: var(--border);
            margin: 0 0.25rem;
        }

        /* ── AUTH BUTTONS ── */
        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.1rem;
            font-family: var(--font-sans);
            font-size: 0.865rem;
            font-weight: 500;
            border-radius: var(--r-sm);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            border: none;
        }

        .btn-nav-ghost {
            background: transparent;
            color: var(--ink-muted);
            border: 1px solid var(--border);
        }

        .btn-nav-ghost:hover {
            color: var(--ink);
            border-color: var(--border-strong);
            background: var(--surface-hover);
        }

        .btn-nav-solid {
            background: var(--ink);
            color: #fff;
        }

        .btn-nav-solid:hover {
            background: #2a2a2a;
            box-shadow: var(--shadow-sm);
            transform: translateY(-1px);
        }

        /* ── USER PILL ── */
        .nav-user-pill {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.3rem 0.65rem 0.3rem 0.3rem;
            border-radius: 100px;
            background: var(--surface-hover);
            text-decoration: none;
            color: var(--ink);
            transition: background 0.15s;
            cursor: pointer;
            border: 1px solid var(--border);
        }

        .nav-user-pill:hover { background: #ebe9e4; }

        .nav-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }

        .nav-avatar-initial {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--ink);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0;
        }

        .nav-username {
            font-size: 0.85rem;
            font-weight: 500;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nav-caret {
            font-size: 0.6rem;
            color: var(--ink-faint);
            transition: transform 0.2s;
        }

        .dropdown.open .nav-caret { transform: rotate(180deg); }

        /* ── DROPDOWN ── */
        .dropdown { position: relative; }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--shadow-lg);
            min-width: 210px;
            padding: 0.4rem;
            display: none;
            z-index: 300;
            animation: popIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes popIn {
            from { opacity: 0; transform: translateY(-6px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .dropdown.open .dropdown-menu { display: block; }

        .dropdown-section-label {
            padding: 0.55rem 0.75rem 0.2rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ink-faint);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--ink-muted);
            text-decoration: none;
            border-radius: var(--r-sm);
            transition: color 0.12s, background 0.12s;
        }

        .dropdown-item .di-icon {
            width: 18px;
            text-align: center;
            font-size: 0.8rem;
            flex-shrink: 0;
            color: var(--ink-faint);
            transition: color 0.12s;
        }

        .dropdown-item:hover {
            background: var(--surface-hover);
            color: var(--ink);
        }

        .dropdown-item:hover .di-icon { color: var(--ink-muted); }

        .dropdown-item.danger { color: var(--danger); }
        .dropdown-item.danger .di-icon { color: var(--danger); opacity: 0.7; }
        .dropdown-item.danger:hover { background: #fef2f2; color: var(--danger); }

        .dropdown-divider {
            height: 1px;
            background: var(--border);
            margin: 0.35rem 0;
        }

        /* ── NOTIFICATIONS PANEL ── */
        .notif-panel {
            min-width: 340px;
            max-width: 380px;
        }

        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 0.9rem 0.65rem;
        }

        .notif-header h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
        }

        .notif-mark-read {
            font-size: 0.775rem;
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        .notif-mark-read:hover { text-decoration: underline; }

        .notif-list {
            max-height: 320px;
            overflow-y: auto;
            padding: 0 0.4rem;
        }

        .notif-list::-webkit-scrollbar { width: 4px; }
        .notif-list::-webkit-scrollbar-track { background: transparent; }
        .notif-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.65rem 0.5rem;
            border-radius: var(--r-sm);
            text-decoration: none;
            color: var(--ink-muted);
            transition: background 0.12s;
            position: relative;
        }

        .notif-item:hover { background: var(--surface-hover); color: var(--ink); }

        .notif-item.unread { background: #f5f8ff; }
        .notif-item.unread:hover { background: #edf2ff; }

        .notif-dot-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: #fff;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notif-body { flex: 1; min-width: 0; }

        .notif-msg {
            font-size: 0.825rem;
            line-height: 1.45;
            color: var(--ink);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notif-time {
            font-size: 0.75rem;
            color: var(--ink-faint);
            margin-top: 3px;
        }

        .notif-unread-pip {
            position: absolute;
            top: 50%;
            right: 0.5rem;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
        }

        .notif-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2.5rem 1rem;
            gap: 0.5rem;
            color: var(--ink-faint);
            font-size: 0.85rem;
        }

        .notif-empty i { font-size: 1.5rem; }

        .notif-footer {
            border-top: 1px solid var(--border);
            padding: 0.55rem 0.9rem;
        }

        .notif-view-all {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.825rem;
            font-weight: 500;
            color: var(--ink-muted);
            text-decoration: none;
            transition: color 0.12s;
        }

        .notif-view-all:hover { color: var(--accent); }

        /* ── MOBILE TOGGLE ── */
        .nav-toggle {
            display: none;
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            background: transparent;
            border-radius: var(--r-sm);
            color: var(--ink-muted);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: color 0.15s, background 0.15s;
        }

        .nav-toggle:hover { background: var(--surface-hover); color: var(--ink); }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .nav-container { grid-template-columns: auto auto; }
            .nav-toggle { display: flex; }

            .nav-links {
                display: none;
                position: absolute;
                top: var(--nav-h);
                left: 0; right: 0;
                flex-direction: column;
                align-items: stretch;
                background: var(--surface);
                border-bottom: 1px solid var(--border);
                padding: 0.75rem 1rem 1rem;
                gap: 0.2rem;
                box-shadow: var(--shadow-md);
                z-index: 199;
                animation: slideDown 0.2s ease;
            }

            .nav-links.open { display: flex; }

            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-8px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            .nav-link { justify-content: flex-start; padding: 0.65rem 0.75rem; }
            .nav-username { display: none; }
            .nav-link.mobile-only { display: flex; }
            .nav-actions .dropdown#userDropdown { display: none; }
            .nav-actions .btn-nav { display: none; }
        }

        @media (max-width: 480px) {
            .nav-container { padding: 0 1rem; }
            .notif-panel { min-width: 290px; max-width: 320px; }
        }

        /* ── GLOBAL RESPONSIVE HELPERS ── */
        img, video, canvas { max-width: 100%; height: auto; }
        table { width: 100%; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        pre { white-space: pre-wrap; word-break: break-word; }

        @media (max-width: 768px) {
            .nav-container { gap: 1rem; }
            .nav-actions { gap: 0.35rem; }
            .nav-brand-text { font-size: 1.2rem; }
            .dropdown-menu { max-width: calc(100vw - 2rem); }
        }
        @media (max-width: 640px) {
            .nav-container { grid-template-columns: 1fr auto; }
            .nav-brand-text { font-size: 1.1rem; }
            .nav-actions { flex-wrap: wrap; justify-content: flex-end; }
            .nav-icon-btn { width: 34px; height: 34px; }
            .btn-nav { padding: 0.45rem 0.85rem; font-size: 0.82rem; }
            .nav-user-pill { padding: 0.2rem 0.4rem; }
            .nav-avatar, .nav-avatar-initial { width: 24px; height: 24px; }
            .nav-divider { display: none; }
        }
        @media (max-width: 420px) {
            .nav-brand-text { display: none; }
            .nav-container { gap: 0.6rem; }
        }
    </style>
</head>
<body>
<nav class="main-nav" id="mainNav">
    <div class="nav-container">

        <!-- Brand -->
        <a href="index.php" class="nav-brand">
            <div class="nav-brand-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <span class="nav-brand-text"><?= SITE_NAME ?></span>
        </a>

        <!-- Centre Links -->
        <div class="nav-links" id="navLinks">
            <a href="index.php" class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-house"></i> Home
            </a>
            <a href="explore.php" class="nav-link <?= $current_page === 'explore.php' ? 'active' : '' ?>">
                <i class="fas fa-compass"></i> Explore
            </a>
            <a href="safespeak.php" class="nav-link <?= $current_page === 'safespeak.php' ? 'active' : '' ?>">
                <i class="fas fa-shield-halved"></i> SafeSpeak
            </a>
            <a href="marketplace.php" class="nav-link <?= $current_page === 'marketplace.php' ? 'active' : '' ?>">
                <i class="fas fa-store"></i> Marketplace
            </a>
            <a href="opportunities.php" class="nav-link <?= $current_page === 'opportunities.php' ? 'active' : '' ?>">
                <i class="fas fa-briefcase"></i> Opportunities
            </a>
            <a href="events.php" class="nav-link <?= $current_page === 'events.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-days"></i> Events
            </a>
            <?php if ($current_user): ?>
            <a href="profile.php" class="nav-link mobile-only">
                <i class="fas fa-circle-user"></i> My Account
            </a>
            <?php else: ?>
            <a href="login.php" class="nav-link mobile-only nav-link-auth">
                <i class="fas fa-right-to-bracket"></i> Sign in
            </a>
            <a href="register.php" class="nav-link mobile-only nav-link-auth nav-link-auth--solid">
                Get started <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Right Actions -->
        <div class="nav-actions">

            <!-- Cart -->
            <a href="cart.php" class="nav-icon-btn" title="Cart">
                <i class="fas fa-bag-shopping"></i>
                <?php if ($cart_count > 0): ?>
                <span class="nav-badge"><?= $cart_count > 9 ? '9+' : $cart_count ?></span>
                <?php endif; ?>
            </a>

            <?php if ($current_user): ?>

                <!-- Bookmarks -->
                <a href="bookmarks.php" class="nav-icon-btn" title="Bookmarks">
                    <i class="fas fa-bookmark"></i>
                </a>

                <!-- Notifications -->
                <div class="dropdown" id="notifDropdown">
                    <button class="nav-icon-btn" title="Notifications" onclick="toggleDropdown('notifDropdown'); return false;">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="nav-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="dropdown-menu notif-panel">
                        <div class="notif-header">
                            <h4>Notifications<?php if ($unread_count > 0): ?> <span style="background:#eff4ff;color:var(--accent);font-size:0.7rem;padding:2px 7px;border-radius:20px;font-weight:600;"><?= $unread_count ?></span><?php endif; ?></h4>
                            <?php if ($unread_count > 0): ?>
                            <a href="notifications.php?action=mark_all_read" class="notif-mark-read">Mark all read</a>
                            <?php endif; ?>
                        </div>

                        <div class="notif-list">
                            <?php
                            $recent_notifs = $conn->prepare("SELECT id, type, message, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                            $recent_notifs->bind_param("i", $current_user['id']);
                            $recent_notifs->execute();
                            $notif_result = $recent_notifs->get_result();

                            $icon_map = [
                                'comment'  => ['fas fa-comment', '#4f46e5'],
                                'like'     => ['fas fa-heart',   '#e11d48'],
                                'follow'   => ['fas fa-user-plus','#059669'],
                                'mention'  => ['fas fa-at',      '#d97706'],
                                'post'     => ['fas fa-newspaper','#7c3aed'],
                                'system'   => ['fas fa-circle-info','#6b7280'],
                                'bookmark' => ['fas fa-bookmark','#ea580c'],
                            ];

                            if ($notif_result->num_rows > 0):
                                while ($n = $notif_result->fetch_assoc()):
                                    $unread = is_null($n['read_at']);
                                    [$ic, $clr] = $icon_map[$n['type']] ?? ['fas fa-bell', '#6b7280'];
                            ?>
                            <a href="notifications.php" class="notif-item <?= $unread ? 'unread' : '' ?>">
                                <div class="notif-dot-icon" style="background:<?= $clr ?>20;color:<?= $clr ?>">
                                    <i class="<?= $ic ?>"></i>
                                </div>
                                <div class="notif-body">
                                    <div class="notif-msg"><?= htmlspecialchars(substr($n['message'], 0, 90)) . (strlen($n['message']) > 90 ? '…' : '') ?></div>
                                    <div class="notif-time"><?= time_ago($n['created_at']) ?></div>
                                </div>
                                <?php if ($unread): ?><div class="notif-unread-pip"></div><?php endif; ?>
                            </a>
                            <?php endwhile;
                            else: ?>
                            <div class="notif-empty">
                                <i class="fas fa-bell-slash"></i>
                                <span>All caught up!</span>
                            </div>
                            <?php endif; ?>
                            <?php $recent_notifs->close(); ?>
                        </div>

                        <div class="notif-footer">
                            <a href="notifications.php" class="notif-view-all">
                                View all notifications <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="nav-divider"></div>

                <!-- User Dropdown -->
                <div class="dropdown" id="userDropdown">
                    <div class="nav-user-pill" onclick="toggleDropdown('userDropdown')">
                        <?php if (!empty($current_user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($current_user['avatar']) ?>" alt="" class="nav-avatar">
                        <?php else: ?>
                            <div class="nav-avatar-initial"><?= strtoupper(substr($current_user['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <span class="nav-username"><?= htmlspecialchars($current_user['username']) ?></span>
                        <i class="fas fa-chevron-down nav-caret"></i>
                    </div>

                    <div class="dropdown-menu" style="min-width:220px;">
                        <div style="padding:0.6rem 0.75rem 0.3rem; display:flex; align-items:center; gap:0.6rem;">
                            <?php if (!empty($current_user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($current_user['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" alt="">
                            <?php else: ?>
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--ink);color:#fff;font-size:0.8rem;font-weight:600;display:flex;align-items:center;justify-content:center;"><?= strtoupper(substr($current_user['username'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-size:0.85rem;font-weight:600;color:var(--ink);"><?= htmlspecialchars($current_user['username']) ?></div>
                                <?php if (!empty($current_user['email'])): ?>
                                <div style="font-size:0.75rem;color:var(--ink-faint);"><?= htmlspecialchars($current_user['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>

                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-circle-user di-icon"></i> My Profile
                        </a>
                        <a href="bookmarks.php" class="dropdown-item">
                            <i class="fas fa-bookmark di-icon"></i> Bookmarks
                        </a>
                        <a href="my-comments.php" class="dropdown-item">
                            <i class="fas fa-comments di-icon"></i> My Comments
                        </a>
                        <a href="following.php" class="dropdown-item">
                            <i class="fas fa-users di-icon"></i> Following
                        </a>

                        <?php if (is_author()): ?>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-section-label">Content</div>
                        <a href="admin/posts.php" class="dropdown-item">
                            <i class="fas fa-pen-to-square di-icon"></i> My Posts
                        </a>
                        <a href="admin/new-post.php" class="dropdown-item">
                            <i class="fas fa-plus di-icon"></i> New Post
                        </a>
                        <?php endif; ?>

                        <?php if (is_admin()): ?>
                        <div class="dropdown-divider"></div>
                        <a href="admin/index.php" class="dropdown-item">
                            <i class="fas fa-sliders di-icon"></i> Admin Panel
                        </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>
                        <form id="logoutForm" method="POST" action="logout.php" style="display:none;">
                            <input type="hidden" name="csrf_token" value="<?= function_exists('generate_csrf_token') ? generate_csrf_token() : '' ?>">
                        </form>
                        <a href="#" onclick="document.getElementById('logoutForm').submit(); return false;" class="dropdown-item danger">
                            <i class="fas fa-arrow-right-from-bracket di-icon"></i> Sign out
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <a href="login.php" class="btn-nav btn-nav-ghost">Sign in</a>
                <a href="register.php" class="btn-nav btn-nav-solid">
                    Get started <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
                </a>
            <?php endif; ?>

            <button class="nav-toggle" id="navToggle" onclick="toggleMobileNav()" aria-label="Menu">
                <i class="fas fa-bars" id="toggleIcon"></i>
            </button>
        </div>
    </div>
</nav>

<script>
(function () {
    function toggleDropdown(id) {
        const el = document.getElementById(id);
        const isOpen = el.classList.contains('open');
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
        if (!isOpen) el.classList.add('open');
    }

    function toggleMobileNav() {
        const links = document.getElementById('navLinks');
        const icon = document.getElementById('toggleIcon');
        const open = links.classList.toggle('open');
        icon.className = open ? 'fas fa-xmark' : 'fas fa-bars';
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
        }
        if (!e.target.closest('#navLinks') && !e.target.closest('#navToggle') && window.innerWidth <= 900) {
            const links = document.getElementById('navLinks');
            if (links) {
                links.classList.remove('open');
                document.getElementById('toggleIcon').className = 'fas fa-bars';
            }
        }
    });

    window.toggleDropdown = toggleDropdown;
    window.toggleMobileNav = toggleMobileNav;

    // Close mobile nav on link click
    document.querySelectorAll('.nav-link').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth <= 900) {
                document.getElementById('navLinks').classList.remove('open');
                document.getElementById('toggleIcon').className = 'fas fa-bars';
            }
        });
    });
})();
</script>
