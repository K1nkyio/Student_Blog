<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/mpesa.php';

mpesa_ensure_tables($conn);

$page_title      = 'Cart — ' . SITE_NAME;
$meta_description = 'Your marketplace cart.';

$message           = '';
$messageType       = 'info';
$cart              = $_SESSION['cart'] ?? [];
$checkoutRequested = false;
$checkoutPhone     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);

    if ($action === 'remove' && $itemId > 0) {
        unset($cart[$itemId]);
        $message = 'Item removed from cart.';
        $messageType = 'info';
    }

    if ($action === 'update' && $itemId > 0) {
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $cart[$itemId] = $qty;
        $message = 'Cart updated.';
        $messageType = 'info';
    }

    if ($action === 'clear') {
        $cart = [];
        $message = 'Cart cleared.';
        $messageType = 'info';
    }

    if ($action === 'checkout') {
        $checkoutRequested = true;
        $checkoutPhone     = trim($_POST['phone'] ?? '');
        if ($checkoutPhone !== '') $_SESSION['mpesa_phone'] = $checkoutPhone;
    }

    $_SESSION['cart'] = $cart;
}

$items       = [];
$total       = 0;
$hasUnpriced = false;

if (!empty($cart)) {
    $ids          = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types        = str_repeat('i', count($ids));
    $stmt         = $conn->prepare("SELECT * FROM marketplace_items WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[$row['id']] = $row;
    $stmt->close();

    foreach ($cart as $id => $qty) {
        if (!isset($items[$id])) continue;
        if ($items[$id]['price'] === null) { $hasUnpriced = true; continue; }
        $total += (float)$items[$id]['price'] * (int)$qty;
    }
}

if ($checkoutRequested) {
    if (empty($cart)) {
        $message = 'Your cart is empty.'; $messageType = 'error';
    } elseif ($hasUnpriced) {
        $message = 'Some items are priced on request — remove them before paying online.'; $messageType = 'error';
    } elseif ($total <= 0) {
        $message = 'Total must be greater than 0 to pay via M-Pesa.'; $messageType = 'error';
    } else {
        $phone = mpesa_format_phone($checkoutPhone);
        if ($phone === null) {
            $message = 'Enter a valid M-Pesa number (e.g. 07XXXXXXXX or 2547XXXXXXXX).'; $messageType = 'error';
        } else {
            $accountRef = 'Cart-' . substr(session_id(), 0, 10);
            $result     = mpesa_initiate_stk_push($total, $phone, $accountRef, 'Marketplace purchase');
            if (!empty($result['success'])) {
                $checkoutId = $result['checkoutRequestId'] ?? '';
                $merchantId = $result['merchantRequestId'] ?? null;
                $mode       = $result['mode'] ?? MPESA_MODE;
                $status     = ($mode === 'mock') ? 'mock' : 'pending';
                $rawReq     = json_encode($result['raw'] ?? []);
                if ($checkoutId !== '') {
                    $stmt = $conn->prepare("INSERT INTO mpesa_payments
                        (merchant_request_id, checkout_request_id, amount, phone_number, account_reference, status, raw_request)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            merchant_request_id = VALUES(merchant_request_id),
                            amount = VALUES(amount),
                            phone_number = VALUES(phone_number),
                            account_reference = VALUES(account_reference),
                            status = VALUES(status),
                            raw_request = VALUES(raw_request)");
                    $stmt->bind_param('ssdssss', $merchantId, $checkoutId, $total, $phone, $accountRef, $status, $rawReq);
                    $stmt->execute();
                    $stmt->close();
                }
                $message     = ($mode === 'mock')
                    ? 'STK Push initiated (mock). Add Daraja credentials in shared/mpesa_config.php to enable real payments.'
                    : ($result['message'] ?? 'STK Push sent. Approve the payment on your phone.');
                $messageType = 'success';
            } else {
                $message     = $result['message'] ?? 'M-Pesa request failed. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

$prefillPhone = $_SESSION['mpesa_phone'] ?? '';
$cartCount    = array_sum(array_values($cart));

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

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;
  --max-w:      1360px;
  --gutter:     1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
  --shadow:     0 1px 3px rgba(24,22,15,.06), 0 4px 16px rgba(24,22,15,.06);
  --radius:     4px;
  --radius-md:  6px;
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
  padding-bottom: 6rem;
}
a { color: inherit; text-decoration: none; }
button { font-family: inherit; }

/* ═══════════════════════════════════════════
   HERO STRIP
═══════════════════════════════════════════ */
.cart-hero {
  background: var(--ink);
  color: #fff;
  padding: 3rem var(--gutter) 2.75rem;
  position: relative;
  overflow: hidden;
}

/* watermark */
.cart-hero::before {
  content: 'Cart.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.25rem;
  font-family: var(--font-serif);
  font-size: clamp(5rem, 14vw, 11rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.032);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* dot grid */
.cart-hero::after {
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

/* eyebrow — same live-dot pattern */
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
.live-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  box-shadow: 0 0 6px var(--accent);
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

.hero-headline {
  font-family: var(--font-serif);
  font-size: clamp(2rem, 4.5vw, 3.5rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em;
  color: #fff;
  margin-bottom: .7rem;
}
.hero-headline em { font-style: italic; color: rgba(255,255,255,.4); }

.hero-sub {
  font-size: .9rem;
  color: rgba(255,255,255,.4);
  font-weight: 300;
  max-width: 38ch;
  line-height: 1.75;
  margin-bottom: 1.75rem;
}

/* hero stats row */
.hero-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
.hero-stat-num {
  font-family: var(--font-serif);
  font-size: 1.8rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
  display: block;
}
.hero-stat-label {
  font-size: .62rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.3);
  display: block;
  margin-top: .2rem;
}

/* ═══════════════════════════════════════════
   BODY
═══════════════════════════════════════════ */
.cart-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2rem var(--gutter) 0;
}

/* ── alert banner ── */
.cart-alert {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  padding: .85rem 1rem;
  border-radius: var(--radius-md);
  font-size: .845rem;
  line-height: 1.55;
  margin-bottom: 1.5rem;
  animation: slideUp .3s ease both;
}
.cart-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .15rem; }
.cart-alert--info    { background: var(--sky-dim);    color: var(--sky);    border: 1px solid rgba(26,95,200,.18); }
.cart-alert--success { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(26,122,74,.18); }
.cart-alert--error   { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(176,48,48,.18); }

/* ═══════════════════════════════════════════
   MAIN LAYOUT — cart + summary
═══════════════════════════════════════════ */
.cart-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.5rem;
  align-items: start;
}

/* ── section heading ── */
.cart-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  gap: 1rem;
  flex-wrap: wrap;
}
.cart-section-title {
  font-family: var(--font-serif);
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}
.cart-section-meta {
  font-size: .78rem;
  color: var(--ink-light);
  font-weight: 500;
}

/* clear cart ghost btn */
.btn-clear {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--red);
  border: 1.5px solid rgba(176,48,48,.22);
  border-radius: 3px;
  padding: .35rem .85rem;
  background: transparent;
  cursor: pointer;
  transition: all var(--transition);
  font-family: var(--font-sans);
}
.btn-clear:hover { background: var(--red-dim); border-color: rgba(176,48,48,.4); }
.btn-clear svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   CART ITEM ROWS — white cards like opp-row
═══════════════════════════════════════════ */
.cart-list { display: flex; flex-direction: column; gap: .75rem; }

.cart-row {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  display: grid;
  grid-template-columns: 56px 1fr auto auto auto;
  gap: 1rem;
  align-items: center;
  padding: 1.1rem 1.35rem;
  position: relative;
  overflow: hidden;
  transition: box-shadow var(--transition), border-color var(--transition), transform var(--transition);
  animation: rowIn .35s ease both;
}
.cart-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-1px);
}
/* accent left bar */
.cart-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--rule);
  transition: background var(--transition);
}
.cart-row:hover::before { background: var(--accent); }

