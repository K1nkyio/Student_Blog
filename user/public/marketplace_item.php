<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';

$conn->query("CREATE TABLE IF NOT EXISTS marketplace_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    item_condition VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
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
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS marketplace_item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES marketplace_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS marketplace_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES marketplace_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$columns = ['item_condition','contact','link','stock_status','variant','delivery_estimate','features','specifications','materials','usage_instructions'];
foreach ($columns as $col) {
    $chk = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE '$col'");
    if ($chk && $chk->num_rows === 0) {
        $type = in_array($col, ['features','specifications','materials','usage_instructions']) ? 'TEXT' : (in_array($col, ['link']) ? 'VARCHAR(500)' : 'VARCHAR(255)');
        $conn->query("ALTER TABLE marketplace_items ADD COLUMN $col $type DEFAULT NULL");
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: marketplace.php'); exit(); }

$commentError = $commentSuccess = $cartMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_to_cart' || $action === 'buy_now') {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = 0;
        $_SESSION['cart'][$id] += $quantity;
        $cartMessage = $action === 'buy_now' ? 'Item added to cart. Proceed to checkout when ready.' : 'Item added to cart successfully.';
    }
    if ($action === 'comment') {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') {
            $commentError = 'Comment is required.';
        } else {
            if ($name === '') $name = 'Anonymous';
            $stmt = $conn->prepare("INSERT INTO marketplace_comments (item_id, name, email, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $id, $name, $email, $comment);
            if ($stmt->execute()) $commentSuccess = 'Comment posted.';
            else $commentError = 'Failed to post comment.';
            $stmt->close();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM marketplace_items WHERE id = ? AND LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open')");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

$imageStmt = $conn->prepare("SELECT image_path FROM marketplace_item_images WHERE item_id = ? ORDER BY id ASC");
$imageStmt->bind_param("i", $id);
$imageStmt->execute();
$imagesResult = $imageStmt->get_result();
$images = [];
while ($r = $imagesResult->fetch_assoc()) $images[] = $r['image_path'];
$imageStmt->close();

$commentStmt = $conn->prepare("SELECT name, comment, created_at FROM marketplace_comments WHERE item_id = ? ORDER BY created_at DESC");
$commentStmt->bind_param("i", $id);
$commentStmt->execute();
$comments = $commentStmt->get_result();
$commentStmt->close();

$recentPoolStmt = $conn->prepare("SELECT mi.*, (SELECT image_path FROM marketplace_item_images WHERE item_id = mi.id ORDER BY id ASC LIMIT 1) AS image_path FROM marketplace_items mi WHERE mi.id != ? AND LOWER(COALESCE(NULLIF(mi.status, ''), 'active')) IN ('active','open') ORDER BY mi.created_at DESC LIMIT 4");
$recentPoolStmt->bind_param("i", $id);
$recentPoolStmt->execute();
$recentPool = [];
$r2 = $recentPoolStmt->get_result();
while ($row = $r2->fetch_assoc()) $recentPool[] = $row;
$recentPoolStmt->close();

if (!$item) { header('Location: marketplace.php'); exit(); }

$isUrgent      = !empty($item['stock_status']) && strtolower(trim($item['stock_status'])) === 'urgent';
$isNegotiable  = !empty($item['stock_status']) && strtolower(trim($item['stock_status'])) === 'negotiable';
$sellerName    = 'Campus Seller';
$sellerInitials= 'CS';
$phoneDigits   = !empty($item['contact']) ? preg_replace('/\D+/', '', $item['contact']) : '';
$sellerNumber  = $item['contact'] ?: '';

$page_title      = htmlspecialchars($item['title']) . ' — Marketplace';
$meta_description = 'Marketplace item details.';

include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS — exact match opportunities.php
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

  --max-w:    1360px;
  --gutter:   1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
  --shadow:   0 1px 3px rgba(24,22,15,.06), 0 4px 16px rgba(24,22,15,.06);
  --radius:   4px;
  --radius-md: 6px;
}

@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
img { display: block; max-width: 100%; }
button { font-family: inherit; }

/* ═══════════════════════════════════════════
   HERO STRIP — same dark-ink as opp-hero
═══════════════════════════════════════════ */
.item-hero {
  background: var(--ink);
  color: #fff;
  padding: 2rem var(--gutter) 1.75rem;
  position: relative;
  overflow: hidden;
}

/* watermark */
.item-hero::before {
  content: 'Market.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.25rem;
  font-family: var(--font-serif);
  font-size: clamp(5rem, 13vw, 10rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.03);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* dot grid */
.item-hero::after {
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
  animation: slideUp .5s ease both;
}
@keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

/* breadcrumb in hero */
.hero-crumbs {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .72rem;
  color: rgba(255,255,255,.35);
  margin-bottom: .85rem;
}
.hero-crumbs a {
  color: rgba(255,255,255,.45);
  transition: color var(--transition);
}
.hero-crumbs a:hover { color: #fff; }
.hero-crumbs svg { width: 10px; height: 10px; opacity: .4; }

/* eyebrow — same live-dot pattern */
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.4);
  padding: .26rem .78rem;
  border-radius: 2px;
  margin-bottom: .9rem;
}
.live-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  box-shadow: 0 0 6px var(--accent);
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

.hero-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 4vw, 3rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em;
  color: #fff;
  margin-bottom: .55rem;
  max-width: 70ch;
}

.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem 1.25rem;
  font-size: .8rem;
  color: rgba(255,255,255,.38);
}
.hero-meta-item { display: inline-flex; align-items: center; gap: .28rem; }
.hero-meta-item svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   ALERT BANNER
═══════════════════════════════════════════ */
.alert-wrap {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 1rem var(--gutter) 0;
}
.alert-banner {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .85rem 1rem;
  border-radius: var(--radius-md);
  font-size: .845rem;
  font-weight: 500;
}
.alert-banner svg { width: 14px; height: 14px; flex-shrink: 0; }
.alert-banner--success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(26,122,74,.18); }
.alert-banner--error   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(176,48,48,.18); }

