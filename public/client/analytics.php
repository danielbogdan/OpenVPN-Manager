<?php
require_once __DIR__.'/../../config.php';

use App\ClientAuth;
use App\Analytics;
use App\TrafficMonitor;

// Check if client is logged in
if (!ClientAuth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$tenantId = ClientAuth::getCurrentTenantId();
$user = ClientAuth::getCurrentUser();
$hours = (int)($_GET['hours'] ?? 72);

try {
    $data = Analytics::getDashboardData($tenantId, $hours);
} catch (Exception $e) {
    http_response_code(404);
    echo "Error: " . $e->getMessage();
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Analytics - <?= htmlspecialchars($data['tenant']['name']) ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/analytics.css?v=<?= time() + 2 ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() + 3 ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <!-- Client Header -->
    <div class="client-header">
        <div class="client-header-content">
            <div class="client-title">
                <h1><?= htmlspecialchars($data['tenant']['name']) ?> - Analytics</h1>
                <div class="client-meta">
                    <span class="client-welcome">Welcome, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>!</span>
                    <span class="client-tenant">Analytics Portal</span>
                    <div class="current-time">
                      <span id="live-clock"><?php
                        $currentTime = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
                        echo $currentTime->format('M j, Y H:i:s');
                      ?></span>
                      <span class="timezone">(Local Time)</span>
                    </div>
                </div>
            </div>
            <div class="client-actions">
                <a href="/client/dashboard.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🏠</span>
                    Dashboard
                </a>
                <a href="/client/users.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">👥</span>
                    Users
                </a>
                <a href="/client/settings.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">⚙️</span>
                    Settings
                </a>
                <a href="/client/logout.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🚪</span>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <div class="client-container">
        
        <!-- Analytics-specific controls -->
        <div class="analytics-controls">
            <div class="analytics-breadcrumb">
                <a href="/client/dashboard.php" class="breadcrumb-link">
                    <span class="breadcrumb-icon">🏠</span>
                    Dashboard
                </a>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current">
                    <span class="breadcrumb-icon">📊</span>
                    Analytics
                </span>
            </div>
            
            <div class="time-range-selector">
                <label for="timeRange" class="time-range-label">Time Range:</label>
                <select id="timeRange" onchange="changeTimeRange()" class="time-range-select">
                    <option value="24" <?= $hours === 24 ? 'selected' : '' ?>>Last 24 hours</option>
                    <option value="72" <?= $hours === 72 ? 'selected' : '' ?>>Last 72 hours</option>
                    <option value="168" <?= $hours === 168 ? 'selected' : '' ?>>Last 7 days</option>
                </select>
            </div>
        </div>

        <main>
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon">👥</div>
                    <div class="summary-content">
                        <h3><?= $data['summary']['active_users'] ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon">📊</div>
                    <div class="summary-content">
                        <h3><?= Analytics::formatBytes($data['summary']['total_traffic']) ?></h3>
                        <p>Total Traffic</p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon">⬇️</div>
                    <div class="summary-content">
                        <h3><?= Analytics::formatBytes($data['summary']['total_in']) ?></h3>
                        <p>Downloaded</p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon">⬆️</div>
                    <div class="summary-content">
                        <h3><?= Analytics::formatBytes($data['summary']['total_out']) ?></h3>
                        <p>Uploaded</p>
                    </div>
                </div>
            </div>

            <!-- Traffic Chart -->
            <div class="card">
                <h2>Traffic Over Time</h2>
                <canvas id="trafficChart" width="400" height="200"></canvas>
            </div>

            <div class="dashboard-grid">
                <!-- Application Breakdown -->
                <div class="card">
                    <h2>Application Usage</h2>
                    <canvas id="appChart" width="300" height="300"></canvas>
                </div>

                <!-- Geographic Distribution -->
                <div class="card">
                    <h2>Geographic Distribution</h2>
                    <div id="worldMap" style="height: 300px;"></div>
                </div>
            </div>

            <!-- Top Destinations -->
            <div class="card">
                <h2>Top Destinations</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Destination</th>
                                <th>Application</th>
                                <th>Country</th>
                                <th>Traffic</th>
                                <th>Connections</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['top_destinations'] as $dest): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($dest['domain'] ?: $dest['destination_ip']) ?>
                                </td>
                                <td>
                                    <?php 
                                    $appInfo = Analytics::getApplicationDisplayInfo($dest['application_type']);
                                    echo $appInfo['icon'] . ' ' . $appInfo['name'];
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($dest['country_code'] ?: 'Unknown') ?></td>
                                <td><?= Analytics::formatBytes($dest['total_bytes']) ?></td>
                                <td><?= number_format($dest['connection_count']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Connection Trends -->
            <div class="card">
                <h2>Active Connections Trend</h2>
                <canvas id="connectionsChart" width="400" height="200"></canvas>
            </div>
        </main>
    </div>

    <script>
        // Traffic Chart
        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        const trafficData = <?= json_encode($data['hourly_data']) ?>;
        
        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: trafficData.map(d => d.hour),
                datasets: [{
                    label: 'Download',
                    data: trafficData.map(d => d.bytes_in),
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Upload',
                    data: trafficData.map(d => d.bytes_out),
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatBytes(value);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatBytes(context.parsed.y);
                            }
                        }
                    }
                }
            }
        });

        // Application Chart
        const appCtx = document.getElementById('appChart').getContext('2d');
        const appData = <?= json_encode($data['application_breakdown']) ?>;
        
        new Chart(appCtx, {
            type: 'doughnut',
            data: {
                labels: appData.map(d => {
                    const info = <?= json_encode(array_map([Analytics::class, 'getApplicationDisplayInfo'], array_column($data['application_breakdown'], 'application_type'))) ?>;
                    return info[appData.indexOf(d)].name;
                }),
                datasets: [{
                    data: appData.map(d => d.total_bytes),
                    backgroundColor: appData.map(d => {
                        const info = <?= json_encode(array_map([Analytics::class, 'getApplicationDisplayInfo'], array_column($data['application_breakdown'], 'application_type'))) ?>;
                        return info[appData.indexOf(d)].color;
                    })
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + formatBytes(context.parsed) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Connections Chart
        const connectionsCtx = document.getElementById('connectionsChart').getContext('2d');
        const connectionsData = <?= json_encode($data['connection_trends']) ?>;
        
        new Chart(connectionsCtx, {
            type: 'line',
            data: {
                labels: connectionsData.map(d => d.hour),
                datasets: [{
                    label: 'Active Connections',
                    data: connectionsData.map(d => d.active_connections),
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // World Map
        const geoData = <?= json_encode($data['geographic_distribution']) ?>;
        const map = L.map('worldMap').setView([20, 0], 2);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add markers for countries with traffic
        geoData.forEach(function(country) {
            // This is a simplified approach - in production you'd want to use proper country coordinates
            const coords = getCountryCoordinates(country.country_code);
            if (coords) {
                const marker = L.circleMarker(coords, {
                    radius: Math.max(5, Math.min(20, country.total_bytes / 1000000000)),
                    fillColor: '#3B82F6',
                    color: '#1E40AF',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.6
                }).addTo(map);
                
                marker.bindPopup(`
                    <strong>${country.country_code}</strong><br>
                    Traffic: ${formatBytes(country.total_bytes)}<br>
                    Users: ${country.unique_users}
                `);
            }
        });

        function changeTimeRange() {
            const hours = document.getElementById('timeRange').value;
            window.location.href = `?hours=${hours}`;
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function getCountryCoordinates(countryCode) {
            // Simplified country coordinates - in production use a proper geocoding service
            const coords = {
                'US': [39.8283, -98.5795],
                'GB': [55.3781, -3.4360],
                'DE': [51.1657, 10.4515],
                'FR': [46.2276, 2.2137],
                'CA': [56.1304, -106.3468],
                'AU': [-25.2744, 133.7751],
                'JP': [36.2048, 138.2529],
                'CN': [35.8617, 104.1954],
                'IN': [20.5937, 78.9629],
                'BR': [-14.2350, -51.9253]
            };
            return coords[countryCode] || null;
        }

        // Live Clock Functionality
        function updateLiveClock() {
            const now = new Date();
            const options = {
                timeZone: 'Europe/Bucharest',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            };
            
            const formatter = new Intl.DateTimeFormat('en-US', options);
            const formattedTime = formatter.format(now);
            
            const clockElement = document.getElementById('live-clock');
            if (clockElement) {
                clockElement.textContent = formattedTime;
            }
        }

        updateLiveClock();
        setInterval(updateLiveClock, 1000);
    </script>
    
    <!-- Client Activity Tracker -->
    <script src="/assets/client-activity.js?v=<?= time() ?>"></script>
    </div>
</body>
</html>
