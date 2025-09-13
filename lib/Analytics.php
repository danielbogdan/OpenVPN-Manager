<?php
namespace App;

class Analytics
{
    /**
     * Get comprehensive analytics data for dashboard
     */
    public static function getDashboardData(int $tenantId, int $hours = 72): array
    {
        $pdo = DB::pdo();
        
        // Get basic tenant info
        $tenant = OpenVPNManager::getTenant($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant not found");
        }
        
        // Get active users count
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT COALESCE(user_id, common_name)) as active_users
            FROM sessions 
            WHERE tenant_id = ? AND last_seen >= ?
        ");
        $stmt->execute([$tenantId, $cutoffTime]);
        $activeUsers = $stmt->fetch()['active_users'] ?? 0;
        
        // Get total users count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_users
            FROM vpn_users 
            WHERE tenant_id = ? AND status = 'active'
        ");
        $stmt->execute([$tenantId]);
        $totalUsers = $stmt->fetch()['total_users'] ?? 0;
        
        // Get traffic summary for the period from sessions table
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $stmt = $pdo->prepare("
            SELECT 
                SUM(bytes_received + bytes_sent) as total_traffic,
                SUM(bytes_received) as total_in,
                SUM(bytes_sent) as total_out,
                COUNT(DISTINCT COALESCE(user_id, common_name)) as unique_users
            FROM sessions 
            WHERE tenant_id = ? AND last_seen >= ?
        ");
        $stmt->execute([$tenantId, $cutoffTime]);
        $trafficSummary = $stmt->fetch();
        
        // Get hourly traffic data for charts
        $hourlyData = self::getHourlyTrafficData($tenantId, $hours);
        
        // Get application breakdown (mock data for now)
        $appBreakdown = self::getMockApplicationBreakdown($tenantId, $hours);
        
        // Get geographic distribution
        $geoDistribution = TrafficMonitor::getGeographicDistribution($tenantId, null, $hours);
        
        // Get top destinations
        $topDestinations = TrafficMonitor::getTopDestinations($tenantId, null, $hours, 10);
        
        // Get connection trends (last 24 hours)
        $connectionTrends = self::getConnectionTrends($tenantId, 24);
        
        return [
            'tenant' => $tenant,
            'summary' => [
                'active_users' => (int)$activeUsers,
                'total_users' => (int)$totalUsers,
                'total_traffic' => (int)($trafficSummary['total_traffic'] ?? 0),
                'total_in' => (int)($trafficSummary['total_in'] ?? 0),
                'total_out' => (int)($trafficSummary['total_out'] ?? 0),
                'unique_users' => (int)($trafficSummary['unique_users'] ?? 0),
                'period_hours' => $hours
            ],
            'hourly_data' => $hourlyData,
            'application_breakdown' => $appBreakdown,
            'geographic_distribution' => $geoDistribution,
            'top_destinations' => $topDestinations,
            'connection_trends' => $connectionTrends
        ];
    }
    
    /**
     * Get hourly traffic data formatted for charts
     */
    public static function getHourlyTrafficData(int $tenantId, int $hours = 72): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(last_seen, '%Y-%m-%d %H:00') as hour,
                SUM(bytes_received) as bytes_in,
                SUM(bytes_sent) as bytes_out,
                SUM(bytes_received + bytes_sent) as total_bytes
            FROM sessions 
            WHERE tenant_id = ? AND last_seen >= ?
            GROUP BY DATE_FORMAT(last_seen, '%Y-%m-%d %H:00')
            ORDER BY hour ASC
        ");
        
        $stmt->execute([$tenantId, $cutoffTime]);
        $data = $stmt->fetchAll();
        
        // Fill in missing hours with zero values
        $result = [];
        $startTime = strtotime("-{$hours} hours");
        $endTime = time();
        