/* ═══════════════════════════════════════════
   PAGE BODY
═══════════════════════════════════════════ */
.item-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2rem var(--gutter) 6rem;
}

/* ── Main layout grid ── */
.item-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: 2.5rem;
  align-items: start;
  margin-bottom: 2.5rem;
}

/* ═══════════════════════════════════════════
   GALLERY
═══════════════════════════════════════════ */
.gallery { display: flex; flex-direction: column; gap: .85rem; }

.gallery-hero {
  position: relative;
  aspect-ratio: 5/4;
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--bg-warm);
  cursor: zoom-in;
  border: 1px solid var(--rule-light);
}
.gallery-hero img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform .5s ease;
}
.gallery-hero:hover img { transform: scale(1.04); }

/* gradient overlay on hover */
.gallery-hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(24,22,15,.28) 0%, transparent 55%);
  opacity: 0;
  transition: opacity .3s;
  pointer-events: none;
}
.gallery-hero:hover .gallery-hero-overlay { opacity: 1; }

/* badge stack top-left */
.gallery-badges {
  position: absolute;
  top: 10px; left: 10px;
  display: flex; gap: .35rem; flex-wrap: wrap;
  z-index: 1;
}

/* photo count pill — bottom-right */
.photo-pill {
  position: absolute;
  bottom: 10px; right: 10px;
  background: rgba(24,22,15,.62);
  backdrop-filter: blur(6px);
  color: rgba(255,255,255,.88);
  border-radius: 2px;
  padding: .28rem .7rem;
  font-size: .7rem;
  font-weight: 600;
  display: flex; align-items: center; gap: .3rem;
  z-index: 1;
  letter-spacing: .04em;
}
.photo-pill svg { width: 11px; height: 11px; }

/* thumbnail strip */
.thumb-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: .5rem;
}
.thumb-item {
  aspect-ratio: 1;
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--bg-warm);
  cursor: pointer;
  border: 1.5px solid var(--rule-light);
  transition: border-color var(--transition), transform var(--transition);
}
.thumb-item img { width: 100%; height: 100%; object-fit: cover; }
.thumb-item:hover { transform: translateY(-1px); border-color: var(--rule); }
.thumb-item.active { border-color: var(--accent); }

/* no-image placeholder thumb */
.thumb-placeholder {
  display: flex; align-items: center; justify-content: center;
  font-size: .65rem; font-weight: 600;
  letter-spacing: .06em; text-transform: uppercase;
  color: var(--ink-light); background: var(--bg-warmer);
  border-radius: var(--radius-md);
  aspect-ratio: 1;
}

/* gallery no-image hero */
.gallery-no-img {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: .65rem;
  background: linear-gradient(135deg, var(--bg-warm), var(--bg-warmer));
}
.gallery-no-img svg { width: 36px; height: 36px; color: var(--rule); }
.gallery-no-img span { font-size: .78rem; color: var(--ink-light); font-weight: 500; }

/* ═══════════════════════════════════════════
   INFO PANEL (sticky right)
═══════════════════════════════════════════ */
.info-panel {
  position: sticky;
  top: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* card — same treatment as home panel cards */
.panel-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.5rem;
  box-shadow: var(--shadow);
}

/* category kicker */
.item-kicker {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .45rem;
}

/* item title — serif, large */
.item-title {
  font-family: var(--font-serif);
  font-size: clamp(1.55rem, 3vw, 2.2rem);
  font-weight: 700;
  line-height: 1.12;
  letter-spacing: -.015em;
  color: var(--ink);
  margin-bottom: 1rem;
}

/* badge strip — same .badge pattern */
.badge-strip { display: flex; gap: .35rem; flex-wrap: wrap; margin-bottom: 1.25rem; }

.badge {
  display: inline-flex;
  align-items: center;
  gap: .28rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .2rem .65rem;
  border-radius: 2px;
}
.badge-condition  { background: var(--green-dim);   color: var(--green);   border: 1px solid rgba(26,122,74,.15); }
.badge-category   { background: var(--sky-dim);     color: var(--sky);     border: 1px solid rgba(26,95,200,.15); }
.badge-urgent     { background: var(--red-dim);     color: var(--red);     border: 1px solid rgba(176,48,48,.18); }
.badge-negotiable { background: var(--amber-dim);   color: var(--amber);   border: 1px solid rgba(184,134,11,.18); }

.badge-urgent .urgent-dot {
  width: 5px; height: 5px; border-radius: 50%;
  background: var(--red);
  animation: blink 1.5s infinite;
}

/* price */
.price-block { margin-bottom: 1.25rem; }
.price-label {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .28rem;
}
.price-value {
  font-family: var(--font-serif);
  font-size: 2.4rem;
  font-weight: 700;
  color: var(--accent);
  line-height: 1;
  letter-spacing: -.02em;
}
.price-value .currency {
  font-family: var(--font-sans);
  font-size: .95rem;
  font-weight: 600;
  vertical-align: super;
  margin-right: .08em;
  color: var(--accent);
}
.price-note {
  font-size: .78rem;
  color: var(--ink-light);
  margin-top: .25rem;
  display: flex; align-items: center; gap: .3rem;
}
.price-note svg { width: 11px; height: 11px; }
.price-free { font-size: 1.5rem; color: var(--ink-mid); }

/* meta row */
.meta-divider {
  height: 1px;
  background: var(--rule-light);
  margin: 1.1rem 0;
}
.meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem 1.25rem;
  font-size: .8rem;
  color: var(--ink-light);
}
.meta-item { display: inline-flex; align-items: center; gap: .3rem; }
.meta-item svg { width: 11px; height: 11px; }
.meta-item strong { color: var(--ink-mid); font-weight: 500; }

