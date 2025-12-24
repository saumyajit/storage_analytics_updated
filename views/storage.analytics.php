<?php
/**
 * Storage Analytics - Main View Template
 * @var CView $this
 * @var array $data
 */

// Extract variables for cleaner access
$storageData = $data['storageData'] ?? [];
$summary = $data['summary'] ?? [];
$filter = $data['filter'] ?? [];
$filterOptions = $data['filterOptions'] ?? [];
$formatBytes = $data['formatBytes'] ?? function($b) { return $b; };
$buildQueryString = $data['buildQueryString'] ?? function() { return ''; };
$page = $data['page'] ?? 1;
$page_limit = $data['page_limit'] ?? 50;
$total_records = $data['total_records'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_records / $page_limit);
$start_record = ($page - 1) * $page_limit + 1;
$end_record = min($page * $page_limit, $total_records);
?>

<!-- Main Container -->
<div class="storage-analytics-container" id="storage-analytics-container">
    
    <!-- Page Header -->
    <div class="header">
        <div class="header-left">
            <h1><?= _('Storage Analytics') ?></h1>
            <p class="header-subtitle"><?= _('Monitor disk space usage and predict storage capacity needs') ?></p>
        </div>
        <div class="header-right">
            <!-- UPDATED Export Dropdown -->
            <div class="export-dropdown">
                <button type="button" class="btn-export btn-alt" id="export-btn">
                    <span class="icon-export"></span> <?= _('Export') ?> ▼
                </button>
                <div class="export-menu" id="export-menu" style="display: none;">
                    <a href="?action=storage.analytics&export=csv<?= $buildQueryString($filter) ?>" 
                       class="export-option" data-format="csv" target="_blank">
                        <span class="icon-csv"></span> <?= _('CSV File') ?>
                    </a>
                    <a href="?action=storage.analytics&export=html<?= $buildQueryString($filter) ?>" 
                       class="export-option" data-format="html" target="_blank">
                        <span class="icon-html"></span> <?= _('HTML Report') ?>
                    </a>
                    <a href="?action=storage.analytics&export=json<?= $buildQueryString($filter) ?>" 
                       class="export-option" data-format="json" target="_blank">
                        <span class="icon-json"></span> <?= _('JSON Data') ?>
                    </a>
                </div>
            </div>
            
            <!-- Charts Toggle Button -->
            <button type="button" class="btn-alt" id="toggle-charts" style="margin-right: 10px;">
                📊 <?= _('Show Charts') ?>
            </button>
            
            <div class="last-updated">
                <?= sprintf(_('Updated: %s'), date('H:i:s')) ?>
            </div>
        </div>
    </div>
    
    <!-- Include Filter Panel -->
    <?php include __DIR__ . '/partials/filter_panel.php'; ?>
    
    <!-- Include Summary Cards -->
    <?php include __DIR__ . '/partials/summary_cards.php'; ?>
    
    <!-- Include Charts Section -->
    <?php include __DIR__ . '/partials/charts.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Include Host Table -->
        <?php include __DIR__ . '/partials/host_table.php'; ?>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="footer-left">
            <span class="calculation-info">
                <?= _('Calculations based on') ?>: 
                <strong><?= $filterOptions['time_ranges'][$filter['time_range']] ?? $filter['time_range'] . ' days' ?></strong> | 
                <?= _('Method') ?>: 
                <strong><?= $filterOptions['prediction_methods'][$filter['prediction_method']] ?? $filter['prediction_method'] ?></strong>
            </span>
        </div>
        <div class="footer-right">
            <span class="record-count">
                <?php if ($total_records > 0): ?>
                    <?= sprintf(_('Showing %1$s-%2$s of %3$s hosts'), $start_record, $end_record, $total_records) ?>
                <?php else: ?>
                    <?= _('No data found') ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- Include Scripts -->
<?php include __DIR__ . '/partials/scripts.php'; ?>

<!-- Include Styles -->
<?php include __DIR__ . '/partials/styles.php'; ?>

<style>
/* Add these styles to your styles.css or include here */
.export-dropdown {
    position: relative;
    display: inline-block;
    margin-right: 10px;
}

.export-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-width: 180px;
    z-index: 1000;
    margin-top: 5px;
}

.export-option {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

.export-option:hover {
    background: #f5f5f5;
}

.export-option:last-child {
    border-bottom: none;
}

.icon-csv:before { content: '📊'; margin-right: 8px; }
.icon-html:before { content: '📄'; margin-right: 8px; }
.icon-json:before { content: '{}'; margin-right: 8px; font-weight: bold; }
.icon-export:before { content: '📥'; margin-right: 5px; }
</style>

<script>
// Export dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const exportBtn = document.getElementById('export-btn');
    const exportMenu = document.getElementById('export-menu');
    
    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            exportMenu.style.display = exportMenu.style.display === 'none' ? 'block' : 'none';
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function() {
            exportMenu.style.display = 'none';
        });
    }
    
    // Charts toggle functionality
    const chartsToggle = document.getElementById('toggle-charts');
    const chartsSection = document.getElementById('charts-section');
    
    if (chartsToggle && chartsSection) {
        chartsToggle.addEventListener('click', function() {
            const isVisible = chartsSection.style.display !== 'none';
            chartsSection.style.display = isVisible ? 'none' : 'block';
            chartsToggle.innerHTML = isVisible ? '📊 <?= _("Show Charts") ?>' : '📈 <?= _("Hide Charts") ?>';
            
            // Initialize charts if showing for first time
            if (!isVisible && typeof StorageCharts !== 'undefined' && window.storageData) {
                window.storageCharts = new StorageCharts({
                    storageData: <?= json_encode($storageData) ?>,
                    summary: <?= json_encode($summary) ?>
                });
            }
        });
    }
});
</script>