@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* item thumbnail / icon */
.cart-row-icon {
  width: 52px; height: 52px;
  border-radius: var(--radius-md);
  background: var(--bg-warm);
  border: 1px solid var(--rule-light);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}
.cart-row-icon img { width: 100%; height: 100%; object-fit: cover; }
.cart-row-icon svg { width: 20px; height: 20px; color: var(--rule); }

/* item info */
.cart-row-body { min-width: 0; }
.cart-row-title {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
  line-height: 1.25;
  margin-bottom: .22rem;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cart-row-meta {
  font-size: .75rem;
  color: var(--ink-light);
  display: flex; gap: .65rem; flex-wrap: wrap;
  align-items: center;
}
.cart-row-meta-item { display: inline-flex; align-items: center; gap: .25rem; }
.cart-row-meta-item svg { width: 10px; height: 10px; }

/* unit price */
.cart-row-price {
  font-family: var(--font-serif);
  font-style: italic;
  font-size: 1rem;
  color: var(--ink-mid);
  white-space: nowrap;
  flex-shrink: 0;
  text-align: right;
}
.cart-row-price .on-request {
  font-size: .75rem;
  font-style: normal;
  font-family: var(--font-sans);
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .18rem .55rem;
  border-radius: 2px;
}

/* quantity control — same as marketplace_item.php */
.cart-qty {
  flex-shrink: 0;
}
.qty-control {
  display: inline-flex;
  align-items: center;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  overflow: hidden;
  background: #fff;
}
.qty-btn {
  width: 30px; height: 30px;
  border: none; background: transparent;
  color: var(--ink-mid); font-size: .95rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background var(--transition);
}
.qty-btn:hover { background: var(--bg-warm); }
.qty-sep { width: 1px; height: 18px; background: var(--rule-light); flex-shrink: 0; }
.qty-input {
  width: 42px; height: 30px;
  border: none; background: transparent;
  text-align: center;
  font-family: var(--font-sans); font-size: .845rem; font-weight: 600;
  color: var(--ink); outline: none;
}

/* remove button */
.btn-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px; height: 28px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  color: var(--ink-light);
  cursor: pointer;
  transition: all var(--transition);
  flex-shrink: 0;
}
.btn-remove:hover { border-color: rgba(176,48,48,.35); background: var(--red-dim); color: var(--red); }
.btn-remove svg { width: 12px; height: 12px; }