/* seller strip */
.seller-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 0;
  border-top: 1px solid var(--rule-light);
  border-bottom: 1px solid var(--rule-light);
  margin: 1.1rem 0;
}
.seller-left { display: flex; align-items: center; gap: .85rem; }
.seller-avatar {
  width: 40px; height: 40px;
  border-radius: 3px;
  background: var(--accent-dim);
  color: var(--accent);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif);
  font-weight: 700; font-size: 1rem;
  border: 1.5px solid rgba(200,100,26,.2);
  flex-shrink: 0;
  letter-spacing: -.01em;
}
.seller-name { font-weight: 600; font-size: .875rem; color: var(--ink); }
.seller-verified {
  display: inline-flex; align-items: center; gap: .28rem;
  font-size: .7rem; color: var(--green); font-weight: 600; margin-left: .35rem;
}
.seller-verified svg { width: 10px; height: 10px; }
.seller-sub { font-size: .72rem; color: var(--ink-light); margin-top: .1rem; }
.seller-actions { display: flex; gap: .35rem; }

/* icon buttons — matches admin .btn-icon */
.icon-btn {
  width: 32px; height: 32px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  color: var(--ink-light);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all var(--transition);
}
.icon-btn:hover { border-color: var(--ink-mid); color: var(--ink); background: var(--bg-warm); }
.icon-btn svg { width: 13px; height: 13px; }

/* quantity */
.qty-row {
  display: flex;
  align-items: center;
  gap: .85rem;
  margin-bottom: 1rem;
}
.qty-label { font-size: .78rem; font-weight: 600; color: var(--ink-mid); white-space: nowrap; }
.qty-control {
  display: inline-flex; align-items: center;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  overflow: hidden;
  background: #fff;
}
.qty-btn {
  width: 34px; height: 34px;
  border: none; background: transparent;
  color: var(--ink-mid); font-size: 1rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background var(--transition);
}
.qty-btn:hover { background: var(--bg-warm); }
.qty-sep { width: 1px; height: 20px; background: var(--rule-light); flex-shrink: 0; }
.qty-input {
  width: 48px; height: 34px;
  border: none; background: transparent;
  text-align: center;
  font-family: var(--font-sans); font-size: .88rem; font-weight: 600;
  color: var(--ink); outline: none;
}

/* CTA buttons */
.cta-stack { display: flex; flex-direction: column; gap: .5rem; }

/* WhatsApp — green primary */
.cta-whatsapp {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  background: #25d366; color: #fff;
  border: none; border-radius: 3px;
  padding: .85rem 1.25rem;
  font-family: var(--font-sans); font-size: .875rem; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all var(--transition);
  box-shadow: 0 3px 10px rgba(37,211,102,.28);
}
.cta-whatsapp:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,211,102,.36); color: #fff; }
.cta-whatsapp:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }
.cta-whatsapp svg { width: 16px; height: 16px; flex-shrink: 0; }

/* secondary row */
.cta-row { display: flex; gap: .5rem; }

/* cart / buy — ink primary & ghost */
.cta-cart {
  flex: 1;
  display: flex; align-items: center; justify-content: center; gap: .45rem;
  background: var(--ink); color: #fff;
  border: none; border-radius: 3px;
  padding: .75rem 1rem;
  font-family: var(--font-sans); font-size: .845rem; font-weight: 600;
  cursor: pointer; width: 100%;
  transition: all var(--transition);
}
.cta-cart:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); }
.cta-cart svg { width: 13px; height: 13px; flex-shrink: 0; }

.cta-buy {
  flex: 1;
  display: flex; align-items: center; justify-content: center; gap: .45rem;
  background: transparent; color: var(--ink-mid);
  border: 1.5px solid var(--rule); border-radius: 3px;
  padding: .75rem 1rem;
  font-family: var(--font-sans); font-size: .845rem; font-weight: 600;
  cursor: pointer; width: 100%;
  transition: all var(--transition);
}
.cta-buy:hover { border-color: var(--ink-mid); background: var(--bg-warm); color: var(--ink); }
.cta-buy svg { width: 11px; height: 11px; }

/* call link */
.cta-call {
  display: flex; align-items: center; justify-content: center; gap: .45rem;
  border: 1.5px solid var(--rule); border-radius: 3px;
  padding: .68rem 1.25rem;
  font-family: var(--font-sans); font-size: .845rem; font-weight: 600;
  color: var(--ink-mid); text-decoration: none;
  transition: all var(--transition);
}
.cta-call:hover { border-color: var(--ink-mid); background: var(--bg-warm); color: var(--ink); }
.cta-call svg { width: 12px; height: 12px; }

/* like widget */
.like-widget {
  display: flex; align-items: center; justify-content: center;
  gap: .5rem; margin-top: .75rem;
}
.like-btn {
  display: flex; align-items: center; gap: .4rem;
  background: var(--bg);
  border: 1.5px solid var(--rule);
  border-radius: 99px;
  padding: .35rem .9rem;
  font-size: .78rem; font-weight: 500; color: var(--ink-light);
  cursor: pointer;
  transition: all var(--transition);
}
.like-btn:hover { background: var(--red-dim); border-color: rgba(176,48,48,.22); color: var(--red); }
.like-btn.liked  { background: var(--red-dim); border-color: rgba(176,48,48,.22); color: var(--red); }
.like-btn svg { width: 13px; height: 13px; }

/* trust chips */
.trust-row { display: flex; gap: .4rem; flex-wrap: wrap; padding-top: .75rem; }
.trust-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .68rem; font-weight: 500; color: var(--ink-light);
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: 99px;
  padding: .24rem .65rem;
}
.trust-chip svg { width: 10px; height: 10px; color: var(--green); }

/* ═══════════════════════════════════════════
   COLLAPSIBLE CONTENT SECTIONS
═══════════════════════════════════════════ */
.content-sections {
  display: flex; flex-direction: column; gap: .75rem;
}

.content-section {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: var(--shadow);
}

/* section header — same warm bg card-header pattern */
.sec-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.35rem;
  cursor: pointer;
  user-select: none;
  background: var(--bg-warm);
  border-bottom: 1px solid transparent;
  transition: background var(--transition);
}
.sec-header:hover { background: var(--bg-warmer); }
.content-section.open .sec-header { border-bottom-color: var(--rule-light); }

