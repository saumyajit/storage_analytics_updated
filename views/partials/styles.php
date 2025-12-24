<?php
/**
 * CSS Styles Partial
 */
?>

<style>
/* Main Container */
.storage-analytics-container {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    padding: 20px;
    color: #333;
    background: #f5f5f5;
    min-height: 100vh;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.header h1 {
    margin: 0 0 5px 0;
    font-size: 24px;
    color: #2c3e50;
}

.header-subtitle {
    margin: 0;
    color: #7f8c8d;
    font-size: 14px;
}

.last-updated {
    font-size: 12px;
    color: #95a5a6;
    background: #ecf0f1;
    padding: 4px 8px;
    border-radius: 3px;
}

/* Filter Section */
.filter-section {
    margin-bottom: 25px;
}

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

.filter-panel {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

.filter-group .select,
.filter-group .input,
.filter-group .multiselect {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 14px;
}

.filter-group .input-small {
    width: 70px;
    text-align: center;
}

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

.refresh-group .refresh-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.refresh-status {
    font-size: 12px;
    color: #27ae60;
    transition: opacity 0.3s;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    cursor: pointer;
}

.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.btn-apply, .btn-clear, .btn-toggle-advanced {
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

.btn-clear, .btn-toggle-advanced {
    background: #ecf0f1;
    color: #7f8c8d;
}

.btn-clear:hover, .btn-toggle-advanced:hover {
    background: #bdc3c7;
}

.advanced-filters {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px dashed #ddd;
}

.filter-hint {
    font-size: 11px;
    color: #95a5a6;
    margin-top: 3px;
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.card-icon {
    font-size: 32px;
    opacity: 0.8;
}

.card-content {
    flex: 1;
}

.card-content h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: #2c3e50;
}

.card-subtitle {
    margin: 0 0 15px 0;
    font-size: 12px;
    color: #7f8c8d;
}

.card-value {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
    color: #2c3e50;
}

.card-progress {
    margin-top: 10px;
}

.progress-bar {
    height: 8px;
    background: #ecf0f1;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: #3498db;
    transition: width 0.3s ease;
}

.progress-fill.critical-fill {
    background: #e74c3c;
}

.progress-fill.warning-fill {
    background: #f39c12;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #7f8c8d;
}

.card-stats, .card-details {
    margin-top: 10px;
}

.stat-item, .detail-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 4px;
}

.stat-label, .detail-label {
    color: #7f8c8d;
}

.stat-value, .detail-value {
    font-weight: 600;
}

/* Top Growers */
.top-growers {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    margin-top: 20px;
}

.top-growers h4 {
    margin: 0 0 15px 0;
    font-size: 14px;
    color: #2c3e50;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.mini-table th,
.mini-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.mini-table th {
    font-weight: 600;
    color: #7f8c8d;
    background: #f8f9fa;
}

.growth-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
}

.growth-badge.rapid {
    background: #e74c3c;
    color: white;
}

.days-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.days-badge.ok {
    background: #2ecc71;
    color: white;
}

.days-badge.warning {
    background: #f39c12;
    color: white;
}

.days-badge.critical {
    background: #e74c3c;
    color: white;
}

/* Table Section */
.table-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}

.table-header h2 {
    margin: 0;
    font-size: 18px;
    color: #2c3e50;
}

.table-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.view-toggle {
    display: flex;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    overflow: hidden;
}

.btn-view-toggle {
    padding: 6px 12px;
    background: white;
    border: none;
    cursor: pointer;
    font-size: 13px;
    color: #7f8c8d;
}

.btn-view-toggle.active {
    background: #3498db;
    color: white;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pagination-link {
    padding: 4px 8px;
    background: #ecf0f1;
    color: #7f8c8d;
    text-decoration: none;
    border-radius: 3px;
    font-size: 12px;
}

.pagination-link:hover {
    background: #bdc3c7;
}

.pagination-info {
    font-size: 12px;
    color: #7f8c8d;
}

/* Tables */
.list-table {
    width: 100%;
    border-collapse: collapse;
}

.list-table th,
.list-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.list-table th {
    font-weight: 600;
    color: #7f8c8d;
    background: #f8f9fa;
    position: sticky;
    top: 0;
}

.list-table tr:hover {
    background: #f8f9fa;
}

.list-table tr.critical {
    background: #fff5f5;
}

.list-table tr.warning {
    background: #fffbf0;
}

.host-cell strong {
    display: block;
    margin-bottom: 3px;
}

.host-alias {
    font-size: 11px;
    color: #95a5a6;
}

.usage-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.usage-bar {
    flex: 1;
    height: 6px;
    background: #ecf0f1;
    border-radius: 3px;
    overflow: hidden;
}

.usage-fill {
    height: 100%;
    background: #2ecc71;
}

.growth-value {
    font-weight: 600;
    font-size: 13px;
}

.growth-value.positive {
    color: #e74c3c;
}

.growth-value.neutral {
    color: #7f8c8d;
}

.days-cell {
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 13px;
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

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.ok {
    background: #d5f4e6;
    color: #27ae60;
}

.status-badge.warning {
    background: #fef5e7;
    color: #f39c12;
}

.status-badge.critical {
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

.btn-details, .btn-small {
    padding: 5px 10px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.btn-details:hover {
    background: #2980b9;
}

/* Filesystem Details */
.filesystem-view-content {
    padding: 20px;
}

.host-filesystems {
    margin-bottom: 30px;
}

.host-header {
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #3498db;
    color: #2c3e50;
    font-size: 16px;
}

.host-header .fs-count {
    font-size: 12px;
    color: #7f8c8d;
    margin-left: 5px;
}

.fs-details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.fs-details-table th,
.fs-details-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.fs-details-table th {
    font-weight: 600;
    color: #7f8c8d;
    background: #f8f9fa;
}

.fs-details-table tr.fs-row:hover {
    background: #f8f9fa;
}

.fs-details-table tr.fs-row.critical {
    background: #fff5f5;
}

.fs-details-table tr.fs-row.warning {
    background: #fffbf0;
}

.fs-name {
    display: flex;
    align-items: center;
    gap: 8px;
}

.fs-icon {
    font-size: 16px;
    opacity: 0.7;
}

.free-percent {
    font-size: 11px;
    color: #95a5a6;
    margin-top: 2px;
}

.growth-cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.confidence-badge {
    display: inline-block;
    padding: 1px 5px;
    background: #ecf0f1;
    color: #7f8c8d;
    border-radius: 8px;
    font-size: 10px;
    width: fit-content;
}

.trend-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.trend-badge.rapid_increase {
    background: #e74c3c;
    color: white;
}

.trend-badge.increasing {
    background: #f39c12;
    color: white;
}

.trend-badge.slow_increase {
    background: #f1c40f;
    color: #333;
}

.trend-badge.stable {
    background: #ecf0f1;
    color: #7f8c8d;
}

.trend-badge.decreasing {
    background: #27ae60;
    color: white;
}

.fs-actions {
    display: flex;
    gap: 5px;
}

.btn-icon {
    background: none;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 5px;
    cursor: pointer;
    font-size: 14px;
}

.btn-icon:hover {
    background: #f8f9fa;
}

/* Footer */
.footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    font-size: 12px;
    color: #7f8c8d;
}

.calculation-info strong {
    color: #2c3e50;
}

.record-count {
    font-weight: 600;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #2c3e50;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #7f8c8d;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

/* Loading States */
.loading {
    text-align: center;
    padding: 40px;
    color: #7f8c8d;
}

.refreshing {
    opacity: 0.7;
    position: relative;
}

.refreshing::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.5);
    z-index: 10;
}

/* No Data */
.no-data {
    text-align: center;
    padding: 40px;
    color: #95a5a6;
    font-style: italic;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .table-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .header {
        flex-direction: column;
        gap: 10px;
    }
    
    .footer {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
}

/* Utility Classes */
.btn-main {
    background: #3498db;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-alt {
    background: #ecf0f1;
    color: #7f8c8d;
    border: 1px solid #bdc3c7;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-main:hover {
    background: #2980b9;
}

.btn-alt:hover {
    background: #bdc3c7;
}

.hidden {
    display: none !important;
}

.text-success { color: #27ae60; }
.text-warning { color: #f39c12; }
.text-danger { color: #e74c3c; }
.text-muted { color: #95a5a6; }

.bg-success { background: #d5f4e6; }
.bg-warning { background: #fef5e7; }
.bg-danger { background: #fdedec; }

/* Animation for refresh */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.refreshing .summary-card {
    animation: pulse 1.5s infinite;
}
</style>
