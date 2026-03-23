<?php
$topbar_title_override = 'Analytics';
$topbar_subtitle_override = 'Platform performance and engagement metrics';

include 'includes/header.php';
include '../shared/db_connect.php';

function table_exists(mysqli $conn, string $table): bool {
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return false; }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function safe_count(mysqli $conn, string $table, string $where = ''): int {
    if (!table_exists($conn, $table)) { return 0; }
    $tableSafe = '`' . str_replace('`', '', $table) . '`';
    $sql = "SELECT COUNT(*) as cnt FROM {$tableSafe}";
    if ($where) { $sql .= " WHERE {$where}"; }
    $res = $conn->query($sql);
    if (!$res) { return 0; }
    $row = $res->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}

function safe_sum(mysqli $conn, string $table, string $column, string $where = ''): int {
    if (!table_exists($conn, $table) || !column_exists($conn, $table, $column)) { return 0; }
    $tableSafe = '`' . str_replace('`', '', $table) . '`';
    $colSafe = '`' . str_replace('`', '', $column) . '`';
    $sql = "SELECT COALESCE(SUM({$colSafe}), 0) as total FROM {$tableSafe}";
    if ($where) { $sql .= " WHERE {$where}"; }
    $res = $conn->query($sql);
    if (!$res) { return 0; }
    $row = $res->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function safe_recent(mysqli $conn, string $table, string $dateColumn): int {
    if (!table_exists($conn, $table) || !column_exists($conn, $table, $dateColumn)) { return 0; }
    $colSafe = '`' . str_replace('`', '', $dateColumn) . '`';
    return safe_count($conn, $table, "{$colSafe} >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

function get_month_buckets(int $months): array {
    $months = max(1, $months);
    $labels = [];
    $keys = [];
    $dt = new DateTime('first day of this month');
    $dt->modify('-' . ($months - 1) . ' months');
    for ($i = 0; $i < $months; $i++) {
        $keys[] = $dt->format('Y-m');
        $labels[] = $dt->format('M Y');
        $dt->modify('+1 month');
    }
    return [$keys, $labels];
}

function get_monthly_counts(mysqli $conn, string $table, string $dateColumn, int $months = 6): array {
    [$keys, $labels] = get_month_buckets($months);
    $counts = array_fill_keys($keys, 0);
    if (!table_exists($conn, $table) || !column_exists($conn, $table, $dateColumn)) {
        return [$labels, array_values($counts)];
    }
    $start = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months')->format('Y-m-01');
    $tableSafe = '`' . str_replace('`', '', $table) . '`';
    $colSafe = '`' . str_replace('`', '', $dateColumn) . '`';
    $sql = "SELECT DATE_FORMAT({$colSafe}, '%Y-%m') as ym, COUNT(*) as cnt
            FROM {$tableSafe}
            WHERE {$colSafe} >= '{$start}'
            GROUP BY ym";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ym = $row['ym'] ?? '';
            if (isset($counts[$ym])) {
                $counts[$ym] = (int)$row['cnt'];
            }
        }
    }
    return [$labels, array_values($counts)];
}

$posts_total = safe_count($conn, 'posts');
$posts_visible = column_exists($conn, 'posts', 'visible')
    ? safe_count($conn, 'posts', 'COALESCE(visible, 1) = 1')
    : $posts_total;
$posts_30d = safe_recent($conn, 'posts', 'created_at');
$views_total = safe_sum($conn, 'posts', 'view_count');

$comments_total = safe_count($conn, 'comments');
$comments_approved = column_exists($conn, 'comments', 'approved')
    ? safe_count($conn, 'comments', 'approved = 1')
    : 0;
$comments_pending = max(0, $comments_total - $comments_approved);
$comments_30d = safe_recent($conn, 'comments', 'created_at');

$users_total = safe_count($conn, 'users');
$users_30d = safe_recent($conn, 'users', 'created_at');

$bookmarks_total = safe_count($conn, 'bookmarks');
$bookmarks_30d = safe_recent($conn, 'bookmarks', 'created_at');

$likes_total = safe_count($conn, 'likes');
$likes_30d = safe_recent($conn, 'likes', 'created_at');

$reactions_total = safe_count($conn, 'post_reactions');
$reactions_30d = safe_recent($conn, 'post_reactions', 'created_at');

$events_total = safe_count($conn, 'events');
$events_30d = safe_recent($conn, 'events', 'created_at');

$opportunities_total = safe_count($conn, 'opportunities');
$opportunities_30d = safe_recent($conn, 'opportunities', 'created_at');

$marketplace_total = safe_count($conn, 'marketplace_items');
$marketplace_30d = safe_recent($conn, 'marketplace_items', 'created_at');

$safespeak_total = safe_count($conn, 'anonymous_reports');
$safespeak_30d = safe_recent($conn, 'anonymous_reports', 'created_at');

$approval_rate = $comments_total > 0 ? round(($comments_approved / $comments_total) * 100) : 0;
$visible_rate = $posts_total > 0 ? round(($posts_visible / $posts_total) * 100) : 0;

$top_share_expr = '0';
if (column_exists($conn, 'posts', 'share_count')) {
    $top_share_expr = 'COALESCE(p.share_count, 0)';
} elseif (table_exists($conn, 'post_shares')) {
    $top_share_expr = '(SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id = p.id)';
}

$top_posts = [];
if (table_exists($conn, 'posts')) {
    $res = $conn->query("SELECT p.id, p.title,
        COALESCE(p.view_count, 0) as views,
        (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND COALESCE(c.approved, 1) = 1) as comments,
        {$top_share_expr} as shares,
        p.created_at
        FROM posts p
        ORDER BY (COALESCE(p.view_count,0) + (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id)
          + (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND COALESCE(c.approved, 1) = 1)
          + {$top_share_expr}) DESC, p.created_at DESC
        LIMIT 5");
    if ($res) {
        while ($row = $res->fetch_assoc()) { $top_posts[] = $row; }
    }
}

$top_events = [];
if (table_exists($conn, 'events')) {
    $event_view_expr = column_exists($conn, 'events', 'view_count') ? 'COALESCE(e.view_count, 0)' : '0';
    $event_like_expr = column_exists($conn, 'events', 'like_count') ? 'COALESCE(e.like_count, 0)' : '0';
    $event_comment_expr = column_exists($conn, 'events', 'comment_count') ? 'COALESCE(e.comment_count, 0)' : '0';
    $event_share_expr = column_exists($conn, 'events', 'share_count') ? 'COALESCE(e.share_count, 0)' : '0';
    $res = $conn->query("SELECT e.id, e.title, e.event_date,
        {$event_view_expr} as views,
        {$event_like_expr} as likes,
        {$event_comment_expr} as comments,
        {$event_share_expr} as shares
        FROM events e
        ORDER BY ({$event_view_expr} + {$event_like_expr} + {$event_comment_expr} + {$event_share_expr}) DESC, e.event_date DESC
        LIMIT 5");
    if ($res) {
        while ($row = $res->fetch_assoc()) { $top_events[] = $row; }
    }
}

[$user_month_labels, $user_month_counts] = get_monthly_counts($conn, 'users', 'created_at', 6);
[$post_month_labels, $post_month_counts] = get_monthly_counts($conn, 'posts', 'created_at', 6);
?>

<style>
.an-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.75rem 1.5rem 3rem;
}
.an-heading { margin-bottom: 1.6rem; }
.an-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .6rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .25rem .75rem;
  border-radius: 2px;
  margin-bottom: .7rem;
}
.an-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.5rem);
  font-weight: 700;
  color: var(--ink);
  margin-bottom: .3rem;
}
.an-sub { color: var(--ink-light); font-size: .9rem; }

