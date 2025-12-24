<?php
/**
 * Filesystem Details Partial
 * Note: This is used both inline and in modal
 */

/**
 * Local helper to convert stored trend code into label.
 */
function sa_get_trend_label(string $trend): string {
    switch ($trend) {
        case 'increasing':
            return _('Increasing');
        case 'decreasing':
            return _('Decreasing');
        case 'seasonal':
            return _('Seasonal pattern');
        default:
            return _('Stable');
    }
}


// For inline use, we need the data
if (!isset($storageData)) {
    return;
}

// Group by host for the filesystem view
$hostsGrouped = [];
foreach ($storageData as $item) {
    $hostId = $item['hostid'];
    if (!isset($hostsGrouped[$hostId])) {
        $hostsGrouped[$hostId] = [
            'host' => $item['host'],
            'filesystems' => []
        ];
    }
    $hostsGrouped[$hostId]['filesystems'][] = $item;
}
?>

<div class="filesystem-view-content">
    <?php foreach ($hostsGrouped as $hostId => $hostData): ?>
    <div class="host-filesystems">
        <h4 class="host-header">
            <?= htmlspecialchars($hostData['host']) ?>
            <span class="fs-count">(<?= count($hostData['filesystems']) ?> filesystems)</span>
        </h4>

        <table class="fs-details-table">
            <thead>
                <tr>
                    <th><?= _('Mount Point') ?></th>
                    <th><?= _('Total') ?></th>
                    <th><?= _('Used') ?></th>
                    <th><?= _('Free') ?></th>
                    <th><?= _('Usage %') ?></th>
                    <th><?= _('Daily Growth') ?></th>
                    <th><?= _('Days Until Full') ?></th>
                    <th><?= _('Trend') ?></th>
                    <th><?= _('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hostData['filesystems'] as $fs):
                    $free = $fs['total_raw'] - $fs['used_raw'];
                    $free_percent = $fs['total_raw'] > 0 ? round(($free / $fs['total_raw']) * 100, 1) : 0;
                ?>
                <tr class="fs-row <?= $fs['status'] ?>">
                    <td class="fs-name">
                        <span class="fs-icon">💽</span>
                        <strong><?= htmlspecialchars($fs['mount']) ?></strong>
                    </td>
                    <td><?= $fs['total_space'] ?></td>
                    <td><?= $fs['used_space'] ?></td>
                    <td>
                        <?= $formatBytes($free) ?>
                        <div class="free-percent">(<?= $free_percent ?>%)</div>
                    </td>
                    <td>
                        <div class="usage-cell">
                            <span class="usage-value"><?= $fs['usage_pct'] ?>%</span>
                            <div class="usage-bar">
                                <div class="usage-fill" style="width: <?= min($fs['usage_pct'], 100) ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($fs['daily_growth_raw'] > 0): ?>
                            <div class="growth-cell">
                                <span class="growth-value"><?= $fs['daily_growth'] ?></span>
                                <?php if (isset($fs['confidence']) && $fs['confidence'] > 0): ?>
                                    <div class="confidence-badge" title="<?= sprintf(_('Confidence: %s%%'), $fs['confidence']) ?>">
                                        <?= $fs['confidence'] ?>%
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="growth-neutral"><?= _('Stable') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="days-cell <?= $fs['status'] ?>">
                            <?= $fs['days_until_full'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if (isset($fs['growth_trend'])): ?>
                            <span class="trend-badge <?= $fs['growth_trend'] ?>"
                                  title="<?= _('Growth trend') ?>">
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fs-actions">
                            <button type="button" class="btn-chart btn-icon"
                                    title="<?= _('View growth chart') ?>"
                                    data-hostid="<?= $hostId ?>"
                                    data-mount="<?= htmlspecialchars($fs['mount']) ?>">
                                📈
                            </button>
                            <?php if (!empty($fs['seasonal_pattern'])): ?>
                                <button type="button" class="btn-pattern btn-icon"
                                        title="<?= _('View seasonal pattern') ?>"
                                        data-pattern='<?= json_encode($fs['seasonal_pattern']) ?>'>
                                    📅
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<?php
// Helper function for trend labels
function getTrendLabel($trend) {
    $labels = [
        'rapid_increase' => _('Rapid ↑'),
        'increasing' => _('Increasing ↑'),
        'slow_increase' => _('Slow ↑'),
        'stable' => _('Stable →'),
        'decreasing' => _('Decreasing ↓')
    ];
    return $labels[$trend] ?? $trend;
}
?>
