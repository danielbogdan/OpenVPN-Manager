<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\Util;
use App\OpenVPNManager;

Auth::require();

$pdo     = DB::pdo();
$tenants = $pdo->query("SELECT * FROM tenants ORDER BY id DESC")->fetchAll();
$csrf    = Auth::csrf();

// Refresh sessions for all tenants to get latest connection data
foreach ($tenants as $tenant) {
    try {
        OpenVPNManager::refreshSessions($tenant['id']);
    } catch (\Throwable $e) {
        // Log error but don't break the page
        error_log("Failed to refresh sessions for tenant {$tenant['id']}: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>OpenVPN Admin · Dashboard</title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() + 1 ?>">
  <link rel="stylesheet" href="/assets/client.css?v=<?= time() + 2 ?>">
  <link rel="stylesheet" href="/assets/dashboard.css?v=<?= time() + 5 ?>">
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

<main>
  <!-- Dashboard Overview Cards -->
  <div class="dashboard-overview">
    <div class="overview-card">
      <div class="overview-icon">🏢</div>
      <div class="overview-content">
        <h3><?= count($tenants) ?></h3>
        <p>Total Tenants</p>
      </div>
    </div>
    <div class="overview-card">
      <div class="overview-icon">👥</div>
      <div class="overview-content">
        <h3><?= $pdo->query("SELECT COUNT(*) FROM vpn_users")->fetchColumn() ?></h3>
        <p>Total Users</p>
      </div>
    </div>
    <div class="overview-card">
      <div class="overview-icon">🌐</div>
      <div class="overview-content">
        <h3><?= $pdo->query("SELECT COUNT(DISTINCT geo_country) FROM sessions WHERE geo_country IS NOT NULL")->fetchColumn() ?></h3>
        <p>Countries</p>
      </div>
    </div>
    <div class="overview-card">
      <div class="overview-icon">📊</div>
      <div class="overview-content">
        <h3><?= $pdo->query("SELECT COUNT(*) FROM sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn() ?></h3>
        <p>Active Sessions</p>
      </div>
    </div>
  </div>

  <!-- Top Row - Global Connections and Recent Activity -->
  <div class="dashboard-top-row">
    <!-- Global Connections -->
    <div class="dashboard-top-left">
      <div class="card">
        <div class="card-header">
          <h2>🌍 Global Connections</h2>
          <div class="card-actions">
            <button class="btn btn-secondary" onclick="refreshMap()">
              <span class="btn-icon">🔄</span>
              Refresh
            </button>
          </div>
        </div>

        <div class="card-content">
          <div id="worldMap" class="world-map"></div>
          <div class="map-legend">
            <div class="legend-item">
              <div class="legend-color" style="background: #3b82f6;"></div>
              <span>Recent Connections (7 days)</span>
            </div>
            <div class="legend-item">
              <div class="legend-color" style="background: #10b981;"></div>
              <span>High Activity</span>
            </div>
            <div class="legend-item">
              <div class="legend-color" style="background: #f59e0b;"></div>
              <span>Medium Activity</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="dashboard-top-right">
      <div class="card">
        <div class="card-header">
          <h2>📈 Recent Activity</h2>
        </div>
        <div class="card-content">
          <div class="activity-list">
          <?php
          $recentSessions = $pdo->query("
            SELECT s.*, t.name as tenant_name, vu.username 
            FROM sessions s 
            JOIN tenants t ON s.tenant_id = t.id 
            LEFT JOIN vpn_users vu ON s.user_id = vu.id 
            WHERE s.last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY s.last_seen DESC 
            LIMIT 10
          ")->fetchAll();
          ?>
          
          <?php if (empty($recentSessions)): ?>
            <div class="empty-state">
              <div class="empty-icon">📊</div>
              <p>No recent activity</p>
            </div>
          <?php else: ?>
            <?php foreach ($recentSessions as $session): ?>
              <div class="activity-item">
                <div class="activity-icon">🌐</div>
                <div class="activity-content">
                  <div class="activity-title">
                    <?= htmlspecialchars($session['username'] ?: $session['common_name']) ?>
                    <span class="activity-tenant"><?= htmlspecialchars($session['tenant_name']) ?></span>
                  </div>
                  <div class="activity-details">
                    <?= htmlspecialchars($session['geo_country'] ?: 'Unknown') ?> • 
                    <?= htmlspecialchars($session['geo_city'] ?: 'Unknown City') ?>
                  </div>
                </div>
                <div class="activity-time">
                  <?= date('H:i', strtotime($session['last_seen'])) ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- Tenants Overview Section -->
  <div class="dashboard-tenants-section">
    <div class="card">
      <div class="card-header">
        <h2>🏢 Tenants Overview</h2>
        <button id="manual-refresh-btn" class="btn btn-secondary btn-sm" onclick="window.adminDashboardUpdates.update()">
          <span class="btn-icon">🔄</span>
          Refresh
        </button>
      </div>
      
      <div class="card-content">
        <?php
        // Get tenant summary information
        $tenantStats = $pdo->query("
          SELECT 
            t.id,
            t.name,
            COUNT(DISTINCT vu.id) as vpn_users,
            COUNT(DISTINCT cu.id) as client_users,
            COUNT(DISTINCT s.id) as active_sessions
          FROM tenants t
          LEFT JOIN vpn_users vu ON t.id = vu.tenant_id
          LEFT JOIN client_users cu ON t.id = cu.tenant_id
          LEFT JOIN sessions s ON t.id = s.tenant_id AND s.last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
          GROUP BY t.id, t.name
          ORDER BY t.name
        ")->fetchAll();
        ?>
        
        <div class="tenants-overview-list">
          <?php if (empty($tenantStats)): ?>
            <div class="no-tenants">
              <span class="no-tenants-icon">🏢</span>
              <span class="no-tenants-text">No tenants yet</span>
            </div>
          <?php else: ?>
            <?php foreach ($tenantStats as $tenant): ?>
              <div class="tenant-overview-item" data-tenant-id="<?= $tenant['id'] ?>">
                <div class="tenant-overview-info">
                  <div class="tenant-overview-name"><?= htmlspecialchars($tenant['name']) ?></div>
                  <div class="tenant-overview-stats">
                    <span class="tenant-stat">👥 VPN Profile Users: <?= $tenant['vpn_users'] ?></span>
                    <span class="tenant-stat">👨‍💼 Portal Client Users: <?= $tenant['client_users'] ?></span>
                    <span class="tenant-stat">🟢 Active Sessions: <span class="session-count"><?= $tenant['active_sessions'] ?></span></span>
                  </div>
                </div>
                <div class="tenant-overview-actions">
                  <a href="/tenants.php" class="btn btn-secondary btn-sm">
                    <span class="btn-icon">👁️</span>
                    View Details
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Tenant Form (Hidden by default) -->
  <div id="createTenantModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create New Tenant</h2>
        <button class="modal-close" onclick="hideCreateTenantForm()">&times;</button>
      </div>
      <form method="post" action="/actions/tenant_create.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="form-group">
          <label for="name">Tenant Name:</label>
          <input type="text" id="name" name="name" required placeholder="Enter tenant name">
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" name="nat" value="1" checked>
            Enable NAT
          </label>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="hideCreateTenantForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Tenant</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
// Global map variable for updates
let globalMap = null;
let mapMarkers = [];

// World Map
try {
    const map = L.map('worldMap').setView([20, 0], 2);
    globalMap = map; // Make map globally accessible

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Get real-time connection data and add markers
<?php
$connectionData = $pdo->query("
    SELECT 
        geo_country, 
        geo_city,
        real_address,
        common_name,
        tenant_id,
        COUNT(*) as connection_count, 
        COUNT(DISTINCT tenant_id) as tenant_count
    FROM sessions 
    WHERE geo_country IS NOT NULL 
    AND last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    GROUP BY geo_country, geo_city, real_address
")->fetchAll();

$countryCoordinates = [
    'US' => [39.8283, -98.5795],
    'GB' => [55.3781, -3.4360],
    'DE' => [51.1657, 10.4515],
    'FR' => [46.2276, 2.2137],
    'CA' => [56.1304, -106.3468],
    'AU' => [-25.2744, 133.7751],
    'JP' => [36.2048, 138.2529],
    'CN' => [35.8617, 104.1954],
    'IN' => [20.5937, 78.9629],
    'BR' => [-14.2350, -51.9253],
    'RU' => [61.5240, 105.3188],
    'IT' => [41.8719, 12.5674],
    'ES' => [40.4637, -3.7492],
    'NL' => [52.1326, 5.2913],
    'SE' => [60.1282, 18.6435],
    'NO' => [60.4720, 8.4689],
    'DK' => [56.2639, 9.5018],
    'FI' => [61.9241, 25.7482],
    'PL' => [51.9194, 19.1451],
    'CZ' => [49.8175, 15.4730],
    'HU' => [47.1625, 19.5033],
    'RO' => [45.9432, 24.9668],
    'BG' => [42.7339, 25.4858],
    'GR' => [39.0742, 21.8243],
    'TR' => [38.9637, 35.2433],
    'UA' => [48.3794, 31.1656],
    'BY' => [53.7098, 27.9534],
    'LT' => [55.1694, 23.8813],
    'LV' => [56.8796, 24.6032],
    'EE' => [58.5953, 25.0136],
    'IE' => [53.4129, -8.2439],
    'PT' => [39.3999, -8.2245],
    'CH' => [46.8182, 8.2275],
    'AT' => [47.5162, 14.5501],
    'BE' => [50.5039, 4.4699],
    'LU' => [49.8153, 6.1296],
    'SK' => [48.6690, 19.6990],
    'SI' => [46.1512, 14.9955],
    'HR' => [45.1000, 15.2000],
    'BA' => [43.9159, 17.6791],
    'RS' => [44.0165, 21.0059],
    'ME' => [42.7087, 19.3744],
    'MK' => [41.6086, 21.7453],
    'AL' => [41.1533, 20.1683],
    'XK' => [42.6026, 20.9030],
    'MD' => [47.4116, 28.3699],
    'MX' => [23.6345, -102.5528],
    'AR' => [-38.4161, -63.6167],
    'CL' => [-35.6751, -71.5430],
    'CO' => [4.5709, -74.2973],
    'PE' => [-9.1900, -75.0152],
    'VE' => [6.4238, -66.5897],
    'EC' => [-1.8312, -78.1834],
    'UY' => [-32.5228, -55.7658],
    'PY' => [-23.4425, -58.4438],
    'BO' => [-16.2902, -63.5887],
    'GY' => [4.8604, -58.9302],
    'SR' => [3.9193, -56.0278],
    'GF' => [3.9339, -53.1258],
    'ZA' => [-30.5595, 22.9375],
    'EG' => [26.0975, 30.0444],
    'NG' => [9.0820, 8.6753],
    'KE' => [-0.0236, 37.9062],
    'GH' => [7.9465, -1.0232],
    'MA' => [31.6295, -7.9811],
    'TN' => [33.8869, 9.5375],
    'DZ' => [28.0339, 1.6596],
    'LY' => [26.3351, 17.2283],
    'SD' => [12.8628, 30.2176],
    'ET' => [9.1450, 40.4897],
    'UG' => [1.3733, 32.2903],
    'TZ' => [-6.3690, 34.8888],
    'ZW' => [-19.0154, 29.1549],
    'ZM' => [-13.1339, 27.8493],
    'BW' => [-22.3285, 24.6849],
    'NA' => [-22.9576, 18.4904],
    'SZ' => [-26.5225, 31.4659],
    'LS' => [-29.6100, 28.2336],
    'MW' => [-13.2543, 34.3015],
    'MZ' => [-18.6657, 35.5296],
    'MG' => [-18.7669, 46.8691],
    'MU' => [-20.3484, 57.5522],
    'SC' => [-4.6796, 55.4920],
    'KM' => [-11.8750, 43.8722],
    'DJ' => [11.8251, 42.5903],
    'SO' => [5.1521, 46.1996],
    'ER' => [15.1794, 39.7823],
    'SS' => [6.8770, 31.3070],
    'CF' => [6.6111, 20.9394],
    'TD' => [15.4542, 18.7322],
    'NE' => [17.6078, 8.0817],
    'ML' => [17.5707, -3.9962],
    'BF' => [12.2383, -1.5616],
    'CI' => [7.5400, -5.5471],
    'LR' => [6.4281, -9.4295],
    'SL' => [8.4606, -11.7799],
    'GN' => [9.6412, -10.9408],
    'GW' => [11.8037, -15.1804],
    'GM' => [13.4432, -15.3101],
    'SN' => [14.4974, -14.4524],
    'MR' => [21.0079, -10.9408],
    'CV' => [16.0021, -24.0132],
    'ST' => [0.1864, 6.6131],
    'GQ' => [1.6508, 10.2679],
    'GA' => [-0.8037, 11.6094],
    'CG' => [-0.2280, 15.8277],
    'CD' => [-4.0383, 21.7587],
    'AO' => [-11.2027, 17.8739],
    'CM' => [7.3697, 12.3547],
    'TD' => [15.4542, 18.7322],
    'NE' => [17.6078, 8.0817],
    'ML' => [17.5707, -3.9962],
    'BF' => [12.2383, -1.5616],
    'CI' => [7.5400, -5.5471],
    'LR' => [6.4281, -9.4295],
    'SL' => [8.4606, -11.7799],
    'GN' => [9.6412, -10.9408],
    'GW' => [11.8037, -15.1804],
    'GM' => [13.4432, -15.3101],
    'SN' => [14.4974, -14.4524],
    'MR' => [21.0079, -10.9408],
    'CV' => [16.0021, -24.0132],
    'ST' => [0.1864, 6.6131],
    'GQ' => [1.6508, 10.2679],
    'GA' => [-0.8037, 11.6094],
    'CG' => [-0.2280, 15.8277],
    'CD' => [-4.0383, 21.7587],
    'AO' => [-11.2027, 17.8739],
    'CM' => [7.3697, 12.3547]
];

// Country name to country code mapping
$countryNameToCode = [
    'Romania' => 'RO',
    'United States' => 'US',
    'Germany' => 'DE',
    'France' => 'FR',
    'United Kingdom' => 'GB',
    'Italy' => 'IT',
    'Spain' => 'ES',
    'Netherlands' => 'NL',
    'Poland' => 'PL',
    'Czech Republic' => 'CZ',
    'Hungary' => 'HU',
    'Bulgaria' => 'BG',
    'Greece' => 'GR',
    'Portugal' => 'PT',
    'Belgium' => 'BE',
    'Austria' => 'AT',
    'Switzerland' => 'CH',
    'Sweden' => 'SE',
    'Norway' => 'NO',
    'Denmark' => 'DK',
    'Finland' => 'FI',
    'Canada' => 'CA',
    'Australia' => 'AU',
    'Japan' => 'JP',
    'South Korea' => 'KR',
    'China' => 'CN',
    'India' => 'IN',
    'Brazil' => 'BR',
    'Mexico' => 'MX',
    'Argentina' => 'AR',
    'Chile' => 'CL',
    'Colombia' => 'CO',
    'Peru' => 'PE',
    'Venezuela' => 'VE',
    'Ecuador' => 'EC',
    'Bolivia' => 'BO',
    'Paraguay' => 'PY',
    'Uruguay' => 'UY',
    'Russia' => 'RU',
    'Ukraine' => 'UA',
    'Belarus' => 'BY',
    'Moldova' => 'MD',
    'Turkey' => 'TR',
    'Israel' => 'IL',
    'Saudi Arabia' => 'SA',
    'United Arab Emirates' => 'AE',
    'Egypt' => 'EG',
    'South Africa' => 'ZA',
    'Nigeria' => 'NG',
    'Kenya' => 'KE',
    'Morocco' => 'MA',
    'Algeria' => 'DZ',
    'Tunisia' => 'TN',
    'Libya' => 'LY',
    'Sudan' => 'SD',
    'Ethiopia' => 'ET',
    'Ghana' => 'GH',
    'Ivory Coast' => 'CI',
    'Senegal' => 'SN',
    'Mali' => 'ML',
    'Burkina Faso' => 'BF',
    'Niger' => 'NE',
    'Chad' => 'TD',
    'Cameroon' => 'CM',
    'Central African Republic' => 'CF',
    'Democratic Republic of the Congo' => 'CD',
    'Republic of the Congo' => 'CG',
    'Gabon' => 'GA',
    'Equatorial Guinea' => 'GQ',
    'Sao Tome and Principe' => 'ST',
    'Angola' => 'AO'
];

// Make country coordinates and mapping globally accessible
echo "window.countryCoordinates = " . json_encode($countryCoordinates) . ";\n";
echo "window.countryNameToCode = " . json_encode($countryNameToCode) . ";\n";

// Wait for map to be fully loaded
echo "map.whenReady(function() {\n";

if (!empty($connectionData)) {
    echo "    // Add real-time connection markers to map\n";
    $markerGroups = [];
    
    foreach ($connectionData as $data) {
        $countryCode = $data['geo_country'];
        $city = $data['geo_city'] ?: 'Unknown City';
        $realAddress = $data['real_address'];
        $commonName = $data['common_name'];
        $tenantId = $data['tenant_id'];
        $connectionCount = $data['connection_count'];
        
        if (isset($countryCoordinates[$countryCode])) {
            $coords = $countryCoordinates[$countryCode];
            
            // Add small random offset for multiple connections from same country
            $offsetLat = (rand(-50, 50) / 1000);
            $offsetLng = (rand(-50, 50) / 1000);
            $coords[0] += $offsetLat;
            $coords[1] += $offsetLng;
            
            // Different colors for different tenants
            $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
            $color = $colors[$tenantId % count($colors)];
            
            echo "    L.circleMarker([{$coords[0]}, {$coords[1]}], {
                radius: 8,
                fillColor: '{$color}',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map).bindPopup(`
                <div style='min-width: 200px;'>
                    <strong>🌍 {$countryCode} - {$city}</strong><br>
                    <strong>👤 User:</strong> {$commonName}<br>
                    <strong>🌐 IP:</strong> {$realAddress}<br>
                    <strong>🏢 Tenant:</strong> {$tenantId}<br>
                    <strong>📊 Status:</strong> <span style='color: #10b981;'>● Active</span>
                </div>
            `);\n";
            
            // Group markers by country for summary
            if (!isset($markerGroups[$countryCode])) {
                $markerGroups[$countryCode] = [
                    'count' => 0,
                    'tenants' => [],
                    'coords' => $countryCoordinates[$countryCode]
                ];
            }
            $markerGroups[$countryCode]['count'] += $connectionCount;
            if (!in_array($tenantId, $markerGroups[$countryCode]['tenants'])) {
                $markerGroups[$countryCode]['tenants'][] = $tenantId;
            }
        }
    }
    
    // Add country summary markers
    echo "    // Add country summary markers\n";
    foreach ($markerGroups as $countryCode => $group) {
        $coords = $group['coords'];
        $totalConnections = $group['count'];
        $tenantCount = count($group['tenants']);
        $radius = max(12, min(30, $totalConnections * 3));
        $color = $totalConnections > 5 ? '#10b981' : '#3b82f6';
        
        echo "    L.circleMarker([{$coords[0]}, {$coords[1]}], {
            radius: {$radius},
            fillColor: '{$color}',
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.6
        }).addTo(map).bindPopup(`
            <div style='min-width: 200px;'>
                <strong>🌍 {$countryCode} Summary</strong><br>
                <strong>📊 Total Connections:</strong> {$totalConnections}<br>
                <strong>🏢 Active Tenants:</strong> {$tenantCount}<br>
                <strong>📍 Locations:</strong> " . count($connectionData) . " connection points
            </div>
        `);\n";
    }
} else {
    echo "    // No initial connections: show clean global view\n";
    echo "    map.setView([20, 0], 2);\n";
}

echo "});\n";
?>
} catch (error) {
    console.error('Map initialization error:', error);
}


function refreshMap() {
    location.reload();
}

// Function to update map with real-time data
function updateGlobalMap() {
    if (!globalMap) return;
    
    // Clear existing markers
    mapMarkers.forEach(marker => globalMap.removeLayer(marker));
    mapMarkers = [];
    
    // Fetch real-time connection data
    console.log('🔄 Starting global map update...');
    fetch('/actions/get_global_sessions.php', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-cache'
    })
    .then(response => {
        console.log('📡 Global map response received:', response.status, response.statusText);
        return response.json();
    })
    .then(data => {
        console.log('📊 Global map data received:', data);
        if (data.success && data.sessions.length > 0) {
            
            // Add markers for each connection
            const markerGroups = {};
            
            // Collect all valid coordinates for dynamic bounds
            const validSessions = [];
            const allCoords = [];
            
            data.sessions.forEach(session => {
                const countryName = session.geo_country;
                const city = session.geo_city || 'Unknown City';
                const realAddress = session.real_address;
                const commonName = session.common_name;
                const tenantId = session.tenant_id;
                
                // Use actual coordinates if available, otherwise fall back to country center
                let coords;
                if (session.geo_lat && session.geo_lon) {
                    // Use precise coordinates from GeoIP
                    coords = [parseFloat(session.geo_lat), parseFloat(session.geo_lon)];
                    console.log(`📍 Using precise coordinates for ${commonName}: [${coords[0]}, ${coords[1]}]`);
                } else {
                    // Fall back to country center coordinates
                    const countryCode = window.countryNameToCode && window.countryNameToCode[countryName] ? window.countryNameToCode[countryName] : countryName;
                    
                    if (countryCode && window.countryCoordinates && window.countryCoordinates[countryCode]) {
                        coords = [...window.countryCoordinates[countryCode]];
                        
                        // Add small random offset for multiple connections from same country
                        const offsetLat = (Math.random() - 0.5) * 0.1;
                        const offsetLng = (Math.random() - 0.5) * 0.1;
                        coords[0] += offsetLat;
                        coords[1] += offsetLng;
                        console.log(`📍 Using country center coordinates for ${commonName}: [${coords[0]}, ${coords[1]}]`);
                    } else {
                        console.log(`⚠️ No coordinates available for ${commonName} in ${countryName}`);
                        return; // Skip this session if no coordinates
                    }
                }
                
                if (coords) {
                    validSessions.push({...session, coords});
                    allCoords.push(coords);
                }
            });
            
            // Dynamic map bounds and zoom based on connections
            if (allCoords.length > 0) {
                if (allCoords.length === 1) {
                    // Single connection: zoom in close
                    globalMap.setView(allCoords[0], 10);
                    console.log(`🎯 Single connection: zooming to level 10`);
                } else {
                    // Multiple connections: fit bounds with padding
                    const group = new L.featureGroup();
                    allCoords.forEach(coord => {
                        group.addLayer(L.marker(coord));
                    });
                    globalMap.fitBounds(group.getBounds().pad(0.1));
                    console.log(`🎯 Multiple connections: fitting bounds for ${allCoords.length} locations`);
                }
            } else {
                // No connections: keep global view
                globalMap.setView([20, 0], 2);
                console.log(`🎯 No connections: keeping global view`);
            }
            
            // Add markers for each valid session
            validSessions.forEach(session => {
                const { coords, geo_country: countryName, geo_city: city, real_address: realAddress, common_name: commonName, tenant_id: tenantId } = session;
                
                // Different colors for different tenants
                const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                const color = colors[tenantId % colors.length];
                
                const marker = L.circleMarker(coords, {
                    radius: 8,
                    fillColor: color,
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).bindPopup(`
                    <div style='min-width: 200px;'>
                        <strong>🌍 ${countryName} - ${city}</strong><br>
                        <strong>👤 User:</strong> ${commonName}<br>
                        <strong>🌐 IP:</strong> ${realAddress}<br>
                        <strong>🏢 Tenant:</strong> ${tenantId}<br>
                        <strong>📊 Status:</strong> <span style='color: #10b981;'>● Active</span>
                    </div>
                `);
                
                globalMap.addLayer(marker);
                mapMarkers.push(marker);
                
                // Group for summary (use country name as key)
                if (!markerGroups[countryName]) {
                    markerGroups[countryName] = {
                        count: 0,
                        tenants: new Set(),
                        coords: coords // Use the actual coordinates for this session
                    };
                }
                markerGroups[countryName].count++;
                markerGroups[countryName].tenants.add(tenantId);
            });
            
            // Add country summary markers
            Object.keys(markerGroups).forEach(countryName => {
                const group = markerGroups[countryName];
                const coords = group.coords;
                const totalConnections = group.count;
                const tenantCount = group.tenants.size;
                const radius = Math.max(12, Math.min(30, totalConnections * 3));
                const color = totalConnections > 5 ? '#10b981' : '#3b82f6';
                
                const summaryMarker = L.circleMarker(coords, {
                    radius: radius,
                    fillColor: color,
                    color: '#fff',
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 0.6
                }).bindPopup(`
                    <div style='min-width: 200px;'>
                        <strong>🌍 ${countryName} Summary</strong><br>
                        <strong>📊 Total Connections:</strong> ${totalConnections}<br>
                        <strong>🏢 Active Tenants:</strong> ${tenantCount}<br>
                        <strong>📍 Locations:</strong> ${data.sessions.length} connection points
                    </div>
                `);
                
                globalMap.addLayer(summaryMarker);
                mapMarkers.push(summaryMarker);
            });
        } else {
            // No connections: keep global view without overlay
            globalMap.setView([20, 0], 2);
            console.log('⚠️ No active sessions for global map - showing clean global view');
        }
        console.log('✅ Global map update completed');
    })
    .catch(error => {
        console.error('💥 Error updating global map:', error);
    });
}

// Map is now always clean - no overlay needed

function showCreateTenantForm() {
    const modal = document.getElementById('createTenantModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function hideCreateTenantForm() {
    document.getElementById('createTenantModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('createTenantModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<!-- Live Clock JavaScript -->
<script>
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

// Update clock immediately and then every second
updateLiveClock();
setInterval(updateLiveClock, 1000);
</script>

<!-- Admin Dashboard Auto-Updates -->
<script src="/assets/admin-dashboard-updates.js?v=<?= time() ?>"></script>
</body>
</html>