.an-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}
.an-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.25rem 1.3rem;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
}
.an-card::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px; border-radius: 0 2px 2px 0;
  background: var(--sky);
}
.an-card.green::before { background: var(--green); }
.an-card.amber::before { background: var(--accent); }
.an-card.purple::before { background: var(--purple); }

.an-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .45rem;
}
.an-value {
  font-family: var(--font-serif);
  font-size: 1.8rem;
  color: var(--ink);
  margin-bottom: .35rem;
}
.an-meta { font-size: .8rem; color: var(--ink-light); }

.section {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  margin-bottom: 1.5rem;
  overflow: hidden;
}
.section-head {
  padding: 1rem 1.3rem;
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
}
.section-body { padding: 1.2rem 1.3rem; }

.list-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .8rem;
}
.list-item {
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  padding: .9rem 1rem;
  background: #fff;
}
.list-item strong { display: block; font-size: .9rem; color: var(--ink); }
.list-item span { font-size: .78rem; color: var(--ink-light); }

.progress-row { margin-bottom: .9rem; }
.progress-label { font-size: .8rem; color: var(--ink-mid); margin-bottom: .35rem; display: flex; justify-content: space-between; }
.progress-track {
  height: 8px;
  border-radius: 99px;
  background: var(--rule-light);
  overflow: hidden;
}
.progress-bar {
  height: 100%;
  background: var(--sky);
}
.progress-bar.green { background: var(--green); }