.sec-header-left { display: flex; align-items: center; gap: .65rem; }

.sec-icon {
  width: 28px; height: 28px;
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sec-icon svg { width: 13px; height: 13px; }

.sec-title {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}
.sec-count {
  font-size: .72rem;
  color: var(--ink-light);
  font-weight: 400;
  margin-left: .25rem;
}

.sec-chevron {
  width: 22px; height: 22px;
  border: 1px solid var(--rule);
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  background: var(--bg);
  transition: all var(--transition);
  flex-shrink: 0;
}
.sec-chevron svg { width: 10px; height: 10px; color: var(--ink-light); transition: transform var(--transition); }
.content-section.open .sec-chevron svg { transform: rotate(180deg); }

.sec-body {
  padding: 1.25rem 1.35rem;
  display: none;
  animation: secIn .2s ease;
}
.content-section.open .sec-body { display: block; }

@keyframes secIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }

/* description text */
.desc-text {
  font-size: .88rem;
  color: var(--ink-mid);
  line-height: 1.75;
  text-align: justify;
  text-align-last: left;
  hyphens: auto;
}

/* details table */
.details-table {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  overflow: hidden;
  margin-top: .5rem;
}
.detail-cell {
  padding: .8rem 1rem;
  border-bottom: 1px solid var(--rule-light);
  border-right: 1px solid var(--rule-light);
}
.detail-cell:nth-child(even) { border-right: none; }
.detail-cell:nth-last-child(-n+2) { border-bottom: none; }
.detail-label {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .18rem;
}
.detail-value {
  font-size: .855rem;
  font-weight: 500;
  color: var(--ink);
}

/* ── Comments / Q&A ── */
.qa-wrap {}
.qa-form-label {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .5rem;
  display: flex; align-items: center; gap: .35rem;
}
.qa-form-label svg { width: 12px; height: 12px; color: var(--ink-light); }

.qa-textarea {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .72rem .9rem;
  font-family: var(--font-sans); font-size: .875rem;
  color: var(--ink); background: var(--bg);
  resize: vertical; min-height: 90px;
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.qa-textarea:focus { border-color: var(--ink); background: #fff; box-shadow: 0 0 0 3px rgba(24,22,15,.06); }
.qa-textarea::placeholder { color: var(--ink-light); }

.qa-form-foot {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: .55rem;
}
.char-count { font-size: .72rem; color: var(--ink-light); }

.qa-submit {
  display: inline-flex; align-items: center; gap: .4rem;
  background: var(--ink); color: #fff;
  border: none; border-radius: 3px;
  padding: .55rem 1.1rem;
  font-family: var(--font-sans); font-size: .82rem; font-weight: 600;
  cursor: pointer;
  transition: all var(--transition);
}
.qa-submit:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); }
.qa-submit svg { width: 12px; height: 12px; }

.comments-divider {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  display: flex; align-items: center; gap: .5rem;
  padding: 1rem 0 .75rem;
  border-top: 1px solid var(--rule-light);
  margin-top: 1.1rem;
}
.comments-divider svg { width: 12px; height: 12px; }

.comment-list { display: flex; flex-direction: column; gap: .75rem; }

.comment-item {
  display: grid;
  grid-template-columns: 32px 1fr;
  gap: .65rem;
  padding: .85rem;
  background: var(--bg-warm);
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
}
.comment-av {
  width: 32px; height: 32px; border-radius: 3px;
  background: var(--bg-warmer);
  border: 1px solid var(--rule);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif);
  font-size: .95rem; font-weight: 700; color: var(--ink-light);
  flex-shrink: 0;
}
.comment-name { font-size: .82rem; font-weight: 600; color: var(--ink); }
.comment-date { font-size: .72rem; color: var(--ink-light); margin-left: .5rem; }
.comment-text { font-size: .845rem; color: var(--ink-mid); line-height: 1.6; margin-top: .25rem; }

.no-comments {
  text-align: center; padding: 2rem;
  color: var(--ink-light); font-size: .845rem;
}
.no-comments svg { width: 28px; height: 28px; margin: 0 auto .5rem; opacity: .3; display: block; }

/* ── Product cards (related / recent) ── */
.products-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: .85rem;
  margin-top: .5rem;
}

.prod-card {
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  background: #fff;
  overflow: hidden;
  text-decoration: none;
  color: var(--ink);
  display: flex; flex-direction: column;
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  box-shadow: var(--shadow);
}
.prod-card:hover {
  transform: translateY(-2px);
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
}
.prod-img {
  aspect-ratio: 1;
  background: var(--bg-warm);
  overflow: hidden;
  position: relative;
}
.prod-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
.prod-card:hover .prod-img img { transform: scale(1.05); }
.prod-img-ph {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--bg-warm), var(--bg-warmer));
}
.prod-img-ph svg { width: 22px; height: 22px; color: var(--rule); }

.prod-body { padding: .75rem; display: flex; flex-direction: column; gap: .25rem; flex: 1; }
.prod-name {
  font-size: .78rem; font-weight: 600; color: var(--ink); line-height: 1.35;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.prod-price {
  font-family: var(--font-serif);
  font-style: italic;
  font-size: .88rem; color: var(--accent);
}
.prod-cat { font-size: .68rem; color: var(--ink-light); }

.empty-section {
  padding: 2rem;
  text-align: center;
  border: 1px dashed var(--rule);
  border-radius: var(--radius-md);
  color: var(--ink-light);
  font-size: .845rem;
  margin-top: .5rem;
}
.empty-section svg { width: 24px; height: 24px; margin: 0 auto .5rem; opacity: .3; display: block; }

/* ═══════════════════════════════════════════
   LIGHTBOX
═══════════════════════════════════════════ */
.lightbox {
  position: fixed; inset: 0;
  background: rgba(24,22,15,.85);
  backdrop-filter: blur(6px);
  display: none; align-items: center; justify-content: center;
  z-index: 9999; padding: 1.5rem;
}
.lightbox.active { display: flex; }

.lightbox-inner {
  position: relative;
  max-width: 960px; width: 100%;
}
.lightbox-img {
  width: 100%; height: auto; max-height: 85vh;
  object-fit: contain;
  border-radius: var(--radius-md);
  display: block;
}

.lightbox-close {
  position: absolute; top: -1rem; right: -1rem;
  width: 32px; height: 32px; border-radius: 3px;
  background: #fff; border: 1px solid var(--rule);
  color: var(--ink-mid); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition);
}
.lightbox-close:hover { background: var(--bg-warm); }
.lightbox-close svg { width: 12px; height: 12px; }

