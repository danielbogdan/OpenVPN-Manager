<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\Analytics;
use App\TrafficMonitor;

Auth::require();

$tenantId = (int)($_GET['id'] ?? 0);
$hours = (int)($_GET['hours'] ?? 72);

if (!$tenantId) {
    http_response_code(400);
    echo "Tenant ID required";
    exit;
}

try {
    $data = Analytics::getDashboardData($tenantId, $hours);
} catch (Exception $e) {
    http_response_code(404);
    echo "Error: " . $e->getMessage();
    exit;
}

$csrf = Auth::csrf();
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
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() + 1 ?>">
    <link rel="stylesheet" href="/assets/dashboard.css?v=<?= time() + 5 ?>">
    <link rel="stylesheet" href="/assets/analytics.css?v=<?= time() + 2 ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <!-- Admin Header -->
    <div class="client-header">
        <div class="client-header-content">
            <div class="client-title">
                <h1>OpenVPN Admin</h1>
                <div class="client-meta">
                    <span class="client-role">Administrator</span>
                    <span class="client-tenant">Admin Panel</span>
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
                <a href="/dashboard.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🏠</span>
                    Dashboard
                </a>
                <a href="/tenants.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🏢</span>
                    Manage Tenants
                </a>
                <a href="/email_config.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">📧</span>
                    Email Config
                </a>
                <a href="/admin_settings.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">⚙️</span>
                    Settings
                </a>
                <a href="/logout.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🚪</span>
                    Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Time range selector -->
    <div class="time-range-container">
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
                                const total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
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

        // Country coordinates (same as dashboard)
        const countryCoordinates = {
            'US': [39.8283, -98.5795], 'CA': [56.1304, -106.3468], 'MX': [23.6345, -102.5528],
            'BR': [-14.2350, -51.9253], 'AR': [-38.4161, -63.6167], 'CL': [-35.6751, -71.5430],
            'CO': [4.5709, -74.2973], 'PE': [-9.1900, -75.0152], 'VE': [6.4238, -66.5897],
            'EC': [-1.8312, -78.1834], 'BO': [-16.2902, -63.5887], 'PY': [-23.4425, -58.4438],
            'UY': [-32.5228, -55.7658], 'GB': [55.3781, -3.4360], 'FR': [46.2276, 2.2137],
            'DE': [51.1657, 10.4515], 'IT': [41.8719, 12.5674], 'ES': [40.4637, -3.7492],
            'NL': [52.1326, 5.2913], 'BE': [50.5039, 4.4699], 'CH': [46.8182, 8.2275],
            'AT': [47.5162, 14.5501], 'PL': [51.9194, 19.1451], 'CZ': [49.8175, 15.4730],
            'HU': [47.1625, 19.5033], 'RO': [45.9432, 24.9668], 'BG': [42.7339, 25.4858],
            'GR': [39.0742, 21.8243], 'PT': [39.3999, -8.2245], 'SE': [60.1282, 18.6435],
            'NO': [60.4720, 8.4689], 'DK': [56.2639, 9.5018], 'FI': [61.9241, 25.7482],
            'RU': [61.5240, 105.3188], 'UA': [48.3794, 31.1656], 'BY': [53.7098, 27.9534],
            'MD': [47.4116, 28.3699], 'TR': [38.9637, 35.2433], 'IL': [31.0461, 34.8516],
            'SA': [23.8859, 45.0792], 'AE': [23.4241, 53.8478], 'EG': [26.0975, 30.0444],
            'ZA': [-30.5595, 22.9375], 'NG': [9.0820, 8.6753], 'KE': [-0.0236, 37.9062],
            'MA': [31.6295, -7.9811], 'DZ': [28.0339, 1.6596], 'TN': [33.8869, 9.5375],
            'LY': [26.3351, 17.2283], 'SD': [12.8628, 30.2176], 'ET': [9.1450, 40.4897],
            'GH': [7.9465, -1.0232], 'CI': [7.5400, -5.5471], 'SN': [14.4974, -14.4524],
            'ML': [17.5707, -3.9962], 'BF': [12.2383, -1.5616], 'NE': [17.6078, 8.0817],
            'TD': [15.4542, 18.7322], 'CM': [7.3697, 12.3547], 'CF': [6.6111, 20.9394],
            'CD': [-4.0383, 21.7587], 'AO': [-11.2027, 17.8739], 'CN': [35.8617, 104.1954],
            'JP': [36.2048, 138.2529], 'KR': [35.9078, 127.7669], 'IN': [20.5937, 78.9629],
            'AU': [-25.2744, 133.7751], 'NZ': [-40.9006, 174.8860], 'TH': [15.8700, 100.9925],
            'VN': [14.0583, 108.2772], 'MY': [4.2105, 101.9758], 'SG': [1.3521, 103.8198],
            'ID': [-0.7893, 113.9213], 'PH': [12.8797, 121.7740], 'MM': [21.9162, 95.9560],
            'BD': [23.6850, 90.3563], 'LK': [7.8731, 80.7718], 'PK': [30.3753, 69.3451],
            'AF': [33.9391, 67.7100], 'IR': [32.4279, 53.6880], 'IQ': [33.2232, 43.6793],
            'SY': [34.8021, 38.9968], 'LB': [33.8547, 35.8623], 'JO': [30.5852, 36.2384],
            'KW': [29.3117, 47.4818], 'QA': [25.3548, 51.1839], 'BH': [25.9304, 50.6378],
            'OM': [21.4735, 55.9754], 'YE': [15.5527, 48.5164]
        };

        // Country name to country code mapping
        const countryNameToCode = {
            'Romania': 'RO', 'United States': 'US', 'Germany': 'DE', 'France': 'FR',
            'United Kingdom': 'GB', 'Italy': 'IT', 'Spain': 'ES', 'Netherlands': 'NL',
            'Poland': 'PL', 'Czech Republic': 'CZ', 'Hungary': 'HU', 'Bulgaria': 'BG',
            'Greece': 'GR', 'Portugal': 'PT', 'Belgium': 'BE', 'Austria': 'AT',
            'Switzerland': 'CH', 'Sweden': 'SE', 'Norway': 'NO', 'Denmark': 'DK',
            'Finland': 'FI', 'Canada': 'CA', 'Australia': 'AU', 'Japan': 'JP',
            'South Korea': 'KR', 'China': 'CN', 'India': 'IN', 'Brazil': 'BR',
            'Mexico': 'MX', 'Argentina': 'AR', 'Chile': 'CL', 'Colombia': 'CO',
            'Peru': 'PE', 'Venezuela': 'VE', 'Ecuador': 'EC', 'Bolivia': 'BO',
            'Paraguay': 'PY', 'Uruguay': 'UY', 'Russia': 'RU', 'Ukraine': 'UA',
            'Belarus': 'BY', 'Moldova': 'MD', 'Turkey': 'TR', 'Israel': 'IL',
            'Saudi Arabia': 'SA', 'United Arab Emirates': 'AE', 'Egypt': 'EG',
            'South Africa': 'ZA', 'Nigeria': 'NG', 'Kenya': 'KE', 'Morocco': 'MA',
            'Algeria': 'DZ', 'Tunisia': 'TN', 'Libya': 'LY', 'Sudan': 'SD',
            'Ethiopia': 'ET', 'Ghana': 'GH', 'Ivory Coast': 'CI', 'Senegal': 'SN',
            'Mali': 'ML', 'Burkina Faso': 'BF', 'Niger': 'NE', 'Chad': 'TD',
            'Cameroon': 'CM', 'Central African Republic': 'CF', 'Democratic Republic of the Congo': 'CD',
            'Republic of the Congo': 'CG', 'Gabon': 'GA', 'Equatorial Guinea': 'GQ',
            'Sao Tome and Principe': 'ST', 'Angola': 'AO'
        };

        // Add markers for countries with traffic
        if (geoData && geoData.length > 0) {
            geoData.forEach(function(country) {
                // Handle both country_code and country_name formats
                let countryCode = country.country_code || country.country_name;
                
                // Convert country name to country code if needed
                if (countryNameToCode[countryCode]) {
                    countryCode = countryNameToCode[countryCode];
                }
                
                const coords = countryCoordinates[countryCode];
                if (coords) {
                    const radius = Math.max(8, Math.min(25, Math.sqrt(country.total_bytes / 1000000)));
                    const marker = L.circleMarker(coords, {
                        radius: radius,
                        fillColor: '#3B82F6',
                        color: '#1E40AF',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.6
                    }).addTo(map);
                    
                    marker.bindPopup(`
                        <strong>${country.country_name || country.country_code}</strong><br>
                        Traffic: ${formatBytes(country.total_bytes)}<br>
                        Users: ${country.unique_users}
                    `);
                } else {
                    console.log('No coordinates found for country:', countryCode);
                }
            });
            
            // Fit map to show all markers
            if (geoData.length > 0) {
                const group = new L.featureGroup();
                geoData.forEach(function(country) {
                    let countryCode = country.country_code || country.country_name;
                    if (countryNameToCode[countryCode]) {
                        countryCode = countryNameToCode[countryCode];
                    }
                    const coords = countryCoordinates[countryCode];
                    if (coords) {
                        group.addLayer(L.marker(coords));
                    }
                });
                if (group.getLayers().length > 0) {
                    map.fitBounds(group.getBounds().pad(0.1));
                }
            }
        } else {
            console.log('No geographic data available');
        }

        function changeTimeRange() {
            const hours = document.getElementById('timeRange').value;
            window.location.href = `?id=<?= $tenantId ?>&hours=${hours}`;
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

        // Live session updates
        function updateSessionData() {
            const tenantId = <?= $tenantId ?>;
            
            fetch(`/actions/get_live_sessions.php?tenant_id=${tenantId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatsCards(data.stats);
                        updateConnectionsList(data.sessions);
                        updateTrafficChart(data.sessions);
                        updateGeoMap(data.sessions);
                    }
                })
                .catch(error => {
                    console.error('Error updating session data:', error);
                });
        }

        function updateStatsCards(stats) {
            // Update Active Users
            const activeUsersEl = document.querySelector('.stat-card:nth-child(1) .stat-value');
            if (activeUsersEl) activeUsersEl.textContent = stats.active_users;

            // Update Total Traffic
            const totalTrafficEl = document.querySelector('.stat-card:nth-child(2) .stat-value');
            if (totalTrafficEl) totalTrafficEl.textContent = formatBytes(stats.total_traffic);

            // Update Downloaded
            const downloadedEl = document.querySelector('.stat-card:nth-child(3) .stat-value');
            if (downloadedEl) downloadedEl.textContent = formatBytes(stats.downloaded);

            // Update Uploaded
            const uploadedEl = document.querySelector('.stat-card:nth-child(4) .stat-value');
            if (uploadedEl) uploadedEl.textContent = formatBytes(stats.uploaded);
        }

        function updateConnectionsList(sessions) {
            const connectionsList = document.querySelector('.connections-list');
            if (!connectionsList) return;

            if (sessions.length === 0) {
                connectionsList.innerHTML = '<div class="no-connections">No active connections</div>';
                return;
            }

            connectionsList.innerHTML = sessions.map(session => `
                <div class="connection-item">
                    <div class="connection-info">
                        <span class="connection-name">${session.common_name}</span>
                        <span class="connection-ip">${session.virtual_address || 'N/A'}</span>
                    </div>
                    <div class="connection-location">
                        ${session.geo_country || 'Unknown'} • ${session.geo_city || 'Unknown City'}
                    </div>
                    <div class="connection-time">
                        ${new Date(session.last_seen).toLocaleTimeString()}
                    </div>
                </div>
            `).join('');
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        // Update every 2 seconds for more responsive updates
        updateSessionData();
        setInterval(updateSessionData, 2000);
    </script>
</body>
</html>
