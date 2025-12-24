<?php
/**
 * Summary Cards Partial
 */
?>

<div class="summary-cards">
    <!-- Total Storage Card -->
    <div class="summary-card card-total">
        <div class="card-icon">💾</div>
        <div class="card-content">
            <h3><?= _('Total Storage') ?></h3>
            <p class="card-subtitle"><?= _('Across all selected hosts') ?></p>
            <div class="card-value"><?= $summary['total_capacity'] ?></div>
            <div class="card-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= min($summary['total_usage_pct'], 100) ?>%"></div>
                </div>
                <div class="progress-label">
                    <span class="progress-percent"><?= $summary['total_usage_pct'] ?>%</span>
                    <span class="progress-text"><?= _('used') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Used Storage Card -->
    <div class="summary-card card-used">
        <div class="card-icon">📊</div>
        <div class="card-content">
            <h3><?= _('Used Storage') ?></h3>
            <p class="card-subtitle"><?= _('of total capacity') ?></p>
            <div class="card-value"><?= $summary['total_used'] ?></div>
            <div class="card-stats">
                <div class="stat-item">
                    <span class="stat-label warning"><?= _('Warning') ?>:</span>
                    <span class="stat-value"><?= $summary['warning_count'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label critical"><?= _('Critical') ?>:</span>
                    <span class="stat-value"><?= $summary['critical_count'] ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Growth Rate Card -->
    <div class="summary-card card-growth">
        <div class="card-icon">📈</div>
        <div class="card-content">
            <h3><?= _('Avg Daily Growth') ?></h3>
            <p class="card-subtitle"><?= _('Based on historical data') ?></p>
            <div class="card-value"><?= $summary['avg_daily_growth_fmt'] ?></div>
            <div class="card-stats">
                <div class="stat-item">
                    <span class="stat-label"><?= _('Hosts') ?>:</span>
                    <span class="stat-value"><?= $summary['total_hosts'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?= _('Filesystems') ?>:</span>
                    <span class="stat-value"><?= $summary['total_filesystems'] ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Prediction Card -->
    <div class="summary-card card-prediction">
        <div class="card-icon">⏰</div>
        <div class="card-content">
            <h3><?= _('Earliest Full') ?></h3>
            <p class="card-subtitle"><?= _('Based on current growth') ?></p>
            <div class="card-value">
                <?php if ($summary['earliest_full']): ?>
                    <?= $summary['earliest_full']['days'] ?> <?= _('days') ?>
                <?php else: ?>
                    <?= _('N/A') ?>
                <?php endif; ?>
            </div>
            <div class="card-details">
                <?php if ($summary['earliest_full']): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?= _('Host') ?>:</span>
                        <span class="detail-value"><?= htmlspecialchars($summary['earliest_full']['host']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?= _('Date') ?>:</span>
                        <span class="detail-value"><?= $summary['earliest_full']['date'] ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Growers Mini Table -->
<?php if (!empty($summary['top_growers'])): ?>
<div class="top-growers">
    <h4><?= _('Fastest Growing Filesystems') ?></h4>
    <table class="mini-table">
        <thead>
            <tr>
                <th><?= _('Host') ?></th>
                <th><?= _('Mount') ?></th>
                <th><?= _('Growth/Day') ?></th>
                <th><?= _('Days Left') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Helper function to parse days from string (same as in host_table.php)
            function parseDaysFromStringSummary(string $daysStr): int {
                if ($daysStr === _('No growth') || $daysStr === _('Already full') || 
                    $daysStr === _('Growth error') || $daysStr === _('More than 10 years')) {
                    return PHP_INT_MAX;
                }
                
                $days = PHP_INT_MAX;
                
                // Extract years
                if (preg_match('/(\d+)\s*years?/', $daysStr, $matches)) {
                    $days = (int)$matches[1] * 365;
                }
                
                // Extract months
                if (preg_match('/(\d+)\s*months?/', $daysStr, $matches)) {
                    $days = ($days < PHP_INT_MAX) ? $days + ((int)$matches[1] * 30) : ((int)$matches[1] * 30);
                }
                
                // Extract days
                if (preg_match('/(\d+)\s*days?/', $daysStr, $matches)) {
                    $days = ($days < PHP_INT_MAX) ? $days + (int)$matches[1] : (int)$matches[1];
                }
                
                // If it's just a plain number
                if ($days === PHP_INT_MAX && is_numeric($daysStr)) {
                    $days = (int)$daysStr;
                }
                
                return $days;
            }
            
            // Helper function to get days status based on thresholds
            function getDaysStatusSummary(int $days): string {
                if ($days <= 15) {
                    return 'critical';
                } elseif ($days <= 30) {
                    return 'warning';
                }
                return 'ok';
            }
            
            foreach ($summary['top_growers'] as $grower): 
                // Parse days from string
                $days = parseDaysFromStringSummary($grower['days_until_full']);
                $daysStatus = getDaysStatusSummary($days);
                
                // Fix mount point display for Windows
                $mount = $grower['mount'];
                // Windows mount points usually come as "C:" or "C:\" from Zabbix
                // If it's just "/", check if host looks like Windows
                if ($mount === '/' || $mount === '\\') {
                    $hostLower = strtolower($grower['host'] ?? '');
                    if (strpos($hostLower, 'win') !== false || 
                        strpos($hostLower, 'windows') !== false ||
                        strpos($hostLower, 'microsoft') !== false) {
                        // Check if we can get the actual mount from storageData
                        foreach ($storageData as $item) {
                            if ($item['host'] === $grower['host'] && 
                                $item['mount'] !== '/' && $item['mount'] !== '\\') {
                                $mount = $item['mount'];
                                break;
                            }
                        }
                        // If still "/", default to "C:" for Windows
                        if ($mount === '/' || $mount === '\\') {
                            $mount = 'C:';
                        }
                    }
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($grower['host']) ?></td>
                <td>
                    <code><?= htmlspecialchars($mount) ?></code>
                    <?php if ($mount !== $grower['mount']): ?>
                        <small class="text-muted">(<?= _('from') ?> <?= htmlspecialchars($grower['mount']) ?>)</small>
                    <?php endif; ?>
                </td>
                <td class="growth-cell">
                    <span class="growth-badge rapid"><?= $grower['daily_growth'] ?></span>
                </td>
                <td>
                    <span class="days-badge <?= $daysStatus ?>">
                        <?= $grower['days_until_full'] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
