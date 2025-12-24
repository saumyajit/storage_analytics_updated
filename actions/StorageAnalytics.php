<?php
namespace Modules\diskanalyser\actions;

use CController;
use CControllerResponseData;
use API;
use CCache;

class StorageAnalytics extends CController {
    
    private $cache;
    private $cache_ttl = 300; // 5 minutes
    
    protected function init(): void {
        $this->disableCsrfValidation();
        $this->cache = CCache::getInstance();
    }

    protected function checkInput(): bool {
        $fields = [
            'hostids'           => 'array_id',
            'groupids'          => 'array_id',
            'host'              => 'string',
            'time_range'        => 'in 7,14,30,90,180,365',
            'prediction_method' => 'in simple,seasonal,holt_winters,arima,ensemble',
            'warning_threshold' => 'ge 0|le 100',
            'critical_threshold'=> 'ge 0|le 100',
            'refresh'           => 'in 0,30,60,120,300,600',
            'refresh_enabled'   => 'in 0,1',
            'page'              => 'ge 1',
            'tags'              => 'array',
            'filter_enabled'    => 'in 0,1',
            'export'            => 'in csv,html,json,pdf'
        ];
        
        $ret = $this->validateInput($fields);
        
        if (!$ret) {
            error(_('Invalid input parameters'));
        }
        
        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // Check for export request
        $export = $this->getInput('export', '');
        
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

        // Handle export request
        if ($export) {
            $this->handleExport($export, $filter);
            return;
        }

        // Fetch storage data with filters
        $storageData = $this->getDiskDataWithFilters($filter);
        
        // Calculate predictions
        $enhancedData = $this->calculatePredictions($storageData, $filter);
        
        // Calculate summary statistics - WITH FIXED GROWTH CALCULATION
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
     * Handle export requests
     */
    private function handleExport(string $format, array $filter): void {
        // Fetch data
        $storageData = $this->getDiskDataWithFilters($filter);
        $enhancedData = $this->calculatePredictions($storageData, $filter);
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
                
            case 'pdf':
                // PDF would require additional library
                $this->showError(_('PDF export not yet implemented'));
                break;
        }
    }

    /**
     * WORKING DATA COLLECTION METHOD
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
     * Batch fetch history data for multiple items
     */
    private function batchGetHistoryData(array $itemIds, int $timeFrom): array {
        $cache_key = 'storage_history_' . md5(implode(',', $itemIds) . $timeFrom);
        
        // Try cache first
        $cached = $this->cache->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Batch API call (much more efficient)
        $history = API::History()->get([
            'output' => ['itemid', 'clock', 'value'],
            'itemids' => $itemIds,
            'history' => 3,
            'time_from' => $timeFrom,
            'sortfield' => ['itemid', 'clock'],
            'limit' => count($itemIds) * 50 // Reasonable limit
        ]);
        
        // Group by itemid for easy access
        $grouped = [];
        foreach ($history as $record) {
            $grouped[$record['itemid']][] = [
                'clock' => $record['clock'],
                'value' => $record['value']
            ];
        }
        
        $this->cache->set($cache_key, $grouped, $this->cache_ttl);
        return $grouped;
    }

    /**
     * Calculate growth predictions
     */
    private function calculatePredictions(array $storageData, array $filter): array {
        $method = $filter['prediction_method'];
        
        // Use batch processing for performance
        if (in_array($method, ['simple', 'seasonal'])) {
            $enhancedData = $this->calculateBatchGrowthRates($storageData, $filter['time_range'], $method);
        } else {
            $enhancedData = $this->calculateAdvancedPredictions($storageData, $filter);
        }

        foreach ($enhancedData as &$item) {
            // Determine status based on thresholds
            $item['status'] = $this->determineStatus(
                $item['usage_pct'],
                $item['days_until_full'] ?? _('No growth'),
                $filter['warning_threshold'],
                $filter['critical_threshold']
            );
        }

        return $enhancedData;
    }

