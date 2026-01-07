# 💾 Storage Analytics Pro - Zabbix Module

<div align="center">

![Version](https://img.shields.io/badge/version-2.0-blue.svg)
![Zabbix](https://img.shields.io/badge/zabbix-6.0%2B-red.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)
![Status](https://img.shields.io/badge/status-production-brightgreen.svg)

**Advanced storage analytics and capacity forecasting for Zabbix**

[Features](#-features) • [Installation](#-installation) • [Documentation](#-usage) • [Support](#-support)

---

</div>

## 🎯 Overview

Storage Analytics Pro is a powerful Zabbix frontend module that transforms basic disk monitoring into actionable capacity insights. Get predictive analytics, growth forecasting, and intelligent alerting for all your storage systems.

### Why Use This Module?

- 🔮 **Predict Future Capacity** - Know when disks will fill up before it happens
- 📊 **Multiple Algorithms** - Choose from 4 prediction methods based on your needs
- 🎨 **Beautiful UI** - Modern, responsive interface with intuitive visualizations
- 📤 **Export Anywhere** - CSV, HTML, and JSON exports for reports and automation
- ⚡ **Performance Optimized** - Handle thousands of filesystems efficiently
- 🔍 **Smart Filtering** - Quick search and filter across hosts and groups

---

## ✨ Features

<table>
<tr>
<td width="50%">

### 📈 Analytics & Forecasting
- Real-time storage monitoring
- Growth trend analysis (1-90 days)
- 4 prediction algorithms
- Confidence scoring
- Days-until-full calculations
- Top growers identification

</td>
<td width="50%">

### 🎛️ Interface & Usability
- Dual view modes (Host/Filesystem)
- Advanced filtering & search
- Auto-refresh capability
- Color-coded status indicators
- Responsive design
- Pagination support

</td>
</tr>
<tr>
<td width="50%">

### 📊 Export & Reporting
- CSV export (Excel-compatible)
- HTML professional reports
- JSON data export
- Summary statistics
- Growth rate analysis
- Customizable thresholds

</td>
<td width="50%">

### 🔧 Configuration
- Custom warning/critical thresholds
- Exclude specific mount points
- Time range selection
- Prediction method choice
- Refresh interval control
- Per-user preferences

</td>
</tr>
</table>

---

## 🖼️ Screenshots

<details>
<summary><b>📊 Click to view screenshots</b></summary>

### Summary Dashboard
![Summary Dashboard](docs/screenshots/summary.png)
*Overview with key metrics, storage usage, and growth predictions*

### Host View
![Host View](docs/screenshots/host-view.png)
*Aggregated storage data per host with status indicators*

### Filesystem View
![Filesystem View](docs/screenshots/filesystem-view.png)
*Detailed filesystem analysis with mount points and trends*

### Advanced Filters
![Filters](docs/screenshots/filters.png)
*Powerful filtering with searchable host groups and hosts*

### Export Options
![Export](docs/screenshots/export.png)
*Professional reports in CSV, HTML, and JSON formats*

</details>

---

## 🚀 Quick Start

### Prerequisites

- ✅ Zabbix 6.0 or later
- ✅ PHP 7.4+ (PHP 8.x recommended)
- ✅ Standard Zabbix filesystem items (`vfs.fs.size`)

### Installation

**Option 1: Git Clone (Recommended)**

```bash
# Navigate to Zabbix modules directory
cd /usr/share/zabbix/modules/

# Clone the repository
git clone https://github.com/saumyajit/storage_analytics_updated.git diskanalyser

# Set permissions
chown -R www-data:www-data diskanalyser
chmod -R 755 diskanalyser
```

**Option 2: Download ZIP**

```bash
# Download latest release
wget https://github.com/saumyajit/storage_analytics_updated/archive/refs/heads/main.zip

# Extract
unzip main.zip -d /usr/share/zabbix/modules/diskanalyser

# Set permissions
chown -R www-data:www-data /usr/share/zabbix/modules/diskanalyser
```

### Enable in Zabbix

1. Go to **Administration → General → Modules**
2. Click **Scan directory**
3. Find **Storage Analytics Pro upd**
4. Click **Enable**
5. Navigate to **Reports → Storage Analytics**

🎉 **Done!** You should now see the Storage Analytics interface.

---

## 📖 Documentation

### Basic Usage

1. **Select Filters** (optional)
   - Choose host groups to narrow scope
   - Select specific hosts if needed
   - Use search to find hosts quickly

2. **Configure Analysis**
   ```
   Time Range: 30 days (recommended)
   Prediction Method: Seasonal (default)
   Warning Threshold: 80%
   Critical Threshold: 90%
   ```

3. **Apply & Analyze**
   - Click "Apply Filters"
   - Review summary cards
   - Switch between Host/Filesystem views
   - Export data as needed

### Prediction Methods Explained

| Method | Best For | Accuracy | Speed |
|--------|----------|----------|-------|
| **Simple Linear** | Stable growth | ⭐⭐⭐ | ⚡⚡⚡ |
| **Seasonal** | Weekly patterns | ⭐⭐⭐⭐ | ⚡⚡ |
| **Holt-Winters** | Complex trends | ⭐⭐⭐⭐⭐ | ⚡ |
| **Ensemble** | Production use | ⭐⭐⭐⭐⭐ | ⚡ |

<details>
<summary><b>📚 Detailed Method Descriptions</b></summary>

#### Simple Linear
Fast and straightforward. Calculates growth as `(Latest - First) / Days`.
- ✅ Best for steady, predictable growth
- ❌ Doesn't handle seasonal patterns

#### Seasonal Adjusted
Analyzes day-of-week patterns in your data.
- ✅ Accounts for weekend vs weekday differences
- ✅ More accurate for business workloads
- ⚠️ Needs 30+ days of data

#### Holt-Winters (Advanced)
Triple exponential smoothing with trend and seasonality.
- ✅ Handles acceleration/deceleration
- ✅ Detects seasonal patterns automatically
- ⚠️ Computationally intensive

#### Ensemble (Multi-Model)
Combines all methods with intelligent weighting.
- ✅ Most accurate predictions
- ✅ Self-adjusting based on confidence
- ⚠️ Slower computation

</details>

### Status Determination

Filesystems are marked based on **both** usage and time:

```
Critical (🔴) = Usage ≥ 90% OR ≤ 15 days until full
Warning  (🟡) = Usage ≥ 80% OR ≤ 30 days until full  
OK       (🟢) = Everything else
```

---

## ⚙️ Configuration

### Exclude Mount Points

Edit `StorageAnalytics.php` (around line 180):

```php
$exclude_mounts = [
    '/tmp',
    '/var/tmp',
    '/dev/shm',
    'P:',           // Windows P: drive
    '/mnt/backup'   // Backup mount
];
```

### Customize Defaults

Modify default values in `StorageAnalytics.php` (lines 40-47):

```php
'time_range'         => 30,        // Days of analysis
'prediction_method'  => 'seasonal', // Algorithm
'warning_threshold'  => 80,        // Warning %
'critical_threshold' => 90,        // Critical %
'refresh'            => 60,        // Auto-refresh seconds
```

### Required Zabbix Items

The module uses standard Zabbix filesystem items:

```
vfs.fs.size[<mount>,total]  # Total capacity (bytes)
vfs.fs.size[<mount>,pused]  # Used percentage
vfs.fs.size[<mount>,used]   # Used space (bytes) - optional
```

These are automatically created by:
- **Linux**: Template OS Linux → Template Module Linux filesystems
- **Windows**: Template OS Windows → Template Module Windows filesystems

---

## 🐛 Troubleshooting

<details>
<summary><b>Module not appearing in menu</b></summary>

**Check:**
1. Module is enabled: Administration → Modules
2. File permissions: `ls -la /usr/share/zabbix/modules/diskanalyser/`
3. PHP errors: `/var/log/apache2/error.log`
4. Clear browser cache

</details>

<details>
<summary><b>No data showing</b></summary>

**Solutions:**
1. Verify items exist: Configuration → Hosts → Items → Search "vfs.fs.size"
2. Check history is collecting: Monitoring → Latest Data
3. Ensure hosts are monitored (not disabled)
4. Remove all filters and try again

</details>

<details>
<summary><b>Predictions showing "No growth"</b></summary>

**Fix:**
1. Increase time range to 30+ days
2. Verify filesystem has history in Zabbix
3. Check items are actively collecting (not disabled)
4. Try "Seasonal" or "Ensemble" method

</details>

<details>
<summary><b>Performance issues with many hosts</b></summary>

**Optimize:**
1. Use host group filters to limit scope
2. Reduce time range (7-30 days)
3. Increase PHP memory: `php.ini → memory_limit = 512M`
4. Increase execution time: `php.ini → max_execution_time = 300`

</details>

<details>
<summary><b>Export not working</b></summary>

**Check:**
1. PHP execution time: `max_execution_time = 300`
2. PHP memory limit: `memory_limit = 512M`
3. Temp directory write permissions
4. Try smaller dataset first

</details>

**More issues?** [Open a GitHub issue](https://github.com/saumyajit/storage_analytics_updated/issues) with:
- Zabbix version
- PHP version
- Error messages from logs
- Steps to reproduce

---

## 🤝 Contributing

Contributions are welcome! Here's how to help:

### Reporting Bugs
1. Check [existing issues](https://github.com/saumyajit/storage_analytics_updated/issues)
2. Create new issue with:
   - Environment details (Zabbix/PHP versions)
   - Error messages and logs
   - Steps to reproduce

### Submitting Pull Requests
1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push branch: `git push origin feature/amazing-feature`
5. Open Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Add comments for complex logic
- Test on Zabbix 6.0+
- Update documentation for new features

---

## 📊 Project Stats

<div align="center">

![GitHub stars](https://img.shields.io/github/stars/saumyajit/storage_analytics_updated?style=social)
![GitHub forks](https://img.shields.io/github/forks/saumyajit/storage_analytics_updated?style=social)
![GitHub issues](https://img.shields.io/github/issues/saumyajit/storage_analytics_updated)
![GitHub pull requests](https://img.shields.io/github/issues-pr/saumyajit/storage_analytics_updated)
![GitHub last commit](https://img.shields.io/github/last-commit/saumyajit/storage_analytics_updated)

</div>

---

## 🗺️ Roadmap

### Version 2.1 (Planned)
- [ ] Alerting integration (trigger Zabbix actions)
- [ ] Historical trend charts
- [ ] Custom report templates
- [ ] REST API endpoint

### Version 2.2 (Under Consideration)
- [ ] Machine learning anomaly detection
- [ ] Multi-tenant view separation
- [ ] Slack/Teams webhook notifications
- [ ] 6-12 month capacity planning

### Version 3.0 (Future)
- [ ] Cloud storage provider integration
- [ ] Mobile companion app
- [ ] SAN/NAS specific modes
- [ ] Advanced capacity modeling

**Have a feature idea?** [Open a discussion](https://github.com/saumyajit/storage_analytics_updated/discussions)!

---

## 📄 License

This project is licensed under the **GNU General Public License v3.0**.

```
Storage Analytics Pro - Advanced Zabbix Storage Monitoring
Copyright (C) 2024 Saumyajit Pramanik

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

Full license text: [LICENSE](LICENSE)

---

## 🙏 Acknowledgments

- **Zabbix Team** - For the excellent monitoring platform
- **Community Contributors** - For bug reports and feature suggestions
- **You!** - For using and supporting this project

---

## 📞 Support

<div align="center">

**Need help?** We're here for you!

[![GitHub Issues](https://img.shields.io/badge/Issues-Open_a_ticket-red?logo=github)](https://github.com/saumyajit/storage_analytics_updated/issues)
[![Discussions](https://img.shields.io/badge/Discussions-Ask_anything-blue?logo=github)](https://github.com/saumyajit/storage_analytics_updated/discussions)
[![Documentation](https://img.shields.io/badge/Docs-Read_the_guide-green?logo=gitbook)](https://github.com/saumyajit/storage_analytics_updated#-documentation)

</div>

### Commercial Support

Need custom features, training, or enterprise support?  
[Contact for commercial inquiries](https://github.com/saumyajit/storage_analytics_updated/issues/new?labels=commercial)

---

<div align="center">

**Made with ❤️ for the Zabbix Community**

⭐ **Star this repo** if it helps you monitor storage better!

[![Star History](https://img.shields.io/github/stars/saumyajit/storage_analytics_updated?style=social)](https://github.com/saumyajit/storage_analytics_updated/stargazers)

[⬆ Back to top](#-storage-analytics-pro---zabbix-module)

</div>