/* ── empty state ── */
.cart-empty {
  text-align: center;
  padding: 4rem 2rem;
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
}
.cart-empty-icon { font-size: 2.5rem; display: block; opacity: .3; margin-bottom: 1rem; }
.cart-empty h3 {
  font-family: var(--font-serif);
  font-size: 1.3rem;
  color: var(--ink-mid);
  margin-bottom: .4rem;
}
.cart-empty p { font-size: .845rem; color: var(--ink-light); margin-bottom: 1.5rem; }
.btn-browse {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .65rem 1.4rem;
  background: var(--ink);
  color: #fff;
  border-radius: 3px;
  font-size: .845rem;
  font-weight: 600;
  transition: all var(--transition);
  text-decoration: none;
}
.btn-browse:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); color: #fff; }
.btn-browse svg { width: 12px; height: 12px; }

/* ═══════════════════════════════════════════
   ORDER SUMMARY CARD (sticky)
═══════════════════════════════════════════ */
.summary-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
  position: sticky;
  top: 1.5rem;
}

.summary-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule-light);
  padding: 1rem 1.35rem;
  display: flex;
  align-items: center;
  gap: .6rem;
}
.summary-head-icon {
  width: 26px; height: 26px;
  border-radius: 3px;
  background: var(--accent-dim);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.summary-head-icon svg { width: 12px; height: 12px; color: var(--accent); }
.summary-head-title {
  font-family: var(--font-serif);
  font-size: .98rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}

.summary-body { padding: 1.25rem 1.35rem; }

/* line items */
.summary-lines { display: flex; flex-direction: column; gap: .6rem; margin-bottom: 1.1rem; }
.summary-line {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: .85rem;
  font-size: .82rem;
}
.summary-line-name {
  color: var(--ink-mid);
  font-weight: 500;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.summary-line-qty {
  font-size: .7rem;
  color: var(--ink-light);
  background: var(--bg-warm);
  border: 1px solid var(--rule-light);
  border-radius: 2px;
  padding: .1rem .4rem;
  flex-shrink: 0;
}
.summary-line-price {
  font-weight: 600;
  color: var(--ink);
  white-space: nowrap;
  flex-shrink: 0;
}
.summary-line-price.on-req {
  font-size: .72rem;
  color: var(--ink-light);
  font-weight: 400;
}

/* total row */
.summary-divider { height: 1px; background: var(--rule-light); margin: 1rem 0; }
.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 1.35rem;
}
.summary-total-label {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
}
.summary-total-val {
  font-family: var(--font-serif);
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  letter-spacing: -.02em;
}
.summary-total-val .ksh {
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 600;
  vertical-align: super;
  margin-right: .06em;
  color: var(--ink-mid);
}
.summary-unpriced-note {
  font-size: .72rem;
  color: var(--amber);
  background: var(--amber-dim);
  border: 1px solid rgba(184,134,11,.2);
  border-radius: 2px;
  padding: .35rem .7rem;
  margin-bottom: 1rem;
  line-height: 1.5;
  display: flex; align-items: flex-start; gap: .35rem;
}
.summary-unpriced-note svg { width: 11px; height: 11px; flex-shrink: 0; margin-top: .15rem; }

/* phone field */
.phone-label {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .45rem;
  display: block;
}
.phone-field {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .68rem .9rem;
  font-size: .875rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  outline: none;
  margin-bottom: .5rem;
  transition: border-color var(--transition), box-shadow var(--transition);
  -webkit-appearance: none;
}
.phone-field::placeholder { color: var(--ink-light); }
.phone-field:focus { border-color: var(--ink); background: #fff; box-shadow: 0 0 0 3px rgba(24,22,15,.06); }

.phone-hint {
  font-size: .72rem;
  color: var(--ink-light);
  margin-bottom: 1rem;
  line-height: 1.5;
  display: flex; align-items: flex-start; gap: .3rem;
}
.phone-hint svg { width: 11px; height: 11px; flex-shrink: 0; margin-top: .15rem; }

/* pay button — ink primary */
.btn-pay {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  padding: .85rem 1.25rem;
  background: var(--ink);
  color: #fff;
  border: none;
  border-radius: 3px;
  font-family: var(--font-sans);
  font-size: .9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition);
  margin-bottom: .6rem;
}
.btn-pay:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); }
.btn-pay:active { transform: scale(.98); }
.btn-pay:disabled { background: var(--rule); color: var(--ink-light); cursor: not-allowed; transform: none; box-shadow: none; }
.btn-pay svg { width: 15px; height: 15px; flex-shrink: 0; }