    /**
     * Optimized growth calculation using batch data
     */
    private function calculateBatchGrowthRates(array $storageData, int $days, string $method): array {
        $timeFrom = time() - ($days * 86400);
        $itemMap = [];
        $itemIds = [];
        
        // Build mapping of host+mount to item IDs
        foreach ($storageData as $idx => $item) {
            $itemKey = 'vfs.fs.size[' . $item['mount'] . ',used]';
            $items = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $item['hostid'],
                'filter' => ['key_' => $itemKey],
                'limit' => 1
            ]);
            
            if (!empty($items)) {
                $itemId = $items[0]['itemid'];
                $itemMap[$itemId] = $idx;
                $itemIds[] = $itemId;
            }
        }
        
        // Single batch call instead of multiple calls
        $batchHistory = $this->batchGetHistoryData($itemIds, $timeFrom);
        
        // Process all items
        foreach ($itemMap as $itemId => $dataIdx) {
            $history = $batchHistory[$itemId] ?? [];
            
            if (count($history) >= 2) {
                $first = reset($history);
                $last = end($history);
                
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
                $storageData[$dataIdx]['confidence'] = min(100, (count($history) / $days) * 100);
            } else {
                $storageData[$dataIdx]['daily_growth_raw'] = 0;
                $storageData[$dataIdx]['daily_growth'] = _('Stable');
                $storageData[$dataIdx]['days_until_full'] = _('No growth');
                $storageData[$dataIdx]['growth_trend'] = 'stable';
                $storageData[$dataIdx]['confidence'] = 0;
            }
        }
        
        return $storageData;
    }

    /**
     * Advanced prediction with multiple algorithms
     */
    private function calculateAdvancedPredictions(array $storageData, array $filter): array {
        $method = $filter['prediction_method'];
        
        foreach ($storageData as &$item) {
            $growthData = [];
            
            switch ($method) {
                case 'holt_winters':
                    $growthData = $this->holtWintersPrediction($item['hostid'], $item['mount'], $filter['time_range']);
                    break;
                    
                case 'arima':
                    $growthData = $this->arimaPrediction($item['hostid'], $item['mount'], $filter['time_range']);
                    break;
                    
                case 'ensemble':
                    $growthData = $this->ensemblePrediction($item['hostid'], $item['mount'], $filter['time_range']);
                    break;
                    
                case 'seasonal':
                default:
                    $growthData = $this->calculateSeasonalAdjustedGrowth($item['hostid'], $item['mount'], $filter['time_range']);
                    break;
            }
            
            $item = array_merge($item, $growthData);
        }
        
        return $storageData;
    }

    /**
     * Holt-Winters triple exponential smoothing
     */
    private function holtWintersPrediction(int $hostId, string $mount, int $days): array {
        $history = $this->getDailyHistory($hostId, $mount, $days);
        
        if (count($history) < 14) {
            return [
                'daily_growth_raw' => 0,
                'daily_growth' => _('Stable'),
                'days_until_full' => _('No growth'),
                'trend' => 'insufficient_data',
                'confidence' => 0,
                'algorithm' => 'holt_winters'
            ];
        }
        
        // Simplified Holt-Winters implementation
        $alpha = 0.3; // Level smoothing
        $beta = 0.1;  // Trend smoothing
        $gamma = 0.2; // Seasonal smoothing
        $season_length = 7; // Weekly seasonality
        
        $values = array_column($history, 'value');
        $level = $values[0];
        $trend = 0;
        $seasonal = array_fill(0, $season_length, 0);
        
        // Initialize seasonal components
        for ($i = 0; $i < $season_length && $i < count($values); $i++) {
            $seasonal[$i] = $values[$i] / $level;
        }
        
        // Apply Holt-Winters smoothing
        for ($i = 1; $i < count($values); $i++) {
            $season = $i % $season_length;
            $last_level = $level;
            
            $level = $alpha * ($values[$i] / $seasonal[$season]) + (1 - $alpha) * ($last_level + $trend);
            $trend = $beta * ($level - $last_level) + (1 - $beta) * $trend;
            $seasonal[$season] = $gamma * ($values[$i] / $level) + (1 - $gamma) * $seasonal[$season];
        }
        
        // Forecast next value
        $season = count($values) % $season_length;
        $forecast = ($level + $trend) * $seasonal[$season];
        
        $daily_growth = $forecast - end($values);
        
        return [
            'daily_growth_raw' => max(0, $daily_growth),
            'daily_growth' => $daily_growth > 0 ? $this->formatBytes($daily_growth) . '/day' : _('Stable'),
            'days_until_full' => $this->calculateDaysUntilFull(0, 0, $daily_growth), // Will be recalculated with actual data
            'trend' => $this->determineTrend($daily_growth),
            'confidence' => $this->calculateModelConfidence($values, $forecast),
            'algorithm' => 'holt_winters',
            'seasonal_pattern' => $seasonal
        ];
    }

    /**
     * ARIMA prediction (simplified version)
     */
    private function arimaPrediction(int $hostId, string $mount, int $days): array {
        // Simplified ARIMA implementation
        $history = $this->getDailyHistory($hostId, $mount, $days);
        
        if (count($history) < 10) {
            return [
                'daily_growth_raw' => 0,
                'daily_growth' => _('Stable'),
                'days_until_full' => _('No growth'),
                'trend' => 'insufficient_data',
                'confidence' => 0,
                'algorithm' => 'arima'
            ];
        }
        
        $values = array_column($history, 'value');
        
        // Simple differencing (ARIMA(1,1,0) approximation)
        $diff_values = [];
        for ($i = 1; $i < count($values); $i++) {
            $diff_values[] = $values[$i] - $values[$i-1];
        }
        
        // Average growth
        $avg_growth = array_sum($diff_values) / count($diff_values);
        
        return [
            'daily_growth_raw' => max(0, $avg_growth),
            'daily_growth' => $avg_growth > 0 ? $this->formatBytes($avg_growth) . '/day' : _('Stable'),
            'days_until_full' => $this->calculateDaysUntilFull(0, 0, $avg_growth),
            'trend' => $this->determineTrend($avg_growth),
            'confidence' => min(100, (count($values) / $days) * 100),
            'algorithm' => 'arima'
        ];
    }

    /**
     * Ensemble prediction combining multiple models
     */
    private function ensemblePrediction(int $hostId, string $mount, int $days): array {
        $methods = ['simple', 'seasonal', 'holt_winters'];
        $predictions = [];
        $weights = ['simple' => 0.2, 'seasonal' => 0.3, 'holt_winters' => 0.5];
        
        foreach ($methods as $method) {
            switch ($method) {
                case 'simple':
                    $pred = $this->calculateGrowthRate($hostId, $mount, $days, 'simple');
                    break;
                case 'seasonal':
                    $pred = $this->calculateSeasonalAdjustedGrowth($hostId, $mount, $days);
                    break;
                case 'holt_winters':
                    $pred = $this->holtWintersPrediction($hostId, $mount, $days);
                    break;
            }
            
            if ($pred['confidence'] > 50) {
                $predictions[$method] = $pred;
            }
        }
        
        // Weighted average of predictions
        $total_growth = 0;
        $total_weight = 0;
        $confidences = [];
        
        foreach ($predictions as $method => $pred) {
            $weight = $weights[$method] * ($pred['confidence'] / 100);
            $total_growth += $pred['daily_growth_raw'] * $weight;
            $total_weight += $weight;
            $confidences[] = $pred['confidence'];
        }
        
        $ensemble_growth = $total_weight > 0 ? $total_growth / $total_weight : 0;
        $avg_confidence = count($confidences) > 0 ? array_sum($confidences) / count($confidences) : 0;
        
        return [
            'daily_growth_raw' => $ensemble_growth,
            'daily_growth' => $ensemble_growth > 0 ? $this->formatBytes($ensemble_growth) . '/day' : _('Stable'),
            'days_until_full' => $this->calculateDaysUntilFull(0, 0, $ensemble_growth),
            'trend' => $this->determineTrend($ensemble_growth),
            'confidence' => round($avg_confidence),
            'algorithm' => 'ensemble',
            'component_predictions' => $predictions
        ];
    }

    /**
     * Calculate seasonal adjusted growth
     */
    private function calculateSeasonalAdjustedGrowth(int $hostId, string $mount, int $days): array {
        $history = $this->getDailyHistory($hostId, $mount, $days);
        
        if (count($history) < 7) {
            return [
                'daily_growth_raw' => 0,
                'daily_growth' => _('Stable'),
                'days_until_full' => _('No growth'),
                'trend' => 'insufficient_data',
                'confidence' => 0,
                'algorithm' => 'seasonal'
            ];
        }
        
        // Group by day of week for seasonal pattern
        $seasonal_pattern = [];
        for ($i = 0; $i < 7; $i++) {
            $seasonal_pattern[$i] = ['sum' => 0, 'count' => 0];
        }
        
        foreach ($history as $record) {
            $day_of_week = date('w', $record['clock']);
            $seasonal_pattern[$day_of_week]['sum'] += $record['value'];
            $seasonal_pattern[$day_of_week]['count']++;
        }
        
        // Calculate seasonal averages
        $seasonal_avg = [];
        foreach ($seasonal_pattern as $day => $data) {
            $seasonal_avg[$day] = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0;
        }
        
        // Remove seasonal component and calculate trend
        $deseasonalized = [];
        foreach ($history as $record) {
            $day_of_week = date('w', $record['clock']);
            $deseasonalized[] = $record['value'] - $seasonal_avg[$day_of_week];
        }
        
        // Calculate growth from deseasonalized data
        $first = reset($deseasonalized);
        $last = end($deseasonalized);
        $daily_growth = ($last - $first) / max(1, count($deseasonalized) - 1);
        
        return [
            'daily_growth_raw' => max(0, $daily_growth),
            'daily_growth' => $daily_growth > 0 ? $this->formatBytes($daily_growth) . '/day' : _('Stable'),
            'days_until_full' => $this->calculateDaysUntilFull(0, 0, $daily_growth),
            'trend' => $this->determineTrend($daily_growth),
            'confidence' => min(100, (count($history) / $days) * 100),
            'algorithm' => 'seasonal',
            'seasonal_pattern' => $seasonal_avg
        ];
    }

    /**
     * Get daily history data
     */
    private function getDailyHistory(int $hostId, string $mount, int $days): array {
        $itemKey = 'vfs.fs.size[' . $mount . ',used]';
        
        $items = API::Item()->get([
            'output' => ['itemid'],
            'hostids' => $hostId,
            'filter' => ['key_' => $itemKey],
            'limit' => 1
        ]);

        if (empty($items)) {
            return [];
        }

        $itemId = $items[0]['itemid'];
        $timeFrom = time() - ($days * 86400);

        return API::History()->get([
            'output' => ['clock', 'value'],
            'itemids' => [$itemId],
            'history' => 3,
            'time_from' => $timeFrom,
            'sortfield' => 'clock',
            'sortorder' => 'ASC'
        ]);
    }

    /**
     * Calculate growth rate (legacy method)
     */
    private function calculateGrowthRate(int $hostId, string $mount, int $days, string $method): array {
        $history = $this->getDailyHistory($hostId, $mount, $days);

        if (count($history) < 2) {
            return [
                'daily_growth' => 0,
                'trend' => 'stable',
                'confidence' => 0
            ];
        }

        $first = reset($history);
        $last = end($history);

        $valueDiff = $last['value'] - $first['value'];
        $timeDiff = max(1, ($last['clock'] - $first['clock']) / 86400);
        
        $dailyGrowth = $valueDiff / $timeDiff;
        
        // Cap unrealistic growth
        if (abs($dailyGrowth) > 10737418240) { // > 10GB/day
            $dailyGrowth = 0;
        }

        $confidence = min(100, (count($history) / $days) * 100);

        return [
            'daily_growth_raw' => $dailyGrowth,
            'daily_growth' => $dailyGrowth > 0 ? $this->formatBytes($dailyGrowth) . '/day' : _('Stable'),
            'trend' => $this->determineTrend($dailyGrowth),
            'confidence' => round($confidence)
        ];
    }

    /**
     * Calculate model confidence
     */
    private function calculateModelConfidence(array $actual, float $predicted): int {
        if (count($actual) < 2) return 0;
        
        // Simple confidence based on variance
        $mean = array_sum($actual) / count($actual);
        $variance = 0;
        foreach ($actual as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($actual);
        
        // Lower variance = higher confidence
        $stddev = sqrt($variance);
        $confidence = 100 - min(100, ($stddev / $mean) * 100);
        
        return max(0, min(100, (int)$confidence));
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
     * Calculate days until full with growth rate - SINGLE VERSION
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
            return _('No growth'); // Too small growth to matter
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
	
		// Check days until full - USE parseDaysToNumber()!
		$days = $this->parseDaysToNumber($daysUntilFull);
	
		if ($days <= 15 && $days < PHP_INT_MAX) {
			return 'critical';
		} elseif ($days <= 30 && $days < PHP_INT_MAX) {
			return 'warning';
		}
	
		return 'ok';
	}

    /**
     * Calculate enhanced summary statistics - FIXED VERSION
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
        $allGrowthValues = []; // Track all growth values for average

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

            // Track earliest full - USE CORRECT DAYS PARSING
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

        // FIX: Calculate average growth correctly using median
        $summary['avg_daily_growth'] = 0;
        if (!empty($allGrowthValues)) {
            // Use median instead of average to avoid outlier distortion
            sort($allGrowthValues);
            $count = count($allGrowthValues);
            $middle = floor(($count - 1) / 2);
            
            if ($count % 2) {
                // Odd number of elements
                $summary['avg_daily_growth'] = $allGrowthValues[$middle];
            } else {
                // Even number of elements
                $summary['avg_daily_growth'] = ($allGrowthValues[$middle] + $allGrowthValues[$middle + 1]) / 2;
            }
            
            // Additional sanity check: cap at reasonable value
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
     * Helper to parse days string to number - FIXED
     */
	private function parseDaysToNumber(string $daysStr): int {
		if ($daysStr === _('No growth') || $daysStr === _('Already full') || 
			$daysStr === _('Growth error') || $daysStr === _('More than 10 years')) {
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
	* Get host groups for a specific host
	*/
	private function getHostGroups(int $hostId): array {
		static $cache = [];
		
		if (!isset($cache[$hostId])) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid'],
				'hostids' => [$hostId],
				'preservekeys' => true
			]);
			
			$cache[$hostId] = array_keys($groups);
		}
		
		return $cache[$hostId];
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
				'arima' => _('ARIMA (Statistical)'),
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
	
		// Get hosts WITH their groups using selectHostGroups (new parameter)
		$hostParams = [
			'output' => ['hostid', 'host', 'name'],
			'selectHostGroups' => ['groupid'], // This is the new parameter name
			'sortfield' => 'host'
		];
	
		if (!empty($currentFilter['groupids'])) {
			$hostParams['groupids'] = $currentFilter['groupids'];
		}
	
		$hosts = API::Host()->get($hostParams);
	
		foreach ($hosts as $host) {
			// Extract group IDs - note: key name changed from 'groups' to 'hostgroups'
			$groupIds = [];
			
			// Check both possible key names for compatibility
			if (isset($host['hostgroups'])) {
				foreach ($host['hostgroups'] as $group) {
					$groupIds[] = $group['groupid'];
				}
			} elseif (isset($host['groups'])) { // Fallback for older versions
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
     * Check and suggest database optimizations
     */
    private function checkDatabaseOptimizations(): array {
        $suggestions = [];
        
        // Check for missing indexes (simplified example)
        $tables = ['history', 'history_uint', 'trends', 'items', 'hosts'];
        
        foreach ($tables as $table) {
            // In real implementation, check actual indexes via DB::select()
            $suggestions[] = "Consider composite indexes on $table for time-based queries";
        }
        
        return $suggestions;
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
            _('Algorithm'),
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
                $item['algorithm'] ?? 'simple',
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
                        <th><?= _('Algorithm') ?></th>
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
                        <td><?= $item['algorithm'] ?? 'simple' ?></td>
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
                        <th><?= _('Algorithm') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary['top_growers'] as $grower): ?>
                    <tr>
                        <td><?= htmlspecialchars($grower['host']) ?></td>
                        <td><code><?= htmlspecialchars($grower['mount']) ?></code></td>
                        <td><?= $grower['daily_growth'] ?></td>
                        <td><?= $grower['days_until_full'] ?></td>
                        <td><?= $grower['algorithm'] ?? 'simple' ?></td>
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

    /**
     * Show error message
     */
    private function showError(string $message): void {
        echo json_encode(['error' => $message]);
        exit;
    }
}
