<?php
/**
 * Host Table Partial
 */
?>

<div class="table-section">
    <div class="table-header">
        <h2><?= _('Storage Analysis by Host') ?></h2>
        <div class="table-actions">
            <div class="view-toggle">
                <button type="button" class="btn-view-toggle active" data-view="host"><?= _('Host View') ?></button>
                <button type="button" class="btn-view-toggle" data-view="filesystem"><?= _('Filesystem View') ?></button>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?action=storage.analytics&page=<?= $page - 1 ?><?= $buildQueryString($filter, ['page']) ?>" 
                       class="pagination-link prev"><?= _('Previous') ?></a>
                <?php endif; ?>
                
                <span class="pagination-info">
                    <?= sprintf(_('Page %1$s of %2$s'), $page, $total_pages) ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?action=storage.analytics&page=<?= $page + 1 ?><?= $buildQueryString($filter, ['page']) ?>" 
                       class="pagination-link next"><?= _('Next') ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Helper function to parse days from string -->
    <?php
    function parseDaysFromString(string $daysStr): int {
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
    
    // Group by host first
    $hostsData = [];
    foreach ($storageData as $item) {
        $hostId = $item['hostid'];
        if (!isset($hostsData[$hostId])) {
            $hostsData[$hostId] = [
                'host' => $item['host'],
                'host_name' => $item['host_name'],
                'total_raw' => 0,
                'used_raw' => 0,
                'usage_pct' => 0,
                'growth_filesystems' => [], // Track filesystems with growth
                'daily_growth_raw' => 0,
                'days_until_full' => null,
                'filesystems' => [],
                'status' => 'ok', // Start with ok
                'fs_count' => 0
            ];
        }
        
        $hostsData[$hostId]['total_raw'] += $item['total_raw'];
        $hostsData[$hostId]['used_raw'] += $item['used_raw'];
        $hostsData[$hostId]['filesystems'][] = $item;
        $hostsData[$hostId]['fs_count']++;
        
        // Track filesystems with growth for host-level calculation
        if (isset($item['daily_growth_raw']) && $item['daily_growth_raw'] > 0) {
            $hostsData[$hostId]['growth_filesystems'][] = [
                'growth' => $item['daily_growth_raw'],
                'days_until_full' => $item['days_until_full']
            ];
            $hostsData[$hostId]['daily_growth_raw'] += $item['daily_growth_raw'];
        }
        
        // Calculate filesystem status based on BOTH usage % AND days until full
        $fsStatus = 'ok';
        
        // 1. Check usage percentage
        if ($item['usage_pct'] >= $filter['critical_threshold']) {
            $fsStatus = 'critical';
        } elseif ($item['usage_pct'] >= $filter['warning_threshold']) {
            $fsStatus = 'warning';
        }
        
        // 2. Check Days Until Full (ONLY if not already critical from usage)
        if ($fsStatus !== 'critical') {
            $daysUntilFull = parseDaysFromString($item['days_until_full'] ?? _('No growth'));
            
            if ($daysUntilFull <= 15 && $daysUntilFull < PHP_INT_MAX) {
                $fsStatus = 'critical';
            } elseif ($daysUntilFull <= 30 && $daysUntilFull < PHP_INT_MAX) {
                // Only elevate to warning if not already warning from usage
                if ($fsStatus !== 'warning') {
                    $fsStatus = 'warning';
                }
            }
        }
        
        // Update host status to worst among filesystems
        if ($fsStatus === 'critical' || $hostsData[$hostId]['status'] === 'critical') {
            $hostsData[$hostId]['status'] = 'critical';
        } elseif ($fsStatus === 'warning' && $hostsData[$hostId]['status'] !== 'critical') {
            $hostsData[$hostId]['status'] = 'warning';
        }
    }
    
    // Calculate host-level metrics
    foreach ($hostsData as &$hostData) {
        if ($hostData['total_raw'] > 0) {
            $hostData['usage_pct'] = round(($hostData['used_raw'] / $hostData['total_raw']) * 100, 1);
        }
        
        // Calculate REALISTIC host growth (median of growth filesystems to avoid outliers)
        $hostData['growth_rate'] = '0';
        $hostData['growth_rate_display'] = _('Stable');
        $hostData['growth_class'] = 'neutral';
        
        if (!empty($hostData['growth_filesystems'])) {
            // Calculate median growth (to avoid outliers)
            $growthValues = array_column($hostData['growth_filesystems'], 'growth');
            sort($growthValues);
            $count = count($growthValues);
            $middle = floor(($count - 1) / 2);
            
            if ($count % 2) {
                $medianGrowth = $growthValues[$middle];
            } else {
                $medianGrowth = ($growthValues[$middle] + $growthValues[$middle + 1]) / 2;
            }
            
            // Cap unrealistic growth (> 10GB/day)
            if ($medianGrowth > 10737418240) { // 10 GB
                $medianGrowth = 0;
            }
            
            if ($medianGrowth > 0) {
                $hostData['growth_rate'] = $medianGrowth;
                $hostData['growth_rate_display'] = '+' . $formatBytes($medianGrowth) . '/day';
                $hostData['growth_class'] = 'positive';
            }
        }
        
        // Calculate earliest days until full among filesystems (for display)
        $earliestDays = null;
        $validFilesystems = [];
        
        foreach ($hostData['filesystems'] as $fs) {
            if (isset($fs['daily_growth_raw']) && $fs['daily_growth_raw'] > 0) {
                $days = parseDaysFromString($fs['days_until_full'] ?? _('No growth'));
                if ($days < PHP_INT_MAX) {
                    $validFilesystems[] = $days;
                    if ($earliestDays === null || $days < $earliestDays) {
                        $earliestDays = $days;
                    }
                }
            }
        }
        
        // Format for display
        if ($earliestDays !== null && $earliestDays < PHP_INT_MAX) {
            if ($earliestDays > 365) {
                $years = floor($earliestDays / 365);
                $remainingDays = $earliestDays % 365;
                $months = floor($remainingDays / 30);
                
                if ($months > 0) {
                    $hostData['days_until_full'] = sprintf(_('%d years %d months'), $years, $months);
                } else {
                    $hostData['days_until_full'] = sprintf(_('%d years'), $years);
                }
            } elseif ($earliestDays > 30) {
                $months = floor($earliestDays / 30);
                $remainingDays = $earliestDays % 30;
                
                if ($remainingDays > 0) {
                    $hostData['days_until_full'] = sprintf(_('%d months %d days'), $months, $remainingDays);
                } else {
                    $hostData['days_until_full'] = sprintf(_('%d months'), $months);
                }
            } else {
                $hostData['days_until_full'] = sprintf(_('%d days'), $earliestDays);
            }
        } else {
            $hostData['days_until_full'] = _('No growth');
        }
        
        // Determine days cell status for coloring
        $hostData['days_status'] = 'ok';
        if ($earliestDays !== null && $earliestDays < PHP_INT_MAX) {
            if ($earliestDays <= 15) {
                $hostData['days_status'] = 'critical';
            } elseif ($earliestDays <= 30) {
                $hostData['days_status'] = 'warning';
            }
        }
        
        // Recalculate host status based on BOTH usage % AND days until full
        $finalHostStatus = 'ok';
        
        // 1. Check host usage percentage
        if ($hostData['usage_pct'] >= $filter['critical_threshold']) {
            $finalHostStatus = 'critical';
        } elseif ($hostData['usage_pct'] >= $filter['warning_threshold']) {
            $finalHostStatus = 'warning';
        }
        
        // 2. Check earliest days until full among filesystems
        if ($finalHostStatus !== 'critical' && $earliestDays !== null && $earliestDays < PHP_INT_MAX) {
            if ($earliestDays <= 15) {
                $finalHostStatus = 'critical';
            } elseif ($earliestDays <= 30) {
                if ($finalHostStatus !== 'warning') {
                    $finalHostStatus = 'warning';
                }
            }
        }
        
        // Update the host status
        $hostData['status'] = $finalHostStatus;
    }
    
    // Paginate hosts
    $hostsList = array_slice($hostsData, ($page - 1) * $page_limit, $page_limit);
    ?>
    
    <!-- Host View Table -->
    <div class="table-container host-view" id="host-view">
        <table class="list-table">
            <thead>
                <tr>
                    <th><?= _('Host') ?></th>
                    <th><?= _('Total Space') ?></th>
                    <th><?= _('Used Space') ?></th>
                    <th><?= _('Usage %') ?></th>
                    <th><?= _('Growth Rate') ?></th>
                    <th><?= _('Days Until Full') ?></th>
                    <th><?= _('Status') ?></th>
                    <th><?= _('Filesystems') ?></th>
                    <th><?= _('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hostsList as $hostId => $host): ?>
                <tr class="host-row" data-hostid="<?= $hostId ?>">
                    <td class="host-cell">
                        <strong><?= htmlspecialchars($host['host']) ?></strong>
                        <?php if ($host['host_name'] !== $host['host']): ?>
                            <div class="host-alias"><?= htmlspecialchars($host['host_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $formatBytes($host['total_raw']) ?></td>
                    <td><?= $formatBytes($host['used_raw']) ?></td>
                    <td>
                        <div class="usage-cell">
                            <span class="usage-value"><?= $host['usage_pct'] ?>%</span>
                            <div class="usage-bar">
                                <div class="usage-fill" style="width: <?= min($host['usage_pct'], 100) ?>%"
                                     data-threshold-warning="<?= $filter['warning_threshold'] ?>"
                                     data-threshold-critical="<?= $filter['critical_threshold'] ?>">
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <!-- FIXED GROWTH RATE DISPLAY -->
                        <span class="growth-value <?= $host['growth_class'] ?>">
                            <?= $host['growth_rate_display'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="days-cell <?= $host['days_status'] ?>">
                            <?= $host['days_until_full'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= $host['status'] ?>">
                            <?= ucfirst($host['status']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="fs-count"><?= $host['fs_count'] ?></span>
                    </td>
                    <td>
                        <button type="button" class="btn-details btn-small" 
                                data-hostid="<?= $hostId ?>"
                                data-host="<?= htmlspecialchars($host['host']) ?>">
                            <?= _('Details') ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($hostsList)): ?>
                <tr>
                    <td colspan="9" class="no-data">
                        <?= _('No storage data found matching the filters.') ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Filesystem View Table (Initially hidden) -->
    <div class="table-container filesystem-view" id="filesystem-view" style="display: none;">
        <?php include __DIR__ . '/filesystem_details.php'; ?>
    </div>
</div>

<!-- Host Details Modal (for AJAX loading) -->
<div class="modal" id="host-details-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title"><?= _('Host Details') ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="modal-body">
            <!-- Content loaded via AJAX -->
            <div class="loading"><?= _('Loading...') ?></div>
        </div>
    </div>
</div>

<style>
.growth-value.positive {
    color: #d9534f;
    font-weight: 600;
    font-size: 13px;
}

.growth-value.neutral {
    color: #777;
    font-style: italic;
}

.days-cell {
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 13px;
    display: inline-block;
}

.days-cell.ok {
    background: #d5f4e6;
    color: #27ae60;
}

.days-cell.warning {
    background: #fef5e7;
    color: #f39c12;
}

.days-cell.critical {
    background: #fdedec;
    color: #e74c3c;
}

.fs-count {
    display: inline-block;
    padding: 2px 8px;
    background: #ecf0f1;
    color: #7f8c8d;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.ok {
    background: #d5f4e6;
    color: #27ae60;
    border: 1px solid #a3e4d7;
}

.status-badge.warning {
    background: #fef5e7;
    color: #f39c12;
    border: 1px solid #f8c471;
}

.status-badge.critical {
    background: #fdedec;
    color: #e74c3c;
    border: 1px solid #f1948a;
}
</style>

<script>
// JavaScript for view toggle
document.addEventListener('DOMContentLoaded', function() {
    // View toggle buttons
    document.querySelectorAll('.btn-view-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            var view = this.getAttribute('data-view');
            
            // Update active button
            document.querySelectorAll('.btn-view-toggle').forEach(function(btn) {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            // Show/hide tables
            if (view === 'host') {
                document.getElementById('host-view').style.display = 'block';
                document.getElementById('filesystem-view').style.display = 'none';
            } else {
                document.getElementById('host-view').style.display = 'none';
                document.getElementById('filesystem-view').style.display = 'block';
            }
        });
    });
    
    // Details button click handler
    document.querySelectorAll('.btn-details').forEach(function(button) {
        button.addEventListener('click', function() {
            var hostId = this.getAttribute('data-hostid');
            var hostName = this.getAttribute('data-host');
            
            // Show loading state
            document.getElementById('modal-title').textContent = 
                '<?= _("Storage Details") ?>: ' + hostName;
            document.getElementById('modal-body').innerHTML = 
                '<div class="loading"><?= _("Loading...") ?></div>';
            
            // Show modal
            document.getElementById('host-details-modal').style.display = 'block';
            
            // Load host details via AJAX
            loadHostDetails(hostId, hostName);
        });
    });
    
    // Close modal button
    document.querySelector('.modal-close').addEventListener('click', function() {
        document.getElementById('host-details-modal').style.display = 'none';
    });
    
    // Close modal when clicking outside
    document.getElementById('host-details-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    
    // Function to load host details
    function loadHostDetails(hostId, hostName) {
        var modalBody = document.getElementById('modal-body');
        
        // Create a simple table with filesystem details
        var html = '<h4><?= _("Filesystems on") ?> ' + hostName + '</h4>';
        html += '<table class="modal-table">';
        html += '<thead><tr>';
        html += '<th><?= _("Mount Point") ?></th>';
        html += '<th><?= _("Total") ?></th>';
        html += '<th><?= _("Used") ?></th>';
        html += '<th><?= _("Usage %") ?></th>';
        html += '<th><?= _("Growth") ?></th>';
        html += '<th><?= _("Days Until Full") ?></th>';
        html += '<th><?= _("Status") ?></th>';
        html += '</tr></thead><tbody>';
        
        // Find the host data (simplified - in real app you'd fetch via AJAX)
        // For now, we'll just show a message
        html += '<tr><td colspan="7" style="text-align: center; padding: 20px;">';
        html += '<?= _("Detailed filesystem data would be loaded here via AJAX.") ?>';
        html += '</td></tr>';
        
        html += '</tbody></table>';
        modalBody.innerHTML = html;
    }
});
</script>
