/**
 * Filter Scripts for Storage Analytics
 */

class StorageAnalyticsFilters {
    constructor(options = {}) {
        // Configuration
        this.config = {
            autoUpdateDelay: 500,
            debounceDelay: 300,
            ...options
        };
        
        // DOM Elements
        this.groupSelect = document.getElementById('groupids');
        this.hostsContainer = document.getElementById('hosts-container');
        this.hostCountHint = document.getElementById('host-count-hint');
        this.filterForm = document.getElementById('filter-form');
        this.autoUpdateFlag = document.getElementById('auto-update-flag');
        this.clearButton = document.getElementById('clear-filters');
        
        // Data
        this.allHosts = window.filterHostsData || [];
        
        // State
        this.isUpdating = false;
        this.debounceTimer = null;
        
        // Initialize
        this.init();
    }
    
    init() {
        if (!this.groupSelect || !this.hostsContainer) {
            console.error('Required filter elements not found');
            return;
        }
        
        // Bind events
        this.bindEvents();
        
        // Initialize based on current selection
        this.initializeFromCurrentSelection();
    }
    
    bindEvents() {
        // Group selection change
        this.groupSelect.addEventListener('change', (e) => this.onGroupsChange(e));
        
        // Clear filters button
        if (this.clearButton) {
            this.clearButton.addEventListener('click', (e) => this.onClearFilters(e));
        }
        
        // Form submit - disable auto-update for manual submissions
        if (this.filterForm) {
            this.filterForm.addEventListener('submit', (e) => this.onFormSubmit(e));
        }
        
        // Optional: Auto-submit when hosts are selected
        document.addEventListener('change', (e) => {
            if (e.target.id === 'hostids' && this.autoUpdateFlag?.value === '1') {
                this.debounce(() => {
                    this.filterForm?.submit();
                }, 300);
            }
        });
    }
    
    initializeFromCurrentSelection() {
        const selectedGroupIds = this.getSelectedGroupIds();
        
        if (selectedGroupIds.length > 0) {
            this.updateHostsList(selectedGroupIds);
        } else {
            this.showHostsPlaceholder();
        }
    }
    
    onGroupsChange(event) {
        const selectedGroupIds = this.getSelectedGroupIds();
        
        // Show loading indicator
        this.showLoadingIndicator();
        
        // Debounce the update
        this.debounce(() => {
            this.updateHostsList(selectedGroupIds);
        }, this.config.debounceDelay);
    }
    
    onClearFilters(event) {
        event.preventDefault();
        
        // Clear group selection
        this.groupSelect.selectedIndex = -1;
        
        // Reset hosts list
        this.showHostsPlaceholder();
        
        // Submit form to clear everything
        if (this.filterForm) {
            this.filterForm.submit();
        }
    }
    
    onFormSubmit(event) {
        // Disable auto-update for manual form submissions
        if (this.autoUpdateFlag) {
            this.autoUpdateFlag.value = '0';
        }
    }
    
    getSelectedGroupIds() {
        return Array.from(this.groupSelect.selectedOptions)
            .map(opt => opt.value)
            .filter(value => value !== '' && value !== null);
    }
    
    updateHostsList(selectedGroupIds) {
        if (selectedGroupIds.length === 0) {
            this.showHostsPlaceholder();
            return;
        }
        
        // Filter hosts: only those belonging to ANY selected group
        const matchingHosts = this.filterHostsByGroups(selectedGroupIds);
        
        // Build and display the hosts dropdown
        this.renderHostsDropdown(matchingHosts);
        
        // Update count hint
        this.updateHostCountHint(matchingHosts.length);
        
        // Auto-submit if enabled and there are hosts
        if (matchingHosts.length > 0 && this.shouldAutoSubmit()) {
            setTimeout(() => {
                this.filterForm?.submit();
            }, this.config.autoUpdateDelay);
        }
    }
    
    filterHostsByGroups(selectedGroupIds) {
        return this.allHosts.filter(host => {
            const hostGroupIds = host.groupids || [];
            return selectedGroupIds.some(groupId => 
                hostGroupIds.includes(parseInt(groupId))
            );
        });
    }
    
    renderHostsDropdown(hosts) {
        // Get currently selected host IDs from the form
        const currentHostIds = this.getCurrentSelectedHostIds();
        
        // Build options HTML
        let optionsHtml = `
            <option value="">${this.translate('All hosts in selected groups')}</option>
        `;
        
        hosts.forEach(host => {
            const isSelected = currentHostIds.includes(host.id.toString());
            const escapedName = this.escapeHtml(host.name || '');
            const escapedHost = this.escapeHtml(host.host || '');
            
            optionsHtml += `
                <option value="${host.id}" ${isSelected ? 'selected' : ''}>
                    ${escapedName} (${escapedHost})
                </option>
            `;
        });
        
        // Update the container
        this.hostsContainer.innerHTML = `
            <select id="hostids" name="hostids[]" multiple class="select" size="5">
                ${optionsHtml}
            </select>
        `;
    }
    
    getCurrentSelectedHostIds() {
        try {
            // Try to get from existing select
            const hostSelect = document.getElementById('hostids');
            if (hostSelect) {
                return Array.from(hostSelect.selectedOptions)
                    .map(opt => opt.value)
                    .filter(v => v !== '');
            }
        } catch (e) {
            console.warn('Could not get current host selections:', e);
        }
        return [];
    }
    
    showHostsPlaceholder() {
        this.hostsContainer.innerHTML = `
            <select id="hostids" name="hostids[]" multiple class="select" size="5" disabled>
                <option value="">${this.translate('Select host groups first')}</option>
            </select>
        `;
        
        this.hostCountHint.textContent = this.translate('Select host groups to see available hosts');
    }
    
    showLoadingIndicator() {
        if (this.hostCountHint) {
            this.hostCountHint.innerHTML = `
                <span class="loading-indicator">
                    ${this.translate('Updating hosts')}
                </span>
            `;
        }
    }
    
    updateHostCountHint(count) {
        if (this.hostCountHint) {
            this.hostCountHint.textContent = 
                `${count} ${this.translate('hosts available in selected groups')}`;
        }
    }
    
    shouldAutoSubmit() {
        return this.autoUpdateFlag && this.autoUpdateFlag.value === '1';
    }
    
    debounce(func, delay) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(func, delay);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    translate(key) {
        // Simple translation map - you can expand this
        const translations = {
            'All hosts in selected groups': '<?= _("All hosts in selected groups") ?>',
            'Select host groups first': '<?= _("Select host groups first") ?>',
            'Select host groups to see available hosts': '<?= _("Select host groups to see available hosts") ?>',
            'Updating hosts': '<?= _("Updating hosts") ?>',
            'hosts available in selected groups': '<?= _("hosts available in selected groups") ?>'
        };
        
        return translations[key] || key;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Pass hosts data from PHP to JavaScript
    window.filterHostsData = <?= json_encode($filterOptions['hosts']) ?>;
    
    // Initialize the filter manager
    window.storageFilters = new StorageAnalyticsFilters({
        autoUpdateDelay: 500,
        debounceDelay: 300
    });
});
