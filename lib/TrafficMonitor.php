<?php
namespace App;

class TrafficMonitor
{
    /**
     * Classify traffic based on destination IP/domain
     */
    public static function classifyTraffic(string $destinationIp, ?string $domain = null): string
    {
        $pdo = DB::pdo();
        
        // First try domain-based classification
        if ($domain) {
            $stmt = $pdo->prepare("
                SELECT application_type 
                FROM app_classification_rules 
                WHERE is_active = 1 AND ? LIKE CONCAT('%', pattern, '%')
                ORDER BY priority ASC 
                LIMIT 1
            ");
            $stmt->execute([$domain]);
            $result = $stmt->fetch();
            if ($result) {
                return $result['application_type'];
            }
        }
        
        // Try IP-based classification (for CDNs and direct IPs)
        if ($destinationIp) {
            $stmt = $pdo->prepare("
                SELECT application_type 
                FROM app_classification_rules 
                WHERE is_active = 1 AND pattern LIKE CONCAT('%', ?, '%')
                ORDER BY priority ASC 
                LIMIT 1
            ");
            $stmt->execute([$destinationIp]);
            $result = $stmt->fetch();
            if ($result) {
                return $result['application_type'];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Log traffic data for a user session
     */
    public static function logTraffic(
        int $tenantId,
        int $userId,
        int $bytesIn,
        int $bytesOut,
        string $protocol = null,
        string $destinationIp = null,
        int $destinationPort = null,
        string $sourceIp = null,
        int $sourcePort = null,
        string $domain = null
    ): void {
        $pdo = DB::pdo();
        
        // Classify the traffic
        $applicationType = self::classifyTraffic($destinationIp, $domain);
        
        // Get country code for destination IP
        $countryCode = null;
        if ($destinationIp && filter_var($destinationIp, FILTER_VALIDATE_IP)) {
            [$country] = GeoIP::lookup($destinationIp);
            $countryCode = $country ? substr($country, 0, 2) : null;
        }
        
        // Insert traffic log
        $stmt = $pdo->prepare("
            INSERT INTO traffic_logs 
            (tenant_id, user_id, bytes_in, bytes_out, protocol, destination_ip, 
             destination_port, source_ip, source_port, application_type, domain, country_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tenantId, $userId, $bytesIn, $bytesOut, $protocol,
            $destinationIp, $destinationPort, $sourceIp, $sourcePort,
            $applicationType, $domain, $countryCode
        ]);
        
        // Update hourly statistics
        self::updateHourlyStats($tenantId, $userId, $applicationType, $bytesIn, $bytesOut, $countryCode);
    }
    
    /**
     * Update hourly aggregated statistics
     */
    private static function updateHourlyStats(
        int $tenantId,
        int $userId,
        string $applicationType,
        int $bytesIn,
        int $bytesOut,
        ?string $countryCode
    ): void {
        $pdo = DB::pdo();
        
        // Round down to the current hour
        $hourTimestamp = date('Y-m-d H:00:00');
        
        $stmt = $pdo->prepare("
            INSERT INTO traffic_stats_hourly 
            (tenant_id, user_id, hour_timestamp, bytes_in, bytes_out, application_type, country_code)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            bytes_in = bytes_in + VALUES(bytes_in),
            bytes_out = bytes_out + VALUES(bytes_out)
        ");
        
        $stmt->execute([$tenantId, $userId, $hourTimestamp, $bytesIn, $bytesOut, $applicationType, $countryCode]);
    }
    
    /**
     * Get traffic statistics for the last 72 hours
     */
    public static function getTrafficStats(int $tenantId, ?int $userId = null, int $hours = 72): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $whereClause = "WHERE tenant_id = ? AND hour_timestamp >= ?";
        $params = [$tenantId, $cutoffTime];
        
        if ($userId) {
            $whereClause .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                hour_timestamp,
                application_type,
                country_code,
                SUM(bytes_in) as total_bytes_in,
                SUM(bytes_out) as total_bytes_out,
                COUNT(DISTINCT user_id) as unique_users
            FROM traffic_stats_hourly 
            $whereClause
            GROUP BY hour_timestamp, application_type, country_code
            ORDER BY hour_timestamp ASC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get application usage breakdown
     */
    public static function getApplicationBreakdown(int $tenantId, ?int $userId = null, int $hours = 72): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $whereClause = "WHERE tenant_id = ? AND hour_timestamp >= ?";
        $params = [$tenantId, $cutoffTime];
        
        if ($userId) {
            $whereClause .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                application_type,
                SUM(bytes_in + bytes_out) as total_bytes,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as connection_count
            FROM traffic_stats_hourly 
            $whereClause
            GROUP BY application_type
            ORDER BY total_bytes DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get geographic distribution of traffic
     */
    public static function getGeographicDistribution(int $tenantId, ?int $userId = null, int $hours = 72): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $whereClause = "WHERE tenant_id = ? AND hour_timestamp >= ? AND country_code IS NOT NULL";
        $params = [$tenantId, $cutoffTime];
        
        if ($userId) {
            $whereClause .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                country_code,
                SUM(bytes_in + bytes_out) as total_bytes,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as connection_count
            FROM traffic_stats_hourly 
            $whereClause
            GROUP BY country_code
            ORDER BY total_bytes DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get top destinations by traffic
     */
    public static function getTopDestinations(int $tenantId, ?int $userId = null, int $hours = 72, int $limit = 20): array
    {
        $pdo = DB::pdo();
        
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $whereClause = "WHERE tenant_id = ? AND timestamp >= ?";
        $params = [$tenantId, $cutoffTime];
        
        if ($userId) {
            $whereClause .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                destination_ip,
                domain,
                application_type,
                country_code,
                SUM(bytes_in + bytes_out) as total_bytes,
                COUNT(*) as connection_count
            FROM traffic_logs 
            $whereClause
            GROUP BY destination_ip, domain, application_type, country_code
            ORDER BY total_bytes DESC
            LIMIT $limit
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Clean up old traffic logs (keep only last 30 days)
     */
    public static function cleanupOldLogs(): void
    {
        $pdo = DB::pdo();
        
        // Delete old traffic logs
        $cutoff30Days = date('Y-m-d H:i:s', strtotime('-30 days'));
        $stmt = $pdo->prepare("DELETE FROM traffic_logs WHERE timestamp < ?");
        $stmt->execute([$cutoff30Days]);
        
        // Delete old hourly stats (keep 1 year)
        $cutoff1Year = date('Y-m-d H:i:s', strtotime('-1 year'));
        $stmt = $pdo->prepare("DELETE FROM traffic_stats_hourly WHERE hour_timestamp < ?");
        $stmt->execute([$cutoff1Year]);
    }
}