/* mpesa logo / brand */
.mpesa-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  font-size: .72rem;
  color: var(--ink-light);
  padding-top: .35rem;
}
.mpesa-badge {
  display: inline-flex;
  align-items: center;
  gap: .28rem;
  background: var(--green-dim);
  color: var(--green);
  border: 1px solid rgba(26,122,74,.2);
  border-radius: 2px;
  padding: .18rem .55rem;
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
}

/* continue shopping */
.btn-continue {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  width: 100%;
  padding: .62rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  color: var(--ink-mid);
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 500;
  text-decoration: none;
  transition: all var(--transition);
  margin-top: .6rem;
}
.btn-continue:hover { border-color: var(--ink-mid); background: var(--bg-warm); color: var(--ink); }
.btn-continue svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 900px) {
  .cart-layout { grid-template-columns: 1fr; }
  .summary-card { position: static; }
}
@media (max-width: 600px) {
  .cart-row {
    grid-template-columns: 44px 1fr;
    grid-template-rows: auto auto;
  }
  .cart-row-price,
  .cart-qty,
  .btn-remove {
    grid-column: 2;
  }
  .cart-row > .cart-row-price,
  .cart-row > .cart-qty { text-align: left; }
}
</style>

<!-- ══════ HERO ══════ -->
<div class="cart-hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">
      <span class="live-dot"></span>
      Marketplace
    </div>
    <h1 class="hero-headline">Your Cart<em>.</em></h1>
    <p class="hero-sub">Review your selected items and complete your purchase via M-Pesa.</p>
    <div class="hero-stats">
      <div>
        <span class="hero-stat-num"><?php echo count($cart); ?></span>
        <span class="hero-stat-label">Items</span>
      </div>
      <div>
        <span class="hero-stat-num"><?php echo $cartCount; ?></span>
        <span class="hero-stat-label">Total Qty</span>
      </div>
      <div>
        <span class="hero-stat-num">
          <?php echo $total > 0 ? 'KSh ' . number_format($total, 0) : '—'; ?>
        </span>
        <span class="hero-stat-label">Subtotal</span>
      </div>
    </div>
  </div>
</div>