.table {
  width: 100%;
  border-collapse: collapse;
}
.table th, .table td {
  text-align: left;
  padding: .7rem .8rem;
  border-bottom: 1px solid var(--rule-light);
  font-size: .85rem;
}
.table th { color: var(--ink-light); font-weight: 600; }

.chart-block {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  padding: 1.2rem 1.3rem 1.4rem;
}
.chart-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: .8rem;
}
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: .6rem;
  height: 180px;
  padding: .8rem .6rem;
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: 8px;
}
.bar-col {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  gap: .35rem;
}
.bar-col::before {
  content: '';
  width: 100%;
  height: var(--bar, 4%);
  background: var(--sky);
  border-radius: 6px 6px 2px 2px;
  transition: height .2s ease;
}
.bar-val { font-size: .72rem; color: var(--ink-mid); }
.bar-label { font-size: .62rem; color: var(--ink-light); text-align: center; }

.line-wrap {
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  padding: .8rem .8rem .4rem;
}
.line-chart { width: 100%; height: 160px; }
.line-legend {
  display: flex;
  justify-content: space-between;
  font-size: .72rem;
  color: var(--ink-light);
  margin-top: .4rem;
}

@media (max-width: 1100px) {
  .an-grid { grid-template-columns: repeat(2, 1fr); }
  .list-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 640px) {
  .an-grid { grid-template-columns: 1fr; }
  .list-grid { grid-template-columns: 1fr; }
}
</style>

