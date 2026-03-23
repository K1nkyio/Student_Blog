<?php
// AJAX handler for getting SafeSpeak report details - Admin Only
header('Content-Type: application/json');

// Check admin authentication directly (don't include header.php as it redirects on failure)
session_start();
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../../shared/db_connect.php';
include '../../shared/functions.php';

$report_id = (int)($_GET['id'] ?? 0);

if ($report_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

// Get report details
$stmt = $conn->prepare("
    SELECT ar.*
    FROM anonymous_reports ar
    WHERE ar.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$stmt->close();

// Generate HTML for modal content
ob_start();
?>
<div class="report-details">
    <!-- Report Header -->
    <div style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
            <div>
                <h3 style="margin: 0 0 0.5rem 0; color: #1e293b; font-size: 1.5rem; font-weight: 700;">
                    <?php echo htmlspecialchars($report['subject']); ?>
                </h3>
                <div style="color: #64748b; font-size: 0.9rem;">
                    <strong>Report ID:</strong> #<?php echo htmlspecialchars($report['report_id']); ?> •
                    <strong>Submitted:</strong> <?php echo date('F d, Y \a\t g:i A', strtotime($report['created_at'])); ?>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span class="status-badge <?php echo $report['status']; ?>" style="font-size: 0.8rem;">
                        <i class="fas fa-circle"></i>
                        <?php echo ucfirst($report['status']); ?>
                    </span>
                    <span class="urgency-badge <?php echo $report['urgency']; ?>" style="font-size: 0.8rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo ucfirst($report['urgency']); ?>
                    </span>
                </div>
                <div style="font-size: 0.8rem; color: #64748b;">
                    <i class="fas fa-tag"></i> <?php echo ucfirst($report['category']); ?>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem;">Category</div>
                <div style="font-weight: 600; color: #1e293b;"><?php echo ucfirst($report['category']); ?></div>
            </div>
            <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem;">Contact Email</div>
                <div style="font-weight: 600; color: <?php echo $report['contact_email'] ? '#059669' : '#64748b'; ?>;">
                    <?php echo $report['contact_email'] ? 'Provided (anonymized)' : 'Not provided'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div style="margin-bottom: 2rem;">
        <h4 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-alt" style="color: var(--safespeak-primary);"></i>
            Report Details
        </h4>
        <div style="padding: 1.5rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.6; white-space: pre-wrap;">
            <?php echo htmlspecialchars($report['message']); ?>
        </div>
    </div>

    <!-- Admin Section -->
    <?php if ($report['admin_notes'] || $report['reviewed_at']): ?>
    <div style="margin-bottom: 2rem;">
        <h4 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-user-shield" style="color: var(--safespeak-primary);"></i>
            Administrative Notes
        </h4>

        <?php if ($report['reviewed_at']): ?>
            <div style="margin-bottom: 1rem; padding: 0.75rem; background: rgba(99, 102, 241, 0.05); border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.1);">
                <div style="font-size: 0.9rem; color: #3730a3; font-weight: 600;">
                    <i class="fas fa-calendar-check"></i> Last updated: <?php echo date('F d, Y \a\t g:i A', strtotime($report['reviewed_at'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report['admin_notes']): ?>
            <div style="padding: 1.5rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.6; white-space: pre-wrap;">
                <?php echo htmlspecialchars($report['admin_notes']); ?>
            </div>
        <?php else: ?>
            <div style="padding: 2rem; text-align: center; color: #64748b; font-style: italic; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <i class="fas fa-sticky-note" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i><br>
                No administrative notes yet.
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Actions Form -->
    <div>
        <h4 style="margin: 0 0 1.5rem 0; color: #1e293b; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-cogs" style="color: var(--safespeak-primary);"></i>
            Update Report Status
        </h4>

        <form method="POST" action="<?php echo htmlspecialchars($safespeak_form_action ?? '../safespeak.php'); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label for="status" style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.9rem;">
                        <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                        Status
                    </label>
                    <select name="status" id="status" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; background: white; transition: border-color 0.3s ease;">
                        <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>⏳ Pending Review</option>
                        <option value="reviewing" <?php echo $report['status'] === 'reviewing' ? 'selected' : ''; ?>>🔍 Under Review</option>
                        <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>✅ Resolved</option>
                        <option value="dismissed" <?php echo $report['status'] === 'dismissed' ? 'selected' : ''; ?>>❌ Dismissed</option>
                    </select>
                </div>

                <div>
                    <label for="admin_notes" style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.9rem;">
                        <i class="fas fa-sticky-note" style="margin-right: 0.5rem;"></i>
                        Administrative Notes
                    </label>
                    <textarea name="admin_notes" id="admin_notes" rows="4"
                              style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; font-family: inherit; background: white; resize: vertical; transition: border-color 0.3s ease; line-height: 1.5;"
                              placeholder="Add internal notes about this report..."><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="submit" style="padding: 0.75rem 1.5rem; background: var(--safespeak-gradient); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.3s ease; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-save"></i>
                    Update Report
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.report-details .badge {
    font-size: 0.75rem !important;
}

.report-details .bg-light {
    background-color: #f8f9fa !important;
}

.report-details .border {
    border: 1px solid #dee2e6 !important;
}

.report-details .text-muted {
    color: #6c757d !important;
}

.report-details .text-success {
    color: #198754 !important;
}

.report-details .fst-italic {
    font-style: italic !important;
}

.report-details .d-flex {
    display: flex !important;
}

.report-details .align-items-center {
    align-items: center !important;
}

.report-details .gap-2 {
    gap: 0.5rem !important;
}

.report-details .rounded-circle {
    border-radius: 50% !important;
}

.report-details .mb-1 { margin-bottom: 0.25rem !important; }
.report-details .mb-2 { margin-bottom: 0.5rem !important; }
.report-details .mb-3 { margin-bottom: 1rem !important; }
.report-details .mb-4 { margin-bottom: 1.5rem !important; }
.report-details .mt-3 { margin-top: 1rem !important; }

.report-details .p-3 { padding: 1rem !important; }
.report-details .rounded { border-radius: 0.375rem !important; }

.report-details .row { --bs-gutter-x: 1rem; --bs-gutter-y: 0; display: flex; flex-wrap: wrap; margin-top: calc(-1 * var(--bs-gutter-y)); margin-right: calc(-0.5 * var(--bs-gutter-x)); margin-left: calc(-0.5 * var(--bs-gutter-x)); }
.report-details .row > * { --bs-gutter-x: 1rem; --bs-gutter-y: 0; box-sizing: border-box; flex-shrink: 0; width: 100%; max-width: 100%; padding-right: calc(var(--bs-gutter-x) * 0.5); padding-left: calc(var(--bs-gutter-x) * 0.5); margin-top: var(--bs-gutter-y); }
.report-details .col-md-4 { flex: 0 0 auto; width: 33.33333333%; }
.report-details .col-md-6 { flex: 0 0 auto; width: 50%; }
.report-details .col-md-8 { flex: 0 0 auto; width: 66.66666667%; }
.report-details .g-3 { --bs-gutter-x: 1rem; --bs-gutter-y: 1rem; }

@media (max-width: 768px) {
    .report-details .col-md-4, .report-details .col-md-6, .report-details .col-md-8 {
        width: 100%;
    }
}
</style>
<?php
$html = ob_get_clean();

echo json_encode(['success' => true, 'html' => $html]);

$conn->close();
?>
