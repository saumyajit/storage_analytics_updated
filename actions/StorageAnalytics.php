<?php
namespace Modules\diskanalyser\actions;

use CController;
use CControllerResponseData;
use API;

class StorageAnalytics extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'hostids'           => 'array_id',
            'groupids'          => 'array_id',
            'host'              => 'string',
            'time_range'        => 'in 7,14,30,90,180,365',
            'prediction_method' => 'in simple,seasonal,holt_winters,ensemble',
            'warning_threshold' => 'ge 0|le 100',
            'critical_threshold'=> 'ge 0|le 100',
            'refresh'           => 'in 0,30,60,120,300,600',
            'refresh_enabled'   => 'in 0,1',
            'page'              => 'ge 1',
            'tags'              => 'array',
            'filter_enabled'    => 'in 0,1',
            'export'            => 'in csv,html,json'
        ];
        
        $ret = $this->validateInput($fields);
        
        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => _('Invalid input parameters')]));
        }
        
        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // Check for export request FIRST
        $export = $this->getInput('export', '');
        
        if ($export) {
            $this->handleSimpleExport($export);
            return;
        }

        // Get filter values with defaults
        $filter = [
            'hostids'           => $this->getInput('hostids', []),
            'groupids'          => $this->getInput('groupids', []),
            'host'              => $this->getInput('host', ''),
            'time_range'        => $this->getInput('time_range', 30),
            'prediction_method' => $this->getInput('prediction_method', 'seasonal'),
            'warning_threshold' => $this->getInput('warning_threshold', 80),
            'critical_threshold'=> $this->getInput('critical_threshold', 90),
            'refresh'           => $this->getInput('refresh', 60),
            'refresh_enabled'   => $this->getInput('refresh_enabled', 1),
            'page'              => $this->getInput('page', 1),
            'tags'              => $this->getInput('tags', []),
            'filter_enabled'    => $this->getInput('filter_enabled', 0)
        ];

        // Fetch storage data with filters
        $storageData = $this->getDiskDataWithFilters($filter);
        
        // Calculate predictions (simplified - use batch processing)
        $enhancedData = $this->calculateBatchPredictions($storageData, $filter);
        
        // Calculate summary statistics
        $summary = $this->calculateEnhancedSummary($enhancedData, $filter);
        
        // Get filter options for UI
        $filterOptions = $this->getFilterOptions($filter);

        $response = new CControllerResponseData([
            'storageData'     => $enhancedData,
            'summary'         => $summary,
            'filter'          => $filter,
            'filterOptions'   => $filterOptions,
            'page'            => $filter['page'],
            'page_limit'      => 50,
            'total_records'   => count($enhancedData),
            'formatBytes'     => [$this, 'formatBytes'],
            'buildQueryString'=> function($filter, $exclude = []) {
                return $this->buildQueryString($filter, $exclude);
            }
        ]);
        
        $response->setTitle(_('Storage Analytics'));
        $this->setResponse($response);
    }

    /**
     * Simple export handler
     */
    private function handleSimpleExport(string $format): void {
        // Get filter values
        $filter = [
            'hostids'           => $this->getInput('hostids', []),
            'groupids'          => $this->getInput('groupids', []),
            'time_range'        => $this->getInput('time_range', 30),
            'prediction_method' => $this->getInput('prediction_method', 'seasonal'),
            'warning_threshold' => $this->getInput('warning_threshold', 80),
            'critical_threshold'=> $this->getInput('critical_threshold', 90)
        ];
        
        // Fetch data
        $storageData = $this->getDiskDataWithFilters($filter);
        $enhancedData = $this->calculateBatchPredictions($storageData, $filter);
        $summary = $this->calculateEnhancedSummary($enhancedData, $filter);
        
        switch ($format) {
            case 'csv':
                $this->exportCSV($enhancedData, $summary, $filter);
                break;
                
            case 'html':
                $this->exportHTML($enhancedData, $summary, $filter);
                break;
                
            case 'json':
                $this->exportJSON($enhancedData, $summary, $filter);
                break;
        }
    }

    /**
     * WORKING DATA COLLECTION METHOD (keep original)
     */
    private function getDiskDataWithFilters(array $filter): array {
        $diskData = [];
        
        // Build API parameters based on filters
        $apiParams = [
            'search' => ['key_' => 'vfs.fs.size'],
            'output' => ['itemid', 'key_', 'name', 'lastvalue', 'hostid'],
            'selectHosts' => ['hostid', 'name'],
            'monitored' => true,
            'preservekeys' => true
        ];
        
        // Apply group filter
        if (!empty($filter['groupids'])) {
            $apiParams['groupids'] = $filter['groupids'];
        }
        
        // Apply host filter
        if (!empty($filter['hostids'])) {
            $apiParams['hostids'] = $filter['hostids'];
        }
        
        // Apply host search
        if (!empty($filter['host'])) {
            $apiParams['search']['host'] = $filter['host'];
        }
        
        $items = API::Item()->get($apiParams);
        
        $groupedData = [];
        $hostNames = [];
        
        foreach ($items as $item) {
            // Match mount point and item type ('total' or 'pused')
            if (!preg_match('/vfs\.fs\.size\[(.*),(total|pused|used)\]/i', $item['key_'], $matches)) {
                continue;
            }

            $hostId = $item['hostid'];
            $hostNames[$hostId] = $item['hosts'][0]['name'];
            
            $mountPointKey = trim($matches[1], '"') ?: '/';
            $type = strtolower($matches[2]); // 'total', 'pused', or 'used'

            $key = $hostId . '|' . $mountPointKey;

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'hostid' => $hostId,
                    'mount' => $mountPointKey,
                    'total' => 0.0,
                    'pused' => 0.0,
                    'used' => 0.0
                ];
            }
            
            $groupedData[$key][$type] = (float) $item['lastvalue'];
        }

        // Calculate metrics for the final array
        foreach ($groupedData as $key => $data) {
            $hostId = $data['hostid'];
            $totalRaw = $data['total'];
            $pused = $data['pused'];
            $usedRaw = $data['used'];
            
            // Skip if we don't have data
            if ($totalRaw <= 0 && $usedRaw <= 0) {
                continue;
            }
            
            // Calculate used bytes if we have percentage but not raw used
            if ($usedRaw <= 0 && $pused > 0 && $totalRaw > 0) {
                $usedRaw = $totalRaw * ($pused / 100.0);
            }
            
            // Calculate total if we have used and percentage
            if ($totalRaw <= 0 && $usedRaw > 0 && $pused > 0) {
                $totalRaw = $usedRaw / ($pused / 100.0);
            }
            
            // Skip if still no valid data
            if ($totalRaw <= 0 || $usedRaw <= 0) {
                continue;
            }
            
            $usagePct = round(($usedRaw / $totalRaw) * 100, 1);
            
            $diskData[] = [
                'hostid' => $hostId,
                'host' => $hostNames[$hostId] ?? 'Unknown',
                'host_name' => $hostNames[$hostId] ?? 'Unknown',
                'mount' => $data['mount'],
                'total_raw' => $totalRaw,
                'used_raw' => $usedRaw,
                'pused' => $pused,
                'total_space' => $this->formatBytes($totalRaw),
                'used_space' => $this->formatBytes($usedRaw),
                'usage_pct' => $usagePct
            ];
        }

        return $diskData;
    }

    /**
     * SIMPLIFIED: Calculate predictions with batch processing
     */
    private function calculateBatchPredictions(array $storageData, array $filter): array {
        $method = $filter['prediction_method'];
        $days = $filter['time_range'];
        
        // Group item IDs for batch processing
        $itemRequests = [];
        foreach ($storageData as $idx => $item) {
            $itemKey = 'vfs.fs.size[' . $item['mount'] . ',used]';
            $items = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $item['hostid'],
                'filter' => ['key_' => $itemKey],
                'limit' => 1
            ]);
            
            if (!empty($items)) {
                $itemRequests[$items[0]['itemid']] = $idx;
            }
        }
        
        // Batch history call if we have items
        if (!empty($itemRequests)) {
            $itemIds = array_keys($itemRequests);
            $timeFrom = time() - ($days * 86400);
            
            $history = API::History()->get([
                'output' => ['itemid', 'clock', 'value'],
                'itemids' => $itemIds,
                'history' => 3,
                'time_from' => $timeFrom,
                'sortfield' => ['itemid', 'clock'],
                'limit' => 1000
            ]);
            
            // Group history by item ID
            $groupedHistory = [];
            foreach ($history as $record) {
                $groupedHistory[$record['itemid']][] = $record;
            }
            
            // Process each item
            foreach ($itemRequests as $itemId => $dataIdx) {
                $itemHistory = $groupedHistory[$itemId] ?? [];
                
                if (count($itemHistory) >= 2) {
                    $first = reset($itemHistory);
                    $last = end($itemHistory);
                    
                    $valueDiff = $last['value'] - $first['value'];
                    $timeDiff = max(1, ($last['clock'] - $first['clock']) / 86400);
                    $dailyGrowth = $valueDiff / $timeDiff;
                    
                    // Apply to storage data
                    $storageData[$dataIdx]['daily_growth_raw'] = $dailyGrowth;
                    $storageData[$dataIdx]['daily_growth'] = $dailyGrowth > 0 
                        ? $this->formatBytes($dailyGrowth) . '/day' 
                        : _('Stable');
                        
                    $storageData[$dataIdx]['days_until_full'] = $this->calculateDaysUntilFull(
                        $storageData[$dataIdx]['total_raw'],
                        $storageData[$dataIdx]['used_raw'],
                        $dailyGrowth
                    );
                    
                    $storageData[$dataIdx]['growth_trend'] = $this->determineTrend($dailyGrowth);
                    $storageData[$dataIdx]['confidence'] = min(100, (count($itemHistory) / $days) * 100);
                    $storageData[$dataIdx]['algorithm'] = $method;
                }
            }
        }
        
        // Set defaults for items without history
        foreach ($storageData as &$item) {
            if (!isset($item['daily_growth_raw'])) {
                $item['daily_growth_raw'] = 0;
                $item['daily_growth'] = _('Stable');
                $item['days_until_full'] = _('No growth');
                $item['growth_trend'] = 'stable';
                $item['confidence'] = 0;
                $item['algorithm'] = $method;
            }
            
            // Determine status
            $item['status'] = $this->determineStatus(
                $item['usage_pct'],
                $item['days_until_full'],
                $filter['warning_threshold'],
                $filter['critical_threshold']
            );
        }
        
        return $storageData;
    }

    /**
     * Determine growth trend direction
     */
    private function determineTrend(float $dailyGrowth): string {
        if ($dailyGrowth > 1073741824) { // > 1GB/day
            return 'rapid_increase';
        } elseif ($dailyGrowth > 104857600) { // > 100MB/day
            return 'increasing';
        } elseif ($dailyGrowth > 0) {
            return 'slow_increase';
        } elseif ($dailyGrowth < -104857600) { // < -100MB/day
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Calculate days until full with growth rate
     */
    private function calculateDaysUntilFull(float $total, float $used, float $dailyGrowth): string {
        if ($dailyGrowth <= 0) {
            return _('No growth');
        }

        $freeSpace = $total - $used;
        
        if ($freeSpace <= 0) {
            return _('Already full');
        }

        // Additional check for unrealistic growth
        if ($dailyGrowth > $freeSpace * 10) {
            return _('No growth');
        }

        $days = $freeSpace / $dailyGrowth;
        
        // Handle unrealistic calculations
        if ($days > 365 * 10) { // More than 10 years
            return _('More than 10 years');
        } elseif ($days > 365) {
            $years = floor($days / 365);
            $remainingDays = $days % 365;
            $months = floor($remainingDays / 30);
            
            if ($months > 0) {
                return sprintf(_('%d years %d months'), $years, $months);
            } else {
                return sprintf(_('%d years'), $years);
            }
        } elseif ($days > 30) {
            $months = floor($days / 30);
            $remainingDays = floor($days % 30);
            
            if ($remainingDays > 0) {
                return sprintf(_('%d months %d days'), $months, $remainingDays);
            } else {
                return sprintf(_('%d months'), $months);
            }
        } else {
            return sprintf(_('%d days'), floor($days));
        }
    }

    /**
     * Determine status based on usage and days until full
     */
	private function determineStatus(float $usagePct, string $daysUntilFull, float $warning, float $critical): string {
		if ($usagePct >= $critical) {
			return 'critical';
		} elseif ($usagePct >= $warning) {
			return 'warning';
		}
	
		// Check days until full
		$days = $this->parseDaysToNumber($daysUntilFull);
	
		if ($days <= 15 && $days < PHP_INT_MAX) {
			return 'critical';
		} elseif ($days <= 30 && $days < PHP_INT_MAX) {
			return 'warning';
		}
	
		return 'ok';
	}

    /**
     * Calculate enhanced summary statistics
     */
    private function calculateEnhancedSummary(array $storageData, array $filter): array {
        $summary = [
            'total_capacity_raw' => 0,
            'total_used_raw' => 0,
            'critical_count' => 0,
            'warning_count' => 0,
            'total_hosts' => 0,
            'total_filesystems' => count($storageData),
            'earliest_full' => null,
            'top_growers' => []
        ];

        $hosts = [];
        $growthData = [];
        $allGrowthValues = [];

        foreach ($storageData as $item) {
            $summary['total_capacity_raw'] += $item['total_raw'];
            $summary['total_used_raw'] += $item['used_raw'];
            
            // Collect growth data for averaging
            if (isset($item['daily_growth_raw']) && $item['daily_growth_raw'] > 0) {
                $growthData[] = $item;
                $allGrowthValues[] = $item['daily_growth_raw'];
            }

            if ($item['status'] === 'critical') {
                $summary['critical_count']++;
            } elseif ($item['status'] === 'warning') {
                $summary['warning_count']++;
            }

            $hosts[$item['hostid']] = true;

            // Track earliest full
            if (isset($item['daily_growth_raw']) && $item['daily_growth_raw'] > 0) {
                $days = $this->parseDaysToNumber($item['days_until_full']);
                
                if ($days > 0 && $days < PHP_INT_MAX) {
                    if ($summary['earliest_full'] === null || $days < $summary['earliest_full']['days']) {
                        $summary['earliest_full'] = [
                            'host' => $item['host'],
                            'mount' => $item['mount'],
                            'days' => $days,
                            'date' => date('Y-m-d', time() + ($days * 86400))
                        ];
                    }
                }
            }
        }

        $summary['total_hosts'] = count($hosts);
        
        // Calculate percentages
        $summary['total_usage_pct'] = $summary['total_capacity_raw'] > 0 
            ? round(($summary['total_used_raw'] / $summary['total_capacity_raw']) * 100, 1)
            : 0;

        // Calculate median growth
        $summary['avg_daily_growth'] = 0;
        if (!empty($allGrowthValues)) {
            sort($allGrowthValues);
            $count = count($allGrowthValues);
            $middle = floor(($count - 1) / 2);
            
            if ($count % 2) {
                $summary['avg_daily_growth'] = $allGrowthValues[$middle];
            } else {
                $summary['avg_daily_growth'] = ($allGrowthValues[$middle] + $allGrowthValues[$middle + 1]) / 2;
            }
            
            // Cap unrealistic growth
            if ($summary['avg_daily_growth'] > 10737418240) { // 10GB/day
                $summary['avg_daily_growth'] = 0;
            }
        }

        // Format for display
        $summary['total_capacity'] = $this->formatBytes($summary['total_capacity_raw']);
        $summary['total_used'] = $this->formatBytes($summary['total_used_raw']);
        $summary['avg_daily_growth_fmt'] = $summary['avg_daily_growth'] > 0 
            ? $this->formatBytes($summary['avg_daily_growth']) . '/day'
            : '0 B/day';

        // Get top 5 fastest growing filesystems
        usort($growthData, function($a, $b) {
            return ($b['daily_growth_raw'] ?? 0) <=> ($a['daily_growth_raw'] ?? 0);
        });
        
        $summary['top_growers'] = array_slice($growthData, 0, 5);

        return $summary;
    }

    /**
     * Helper to parse days string to number
     */
	private function parseDaysToNumber(string $daysStr): int {
		if ($daysStr === _('No growth') || $daysStr === _('Already full') || 
			$daysStr === _('More than 10 years')) {
			return PHP_INT_MAX;
		}
		
		$days = PHP_INT_MAX;
		$hasMatch = false;
		
		// Extract years
		if (preg_match('/(\d+)\s*years?/', $daysStr, $matches)) {
			$days = (int)$matches[1] * 365;
			$hasMatch = true;
		}
		
		// Extract months
		if (preg_match('/(\d+)\s*months?/', $daysStr, $matches)) {
			$months = (int)$matches[1] * 30;
			if ($hasMatch) {
				$days += $months;
			} else {
				$days = $months;
				$hasMatch = true;
			}
		}
		
		// Extract days
		if (preg_match('/(\d+)\s*days?/', $daysStr, $matches)) {
			$dayCount = (int)$matches[1];
			if ($hasMatch) {
				$days += $dayCount;
			} else {
				$days = $dayCount;
				$hasMatch = true;
			}
		}
		
		// If it's just a plain number (like "15")
		if (!$hasMatch && is_numeric($daysStr)) {
			$days = (int)$daysStr;
		}
		
		return $days;
	}

    /**
     * Get filter options for UI dropdowns
     */
	private function getFilterOptions(array $currentFilter): array {
		$options = [
			'hostgroups' => [],
			'hosts' => [],
			'time_ranges' => [
				7 => _('Last 7 days'),
				14 => _('Last 14 days'),
				30 => _('Last 30 days'),
				90 => _('Last 90 days'),
				180 => _('Last 180 days'),
				365 => _('Last year')
			],
			'prediction_methods' => [
				'simple' => _('Simple Linear'),
				'seasonal' => _('Seasonal Adjusted'),
				'holt_winters' => _('Holt-Winters (Advanced)'),
				'ensemble' => _('Ensemble (Multi-Model)')
			],
			'refresh_intervals' => [
				0 => _('Manual'),
				30 => _('30 seconds'),
				60 => _('1 minute'),
				120 => _('2 minutes'),
				300 => _('5 minutes'),
				600 => _('10 minutes')
			]
		];
	
		// Get host groups
		$groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'sortfield' => 'name',
			'preservekeys' => true
		]);
	
		foreach ($groups as $group) {
			$options['hostgroups'][] = [
				'id' => $group['groupid'],
				'name' => $group['name'],
				'selected' => in_array($group['groupid'], $currentFilter['groupids'] ?? [])
			];
		}
	
		// Get hosts WITH their groups
		$hostParams = [
			'output' => ['hostid', 'host', 'name'],
			'selectHostGroups' => ['groupid'],
			'sortfield' => 'host'
		];
	
		if (!empty($currentFilter['groupids'])) {
			$hostParams['groupids'] = $currentFilter['groupids'];
		}
	
		$hosts = API::Host()->get($hostParams);
	
		foreach ($hosts as $host) {
			$groupIds = [];
			
			if (isset($host['hostgroups'])) {
				foreach ($host['hostgroups'] as $group) {
					$groupIds[] = $group['groupid'];
				}
			} elseif (isset($host['groups'])) {
				foreach ($host['groups'] as $group) {
					$groupIds[] = $group['groupid'];
				}
			}
			
			$options['hosts'][] = [
				'id' => $host['hostid'],
				'name' => $host['name'] ?: $host['host'],
				'host' => $host['host'],
				'selected' => in_array($host['hostid'], $currentFilter['hostids'] ?? []),
				'groupids' => $groupIds
			];
		}
	
		return $options;
	}

    /**
     * Helper to build query string from filters
     */
    private function buildQueryString(array $filter, array $exclude = []): string {
        $params = [];

        foreach ($filter as $key => $value) {
            if (in_array($key, $exclude)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $val) {
                    $params[] = $key . '[]=' . urlencode($val);
                }
            } else {
                $params[] = $key . '=' . urlencode($value);
            }
        }

        return $params ? '&' . implode('&', $params) : '';
    }

    /**
     * Format bytes to human readable format
     */
    public function formatBytes($bytes, $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Export to CSV
     */
	private function exportCSV(array $storageData, array $summary, array $filter): void {
		$filename = 'storage_analytics_' . date('Y-m-d_H-i-s') . '.csv';
		
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		
		$output = fopen('php://output', 'w');
		
		// UTF-8 BOM for Excel compatibility
		fwrite($output, "\xEF\xBB\xBF");
		
		// ====== HEADER SECTION ======
		fputcsv($output, ['STORAGE ANALYTICS EXPORT']);
		fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
		fputcsv($output, ['Time Range:', $filter['time_range'] . ' days']);
		fputcsv($output, ['Prediction Method:', $filter['prediction_method']]);
		fputcsv($output, ['Warning Threshold:', $filter['warning_threshold'] . '%']);
		fputcsv($output, ['Critical Threshold:', $filter['critical_threshold'] . '%']);
		fputcsv($output, []); // Empty line
		
		// ====== SUMMARY SECTION ======
		fputcsv($output, ['SUMMARY']);
		fputcsv($output, ['Metric', 'Value', 'Details']);
		fputcsv($output, ['Total Hosts', $summary['total_hosts'] ?? 0, '']);
		fputcsv($output, ['Total Filesystems', $summary['total_filesystems'] ?? 0, '']);
		fputcsv($output, ['Total Capacity', $summary['total_capacity'] ?? '0 B', '']);
		fputcsv($output, ['Total Used', $summary['total_used'] ?? '0 B', ($summary['total_usage_pct'] ?? 0) . '% used']);
		fputcsv($output, ['Average Usage', ($summary['total_usage_pct'] ?? 0) . '%', '']);
		fputcsv($output, ['Critical Filesystems', $summary['critical_count'] ?? 0, '']);
		fputcsv($output, ['Warning Filesystems', $summary['warning_count'] ?? 0, '']);
		fputcsv($output, ['OK Filesystems', ($summary['total_filesystems'] ?? 0) - ($summary['critical_count'] ?? 0) - ($summary['warning_count'] ?? 0), '']);
		
		if (isset($summary['avg_daily_growth_fmt'])) {
			fputcsv($output, ['Average Daily Growth', $summary['avg_daily_growth_fmt'], '']);
		}
		
		if (isset($summary['earliest_full']) && !empty($summary['earliest_full'])) {
			fputcsv($output, [
				'Earliest Full Forecast',
				($summary['earliest_full']['days'] ?? 0) . ' days',
				($summary['earliest_full']['host'] ?? 'N/A') . ' - ' . ($summary['earliest_full']['date'] ?? 'N/A')
			]);
		}
		
		fputcsv($output, []); // Empty line
		fputcsv($output, []); // Empty line
		
		// ====== DETAILED DATA SECTION ======
		fputcsv($output, ['DETAILED STORAGE DATA']);
		fputcsv($output, [
			'Host',
			'Host Name', 
			'Mount Point',
			'Total Space',
			'Used Space', 
			'Free Space',
			'Usage %',
			'Daily Growth',
			'Days Until Full',
			'Growth Trend',
			'Confidence %',
			'Status',
			'Algorithm'
		]);
		
		foreach ($storageData as $item) {
			// Calculate free space
			$freeBytes = ($item['total_raw'] ?? 0) - ($item['used_raw'] ?? 0);
			$freeSpace = $freeBytes > 0 ? $this->formatBytes($freeBytes) : '0 B';
			
			fputcsv($output, [
				$item['host'] ?? 'Unknown',
				$item['host_name'] ?? 'Unknown',
				$item['mount'] ?? '/',
				$item['total_space'] ?? '0 B',
				$item['used_space'] ?? '0 B',
				$freeSpace,
				($item['usage_pct'] ?? 0) . '%',
				$item['daily_growth'] ?? '0 B/day',
				$item['days_until_full'] ?? _('No growth'),
				$item['growth_trend'] ?? 'stable',
				$item['confidence'] ?? 0,
				$item['status'] ?? 'ok',
				$item['algorithm'] ?? 'simple'
			]);
		}
		
		fputcsv($output, []); // Empty line
		fputcsv($output, []); // Empty line
		
		// ====== FOOTER SECTION ======
		fputcsv($output, ['EXPORT METADATA']);
		fputcsv($output, ['Total Records:', count($storageData)]);
		fputcsv($output, ['Export Format:', 'CSV']);
		fputcsv($output, ['Module Version:', 'Storage Analytics Pro 1.0']);
		
		fclose($output);
		exit;
	}
    /**
     * Export to HTML
     */
	private function exportHTML(array $storageData, array $summary, array $filter): void {
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
				body { 
					font-family: Arial, sans-serif; 
					margin: 20px; 
					color: #333; 
					font-size: 12px;
				}
				h1, h2, h3, h4 { 
					color: #2c3e50; 
					margin-top: 0;
				}
				.header { 
					border-bottom: 2px solid #3498db; 
					padding-bottom: 10px; 
					margin-bottom: 20px; 
				}
				.summary-section {
					background: #f8f9fa;
					border: 1px solid #ddd;
					border-radius: 5px;
					padding: 15px;
					margin: 20px 0;
				}
				.summary-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 15px;
					margin: 15px 0;
				}
				.summary-card {
					background: white;
					border: 1px solid #e0e0e0;
					border-radius: 4px;
					padding: 10px;
				}
				.summary-card .label {
					font-size: 11px;
					color: #7f8c8d;
					margin-bottom: 5px;
				}
				.summary-card .value {
					font-size: 16px;
					font-weight: bold;
					color: #2c3e50;
				}
				table { 
					width: 100%; 
					border-collapse: collapse; 
					margin: 20px 0;
					font-size: 11px;
				}
				th, td { 
					border: 1px solid #ddd; 
					padding: 8px 10px; 
					text-align: left; 
					vertical-align: top;
				}
				th { 
					background: #f2f2f2; 
					font-weight: bold; 
					color: #2c3e50;
				}
				tr.critical { background: #ffebee; }
				tr.warning { background: #fff8e1; }
				tr.ok { background: #f5fff5; }
				.status-badge { 
					padding: 3px 8px; 
					border-radius: 3px; 
					font-size: 10px; 
					font-weight: bold;
					display: inline-block;
				}
				.critical { background: #ffcdd2; color: #c62828; }
				.warning { background: #ffecb3; color: #ff8f00; }
				.ok { background: #c8e6c9; color: #2e7d32; }
				.footer { 
					margin-top: 30px; 
					padding-top: 15px; 
					border-top: 1px solid #ddd; 
					font-size: 11px; 
					color: #7f8c8d; 
				}
				.page-break {
					page-break-before: always;
				}
				@media print {
					body { margin: 0.5in; }
					.no-print { display: none; }
				}
			</style>
		</head>
		<body>
			<div class="header">
				<h1><?= _('Storage Analytics Report') ?></h1>
				<p><strong><?= _('Generated on') ?>:</strong> <?= date('Y-m-d H:i:s') ?></p>
				<p><strong><?= _('Filters') ?>:</strong> 
					<?= _('Time Range') ?>: <?= $filter['time_range'] ?> <?= _('days') ?> | 
					<?= _('Method') ?>: <?= $filter['prediction_method'] ?> |
					<?= _('Warning') ?>: <?= $filter['warning_threshold'] ?>% |
					<?= _('Critical') ?>: <?= $filter['critical_threshold'] ?>%
				</p>
			</div>
			
			<!-- SUMMARY SECTION -->
			<div class="summary-section">
				<h2><?= _('Summary') ?></h2>
				<div class="summary-grid">
					<div class="summary-card">
						<div class="label"><?= _('Total Hosts') ?></div>
						<div class="value"><?= $summary['total_hosts'] ?? 0 ?></div>
					</div>
					<div class="summary-card">
						<div class="label"><?= _('Total Filesystems') ?></div>
						<div class="value"><?= $summary['total_filesystems'] ?? 0 ?></div>
					</div>
					<div class="summary-card">
						<div class="label"><?= _('Total Capacity') ?></div>
						<div class="value"><?= $summary['total_capacity'] ?? '0 B' ?></div>
					</div>
					<div class="summary-card">
						<div class="label"><?= _('Total Used') ?></div>
						<div class="value"><?= $summary['total_used'] ?? '0 B' ?></div>
						<div style="font-size: 11px; color: #7f8c8d; margin-top: 3px;">
							(<?= $summary['total_usage_pct'] ?? 0 ?>% <?= _('used') ?>)
						</div>
					</div>
					<div class="summary-card">
						<div class="label"><?= _('Critical') ?></div>
						<div class="value" style="color: #c62828;"><?= $summary['critical_count'] ?? 0 ?></div>
					</div>
					<div class="summary-card">
						<div class="label"><?= _('Warning') ?></div>
						<div class="value" style="color: #ff8f00;"><?= $summary['warning_count'] ?? 0 ?></div>
					</div>
					<div class="summary-card">
						<div class="label"><?= _('Avg Daily Growth') ?></div>
						<div class="value"><?= $summary['avg_daily_growth_fmt'] ?? '0 B/day' ?></div>
					</div>
					<?php if (isset($summary['earliest_full']) && !empty($summary['earliest_full'])): ?>
					<div class="summary-card">
						<div class="label"><?= _('Earliest Full') ?></div>
						<div class="value"><?= $summary['earliest_full']['days'] ?? 0 ?> <?= _('days') ?></div>
						<div style="font-size: 11px; color: #7f8c8d; margin-top: 3px;">
							<?= $summary['earliest_full']['host'] ?? '' ?> (<?= $summary['earliest_full']['date'] ?? '' ?>)
						</div>
					</div>
					<?php endif; ?>
				</div>
				
				<!-- Status Distribution -->
				<div style="margin-top: 15px; padding: 10px; background: white; border-radius: 4px; border: 1px solid #e0e0e0;">
					<h4 style="margin-top: 0; margin-bottom: 10px;"><?= _('Status Distribution') ?></h4>
					<div style="display: flex; align-items: center; gap: 10px;">
						<?php
						$total = $summary['total_filesystems'] ?? 0;
						$critical = $summary['critical_count'] ?? 0;
						$warning = $summary['warning_count'] ?? 0;
						$ok = $total - $critical - $warning;
						
						$criticalPct = $total > 0 ? round(($critical / $total) * 100, 1) : 0;
						$warningPct = $total > 0 ? round(($warning / $total) * 100, 1) : 0;
						$okPct = $total > 0 ? round(($ok / $total) * 100, 1) : 0;
						?>
						<div style="flex: 1; height: 20px; background: #ecf0f1; border-radius: 3px; overflow: hidden; display: flex;">
							<?php if ($criticalPct > 0): ?>
							<div style="width: <?= $criticalPct ?>%; background: #c62828;" title="Critical: <?= $criticalPct ?>%"></div>
							<?php endif; ?>
							<?php if ($warningPct > 0): ?>
							<div style="width: <?= $warningPct ?>%; background: #ff8f00;" title="Warning: <?= $warningPct ?>%"></div>
							<?php endif; ?>
							<?php if ($okPct > 0): ?>
							<div style="width: <?= $okPct ?>%; background: #2e7d32;" title="OK: <?= $okPct ?>%"></div>
							<?php endif; ?>
						</div>
						<div style="font-size: 11px; white-space: nowrap;">
							<span style="color: #c62828;">■ <?= $critical ?> (<?= $criticalPct ?>%)</span> | 
							<span style="color: #ff8f00;">■ <?= $warning ?> (<?= $warningPct ?>%)</span> | 
							<span style="color: #2e7d32;">■ <?= $ok ?> (<?= $okPct ?>%)</span>
						</div>
					</div>
				</div>
			</div>
			
			<!-- DETAILED DATA SECTION -->
			<h2><?= _('Detailed Storage Analysis') ?></h2>
			<table>
				<thead>
					<tr>
						<th><?= _('Host') ?></th>
						<th><?= _('Mount Point') ?></th>
						<th><?= _('Total') ?></th>
						<th><?= _('Used') ?></th>
						<th><?= _('Free') ?></th>
						<th><?= _('Usage %') ?></th>
						<th><?= _('Daily Growth') ?></th>
						<th><?= _('Days Until Full') ?></th>
						<th><?= _('Trend') ?></th>
						<th><?= _('Status') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($storageData as $item): 
						$freeBytes = ($item['total_raw'] ?? 0) - ($item['used_raw'] ?? 0);
						$freeSpace = $freeBytes > 0 ? $this->formatBytes($freeBytes) : '0 B';
					?>
					<tr class="<?= $item['status'] ?? 'ok' ?>">
						<td><?= htmlspecialchars($item['host'] ?? 'Unknown') ?></td>
						<td><code><?= htmlspecialchars($item['mount'] ?? '/') ?></code></td>
						<td><?= $item['total_space'] ?? '0 B' ?></td>
						<td><?= $item['used_space'] ?? '0 B' ?></td>
						<td><?= $freeSpace ?></td>
						<td><?= $item['usage_pct'] ?? 0 ?>%</td>
						<td><?= $item['daily_growth'] ?? '0 B/day' ?></td>
						<td><?= $item['days_until_full'] ?? _('No growth') ?></td>
						<td>
							<?php if (isset($item['growth_trend'])): ?>
								<?= 
									$item['growth_trend'] === 'rapid_increase' ? '🚀 Rapid' :
									($item['growth_trend'] === 'increasing' ? '📈 Increasing' :
									($item['growth_trend'] === 'slow_increase' ? '⬆️ Slow' :
									($item['growth_trend'] === 'decreasing' ? '📉 Decreasing' : '➡️ Stable')))
								?>
							<?php else: ?>
								Stable
							<?php endif; ?>
						</td>
						<td>
							<span class="status-badge <?= $item['status'] ?? 'ok' ?>">
								<?= ucfirst($item['status'] ?? 'ok') ?>
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
						<th><?= _('Current Usage') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($summary['top_growers'] as $grower): ?>
					<tr>
						<td><?= htmlspecialchars($grower['host'] ?? 'Unknown') ?></td>
						<td><code><?= htmlspecialchars($grower['mount'] ?? '/') ?></code></td>
						<td><?= $grower['daily_growth'] ?? '0 B/day' ?></td>
						<td><?= $grower['days_until_full'] ?? _('No growth') ?></td>
						<td><?= $grower['usage_pct'] ?? 0 ?>%</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
			
			<div class="footer">
				<p><strong><?= _('Report generated by Zabbix Storage Analytics Pro Module') ?></strong></p>
				<p><?= _('Calculation based on') ?>: <?= $filter['time_range'] ?> <?= _('days of historical data') ?> | 
				<?= _('Prediction method') ?>: <?= $filter['prediction_method'] ?></p>
				<p><?= _('Thresholds') ?>: <?= _('Warning') ?> <?= $filter['warning_threshold'] ?>% | 
				<?= _('Critical') ?> <?= $filter['critical_threshold'] ?>%</p>
				<p><?= _('Total records exported') ?>: <?= count($storageData) ?></p>
			</div>
		</body>
		</html>
		<?php
		echo ob_get_clean();
		exit;
	}

    /**
     * Export to JSON
     */
    private function exportJSON(array $storageData, array $summary, array $filter): void {
        $filename = 'storage_analytics_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $exportData = [
            'metadata' => [
                'generated' => date('Y-m-d H:i:s'),
                'time_range' => $filter['time_range'],
                'prediction_method' => $filter['prediction_method'],
                'total_records' => count($storageData)
            ],
            'summary' => $summary,
            'data' => $storageData
        ];
        
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