<div class="an-wrap">
  <div class="an-heading">
    <span class="an-eyebrow">Platform overview</span>
    <div class="an-title">Analytics</div>
    <div class="an-sub">Performance and engagement across the platform.</div>
  </div>

  <div class="an-grid">
    <div class="an-card">
      <div class="an-label">Total Users</div>
      <div class="an-value"><?php echo number_format($users_total); ?></div>
      <div class="an-meta"><?php echo number_format($users_30d); ?> joined in last 30 days</div>
    </div>
    <div class="an-card green">
      <div class="an-label">Total Posts</div>
      <div class="an-value"><?php echo number_format($posts_total); ?></div>
      <div class="an-meta"><?php echo number_format($posts_30d); ?> in last 30 days</div>
    </div>
    <div class="an-card amber">
      <div class="an-label">Total Likes</div>
      <div class="an-value"><?php echo number_format($likes_total); ?></div>
      <div class="an-meta"><?php echo number_format($likes_30d); ?> in last 30 days</div>
    </div>
    <div class="an-card purple">
      <div class="an-label">Total Comments</div>
      <div class="an-value"><?php echo number_format($comments_total); ?></div>
      <div class="an-meta"><?php echo number_format($comments_30d); ?> in last 30 days</div>
    </div>
  </div>

  <div class="section">
    <div class="section-head">User Activity</div>
    <div class="section-body">
      <div class="chart-block">
        <div class="chart-title">New Users by Month</div>
        <div class="bar-chart">
          <?php
            $user_max = max($user_month_counts);
            if ($user_max < 1) { $user_max = 1; }
            foreach ($user_month_counts as $i => $val):
              $height = round(($val / $user_max) * 100);
              $label = $user_month_labels[$i] ?? '';
          ?>
            <div class="bar-col" style="--bar: <?php echo $height; ?>%;">
              <div class="bar-val"><?php echo number_format($val); ?></div>
              <div class="bar-label"><?php echo htmlspecialchars($label); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="list-grid" style="margin-top:1rem;">
        <div class="list-item">
          <strong><?php echo number_format($users_total); ?> total users</strong>
          <span>All-time registrations</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($users_30d); ?> new in 30 days</strong>
          <span>Recent growth</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($bookmarks_total); ?> bookmarks</strong>
          <span>All-time saved posts</span>
        </div>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-head">Post Trends</div>
    <div class="section-body">
      <div class="chart-block">
        <div class="chart-title">Posts Published by Month</div>
        <div class="line-wrap">
          <?php
            $post_max = max($post_month_counts);
            if ($post_max < 1) { $post_max = 1; }
            $w = 320; $h = 120; $pad = 12;
            $count = max(1, count($post_month_counts));
            $points = [];
            for ($i = 0; $i < $count; $i++) {
                $x = $pad + ($count === 1 ? 0 : ($i * ($w - 2 * $pad) / ($count - 1)));
                $val = $post_month_counts[$i];
                $y = $h - $pad - (($val / $post_max) * ($h - 2 * $pad));
                $points[] = round($x, 2) . ',' . round($y, 2);
            }
          ?>
          <svg class="line-chart" viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>" preserveAspectRatio="none">
            <polyline points="<?php echo implode(' ', $points); ?>"
              fill="none" stroke="var(--green)" stroke-width="2"/>
            <?php foreach ($points as $pt):
              [$cx, $cy] = array_map('floatval', explode(',', $pt));
            ?>
              <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="3" fill="var(--green)"/>
            <?php endforeach; ?>
          </svg>
          <div class="line-legend">
            <?php foreach ($post_month_labels as $label): ?>
              <span><?php echo htmlspecialchars($label); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="list-grid" style="margin-top:1rem;">
        <div class="list-item">
          <strong><?php echo number_format($posts_total); ?> total posts</strong>
          <span><?php echo number_format($posts_30d); ?> in last 30 days</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($views_total); ?> views</strong>
          <span>All-time post views</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($likes_total); ?> likes</strong>
          <span>All-time post likes</span>
        </div>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-head">Engagement (30 days)</div>
    <div class="section-body">
      <div class="list-grid">
        <div class="list-item">
          <strong><?php echo number_format($bookmarks_30d); ?> bookmarks</strong>
          <span>Saved posts in the last 30 days</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($likes_30d); ?> likes</strong>
          <span>Post likes in the last 30 days</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($reactions_30d); ?> reactions</strong>
          <span>Emoji reactions in the last 30 days</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($marketplace_30d); ?> marketplace items</strong>
          <span>Listings created in the last 30 days</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($opportunities_30d); ?> opportunities</strong>
          <span>New opportunities in the last 30 days</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($events_30d); ?> events</strong>
          <span>New events in the last 30 days</span>
        </div>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-head">Content Health</div>
    <div class="section-body">
      <div class="progress-row">
        <div class="progress-label">
          <span>Comment approval rate</span>
          <span><?php echo $approval_rate; ?>%</span>
        </div>
        <div class="progress-track">
          <div class="progress-bar green" style="width: <?php echo $approval_rate; ?>%"></div>
        </div>
      </div>
      <div class="progress-row">
        <div class="progress-label">
          <span>Visible posts</span>
          <span><?php echo $visible_rate; ?>%</span>
        </div>
        <div class="progress-track">
          <div class="progress-bar" style="width: <?php echo $visible_rate; ?>%"></div>
        </div>
      </div>
      <div class="list-grid" style="margin-top:1rem;">
        <div class="list-item">
          <strong><?php echo number_format($comments_approved); ?> approved comments</strong>
          <span><?php echo number_format($comments_pending); ?> pending moderation</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($posts_visible); ?> visible posts</strong>
          <span><?php echo number_format(max(0, $posts_total - $posts_visible)); ?> hidden or draft</span>
        </div>
        <div class="list-item">
          <strong><?php echo number_format($safespeak_total); ?> SafeSpeak reports</strong>
          <span><?php echo number_format($safespeak_30d); ?> in the last 30 days</span>
        </div>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-head">Top Posts</div>
    <div class="section-body">
      <?php if (!empty($top_posts)): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Post</th>
            <th>Views</th>
            <th>Likes</th>
            <th>Comments</th>
            <th>Shares</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_posts as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['title'] ?? 'Untitled'); ?></td>
              <td><?php echo number_format((int)($row['views'] ?? 0)); ?></td>
              <td><?php echo number_format((int)($row['likes'] ?? 0)); ?></td>
              <td><?php echo number_format((int)($row['comments'] ?? 0)); ?></td>
              <td><?php echo number_format((int)($row['shares'] ?? 0)); ?></td>
              <td><?php echo !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div style="color: var(--ink-light);">No posts found yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="section">
    <div class="section-head">Top Events</div>
    <div class="section-body">
      <?php if (!empty($top_events)): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Views</th>
            <th>Likes</th>
            <th>Comments</th>
            <th>Shares</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_events as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['title'] ?? 'Untitled'); ?></td>
              <td><?php echo number_format((int)($row['views'] ?? 0)); ?></td>
              <td><?php echo number_format((int)($row['likes'] ?? 0)); ?></td>
              <td><?php echo number_format((int)($row['comments'] ?? 0)); ?></td>
              <td><?php echo number_format((int)($row['shares'] ?? 0)); ?></td>
              <td><?php echo !empty($row['event_date']) ? date('M d, Y', strtotime($row['event_date'])) : 'N/A'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div style="color: var(--ink-light);">No events found yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
