<?php
/**
 * Charts Partial
 */
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

<div class="charts-section" style="display: none;" id="charts-section">
    <div class="chart-header">
        <h3><?= _('Storage Analytics Charts') ?></h3>
        <div class="chart-controls">
            <select id="chart-type" class="select">
                <option value="usage"><?= _('Usage Distribution') ?></option>
                <option value="growth"><?= _('Growth Trends') ?></option>
                <option value="forecast"><?= _('Capacity Forecast') ?></option>
                <option value="seasonal"><?= _('Seasonal Patterns') ?></option>
            </select>
            <button type="button" class="btn-alt" id="export-chart"><?= _('Export Chart') ?></button>
        </div>
    </div>
    
    <div class="chart-container">
        <canvas id="analytics-chart" width="800" height="400"></canvas>
    </div>
    
    <div class="chart-legend" id="chart-legend"></div>
</div>

<style>
.charts-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.chart-container {
    position: relative;
    height: 400px;
    margin: 20px 0;
}

.chart-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}
</style>

<script>
class StorageCharts {
    constructor(analyticsData) {
        this.data = analyticsData;
        this.chart = null;
        this.init();
    }
    
    init() {
        this.createChart();
        this.setupEventListeners();
    }
    
    createChart() {
        const ctx = document.getElementById('analytics-chart').getContext('2d');
        
        // Sample data structure
        const chartData = this.prepareUsageDistributionData();
        
        this.chart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: '<?= _("Usage Percentage") ?>'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '<?= _("Number of Filesystems") ?>'
                        }
                    }
                }
            }
        });
        
        this.updateLegend(chartData);
    }
    
    prepareUsageDistributionData() {
        // Process analytics data for chart
        const buckets = {
            '0-20%': 0, '20-40%': 0, '40-60%': 0, 
            '60-80%': 0, '80-90%': 0, '90-100%': 0
        };
        
        this.data.storageData.forEach(item => {
            const usage = item.usage_pct;
            if (usage <= 20) buckets['0-20%']++;
            else if (usage <= 40) buckets['20-40%']++;
            else if (usage <= 60) buckets['40-60%']++;
            else if (usage <= 80) buckets['60-80%']++;
            else if (usage <= 90) buckets['80-90%']++;
            else buckets['90-100%']++;
        });
        
        return {
            labels: Object.keys(buckets),
            datasets: [{
                label: '<?= _("Filesystems by Usage") ?>',
                data: Object.values(buckets),
                backgroundColor: [
                    '#2ecc71', '#3498db', '#9b59b6', 
                    '#f1c40f', '#e67e22', '#e74c3c'
                ],
                borderColor: '#fff',
                borderWidth: 1
            }]
        };
    }
    
    prepareGrowthTrendsData() {
        // Prepare growth trends data
        const growthRates = this.data.storageData
            .filter(item => item.daily_growth_raw > 0)
            .map(item => ({
                host: item.host,
                mount: item.mount,
                growth: item.daily_growth_raw,
                trend: item.growth_trend
            }))
            .sort((a, b) => b.growth - a.growth)
            .slice(0, 10); // Top 10
        
        return {
            labels: growthRates.map(g => `${g.host}: ${g.mount}`),
            datasets: [{
                label: '<?= _("Daily Growth (bytes)") ?>',
                data: growthRates.map(g => g.growth),
                backgroundColor: growthRates.map(g => 
                    g.trend === 'rapid_increase' ? '#e74c3c' :
                    g.trend === 'increasing' ? '#e67e22' :
                    g.trend === 'slow_increase' ? '#f1c40f' : '#3498db'
                )
            }]
        };
    }
    
    prepareCapacityForecastData() {
        // Prepare forecast data
        const forecasts = this.data.storageData
            .filter(item => item.days_until_full !== '<?= _("No growth") ?>')
            .map(item => {
                const days = this.parseDaysToNumber(item.days_until_full);
                return {
                    host: item.host,
                    mount: item.mount,
                    days: days,
                    date: days < 3650 ? new Date(Date.now() + days * 86400000) : null
                };
            })
            .filter(f => f.days < 365)
            .sort((a, b) => a.days - b.days)
            .slice(0, 15);
        
        return {
            labels: forecasts.map(f => `${f.host}: ${f.mount}`),
            datasets: [{
                label: '<?= _("Days Until Full") ?>',
                data: forecasts.map(f => f.days),
                backgroundColor: forecasts.map(f => 
                    f.days <= 15 ? '#e74c3c' :
                    f.days <= 30 ? '#e67e22' : '#2ecc71'
                )
            }]
        };
    }
    
    updateChart(type) {
        let chartData;
        
        switch(type) {
            case 'growth':
                chartData = this.prepareGrowthTrendsData();
                this.chart.options.scales.x.title.text = '<?= _("Filesystems") ?>';
                this.chart.options.scales.y.title.text = '<?= _("Daily Growth (bytes)") ?>';
                break;
                
            case 'forecast':
                chartData = this.prepareCapacityForecastData();
                this.chart.options.scales.x.title.text = '<?= _("Filesystems") ?>';
                this.chart.options.scales.y.title.text = '<?= _("Days Until Full") ?>';
                break;
                
            case 'usage':
            default:
                chartData = this.prepareUsageDistributionData();
                this.chart.options.scales.x.title.text = '<?= _("Usage Percentage") ?>';
                this.chart.options.scales.y.title.text = '<?= _("Number of Filesystems") ?>';
                break;
        }
        
        this.chart.data = chartData;
        this.chart.update();
        this.updateLegend(chartData);
    }
    
    updateLegend(chartData) {
        const legend = document.getElementById('chart-legend');
        if (!legend) return;
        
        let html = '';
        
        if (chartData.datasets && chartData.datasets[0].backgroundColor) {
            const colors = chartData.datasets[0].backgroundColor;
            const labels = chartData.labels;
            
            labels.forEach((label, index) => {
                const color = Array.isArray(colors) ? colors[index] : colors;
                html += `
                    <div class="legend-item">
                        <span class="legend-color" style="background: ${color}"></span>
                        <span>${label}</span>
                    </div>
                `;
            });
        }
        
        legend.innerHTML = html;
    }
    
    setupEventListeners() {
        // Chart type selector
        document.getElementById('chart-type').addEventListener('change', (e) => {
            this.updateChart(e.target.value);
        });
        
        // Export chart button
        document.getElementById('export-chart').addEventListener('click', () => {
            this.exportChart();
        });
    }
    
    exportChart() {
        const link = document.createElement('a');
        link.download = 'storage-analytics-chart.png';
        link.href = this.chart.toBase64Image();
        link.click();
    }
    
    parseDaysToNumber(daysStr) {
        // Same parsing logic as PHP
        if (daysStr === '<?= _("No growth") ?>' || daysStr === '<?= _("Already full") ?>' || 
            daysStr === '<?= _("More than 10 years") ?>') {
            return 3650; // 10 years as max
        }
        
        let days = 3650;
        
        // Parse days string
        const yearMatch = daysStr.match(/(\d+)\s*years?/);
        const monthMatch = daysStr.match(/(\d+)\s*months?/);
        const dayMatch = daysStr.match(/(\d+)\s*days?/);
        
        if (yearMatch) days = parseInt(yearMatch[1]) * 365;
        if (monthMatch) days += parseInt(monthMatch[1]) * 30;
        if (dayMatch) days += parseInt(dayMatch[1]);
        
        return days;
    }
}

// Initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if we have analytics data
    if (typeof storageData !== 'undefined') {
        window.storageCharts = new StorageCharts({
            storageData: storageData,
            summary: summaryData
        });
        
        // Add chart toggle button to header
        const headerRight = document.querySelector('.header-right');
        if (headerRight) {
            const chartBtn = document.createElement('button');
            chartBtn.type = 'button';
            chartBtn.className = 'btn-alt';
            chartBtn.id = 'toggle-charts';
            chartBtn.innerHTML = '📊 <?= _("Show Charts") ?>';
            headerRight.prepend(chartBtn);
            
            chartBtn.addEventListener('click', () => {
                const chartsSection = document.getElementById('charts-section');
                const isVisible = chartsSection.style.display !== 'none';
                chartsSection.style.display = isVisible ? 'none' : 'block';
                chartBtn.innerHTML = isVisible ? '📊 <?= _("Show Charts") ?>' : '📈 <?= _("Hide Charts") ?>';
            });
        }
    }
});
</script>
