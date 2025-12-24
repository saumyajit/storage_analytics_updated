<?php
namespace Modules\diskanalyser\actions;

use CController;
use CControllerResponseData;
use API;

class StorageAnalyticsExport extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    public function exportCSV(array $storageData, array $summary, array $filter): void {
        $filename = 'storage_analytics_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Header row
        fputcsv($output, [
            _('Host'),
            _('Host Name'),
            _('Mount Point'),
            _('Total Space (bytes)'),
            _('Total Space (human)'),
            _('Used Space (bytes)'),
            _('Used Space (human)'),
            _('Free Space (bytes)'),
            _('Usage %'),
            _('Daily Growth (bytes)'),
            _('Daily Growth (human)'),
            _('Days Until Full'),
            _('Growth Trend'),
            _('Confidence %'),
            _('Status'),
            _('Warning Threshold'),
            _('Critical Threshold'),
            _('Last Updated')
        ]);
        
        // Data rows
        foreach ($storageData as $item) {
            $free = $item['total_raw'] - $item['used_raw'];
            
            fputcsv($output, [
                $item['host'],
                $item['host_name'],
                $item['mount'],
                $item['total_raw'],
                $item['total_space'],
                $item['used_raw'],
                $item['used_space'],
                $free,
                $item['usage_pct'],
                $item['daily_growth_raw'] ?? 0,
                $item['daily_growth'] ?? '0 B/day',
                $item['days_until_full'] ?? _('No growth'),
                $item['growth_trend'] ?? 'stable',
                $item['confidence'] ?? 0,
                $item['status'] ?? 'ok',
                $filter['warning_threshold'],
                $filter['critical_threshold'],
                date('Y-m-d H:i:s')
            ]);
        }
        
        // Summary section
        fputcsv($output, []); // Empty row
        fputcsv($output, [_('SUMMARY SECTION')]);
        fputcsv($output, [
            _('Metric'), _('Value'), _('Details')
        ]);
        
        fputcsv($output, [
            _('Total Hosts'),
            $summary['total_hosts'],
            ''
        ]);
        
        fputcsv($output, [
            _('Total Filesystems'),
            $summary['total_filesystems'],
            ''
        ]);
        
        fputcsv($output, [
            _('Total Capacity'),
            $summary['total_capacity_raw'],
            $summary['total_capacity']
        ]);
        
        fputcsv($output, [
            _('Total Used'),
            $summary['total_used_raw'],
            $summary['total_used']
        ]);
        
        fputcsv($output, [
            _('Average Usage %'),
            $summary['total_usage_pct'],
            ''
        ]);
        
        fputcsv($output, [
            _('Critical Filesystems'),
            $summary['critical_count'],
            ''
        ]);
        
        fputcsv($output, [
            _('Warning Filesystems'),
            $summary['warning_count'],
            ''
        ]);
        
        fputcsv($output, [
            _('Average Daily Growth'),
            $summary['avg_daily_growth'],
            $summary['avg_daily_growth_fmt']
        ]);
        
