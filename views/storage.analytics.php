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
		<div class="export-wrapper">
		<!-- Page Header -->
		<!-- Button Group with Tooltips -->
		<div style="display: flex; align-items: center; margin-right: 10px;">
			<!-- Export Label -->
			<div style="margin-right: 8px; font-size: 12px; color: #7f8c8d; font-weight: 600;">
				<?= _('Export') ?>:
			</div>
			
			<!-- Button Container -->
			<div style="display: flex; border: 1px solid #bdc3c7; border-radius: 4px; overflow: hidden;">
				<!-- CSV -->
				<a href="zabbix.php?action=storage.analytics&export=csv<?= $buildQueryString($filter) ?>" 
				class="export-btn"
				title="<?= _('CSV File') ?>"
				style="position: relative;">
					<span class="export-icon">📊</span>
					<span class="export-tooltip">CSV</span>
				</a>
				
				<!-- HTML -->
				<a href="zabbix.php?action=storage.analytics&export=html<?= $buildQueryString($filter) ?>" 
				class="export-btn"
				title="<?= _('HTML Report') ?>"
				style="position: relative;">
					<span class="export-icon">📄</span>
					<span class="export-tooltip">HTML</span>
				</a>
				
				<!-- JSON -->
				<a href="zabbix.php?action=storage.analytics&export=json<?= $buildQueryString($filter) ?>" 
				class="export-btn"
				title="<?= _('JSON Data') ?>"
				style="position: relative;">
					<span class="export-icon">{ }</span>
					<span class="export-tooltip">JSON</span>
				</a>
			</div>
			<div class="last-updated">
				<?= sprintf(_('Updated: %s'), date('H:i:s')) ?>
			</div>
		</div>
		</div>
	</div>
	<style>
	.export-btn {
		display: inline-block;
		padding: 6px 10px;
		text-decoration: none;
		color: #2c3e50;
		background: white;
		border-right: 1px solid #bdc3c7;
		transition: all 0.2s;
	}
	
	.export-btn:last-child {
		border-right: none;
	}
	
	.export-btn:hover {
		background: #3498db;
		color: white;
	}
	
	.export-icon {
		font-size: 13px;
		display: inline-block;
	}
	
	.export-tooltip {
		display: none;
		position: absolute;
		bottom: -25px;
		left: 50%;
		transform: translateX(-50%);
		background: #2c3e50;
		color: white;
		padding: 3px 8px;
		border-radius: 3px;
		font-size: 11px;
		white-space: nowrap;
		z-index: 1000;
	}
	
	.export-btn:hover .export-tooltip {
		display: block;
	}
	
	.export-tooltip::after {
		content: '';
		position: absolute;
		top: -5px;
		left: 50%;
		transform: translateX(-50%);
		border-width: 0 5px 5px 5px;
		border-style: solid;
		border-color: transparent transparent #2c3e50 transparent;
	}
	
	.export-wrapper {
		display: flex;
		justify-content: flex-end;
		margin-bottom: 10px;
	}

	</style>


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
