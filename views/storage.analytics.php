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
            <button type="button" class="btn-export btn-alt">
                <span class="icon-export"></span> <?= _('Export') ?>
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
