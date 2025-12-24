<?php
/**
 * Filter Panel Partial
 */

// For inline use, we need the data
if (!isset($filterOptions) || !isset($filter)) {
    return;
}

// Filter groups to show only those starting with 'customer/'
$customerGroups = array_filter($filterOptions['hostgroups'], function($group) {
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
                <!-- Step 1: Select Multiple Host Groups (filtered to customer/ groups first) -->
                <div class="filter-group">
                    <label for="groupids"><?= _('Host Groups') ?></label>
                    <select id="groupids" name="groupids[]" multiple class="select" size="6">
                        <option value=""><?= _('-- All groups --') ?></option>
                        <?php foreach ($customerGroups as $group): ?>
                            <?php 
                            $isCustomerGroup = strpos($group['name'], 'customer/') === 0;
                            $groupClass = $isCustomerGroup ? 'customer-group' : 'other-group';
                            ?>
                            <option value="<?= $group['id'] ?>" 
                                    class="<?= $groupClass ?>"
                                    <?= in_array($group['id'], $filter['groupids'] ?? []) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="filter-hint"><?= _('Hold Ctrl/Cmd to select multiple groups. Customer groups shown first.') ?></small>
                </div>
                
                <!-- Step 2: Hosts will be loaded via AJAX when groups are selected -->
                <div class="filter-group">
                    <label for="hostids"><?= _('Hosts') ?></label>
                    <div id="hosts-container">
                        <select id="hostids" name="hostids[]" multiple class="select" size="6" 
                                <?= empty($filter['groupids']) ? 'disabled' : '' ?>>
                            <option value=""><?= _('All hosts in selected groups') ?></option>
                            <?php 
                            // Get selected group IDs
                            $selectedGroupIds = $filter['groupids'] ?? [];
                            
                            if (!empty($selectedGroupIds)) {
                                // Filter hosts: show only hosts that belong to ANY selected group
                                foreach ($filterOptions['hosts'] as $host): 
                                    $hostGroupIds = $host['groupids'] ?? [];
                                    
                                    if (empty($selectedGroupIds) || array_intersect($selectedGroupIds, $hostGroupIds)): 
                            ?>
                                <option value="<?= $host['id'] ?>" 
                                        <?= in_array($host['id'], $filter['hostids'] ?? []) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($host['name']) ?> (<?= htmlspecialchars($host['host']) ?>)
                                </option>
                            <?php 
                                    endif;
                                endforeach;
                            }
                            ?>
                        </select>
                    </div>
                    <small class="filter-hint" id="host-count-hint">
                        <?php 
                        if (empty($selectedGroupIds)) {
                            echo _('Select host groups first');
                        } else {
                            $hostsInGroups = array_filter($filterOptions['hosts'], function($host) use ($selectedGroupIds) {
                                $hostGroupIds = $host['groupids'] ?? [];
                                return array_intersect($selectedGroupIds, $hostGroupIds);
                            });
                            echo sprintf(_('%d hosts in selected groups'), count($hostsInGroups));
                        }
                        ?>
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
/* Style customer groups differently */
.customer-group {
    font-weight: bold;
    color: #2c3e50;
}

.other-group {
    color: #7f8c8d;
}

/* Group separator */
.customer-group + .other-group {
    border-top: 1px dashed #ddd;
    padding-top: 2px;
}

/* Better select styling */
.select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 13px;
    background: white;
}

.select[multiple] {
    min-height: 120px;
    padding: 5px;
}

.select[multiple] option {
    padding: 6px 8px;
    margin: 1px 0;
    border-radius: 3px;
}

.select[multiple] option:hover {
    background: #f5f5f5;
}

.select[multiple] option:checked {
    background: #3498db;
    color: white;
}

/* Loading indicator */
.loading {
    display: inline-block;
    margin-left: 8px;
    color: #3498db;
    font-size: 12px;
}

.loading::after {
    content: '...';
    animation: dots 1.5s infinite;
}

@keyframes dots {
    0%, 20% { content: '.'; }
    40% { content: '..'; }
    60%, 100% { content: '...'; }
}

/* Filter panel toggle */
.filter-panel {
    margin-top: 10px;
    padding: 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filter-actions button {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('groupids');
    const hostsContainer = document.getElementById('hosts-container');
    const hostCountHint = document.getElementById('host-count-hint');
    const filterForm = document.getElementById('filter-form');
    const clearButton = document.getElementById('clear-filters');
    
    // Store all hosts data for client-side filtering
    const allHostsData = <?= json_encode($filterOptions['hosts']) ?>;
    
    // When groups change, filter hosts locally
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            const selectedGroupIds = getSelectedGroupIds();
            updateHostsDropdown(selectedGroupIds);
        });
    }
    
    // Clear all filters
    if (clearButton) {
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Clear all selections
            if (groupSelect) groupSelect.selectedIndex = -1;
            
            // Clear hosts dropdown
            if (hostsContainer) {
                hostsContainer.innerHTML = `
                    <select id="hostids" name="hostids[]" multiple class="select" size="6" disabled>
                        <option value=""><?= _('Select host groups first') ?></option>
                    </select>
                `;
            }
            
            // Update hint
            if (hostCountHint) {
                hostCountHint.textContent = '<?= _('Select host groups first') ?>';
            }
            
            // Submit form to clear all filters
            if (filterForm) {
                // Add a flag to indicate clear action
                const clearInput = document.createElement('input');
                clearInput.type = 'hidden';
                clearInput.name = 'clear_filters';
                clearInput.value = '1';
                filterForm.appendChild(clearInput);
                
                filterForm.submit();
            }
        });
    }
    
    // Initialize hosts dropdown based on current selection
    if (groupSelect && groupSelect.value) {
        const initialGroupIds = getSelectedGroupIds();
        if (initialGroupIds.length > 0) {
            updateHostsDropdown(initialGroupIds);
        }
    }
    
    // Helper functions
    function getSelectedGroupIds() {
        if (!groupSelect) return [];
        
        return Array.from(groupSelect.selectedOptions)
            .map(option => option.value)
            .filter(value => value !== '' && value !== null);
    }
    
    function updateHostsDropdown(selectedGroupIds) {
        if (!hostsContainer || !hostCountHint) return;
        
        if (selectedGroupIds.length === 0) {
            // No groups selected
            hostsContainer.innerHTML = `
                <select id="hostids" name="hostids[]" multiple class="select" size="6" disabled>
                    <option value=""><?= _('Select host groups first') ?></option>
                </select>
            `;
            hostCountHint.textContent = '<?= _('Select host groups first') ?>';
            return;
        }
        
        // Show loading
        hostCountHint.innerHTML = '<span class="loading"><?= _('Filtering hosts') ?></span>';
        
        // Filter hosts locally
        const filteredHosts = allHostsData.filter(host => {
            const hostGroupIds = host.groupids || [];
            return arrayIntersects(selectedGroupIds, hostGroupIds);
        });
        
        // Build hosts dropdown
        let hostsHtml = `
            <select id="hostids" name="hostids[]" multiple class="select" size="6">
                <option value=""><?= _('All hosts in selected groups') ?></option>
        `;
        
        filteredHosts.forEach(host => {
            // Check if this host was previously selected
            const isSelected = isHostSelected(host.id);
            
            hostsHtml += `
                <option value="${host.id}" ${isSelected ? 'selected' : ''}>
                    ${escapeHtml(host.name)} (${escapeHtml(host.host)})
                </option>
            `;
        });
        
        hostsHtml += '</select>';
        
        // Update the container
        hostsContainer.innerHTML = hostsHtml;
        
        // Update count hint
        hostCountHint.textContent = `${filteredHosts.length} <?= _('hosts in selected groups') ?>`;
        
        // Auto-submit after a short delay (optional)
        setTimeout(() => {
            if (filterForm && filteredHosts.length > 0) {
                console.log('Auto-submitting with filtered hosts');
                filterForm.submit();
            }
        }, 500);
    }
    
    function arrayIntersects(arr1, arr2) {
        return arr1.some(item => arr2.includes(parseInt(item)));
    }
    
    function isHostSelected(hostId) {
        // Check URL parameters for selected hosts
        const urlParams = new URLSearchParams(window.location.search);
        const selectedHosts = urlParams.getAll('hostids[]');
        return selectedHosts.includes(hostId.toString());
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Toggle filter panel
    const filterToggle = document.getElementById('filter-toggle');
    const filterPanel = document.getElementById('filter-panel');
    
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
});
</script>