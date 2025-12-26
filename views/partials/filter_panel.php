<?php
/**
 * Filter Panel Partial - Zabbix Style with Search
 */

// For inline use, we need the data
if (!isset($filterOptions) || !isset($filter)) {
    return;
}

// Get limited initial groups (like Zabbix does)
$initialGroups = array_slice($filterOptions['hostgroups'], 0, 20); // Limit to 20 initially
$selectedGroupIds = $filter['groupids'] ?? [];
$selectedHostIds = $filter['hostids'] ?? [];

// Filter customer groups only
$customerGroups = array_filter($initialGroups, function($group) {
    return strpos($group['name'], 'CUSTOMER/') === 0;
});
?>

<div class="filter-section">
    <button type="button" class="btn-filter-toggle btn-alt" id="filter-toggle">
        <span class="toggle-icon">▼</span> <?= _('Filters') ?>
        <?php if ($filter['filter_enabled']): ?>
            <span class="filter-badge"><?= _('Active') ?></span>
        <?php endif; ?>
    </button>
    
    <div class="filter-panel" id="filter-panel" style="<?= $filter['filter_enabled'] ? '' : 'display: none;' ?>">
        <form id="filter-form" method="get" action="zabbix.php">
            <input type="hidden" name="action" value="storage.analytics">
            <input type="hidden" name="filter_enabled" value="1">
            
            <div class="filter-grid">
                <!-- Host Groups Filter with Search -->
                <div class="filter-group">
                    <label for="groupids"><?= _('Host Groups') ?></label>
                    
                    <!-- Group Search -->
                    <div class="dropdown-search-wrapper">
                        <input type="text" 
                               id="group-search" 
                               class="dropdown-search-input"
                               placeholder="<?= _('Search groups...') ?>"
                               oninput="filterDropdown('groupids', this.value)">
                        <div class="search-clear" onclick="clearSearch('group-search', 'groupids')" title="<?= _('Clear search') ?>">×</div>
                    </div>
                    
                    <!-- Groups Dropdown -->
                    <select id="groupids" name="groupids[]" multiple class="select" size="5">
                        <?php foreach ($customerGroups as $group): ?>
                            <option value="<?= $group['id'] ?>" 
                                    class="customer-group"
                                    data-search="<?= htmlspecialchars(strtolower($group['name'])) ?>"
                                    <?= in_array($group['id'], $selectedGroupIds) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Group Count -->
                    <div class="dropdown-count" id="group-count">
                        <?= sprintf(_('%d groups'), count($customerGroups)) ?>
                    </div>
                    
                    <small class="filter-hint">
                        <?= _('Type to search customer groups. Hold Ctrl/Cmd to select multiple.') ?>
                    </small>
                </div>
                
                <!-- Hosts Filter with Search -->
                <div class="filter-group">
                    <label for="hostids"><?= _('Hosts') ?></label>
                    
                    <!-- Host Search -->
                    <div class="dropdown-search-wrapper">
                        <input type="text" 
                               id="host-search" 
                               class="dropdown-search-input"
                               placeholder="<?= _('Search hosts...') ?>"
                               oninput="filterDropdown('hostids', this.value)"
                               <?= empty($selectedGroupIds) ? 'disabled' : '' ?>>
                        <div class="search-clear" onclick="clearSearch('host-search', 'hostids')" title="<?= _('Clear search') ?>">×</div>
                    </div>
                    
                    <!-- Hosts Dropdown -->
                    <select id="hostids" name="hostids[]" multiple class="select" size="5" 
                            <?= empty($selectedGroupIds) ? 'disabled' : '' ?>>
                        <?php 
                        // Only show hosts if groups are selected
                        if (!empty($selectedGroupIds) && !empty($filterOptions['hosts'])) {
                            // Limit to first 100 hosts for performance
                            $limitedHosts = array_slice($filterOptions['hosts'], 0, 100);
                            foreach ($limitedHosts as $host): 
                                $hostGroupIds = $host['groupids'] ?? [];
                                if (array_intersect($selectedGroupIds, $hostGroupIds)): 
                                    $hostName = $host['name'] ?: $host['host'];
                                    $displayName = $hostName . ($host['host'] !== $hostName ? ' (' . $host['host'] . ')' : '');
                        ?>
                            <option value="<?= $host['id'] ?>" 
                                    data-search="<?= htmlspecialchars(strtolower($displayName)) ?>"
                                    <?= in_array($host['id'], $selectedHostIds) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($displayName) ?>
                            </option>
                        <?php 
                                endif;
                            endforeach;
                        }
                        ?>
                    </select>
                    
                    <!-- Host Count -->
                    <div class="dropdown-count" id="host-count">
                        <?php 
                        if (empty($selectedGroupIds)) {
                            echo _('Select groups first');
                        } else {
                            $hostCount = 0;
                            if (!empty($filterOptions['hosts'])) {
                                $limitedHosts = array_slice($filterOptions['hosts'], 0, 100);
                                foreach ($limitedHosts as $host) {
                                    $hostGroupIds = $host['groupids'] ?? [];
                                    if (array_intersect($selectedGroupIds, $hostGroupIds)) {
                                        $hostCount++;
                                    }
                                }
                            }
                            echo sprintf(_('%d hosts'), $hostCount);
                        }
                        ?>
                    </div>
                    
                    <small class="filter-hint">
                        <?= _('Type to search hosts. Select groups first.') ?>
                    </small>
                </div>
                
                <!-- Time Range -->
                <div class="filter-group">
                    <label for="time_range"><?= _('Analysis Period') ?></label>
                    <select id="time_range" name="time_range" class="select">
                        <?php foreach ($filterOptions['time_ranges'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filter['time_range'] == $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Prediction Method -->
                <div class="filter-group">
                    <label for="prediction_method"><?= _('Prediction Method') ?></label>
                    <select id="prediction_method" name="prediction_method" class="select">
                        <?php foreach ($filterOptions['prediction_methods'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filter['prediction_method'] == $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Thresholds -->
                <div class="filter-group threshold-group">
                    <label><?= _('Thresholds') ?></label>
                    <div class="threshold-inputs">
                        <div class="threshold-input">
                            <span class="threshold-label warning"><?= _('Warning') ?>:</span>
                            <input type="number" id="warning_threshold" name="warning_threshold" 
                                   value="<?= $filter['warning_threshold'] ?>" min="0" max="100" step="1" class="input-small">
                            <span class="threshold-unit">%</span>
                        </div>
                        <div class="threshold-input">
                            <span class="threshold-label critical"><?= _('Critical') ?>:</span>
                            <input type="number" id="critical_threshold" name="critical_threshold" 
                                   value="<?= $filter['critical_threshold'] ?>" min="0" max="100" step="1" class="input-small">
                            <span class="threshold-unit">%</span>
                        </div>
                    </div>
                </div>
                
                <!-- Auto-refresh -->
                <div class="filter-group refresh-group">
                    <label><?= _('Auto-refresh') ?></label>
                    <div class="refresh-controls">
                        <label class="checkbox-label">
                            <input type="checkbox" id="refresh_enabled" name="refresh_enabled" value="1" 
                                   <?= $filter['refresh_enabled'] ? 'checked' : '' ?>>
                            <?= _('Enabled') ?>
                        </label>
                        
                        <select id="refresh" name="refresh" class="select" <?= !$filter['refresh_enabled'] ? 'disabled' : '' ?>>
                            <?php foreach ($filterOptions['refresh_intervals'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $filter['refresh'] == $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Filter Actions -->
            <div class="filter-actions">
                <button type="submit" class="btn-apply btn-main">
                    <span class="icon-apply"></span> <?= _('Apply Filters') ?>
                </button>
                <button type="button" class="btn-clear btn-alt" id="clear-filters">
                    <?= _('Clear All') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Filter panel styling */
.filter-panel {
    margin-top: 10px;
    padding: 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 13px;
    color: #2c3e50;
}

/* Dropdown search styling */
.dropdown-search-wrapper {
    position: relative;
    margin-bottom: 8px;
}

.dropdown-search-input {
    width: 100%;
    padding: 8px 30px 8px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 13px;
    background: white;
    box-sizing: border-box;
}

.dropdown-search-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.dropdown-search-input:disabled {
    background: #f5f5f5;
    color: #999;
    cursor: not-allowed;
}

.search-clear {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #95a5a6;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0 4px;
    border-radius: 50%;
}

.search-clear:hover {
    background: #ecf0f1;
    color: #e74c3c;
}

/* Dropdown styling */
.select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 13px;
    background: white;
    box-sizing: border-box;
}

.select[multiple] {
    min-height: 120px;
    padding: 5px;
}

.select[multiple] option {
    padding: 6px 8px;
    margin: 1px 0;
    border-radius: 3px;
    display: none; /* Hidden by default, shown by JavaScript filtering */
}

.select[multiple] option:checked {
    background: #3498db;
    color: white;
}

.select[multiple] option.filter-match {
    display: block; /* Show matching options */
}

/* Dropdown count */
.dropdown-count {
    margin-top: 5px;
    font-size: 11px;
    color: #7f8c8d;
    text-align: right;
}

/* Customer group styling */
.customer-group {
    font-weight: bold;
    color: #2c3e50;
}

.filter-hint {
    font-size: 11px;
    color: #95a5a6;
    margin-top: 3px;
}

/* Thresholds */
.threshold-group .threshold-inputs {
    display: flex;
    gap: 15px;
    align-items: center;
}

.threshold-label {
    font-size: 12px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 3px;
    margin-right: 5px;
}

.threshold-label.warning {
    background: #f39c12;
    color: white;
}

.threshold-label.critical {
    background: #e74c3c;
    color: white;
}

.threshold-unit {
    font-size: 12px;
    color: #7f8c8d;
    margin-left: 3px;
}

/* Refresh controls */
.refresh-group .refresh-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    cursor: pointer;
}

/* Filter actions */
.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.btn-apply, .btn-clear {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-apply {
    background: #2ecc71;
    color: white;
}

.btn-apply:hover {
    background: #27ae60;
}

.btn-clear {
    background: #ecf0f1;
    color: #7f8c8d;
}

.btn-clear:hover {
    background: #bdc3c7;
}

/* Filter toggle */
.btn-filter-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-bottom: 10px;
}

.btn-filter-toggle:hover {
    background: #2980b9;
}

.filter-badge {
    background: #e74c3c;
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}

/* Loading state */
.icon-loading {
    display: inline-block;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Simple dropdown filtering
function filterDropdown(selectId, searchTerm) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    const searchLower = searchTerm.toLowerCase().trim();
    let visibleCount = 0;
    
    // Show/hide options based on search
    for (let i = 0; i < select.options.length; i++) {
        const option = select.options[i];
        const searchText = option.getAttribute('data-search') || option.text.toLowerCase();
        
        if (searchLower === '') {
            // Show all when search is empty
            option.style.display = 'block';
            option.classList.add('filter-match');
            visibleCount++;
        } else if (searchText.includes(searchLower)) {
            option.style.display = 'block';
            option.classList.add('filter-match');
            visibleCount++;
        } else {
            option.style.display = 'none';
            option.classList.remove('filter-match');
        }
    }
    
    // Update count display
    const countElement = document.getElementById(selectId.replace('ids', '-count'));
    if (countElement) {
        if (selectId === 'groupids') {
            countElement.textContent = visibleCount + ' <?= _("groups") ?>';
        } else if (selectId === 'hostids') {
            countElement.textContent = visibleCount + ' <?= _("hosts") ?>';
        }
    }
}

function clearSearch(searchInputId, selectId) {
    const searchInput = document.getElementById(searchInputId);
    const select = document.getElementById(selectId);
    
    if (searchInput) {
        searchInput.value = '';
        filterDropdown(selectId, '');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const filterToggle = document.getElementById('filter-toggle');
    const filterPanel = document.getElementById('filter-panel');
    const groupSelect = document.getElementById('groupids');
    const hostSelect = document.getElementById('hostids');
    const hostSearch = document.getElementById('host-search');
    const clearButton = document.getElementById('clear-filters');
    const filterForm = document.getElementById('filter-form');
    
    // Initialize dropdown filtering
    if (groupSelect) {
        filterDropdown('groupids', '');
    }
    if (hostSelect) {
        filterDropdown('hostids', '');
    }
    
    // Toggle filter panel
    if (filterToggle && filterPanel) {
        filterToggle.addEventListener('click', function() {
            const isVisible = filterPanel.style.display !== 'none';
            filterPanel.style.display = isVisible ? 'none' : 'block';
            
            const icon = this.querySelector('.toggle-icon');
            if (icon) {
                icon.textContent = isVisible ? '▼' : '▲';
            }
        });
    }
    
    // When groups change, update hosts dropdown
    if (groupSelect && hostSelect && hostSearch) {
        groupSelect.addEventListener('change', function() {
            const selectedGroups = Array.from(this.selectedOptions)
                .map(option => option.value)
                .filter(value => value !== '' && value !== null);
            
            if (selectedGroups.length > 0) {
                hostSelect.disabled = false;
                hostSearch.disabled = false;
                
                // Enable all host options and show them
                for (let i = 0; i < hostSelect.options.length; i++) {
                    hostSelect.options[i].disabled = false;
                }
            } else {
                hostSelect.disabled = true;
                hostSearch.disabled = true;
                hostSelect.selectedIndex = -1;
            }
        });
    }
    
    // Clear all filters
    if (clearButton) {
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Clear selections
            if (groupSelect) {
                groupSelect.selectedIndex = -1;
                filterDropdown('groupids', '');
            }
            if (hostSelect) {
                hostSelect.selectedIndex = -1;
                filterDropdown('hostids', '');
                hostSelect.disabled = true;
            }
            
            // Clear search inputs
            const groupSearch = document.getElementById('group-search');
            const hostSearch = document.getElementById('host-search');
            if (groupSearch) groupSearch.value = '';
            if (hostSearch) {
                hostSearch.value = '';
                hostSearch.disabled = true;
            }
            
            // Submit form to clear all filters
            if (filterForm) {
                const clearInput = document.createElement('input');
                clearInput.type = 'hidden';
                clearInput.name = 'clear_filters';
                clearInput.value = '1';
                filterForm.appendChild(clearInput);
                filterForm.submit();
            }
        });
    }
    
    // Simple form submission
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            // Add loading indicator
            const submitBtn = this.querySelector('.btn-apply');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="icon-loading"></span> <?= _("Applying...") ?>';
                submitBtn.disabled = true;
                
                // Restore button after 5 seconds if something goes wrong
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    }
    
    // Enable multi-select with Ctrl/Cmd
    if (groupSelect) {
        groupSelect.addEventListener('mousedown', function(e) {
            e.preventDefault();
            
            const option = e.target;
            if (option.tagName === 'OPTION') {
                const select = option.parentElement;
                
                if (e.ctrlKey || e.metaKey) {
                    // Ctrl/Cmd click: toggle selection
                    option.selected = !option.selected;
                } else if (e.shiftKey) {
                    // Shift click: select range
                    // Simple implementation - just select single
                    select.selectedIndex = option.index;
                } else {
                    // Regular click: select only this one
                    for (let i = 0; i < select.options.length; i++) {
                        select.options[i].selected = false;
                    }
                    option.selected = true;
                }
                
                // Trigger change event
                const event = new Event('change');
                select.dispatchEvent(event);
            }
        });
    }
});
</script>
