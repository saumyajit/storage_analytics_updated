<?php
/**
 * JavaScript Partial
 */
?>

<script>
class StorageAnalytics {
    constructor() {
        this.refreshInterval = null;
        this.currentRefresh = <?= $filter['refresh'] ?>;
        this.refreshEnabled = <?= $filter['refresh_enabled'] ? 'true' : 'false' ?>;
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        // Filter panel toggle
        this.initFilterPanel();
        
        // View toggle (Host/Filesystem)
        this.initViewToggle();
        
        // Details buttons
        this.initDetailsButtons();
        
        // Auto-refresh
        this.initAutoRefresh();
        
        // Form submission
        this.initFormHandling();
        
        // Export functionality
        this.initExport();
        
        // Initialize any tooltips or enhancements
        this.enhanceUI();
    }
    
    initFilterPanel() {
        const toggleBtn = document.getElementById('filter-toggle');
        const panel = document.getElementById('filter-panel');
        const advancedToggle = document.getElementById('toggle-advanced');
        const advancedPanel = document.getElementById('advanced-filters');
        
        if (toggleBtn && panel) {
            toggleBtn.addEventListener('click', () => {
                const isVisible = panel.style.display !== 'none';
                panel.style.display = isVisible ? 'none' : 'block';
                toggleBtn.querySelector('.toggle-icon').textContent = isVisible ? '▼' : '▲';
            });
        }
        
        if (advancedToggle && advancedPanel) {
            advancedToggle.addEventListener('click', () => {
                const isVisible = advancedPanel.style.display !== 'none';
                advancedPanel.style.display = isVisible ? 'none' : 'block';
                advancedToggle.querySelector('.toggle-icon').textContent = isVisible ? '▶' : '▼';
            });
        }
        
        // Clear filters button
        const clearBtn = document.getElementById('clear-filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                document.getElementById('filter-form').reset();
                document.getElementById('filter-form').submit();
            });
        }
    }
    
    initViewToggle() {
        const toggleBtns = document.querySelectorAll('.btn-view-toggle');
        const hostView = document.getElementById('host-view');
        const fsView = document.getElementById('filesystem-view');
        
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active state
                toggleBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show/hide views
                const view = btn.dataset.view;
                if (view === 'host') {
                    hostView.style.display = 'block';
                    fsView.style.display = 'none';
                } else {
                    hostView.style.display = 'none';
                    fsView.style.display = 'block';
                }
            });
        });
    }
    
    initDetailsButtons() {
        // Host details buttons
        document.querySelectorAll('.btn-details').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const hostId = e.target.dataset.hostid;
                const hostName = e.target.dataset.host;
                this.showHostDetails(hostId, hostName);
            });
        });
        
        // Chart buttons
        document.querySelectorAll('.btn-chart').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const hostId = e.target.dataset.hostid;
                const mount = e.target.dataset.mount;
                this.showGrowthChart(hostId, mount);
            });
        });
        
        // Pattern buttons
        document.querySelectorAll('.btn-pattern').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const pattern = JSON.parse(e.target.dataset.pattern);
                this.showSeasonalPattern(pattern);
            });
        });
    }
    
    initAutoRefresh() {
        const refreshEnabled = document.getElementById('refresh_enabled');
        const refreshSelect = document.getElementById('refresh');
        const refreshStatus = document.getElementById('refresh-status');
        
        if (refreshEnabled && refreshSelect) {
            // Toggle refresh
            refreshEnabled.addEventListener('change', (e) => {
                this.refreshEnabled = e.target.checked;
                refreshSelect.disabled = !this.refreshEnabled;
                this.updateRefreshStatus();
                this.toggleAutoRefresh();
            });
            
            // Change interval
            refreshSelect.addEventListener('change', (e) => {
                this.currentRefresh = parseInt(e.target.value);
                this.updateRefreshStatus();
                this.toggleAutoRefresh();
            });
            
            // Initial setup
            this.updateRefreshStatus();
            this.toggleAutoRefresh();
        }
    }
    
    updateRefreshStatus() {
        const refreshStatus = document.getElementById('refresh-status');
        if (!refreshStatus) return;
        
        if (this.refreshEnabled && this.currentRefresh > 0) {
            refreshStatus.textContent = 
                '<?= _("Refreshing every") ?> ' + this.currentRefresh + ' <?= _("seconds") ?>';
            refreshStatus.style.opacity = '1';
        } else {
            refreshStatus.textContent = '<?= _("Auto-refresh disabled") ?>';
            refreshStatus.style.opacity = '0.5';
        }
    }
    
    toggleAutoRefresh() {
        // Clear existing interval
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
        
        // Start new interval if enabled
        if (this.refreshEnabled && this.currentRefresh > 0 && !this.isLoading) {
            this.refreshInterval = setInterval(() => {
                this.refreshData();
            }, this.currentRefresh * 1000);
        }
    }
    
    refreshData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        const container = document.getElementById('storage-analytics-container');
        if (container) {
            container.classList.add('refreshing');
        }
        
        // Get current filter values
        const formData = new FormData(document.getElementById('filter-form'));
        const params = new URLSearchParams(formData);
        
        // Add current page
        params.set('page', <?= $page ?>);
        
        // Make AJAX request
        fetch(`zabbix.php?${params.toString()}`, {
            credentials: 'same-origin'
        })
        .then(response => response.text())
        .then(html => {
            // Extract the main content (skip scripts and styles)
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newContent = tempDiv.querySelector('.storage-analytics-container');
            
            if (newContent) {
                container.innerHTML = newContent.innerHTML;
                // Reinitialize the module
                this.init();
            }
        })
        .catch(error => {
            console.error('Refresh failed:', error);
        })
        .finally(() => {
            this.isLoading = false;
            if (container) {
                container.classList.remove('refreshing');
            }
        });
    }
    
    initFormHandling() {
        const form = document.getElementById('filter-form');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            // Add loading indicator
            const submitBtn = form.querySelector('.btn-apply');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="icon-loading"></span> <?= _("Applying...") ?>';
                submitBtn.disabled = true;
            }
        });
    }
    
    initExport() {
        const exportBtn = document.querySelector('.btn-export');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportData();
            });
        }
    }
    
	exportData(format = 'csv') {
		// Get current filter values
		const formData = new FormData(document.getElementById('filter-form'));
		const params = new URLSearchParams(formData);
		
		// Add export parameters
		params.set('export', format);
		
		// Open in new window
		window.open(`zabbix.php?${params.toString()}`, '_blank');
	}

    showHostDetails(hostId, hostName) {
        const modal = document.getElementById('host-details-modal');
        const title = document.getElementById('modal-title');
        const body = document.getElementById('modal-body');
        
        if (!modal || !title || !body) return;
        
        // Set title
        title.textContent = hostName + ' - <?= _("Storage Details") ?>';
        
        // Show loading
        body.innerHTML = '<div class="loading"><?= _("Loading details...") ?></div>';
        modal.style.display = 'block';
        
        // Load content via AJAX
        fetch(`zabbix.php?action=storage.analytics.host&hostid=${hostId}`, {
            credentials: 'same-origin'
        })
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(error => {
            body.innerHTML = `<div class="error"><?= _("Failed to load details") ?>: ${error.message}</div>`;
        });
        
        // Close button
        modal.querySelector('.modal-close').onclick = () => {
            modal.style.display = 'none';
        };
        
        // Close on background click
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    showGrowthChart(hostId, mount) {
        // Implement chart display
        alert(`<?= _("Chart for") ?> ${hostId}/${mount} - <?= _("To be implemented") ?>`);
    }
    
    showSeasonalPattern(pattern) {
        // Create pattern display
        let html = '<div class="pattern-display"><h4><?= _("Weekly Pattern") ?></h4><ul>';
        
        pattern.forEach(day => {
            const size = this.formatBytes(day.avg);
            html += `<li><strong>${day.day}:</strong> ${size} (${day.samples} <?= _("samples") ?>)</li>`;
        });
        
        html += '</ul></div>';
        
        // Show in modal or tooltip
        this.showInfoModal('<?= _("Seasonal Pattern") ?>', html);
    }
    
    showInfoModal(title, content) {
        // Simple modal for info display
        const modal = document.createElement('div');
        modal.className = 'info-modal';
        modal.innerHTML = `
            <div class="info-modal-content">
                <div class="info-modal-header">
                    <h4>${title}</h4>
                    <button type="button" class="info-modal-close">&times;</button>
                </div>
                <div class="info-modal-body">${content}</div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close handlers
        modal.querySelector('.info-modal-close').onclick = () => {
            document.body.removeChild(modal);
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        };
    }
    
    formatBytes(bytes) {
        // Simple formatter for JavaScript
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    enhanceUI() {
        // Add CSS classes based on thresholds
        document.querySelectorAll('.usage-fill').forEach(fill => {
            const width = parseFloat(fill.style.width);
            const warning = parseFloat(fill.dataset.thresholdWarning || 80);
            const critical = parseFloat(fill.dataset.thresholdCritical || 90);
            
            if (width >= critical) {
                fill.classList.add('critical-fill');
            } else if (width >= warning) {
                fill.classList.add('warning-fill');
            }
        });
        
        // Add tooltips
        document.querySelectorAll('[title]').forEach(el => {
            if (!el.hasAttribute('data-tooltip-initialized')) {
                el.setAttribute('data-tooltip-initialized', 'true');
                // Could initialize a tooltip library here
            }
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.storageAnalytics = new StorageAnalytics();
});
</script>