        if ($summary['earliest_full']) {
            fputcsv($output, [
                _('Earliest Full Forecast'),
                $summary['earliest_full']['days'],
                sprintf(_('%s on %s'), $summary['earliest_full']['host'], $summary['earliest_full']['date'])
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    public function exportHTML(array $storageData, array $summary, array $filter): void {
        $filename = 'storage_analytics_' . date('Y-m-d_H-i-s') . '.html';
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= _('Storage Analytics Export') ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                h1, h2, h3 { color: #2c3e50; }
                .header { border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
                .summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                .card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #f9f9f9; }
                .card h3 { margin-top: 0; font-size: 14px; color: #7f8c8d; }
                .card .value { font-size: 24px; font-weight: bold; margin: 10px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
                th { background: #f2f2f2; font-weight: bold; }
                tr.critical { background: #ffebee; }
                tr.warning { background: #fff8e1; }
                .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
                .critical { background: #ffcdd2; color: #c62828; }
                .warning { background: #ffecb3; color: #ff8f00; }
                .ok { background: #c8e6c9; color: #2e7d32; }
                .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 12px; color: #7f8c8d; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?= _('Storage Analytics Report') ?></h1>
                <p><?= _('Generated on') ?>: <?= date('Y-m-d H:i:s') ?></p>
                <p><?= _('Filter') ?>: 
                    <?= _('Time Range') ?>: <?= $filter['time_range'] ?> <?= _('days') ?> | 
                    <?= _('Method') ?>: <?= $filter['prediction_method'] ?> |
                    <?= _('Warning') ?>: <?= $filter['warning_threshold'] ?>% |
                    <?= _('Critical') ?>: <?= $filter['critical_threshold'] ?>%
                </p>
            </div>
            
            <div class="summary-cards">
                <div class="card">
                    <h3><?= _('Total Storage') ?></h3>
                    <div class="value"><?= $summary['total_capacity'] ?></div>
                    <div><?= $summary['total_usage_pct'] ?>% <?= _('used') ?></div>
                </div>
                
                <div class="card">
                    <h3><?= _('Used Storage') ?></h3>
                    <div class="value"><?= $summary['total_used'] ?></div>
                    <div>
                        <span class="critical"><?= $summary['critical_count'] ?> <?= _('critical') ?></span>,
                        <span class="warning"><?= $summary['warning_count'] ?> <?= _('warning') ?></span>
                    </div>
                </div>
                
                <div class="card">
                    <h3><?= _('Avg Daily Growth') ?></h3>
                    <div class="value"><?= $summary['avg_daily_growth_fmt'] ?></div>
                    <div><?= $summary['total_hosts'] ?> <?= _('hosts') ?>, <?= $summary['total_filesystems'] ?> <?= _('filesystems') ?></div>
                </div>
                
                <div class="card">
                    <h3><?= _('Earliest Full') ?></h3>
                    <div class="value">
                        <?php if ($summary['earliest_full']): ?>
                            <?= $summary['earliest_full']['days'] ?> <?= _('days') ?>
                        <?php else: ?>
                            <?= _('N/A') ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($summary['earliest_full']): ?>
                        <div><?= $summary['earliest_full']['host'] ?> (<?= $summary['earliest_full']['date'] ?>)</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <h2><?= _('Detailed Storage Analysis') ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?= _('Host') ?></th>
                        <th><?= _('Mount Point') ?></th>
                        <th><?= _('Total') ?></th>
                        <th><?= _('Used') ?></th>
                        <th><?= _('Usage %') ?></th>
                        <th><?= _('Daily Growth') ?></th>
                        <th><?= _('Days Until Full') ?></th>
                        <th><?= _('Status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($storageData as $item): ?>
                    <tr class="<?= $item['status'] ?>">
                        <td><?= htmlspecialchars($item['host']) ?></td>
                        <td><code><?= htmlspecialchars($item['mount']) ?></code></td>
                        <td><?= $item['total_space'] ?></td>
                        <td><?= $item['used_space'] ?></td>
                        <td><?= $item['usage_pct'] ?>%</td>
                        <td><?= $item['daily_growth'] ?? '0 B/day' ?></td>
                        <td><?= $item['days_until_full'] ?? _('No growth') ?></td>
                        <td>
                            <span class="status-badge <?= $item['status'] ?>">
                                <?= ucfirst($item['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($summary['top_growers'])): ?>
            <h3><?= _('Fastest Growing Filesystems') ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= _('Host') ?></th>
                        <th><?= _('Mount Point') ?></th>
                        <th><?= _('Growth/Day') ?></th>
                        <th><?= _('Days Left') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary['top_growers'] as $grower): ?>
                    <tr>
                        <td><?= htmlspecialchars($grower['host']) ?></td>
                        <td><code><?= htmlspecialchars($grower['mount']) ?></code></td>
                        <td><?= $grower['daily_growth'] ?></td>
                        <td><?= $grower['days_until_full'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <div class="footer">
                <p><?= _('Report generated by Zabbix Storage Analytics Pro Module') ?></p>
                <p><?= _('Calculation based on') ?>: <?= $filter['time_range'] ?> <?= _('days of historical data') ?></p>
                <p><?= _('Prediction method') ?>: <?= $filter['prediction_method'] ?></p>
                <p><?= _('Thresholds') ?>: <?= _('Warning') ?> <?= $filter['warning_threshold'] ?>%, <?= _('Critical') ?> <?= $filter['critical_threshold'] ?>%</p>
            </div>
        </body>
        </html>
        <?php
        echo ob_get_clean();
        exit;
    }
    
    public function exportJSON(array $storageData, array $summary, array $filter): void {
        $filename = 'storage_analytics_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $exportData = [
            'metadata' => [
                'generated' => date('Y-m-d H:i:s'),
                'time_range' => $filter['time_range'],
                'prediction_method' => $filter['prediction_method'],
                'warning_threshold' => $filter['warning_threshold'],
                'critical_threshold' => $filter['critical_threshold'],
                'total_records' => count($storageData)
            ],
            'summary' => $summary,
            'data' => $storageData,
            'top_growers' => $summary['top_growers'] ?? [],
            'statistics' => [
                'hosts_by_status' => [
                    'ok' => 0,
                    'warning' => 0,
                    'critical' => 0
                ],
                'growth_distribution' => [
                    'rapid_increase' => 0,
                    'increasing' => 0,
                    'slow_increase' => 0,
                    'stable' => 0,
                    'decreasing' => 0
                ]
            ]
        ];
        
        // Calculate additional statistics
        foreach ($storageData as $item) {
            $exportData['statistics']['hosts_by_status'][$item['status']]++;
            
            if (isset($item['growth_trend'])) {
                $exportData['statistics']['growth_distribution'][$item['growth_trend']]++;
            }
        }
        
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