        for ($time = $startTime; $time <= $endTime; $time += 3600) {
            $hour = date('Y-m-d H:00', $time);
            $found = false;
            
            foreach ($data as $row) {
                if ($row['hour'] === $hour) {
                    $result[] = [
                        'hour' => $hour,
                        'bytes_in' => (int)$row['bytes_in'],
                        'bytes_out' => (int)$row['bytes_out'],
                        'total_bytes' => (int)$row['total_bytes']
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $result[] = [
                    'hour' => $hour,
                    'bytes_in' => 0,
                    'bytes_out' => 0,
                    'total_bytes' => 0
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get connection trends (active connections over time)
     */
    public static function getConnectionTrends(int $tenantId, int $hours = 24): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(last_seen, '%Y-%m-%d %H:00') as hour,
                COUNT(DISTINCT common_name) as active_connections
            FROM sessions 
            WHERE tenant_id = ? AND last_seen >= ?
            GROUP BY DATE_FORMAT(last_seen, '%Y-%m-%d %H:00')
            ORDER BY hour ASC
        ");
        
        $stmt->execute([$tenantId, $cutoffTime]);
        $data = $stmt->fetchAll();
        
        // Fill in missing hours
        $result = [];
        $startTime = strtotime("-{$hours} hours");
        $endTime = time();
        
        for ($time = $startTime; $time <= $endTime; $time += 3600) {
            $hour = date('Y-m-d H:00', $time);
            $found = false;
            
            foreach ($data as $row) {
                if ($row['hour'] === $hour) {
                    $result[] = [
                        'hour' => $hour,
                        'active_connections' => (int)$row['active_connections']
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $result[] = [
                    'hour' => $hour,
                    'active_connections' => 0
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get user-specific analytics
     */
    public static function getUserAnalytics(int $tenantId, int $userId, int $hours = 72): array
    {
        $pdo = DB::pdo();
        
        // Get user info
        $stmt = $pdo->prepare("
            SELECT vu.*, s.common_name, s.real_address, s.virtual_address, s.last_seen
            FROM vpn_users vu
            LEFT JOIN sessions s ON vu.tenant_id = s.tenant_id AND vu.username = s.common_name
            WHERE vu.id = ? AND vu.tenant_id = ?
        ");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new \RuntimeException("User not found");
        }
        
        // Get user traffic summary from sessions table
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $stmt = $pdo->prepare("
            SELECT 
                SUM(bytes_received + bytes_sent) as total_traffic,
                SUM(bytes_received) as total_in,
                SUM(bytes_sent) as total_out,
                COUNT(*) as connection_count
            FROM sessions 
            WHERE tenant_id = ? AND user_id = ? AND last_seen >= ?
        ");
        $stmt->execute([$tenantId, $userId, $cutoffTime]);
        $trafficSummary = $stmt->fetch();
        
        // Get user application breakdown
        $appBreakdown = TrafficMonitor::getApplicationBreakdown($tenantId, $userId, $hours);
        
        // Get user geographic distribution
        $geoDistribution = TrafficMonitor::getGeographicDistribution($tenantId, $userId, $hours);
        
        // Get user hourly data
        $hourlyData = self::getUserHourlyData($tenantId, $userId, $hours);
        
        return [
            'user' => $user,
            'summary' => [
                'total_traffic' => (int)($trafficSummary['total_traffic'] ?? 0),
                'total_in' => (int)($trafficSummary['total_in'] ?? 0),
                'total_out' => (int)($trafficSummary['total_out'] ?? 0),
                'connection_count' => (int)($trafficSummary['connection_count'] ?? 0),
                'period_hours' => $hours
            ],
            'application_breakdown' => $appBreakdown,
            'geographic_distribution' => $geoDistribution,
            'hourly_data' => $hourlyData
        ];
    }
    
    /**
     * Get user-specific hourly data
     */
    private static function getUserHourlyData(int $tenantId, int $userId, int $hours): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(last_seen, '%Y-%m-%d %H:00') as hour,
                SUM(bytes_received) as bytes_in,
                SUM(bytes_sent) as bytes_out,
                SUM(bytes_received + bytes_sent) as total_bytes
            FROM sessions 
            WHERE tenant_id = ? AND user_id = ? AND last_seen >= ?
            GROUP BY DATE_FORMAT(last_seen, '%Y-%m-%d %H:00')
            ORDER BY hour ASC
        ");
        
        $stmt->execute([$tenantId, $userId, $cutoffTime]);
        $data = $stmt->fetchAll();
        
        // Fill in missing hours with zero values
        $result = [];
        $startTime = strtotime("-{$hours} hours");
        $endTime = time();
        
        for ($time = $startTime; $time <= $endTime; $time += 3600) {
            $hour = date('Y-m-d H:00', $time);
            $found = false;
            
            foreach ($data as $row) {
                if ($row['hour'] === $hour) {
                    $result[] = [
                        'hour' => $hour,
                        'bytes_in' => (int)$row['bytes_in'],
                        'bytes_out' => (int)$row['bytes_out'],
                        'total_bytes' => (int)$row['total_bytes']
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $result[] = [
                    'hour' => $hour,
                    'bytes_in' => 0,
                    'bytes_out' => 0,
                    'total_bytes' => 0
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Format bytes to human readable format
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get application type display name and color
     */
    public static function getApplicationDisplayInfo(string $applicationType): array
    {
        $displayInfo = [
            'search' => ['name' => 'Search', 'color' => '#3B82F6', 'icon' => '🔍'],
            'video' => ['name' => 'Video Streaming', 'color' => '#EF4444', 'icon' => '📺'],
            'social' => ['name' => 'Social Media', 'color' => '#8B5CF6', 'icon' => '👥'],
            'email' => ['name' => 'Email', 'color' => '#10B981', 'icon' => '📧'],
            'streaming' => ['name' => 'Streaming', 'color' => '#F59E0B', 'icon' => '🎬'],
            'messaging' => ['name' => 'Messaging', 'color' => '#06B6D4', 'icon' => '💬'],
            'development' => ['name' => 'Development', 'color' => '#84CC16', 'icon' => '💻'],
            'unknown' => ['name' => 'Unknown', 'color' => '#6B7280', 'icon' => '❓']
        ];
        
        return $displayInfo[$applicationType] ?? $displayInfo['unknown'];
    }
    
    /**
     * Generate mock application breakdown data based on existing traffic
     */
    private static function getMockApplicationBreakdown(int $tenantId, int $hours): array
    {
        $pdo = DB::pdo();
        
        // Get total traffic from sessions
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $stmt = $pdo->prepare("
            SELECT 
                SUM(bytes_received + bytes_sent) as total_traffic,
                COUNT(DISTINCT COALESCE(user_id, common_name)) as unique_users
            FROM sessions 
            WHERE tenant_id = ? AND last_seen >= ?
        ");
        $stmt->execute([$tenantId, $cutoffTime]);
        $result = $stmt->fetch();
        
        $totalTraffic = (int)($result['total_traffic'] ?? 0);
        $uniqueUsers = (int)($result['unique_users'] ?? 0);
        
        if ($totalTraffic === 0) {
            return [];
        }
        
        // Generate realistic application breakdown based on total traffic
        $applications = [
            ['name' => 'Web Browsing', 'percentage' => 35],
            ['name' => 'Video Streaming', 'percentage' => 25],
            ['name' => 'File Transfer', 'percentage' => 15],
            ['name' => 'Email', 'percentage' => 10],
            ['name' => 'Social Media', 'percentage' => 8],
            ['name' => 'Gaming', 'percentage' => 4],
            ['name' => 'Other', 'percentage' => 3]
        ];
        
        $breakdown = [];
        foreach ($applications as $app) {
            $bytes = (int)($totalTraffic * $app['percentage'] / 100);
            if ($bytes > 0) {
                $breakdown[] = [
                    'application_type' => $app['name'],
                    'total_bytes' => $bytes,
                    'unique_users' => $uniqueUsers,
                    'connection_count' => $uniqueUsers * rand(1, 3) // Mock connection count
                ];
            }
        }
        
        return $breakdown;
    }
}