<!-- ══════ BODY ══════ -->
<div class="cart-body">

  <!-- alert -->
  <?php if ($message): ?>
  <div class="cart-alert cart-alert--<?php echo htmlspecialchars($messageType); ?>">
    <?php if ($messageType === 'success'): ?>
      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <?php elseif ($messageType === 'error'): ?>
      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php else: ?>
      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php endif; ?>
    <?php echo htmlspecialchars($message); ?>
  </div>
  <?php endif; ?>

  <?php if (empty($cart)): ?>
    <!-- ── Empty state ── -->
    <div class="cart-empty">
      <span class="cart-empty-icon">🛒</span>
      <h3>Your cart is empty</h3>
      <p>Browse the marketplace and add items to get started.</p>
      <a class="btn-browse" href="marketplace.php">
        Browse Marketplace
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 14 14">
          <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>

  <?php else: ?>
    <div class="cart-layout">

      <!-- ── Left: cart items ── -->
      <div>
        <div class="cart-section-head">
          <div>
            <div class="cart-section-title">
              Cart Items
            </div>
            <div class="cart-section-meta">
              <?php echo count($cart); ?> item<?php echo count($cart) !== 1 ? 's' : ''; ?> · <?php echo $cartCount; ?> total qty
            </div>
          </div>
          <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="clear">
            <button class="btn-clear" type="submit"
                    onclick="return confirm('Clear all items from cart?')">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6M14 11v6"/>
                <path d="M9 6V4h6v2"/>
              </svg>
              Clear cart
            </button>
          </form>
        </div>

        <div class="cart-list">
          <?php foreach ($cart as $id => $qty):
            if (!isset($items[$id])) continue;
            $it       = $items[$id];
            $hasPrice = ($it['price'] !== null);
            $lineTotal= $hasPrice ? (float)$it['price'] * (int)$qty : null;
          ?>
          <div class="cart-row" style="animation-delay:<?php echo array_search($id, array_keys($cart)) * 0.05; ?>s">

            <!-- icon/thumbnail -->
            <div class="cart-row-icon">
              <svg fill="none" stroke="currentColor" stroke-width="1.3" viewBox="0 0 24 24">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
              </svg>
            </div>

            <!-- title + meta -->
            <div class="cart-row-body">
              <div class="cart-row-title">
                <a href="marketplace_item.php?id=<?php echo (int)$id; ?>" style="color:inherit">
                  <?php echo htmlspecialchars($it['title']); ?>
                </a>
              </div>
              <div class="cart-row-meta">
                <span class="cart-row-meta-item">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                  <?php echo htmlspecialchars($it['category']); ?>
                </span>
                <?php if (!empty($it['item_condition'])): ?>
                <span class="cart-row-meta-item">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                  <?php echo htmlspecialchars($it['item_condition']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($it['location'])): ?>
                <span class="cart-row-meta-item">
                  <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
                  <?php echo htmlspecialchars($it['location']); ?>
                </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- unit price -->
            <div class="cart-row-price">
              <?php if ($hasPrice): ?>
                KSh <?php echo number_format((float)$it['price'], 0); ?>
              <?php else: ?>
                <span class="on-request">On request</span>
              <?php endif; ?>
            </div>

            <!-- quantity -->
            <div class="cart-qty">
              <form method="POST" id="qtyForm_<?php echo (int)$id; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="item_id" value="<?php echo (int)$id; ?>">
                <div class="qty-control">
                  <button class="qty-btn"
                          type="button"
                          data-form="qtyForm_<?php echo (int)$id; ?>"
                          data-dir="-1">−</button>
                  <span class="qty-sep"></span>
                  <input class="qty-input"
                         type="number"
                         name="quantity"
                         min="1"
                         value="<?php echo (int)$qty; ?>"
                         data-form="qtyForm_<?php echo (int)$id; ?>">
                  <span class="qty-sep"></span>
                  <button class="qty-btn"
                          type="button"
                          data-form="qtyForm_<?php echo (int)$id; ?>"
                          data-dir="1">+</button>
                </div>
              </form>
            </div>

            <!-- remove -->
            <form method="POST" style="margin:0">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="item_id" value="<?php echo (int)$id; ?>">
              <button class="btn-remove" type="submit" title="Remove from cart">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
                  <path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/>
                </svg>
              </button>
            </form>

          </div>
          <?php endforeach; ?>
        </div>

        <!-- continue shopping link -->
        <div style="margin-top:1.25rem">
          <a href="marketplace.php" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--ink-light);transition:color var(--transition)" onmouseover="this.style.color='var(--sky)'" onmouseout="this.style.color='var(--ink-light)'">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
              <path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Continue shopping
          </a>
        </div>
      </div>

      <!-- ── Right: order summary ── -->
      <div>
        <div class="summary-card">
          <div class="summary-head">
            <div class="summary-head-icon">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
              </svg>
            </div>
            <span class="summary-head-title">Order Summary</span>
          </div>

          <div class="summary-body">

            <!-- line items -->
            <div class="summary-lines">
              <?php foreach ($cart as $id => $qty):
                if (!isset($items[$id])) continue;
                $it = $items[$id];
                $lp = ($it['price'] !== null) ? 'KSh ' . number_format((float)$it['price'] * (int)$qty, 0) : null;
              ?>
              <div class="summary-line">
                <span class="summary-line-name" title="<?php echo htmlspecialchars($it['title']); ?>">
                  <?php echo htmlspecialchars($it['title']); ?>
                </span>
                <span class="summary-line-qty">×<?php echo (int)$qty; ?></span>
                <?php if ($lp): ?>
                  <span class="summary-line-price"><?php echo $lp; ?></span>
                <?php else: ?>
                  <span class="summary-line-price on-req">On request</span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- unpriced note -->
            <?php if ($hasUnpriced): ?>
            <div class="summary-unpriced-note">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Some items are priced on request and excluded from the M-Pesa total.
            </div>
            <?php endif; ?>

            <div class="summary-divider"></div>

            <!-- total -->
            <div class="summary-total">
              <span class="summary-total-label">Total</span>
              <span class="summary-total-val">
                <span class="ksh">KSh</span><?php echo number_format($total, 0); ?>
              </span>
            </div>

            <!-- checkout form -->
            <form method="POST" id="checkoutForm">
              <input type="hidden" name="action" value="checkout">
              <label class="phone-label" for="mpesaPhone">M-Pesa Number</label>
              <input type="tel" id="mpesaPhone" name="phone"
                     class="phone-field"
                     placeholder="07XXXXXXXX or 2547XXXXXXXX"
                     value="<?php echo htmlspecialchars($prefillPhone); ?>"
                     required>
              <div class="phone-hint">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                You'll receive an STK push on your phone to confirm the payment.
              </div>
              <button class="btn-pay" type="submit" id="payBtn"
                <?php echo ($total <= 0 || $hasUnpriced) ? 'disabled' : ''; ?>>
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                Pay KSh <?php echo number_format($total, 0); ?> via M-Pesa
              </button>
            </form>

            <div class="mpesa-brand">
              <span class="mpesa-badge">
                <svg fill="currentColor" viewBox="0 0 24 24" width="10" height="10"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                Powered by M-Pesa
              </span>
              <span>Secure · Instant</span>
            </div>

            <a class="btn-continue" href="marketplace.php">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
                <path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Continue shopping
            </a>

          </div>
        </div>
      </div>

    </div><!-- /.cart-layout -->
  <?php endif; ?>
</div><!-- /.cart-body -->

<script>
(function () {
  /* ── Quantity +/- buttons ── */
  document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const formId = btn.dataset.form;
      const dir    = parseInt(btn.dataset.dir, 10);
      const form   = document.getElementById(formId);
      if (!form) return;
      const input  = form.querySelector('.qty-input');
      if (!input) return;
      let v = parseInt(input.value, 10) || 1;
      v = Math.max(1, v + dir);
      input.value = String(v);
      form.submit();
    });
  });

  /* ── Auto-submit qty on manual input change ── */
  document.querySelectorAll('.qty-input').forEach(input => {
    input.addEventListener('change', () => {
      const formId = input.dataset.form;
      const form   = document.getElementById(formId);
      if (form) form.submit();
    });
  });

  /* ── Pay button loading state ── */
  document.getElementById('checkoutForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('payBtn');
    if (btn && !btn.disabled) {
      btn.disabled = true;
      btn.innerHTML = '<svg style="animation:spin .7s linear infinite" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9"/></svg> Processing…';
    }
  });

  const s = document.createElement('style');
  s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
})();
</script>

<?php include '../shared/footer.php'; ?>