.lightbox-nav {
  position: absolute; top: 50%; transform: translateY(-50%);
  width: 36px; height: 36px; border-radius: 3px;
  background: rgba(255,255,255,.12); backdrop-filter: blur(4px);
  border: 1px solid rgba(255,255,255,.18);
  color: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background var(--transition);
}
.lightbox-nav:hover { background: rgba(255,255,255,.22); }
.lightbox-nav svg { width: 12px; height: 12px; }
.lightbox-prev { left: -3rem; }
.lightbox-next { right: -3rem; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 1024px) {
  .products-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 860px) {
  .item-layout { grid-template-columns: 1fr; }
  .info-panel { position: static; }
}
@media (max-width: 600px) {
  .thumb-strip { grid-template-columns: repeat(4, 1fr); }
  .details-table { grid-template-columns: 1fr; }
  .detail-cell:nth-child(even) { border-right: none; }
  .detail-cell { border-right: none; }
  .lightbox-prev { left: -.5rem; }
  .lightbox-next { right: -.5rem; }
  .item-title { font-size: 1.6rem; }
}
@media (max-width: 480px) {
  .products-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- ══════ HERO STRIP ══════ -->
<div class="item-hero">
  <div class="hero-inner">

    <nav class="hero-crumbs">
      <a href="/">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 16 16">
          <path d="M2 8l6-5 6 5v7H10V9H6v6H2V8z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Home
      </a>
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <a href="marketplace.php">Marketplace</a>
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span><?php echo htmlspecialchars(mb_strimwidth($item['title'], 0, 42, '…')); ?></span>
    </nav>

    <div class="hero-eyebrow">
      <span class="live-dot"></span>
      <?php echo htmlspecialchars($item['category']); ?>
    </div>

    <h1 class="hero-title"><?php echo htmlspecialchars($item['title']); ?></h1>

    <div class="hero-meta">
      <?php if (!empty($item['location'])): ?>
      <span class="hero-meta-item">
        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
          <path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/>
          <circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/>
        </svg>
        <?php echo htmlspecialchars($item['location']); ?>
      </span>
      <?php endif; ?>
      <span class="hero-meta-item">
        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
          <rect x="2" y="3" width="12" height="11" rx="1.5"/>
          <path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/>
        </svg>
        Listed <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
      </span>
      <span class="hero-meta-item">
        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
          <path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/>
          <circle cx="8" cy="8" r="2"/>
        </svg>
        <span id="viewCount">0</span> views
      </span>
    </div>

  </div>
</div>

<!-- alerts -->
<?php if ($cartMessage): ?>
<div class="alert-wrap">
  <div class="alert-banner alert-banner--success">
    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <?php echo htmlspecialchars($cartMessage); ?>
  </div>
</div>
<?php endif; ?>

<!-- ══════ BODY ══════ -->
<div class="item-body">
  <div class="item-layout">

    <!-- ── Left: Gallery ── -->
    <div class="gallery">
      <div class="gallery-hero" id="openLightbox">
        <?php if (!empty($images)): ?>
          <img id="mainImage"
               src="<?php echo htmlspecialchars($images[0]); ?>"
               alt="<?php echo htmlspecialchars($item['title']); ?>">
        <?php else: ?>
          <div class="gallery-no-img">
            <svg fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
              <rect x="3" y="3" width="18" height="18" rx="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
            <span>No images uploaded</span>
          </div>
        <?php endif; ?>

        <div class="gallery-hero-overlay"></div>

        <div class="gallery-badges">
          <?php if ($isUrgent): ?>
            <span class="badge badge-urgent">
              <span class="urgent-dot"></span>Urgent Sale
            </span>
          <?php endif; ?>
          <?php if ($isNegotiable): ?>
            <span class="badge badge-negotiable">Negotiable</span>
          <?php endif; ?>
        </div>

        <span class="photo-pill">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
          <?php echo max(count($images), 1); ?> photo<?php echo count($images) !== 1 ? 's' : ''; ?>
        </span>
      </div>

      <div class="thumb-strip">
        <?php if (!empty($images)): ?>
          <?php foreach ($images as $i => $src): ?>
            <div class="thumb-item <?php echo $i === 0 ? 'active' : ''; ?>"
                 data-src="<?php echo htmlspecialchars($src); ?>">
              <img src="<?php echo htmlspecialchars($src); ?>" alt="Photo <?php echo $i+1; ?>">
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="thumb-placeholder">No images</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Right: Info Panel ── -->
    <div class="info-panel">
      <div class="panel-card">

        <div class="item-kicker"><?php echo htmlspecialchars($item['category']); ?></div>
        <h2 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h2>

        <div class="badge-strip">
          <span class="badge badge-condition">
            <?php echo htmlspecialchars($item['item_condition'] ?: 'Available'); ?>
          </span>
          <span class="badge badge-category">
            <svg width="9" height="9" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l2-2 10 10-2 2L2 4z"/><circle cx="13" cy="3" r="2"/></svg>
            <?php echo htmlspecialchars($item['category']); ?>
          </span>
          <?php if ($isUrgent): ?>
            <span class="badge badge-urgent"><span class="urgent-dot"></span> Urgent</span>
          <?php endif; ?>
          <?php if ($isNegotiable): ?>
            <span class="badge badge-negotiable">Negotiable</span>
          <?php endif; ?>
        </div>

        <!-- price -->
        <div class="price-block">
          <div class="price-label">Price</div>
          <?php if (!empty($item['price'])): ?>
            <div class="price-value">
              <span class="currency">KSh</span><?php echo number_format((float)$item['price'], 2); ?>
            </div>
            <?php if ($isNegotiable): ?>
              <div class="price-note">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Open to reasonable offers
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="price-free">Price on request</div>
          <?php endif; ?>
        </div>

        <!-- meta row -->
        <div class="meta-divider"></div>
        <div class="meta-row">
          <span class="meta-item">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
            <strong><?php echo htmlspecialchars($item['location'] ?: 'Location not set'); ?></strong>
          </span>
          <span class="meta-item">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
            <strong><?php echo date('M j, Y', strtotime($item['created_at'])); ?></strong>
          </span>
        </div>
        <div class="meta-divider"></div>

        <!-- seller strip -->
        <div class="seller-strip">
          <div class="seller-left">
            <div class="seller-avatar"><?php echo htmlspecialchars($sellerInitials); ?></div>
            <div>
              <div class="seller-name">
                <?php echo htmlspecialchars($sellerName); ?>
                <span class="seller-verified">
                  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  Verified
                </span>
              </div>
              <div class="seller-sub">★★★★★ 4.8 · 32 reviews</div>
            </div>
          </div>
          <div class="seller-actions">
            <button class="icon-btn" id="shareBtn" title="Share">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>
            <button class="icon-btn" id="reportBtn" title="Report">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </button>
          </div>
        </div>

        <!-- quantity -->
        <div class="qty-row">
          <span class="qty-label">Quantity</span>
          <div class="qty-control">
            <button class="qty-btn" id="qtyMinus" type="button">−</button>
            <span class="qty-sep"></span>
            <input class="qty-input" id="quantityInput" type="number" min="1" value="1">
            <span class="qty-sep"></span>
            <button class="qty-btn" id="qtyPlus" type="button">+</button>
          </div>
        </div>

        <!-- CTAs -->
        <div class="cta-stack">
          <?php if (!empty($phoneDigits)): ?>
            <a class="cta-whatsapp"
               href="https://wa.me/<?php echo htmlspecialchars($phoneDigits); ?>"
               target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
              Enquire via WhatsApp
            </a>
          <?php else: ?>
            <button class="cta-whatsapp" disabled style="opacity:.45;cursor:not-allowed;background:#888;box-shadow:none">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              WhatsApp Unavailable
            </button>
          <?php endif; ?>

          <div class="cta-row">
            <form method="POST" style="flex:1;display:flex;">
              <input type="hidden" name="action" value="add_to_cart">
              <input type="hidden" name="quantity" value="1" class="quantity-field">
              <button class="cta-cart" type="submit">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                  <line x1="3" y1="6" x2="21" y2="6"/>
                  <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                Add to Cart
              </button>
            </form>
            <form method="POST" style="flex:1;display:flex;">
              <input type="hidden" name="action" value="buy_now">
              <input type="hidden" name="quantity" value="1" class="quantity-field">
              <button class="cta-buy" type="submit">
                Buy Now
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 14 14">
                  <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </form>
          </div>
        </div>

        <!-- like + trust -->
        <div class="like-widget">
          <button class="like-btn" id="likeBtn" type="button">
            <svg id="likeIcon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
            </svg>
            <span id="likeCount">12</span> Saves
          </button>
        </div>

        <div class="trust-row">
          <span class="trust-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Secure transaction
          </span>
          <span class="trust-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Verified seller
          </span>
          <span class="trust-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Report if issue arises
          </span>
        </div>

      </div><!-- /.panel-card -->

      <!-- call seller -->
      <?php if (!empty($phoneDigits)): ?>
      <a class="cta-call" href="tel:<?php echo htmlspecialchars($phoneDigits); ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.06 6.06l.98-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        Call Seller: <?php echo htmlspecialchars($sellerNumber); ?>
      </a>
      <?php endif; ?>

    </div><!-- /.info-panel -->
  </div><!-- /.item-layout -->

  <!-- ══════ CONTENT SECTIONS ══════ -->
  <div class="content-sections">

    <!-- Description -->
    <div class="content-section open" data-section>
      <div class="sec-header" data-toggle>
        <div class="sec-header-left">
          <span class="sec-icon" style="background:var(--sky-dim)">
            <svg fill="none" stroke="var(--sky)" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h12" stroke-linecap="round"/></svg>
          </span>
          <span class="sec-title">Description</span>
        </div>
        <div class="sec-chevron">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 12 8">
            <path d="M1 1l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>
      <div class="sec-body">
        <p class="desc-text"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
      </div>
    </div>

    <!-- Product Details -->
    <div class="content-section open" data-section>
      <div class="sec-header" data-toggle>
        <div class="sec-header-left">
          <span class="sec-icon" style="background:var(--bg-warm)">
            <svg fill="none" stroke="var(--ink-mid)" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6" stroke-linecap="round"/><line x1="3" y1="12" x2="3.01" y2="12" stroke-linecap="round"/><line x1="3" y1="18" x2="3.01" y2="18" stroke-linecap="round"/></svg>
          </span>
          <span class="sec-title">Product Details</span>
        </div>
        <div class="sec-chevron">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 12 8"><path d="M1 1l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </div>
      <div class="sec-body">
        <div class="details-table">
          <div class="detail-cell">
            <div class="detail-label">Condition</div>
            <div class="detail-value"><?php echo htmlspecialchars($item['item_condition'] ?: '—'); ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Category</div>
            <div class="detail-value"><?php echo htmlspecialchars($item['category']); ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Location</div>
            <div class="detail-value"><?php echo htmlspecialchars($item['location'] ?: '—'); ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Negotiable</div>
            <div class="detail-value"><?php echo $isNegotiable ? 'Yes' : 'No'; ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Variant</div>
            <div class="detail-value"><?php echo htmlspecialchars($item['variant'] ?: '—'); ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Status</div>
            <div class="detail-value"><?php echo htmlspecialchars(ucfirst($item['status'] ?: 'Active')); ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Date Listed</div>
            <div class="detail-value"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-label">Contact</div>
            <div class="detail-value"><?php echo htmlspecialchars($sellerNumber ?: '—'); ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Q&A / Comments -->
    <div class="content-section open" data-section>
      <div class="sec-header" data-toggle>
        <div class="sec-header-left">
          <span class="sec-icon" style="background:var(--accent-dim)">
            <svg fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </span>
          <span class="sec-title">Questions &amp; Comments<span class="sec-count">(<?php echo $comments ? (int)$comments->num_rows : 0; ?>)</span></span>
        </div>
        <div class="sec-chevron">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 12 8"><path d="M1 1l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </div>
      <div class="sec-body">
        <div class="qa-wrap">

          <!-- comment form -->
          <div class="qa-form-label">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Ask the seller a question
          </div>

          <?php if ($commentError): ?>
          <div class="alert-banner alert-banner--error" style="margin-bottom:.75rem">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($commentError); ?>
          </div>
          <?php endif; ?>
          <?php if ($commentSuccess): ?>
          <div class="alert-banner alert-banner--success" style="margin-bottom:.75rem">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars($commentSuccess); ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="#comments">
            <input type="hidden" name="action" value="comment">
            <textarea name="comment" class="qa-textarea" id="commentText"
                      maxlength="1000"
                      placeholder="Write your question or comment here…"
                      required></textarea>
            <div class="qa-form-foot">
              <span class="char-count" id="commentCount">0 / 1000</span>
              <button class="qa-submit" type="submit">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <line x1="22" y1="2" x2="11" y2="13"/>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                Post Comment
              </button>
            </div>
          </form>

          <div class="comments-divider" id="comments">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <?php
              $cnt = $comments ? (int)$comments->num_rows : 0;
              echo $cnt > 0 ? $cnt . ' comment' . ($cnt !== 1 ? 's' : '') : 'No comments yet';
            ?>
          </div>

          <?php if ($comments && $comments->num_rows > 0): ?>
          <div class="comment-list">
            <?php while ($cr = $comments->fetch_assoc()): ?>
            <div class="comment-item">
              <div class="comment-av"><?php echo strtoupper(mb_substr($cr['name'], 0, 1)); ?></div>
              <div>
                <span class="comment-name"><?php echo htmlspecialchars($cr['name']); ?></span>
                <span class="comment-date"><?php echo date('M j, Y', strtotime($cr['created_at'])); ?></span>
                <div class="comment-text"><?php echo nl2br(htmlspecialchars($cr['comment'])); ?></div>
              </div>
            </div>
            <?php endwhile; ?>
          </div>
          <?php else: ?>
          <div class="no-comments">
            <svg fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Be the first to ask a question!
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recently Viewed -->
    <div class="content-section open">
      <div class="sec-header">
        <div class="sec-header-left">
          <span class="sec-icon" style="background:var(--amber-dim)">
            <svg fill="none" stroke="var(--amber)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </span>
          <span class="sec-title">Recently Viewed</span>
        </div>
        <div class="sec-chevron">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 12 8"><path d="M1 1l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </div>
      <div class="sec-body">
        <div class="products-grid" id="recentGrid">
          <div class="empty-section" style="grid-column:1/-1">
            <svg fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Browse a few listings to see them here.
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.content-sections -->
</div><!-- /.item-body -->

<!-- ══════ LIGHTBOX ══════ -->
<div class="lightbox" id="lightboxModal">
  <div class="lightbox-inner">
    <button class="lightbox-close" id="lightboxClose">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
        <path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/>
      </svg>
    </button>
    <button class="lightbox-nav lightbox-prev" id="lightboxPrev">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
        <path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    <button class="lightbox-nav lightbox-next" id="lightboxNext">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
        <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    <img class="lightbox-img" id="lightboxImage" src="" alt="">
  </div>
</div>

<script>
const currentItemId = <?php echo (int)$id; ?>;
const recentPool    = <?php echo json_encode($recentPool, JSON_UNESCAPED_SLASHES); ?>;
const imageSources  = <?php echo json_encode($images,    JSON_UNESCAPED_SLASHES); ?>;
const gallerySources = Array.isArray(imageSources) && imageSources.length ? [...imageSources] : [];

/* ── Collapsible sections ── */
document.querySelectorAll('[data-section]').forEach(sec => {
  sec.querySelector('[data-toggle]')?.addEventListener('click', () => sec.classList.toggle('open'));
});

/* ── Thumbnails ── */
const mainImage  = document.getElementById('mainImage');
const thumbs     = document.querySelectorAll('.thumb-item[data-src]');
let lightboxIndex = 0;

thumbs.forEach((thumb, idx) => {
  thumb.addEventListener('click', () => {
    thumbs.forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    const src = thumb.getAttribute('data-src');
    if (src && mainImage) mainImage.src = src;
    lightboxIndex = idx;
  });
});

/* touch swipe on gallery */
let swipeStart = null;
if (mainImage) {
  mainImage.addEventListener('touchstart', e => { swipeStart = e.touches[0].clientX; });
  mainImage.addEventListener('touchend', e => {
    if (swipeStart === null || !thumbs.length) return;
    const diff = e.changedTouches[0].clientX - swipeStart;
    if (Math.abs(diff) > 40) {
      lightboxIndex = diff < 0
        ? (lightboxIndex + 1) % thumbs.length
        : (lightboxIndex - 1 + thumbs.length) % thumbs.length;
      thumbs[lightboxIndex]?.click();
    }
    swipeStart = null;
  });
}

/* ── Lightbox ── */
const lb    = document.getElementById('lightboxModal');
const lbImg = document.getElementById('lightboxImage');

const openLb  = () => { if (!gallerySources.length) return; lbImg.src = gallerySources[lightboxIndex] || ''; lb.classList.add('active'); };
const closeLb = () => lb.classList.remove('active');
const navLb   = d => {
  if (!gallerySources.length) return;
  lightboxIndex = (lightboxIndex + d + gallerySources.length) % gallerySources.length;
  lbImg.src = gallerySources[lightboxIndex];
  thumbs[lightboxIndex]?.click();
};

document.getElementById('openLightbox')?.addEventListener('click', openLb);
document.getElementById('lightboxClose')?.addEventListener('click', closeLb);
document.getElementById('lightboxPrev')?.addEventListener('click', () => navLb(-1));
document.getElementById('lightboxNext')?.addEventListener('click', () => navLb(1));
lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
document.addEventListener('keydown', e => {
  if (!lb.classList.contains('active')) return;
  if (e.key === 'ArrowLeft')  navLb(-1);
  else if (e.key === 'ArrowRight') navLb(1);
  else if (e.key === 'Escape')     closeLb();
});

/* ── View counter ── */
const viewKey   = `marketplace_views_${currentItemId}`;
const viewedKey = `marketplace_viewed_${currentItemId}`;
let views = parseInt(localStorage.getItem(viewKey) || '0', 10) || 0;
if (!sessionStorage.getItem(viewedKey)) {
  views++;
  localStorage.setItem(viewKey, String(views));
  sessionStorage.setItem(viewedKey, '1');
}
const vc = document.getElementById('viewCount');
if (vc) vc.textContent = String(views);

/* ── Likes ── */
const likeKey      = `marketplace_like_${currentItemId}`;
const likeCountKey = `marketplace_like_count_${currentItemId}`;
const likeBtn  = document.getElementById('likeBtn');
const likeCt   = document.getElementById('likeCount');
const likeIcon = document.getElementById('likeIcon');
let likeNum = parseInt(localStorage.getItem(likeCountKey) || '12', 10);
let liked   = localStorage.getItem(likeKey) === 'true';

const updateLike = () => {
  if (!likeBtn) return;
  if (likeCt) likeCt.textContent = String(likeNum);
  likeBtn.classList.toggle('liked', liked);
  if (likeIcon) likeIcon.setAttribute('fill', liked ? 'currentColor' : 'none');
};
updateLike();

likeBtn?.addEventListener('click', () => {
  liked   = !liked;
  likeNum = liked ? likeNum + 1 : Math.max(0, likeNum - 1);
  localStorage.setItem(likeKey, liked ? 'true' : 'false');
  localStorage.setItem(likeCountKey, String(likeNum));
  updateLike();
});

/* ── Quantity sync ── */
const qtyInput  = document.getElementById('quantityInput');
const qtyFields = document.querySelectorAll('.quantity-field');
const syncQty   = () => {
  if (!qtyInput) return;
  let v = parseInt(qtyInput.value, 10);
  if (!isFinite(v) || v < 1) v = 1;
  qtyInput.value = String(v);
  qtyFields.forEach(f => f.value = String(v));
};
document.getElementById('qtyMinus')?.addEventListener('click', () => { if (qtyInput) { qtyInput.value = String(Math.max(1, parseInt(qtyInput.value, 10) - 1)); syncQty(); }});
document.getElementById('qtyPlus')?.addEventListener('click',  () => { if (qtyInput) { qtyInput.value = String((parseInt(qtyInput.value, 10) || 1) + 1); syncQty(); }});
qtyInput?.addEventListener('input', syncQty);
syncQty();

/* ── Comment char count ── */
const commentText  = document.getElementById('commentText');
const commentCount = document.getElementById('commentCount');
if (commentText && commentCount) {
  const update = () => { commentCount.textContent = `${commentText.value.length} / 1000`; };
  update();
  commentText.addEventListener('input', update);
}

/* ── Recently viewed ── */
const recentKey = 'marketplace_recent';
let recentList  = [];
try { recentList = JSON.parse(localStorage.getItem(recentKey) || '[]'); } catch { recentList = []; }
recentList = recentList.filter(i => i !== currentItemId);
recentList.unshift(currentItemId);
recentList = recentList.slice(0, 4);
localStorage.setItem(recentKey, JSON.stringify(recentList));

const esc = v => String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const recentGrid = document.getElementById('recentGrid');
if (recentGrid) {
  const matches = recentPool.filter(item => recentList.includes(parseInt(item.id, 10))).slice(0, 4);
  if (matches.length) {
    recentGrid.innerHTML = matches.map(item => {
      const price = item.price != null && item.price !== '' && isFinite(Number(item.price))
        ? `KSh ${Number(item.price).toLocaleString()}` : 'On request';
      const img = item.image_path
        ? `<img src="${esc(item.image_path)}" alt="${esc(item.title)}" loading="lazy">`
        : `<div class="prod-img-ph"><svg fill="none" stroke="currentColor" stroke-width="1.3" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>`;
      return `<a class="prod-card" href="marketplace_item.php?id=${item.id}">
        <div class="prod-img">${img}</div>
        <div class="prod-body">
          <div class="prod-name">${esc(item.title)}</div>
          <div class="prod-price">${price}</div>
          <div class="prod-cat">${esc(item.category || 'Marketplace')}</div>
        </div>
      </a>`;
    }).join('');
  }
}

/* ── Share ── */
document.getElementById('shareBtn')?.addEventListener('click', async () => {
  if (navigator.share) {
    try { await navigator.share({ title: document.title, url: location.href }); } catch {}
  } else {
    await navigator.clipboard?.writeText(location.href).catch(() => {});
    const btn = document.getElementById('shareBtn');
    if (btn) {
      btn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="13" height="13"><polyline points="20 6 9 17 4 12"/></svg>';
      setTimeout(() => {
        btn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>';
      }, 1600);
    }
  }
});

/* ── Report ── */
document.getElementById('reportBtn')?.addEventListener('click', () => {
  alert('Thank you for the report. Our team will review this listing.');
});
</script>

<?php include '../shared/footer.php'; ?>
