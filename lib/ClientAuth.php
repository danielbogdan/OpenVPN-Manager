<?php

namespace App;

class ClientAuth
{
    public static function require(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /client/login.php');
            exit;
        }
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['client_user_id']) && isset($_SESSION['client_tenant_id']);
    }

    public static function login(string $username, string $password): bool
    {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("
            SELECT cu.*, t.name as tenant_name 
            FROM client_users cu 
            JOIN tenants t ON cu.tenant_id = t.id 
            WHERE cu.username = ? AND cu.is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Update last login, IP, and activity
        $clientIp = self::getClientIp();
        $realPublicIp = self::getRealPublicIp();
        
        // Use real public IP if available, otherwise fall back to detected IP
        $finalIp = $realPublicIp ?: $clientIp;
        
        $stmt = $pdo->prepare("UPDATE client_users SET last_login = NOW(), last_login_ip = ?, last_activity = NOW() WHERE id = ?");
        $stmt->execute([$finalIp, $user['id']]);

        // Set session
        $_SESSION['client_user_id'] = $user['id'];
        $_SESSION['client_tenant_id'] = $user['tenant_id'];
        $_SESSION['client_username'] = $user['username'];
        $_SESSION['client_tenant_name'] = $user['tenant_name'];
        $_SESSION['client_full_name'] = $user['full_name'];

        return true;
    }

    public static function logout(): void
    {
        // Clear last activity on logout
        if (isset($_SESSION['client_user_id'])) {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("UPDATE client_users SET last_activity = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['client_user_id']]);
        }
        
        unset($_SESSION['client_user_id']);
        unset($_SESSION['client_tenant_id']);
        unset($_SESSION['client_username']);
        unset($_SESSION['client_tenant_name']);
        unset($_SESSION['client_full_name']);
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['client_user_id'],
            'tenant_id' => $_SESSION['client_tenant_id'],
            'username' => $_SESSION['client_username'],
            'tenant_name' => $_SESSION['client_tenant_name'],
            'full_name' => $_SESSION['client_full_name'] ?? null
        ];
    }

    public static function getCurrentTenantId(): ?int
    {
        return $_SESSION['client_tenant_id'] ?? null;
    }

    public static function getCurrentClientUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            SELECT cu.*, t.name as tenant_name 
            FROM client_users cu 
            JOIN tenants t ON cu.tenant_id = t.id 
            WHERE cu.id = ?
        ");
        $stmt->execute([$_SESSION['client_user_id']]);
        
        return $stmt->fetch();
    }

    public static function createClientUser(int $tenantId, string $username, string $password, string $email = null, string $fullName = null): bool
    {
        $pdo = DB::pdo();
        
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO client_users (tenant_id, username, password_hash, email, full_name) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenantId, $username, $passwordHash, $email, $fullName]);
            
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function getClientUsers(int $tenantId): array
    {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("
            SELECT cu.*, t.name as tenant_name 
            FROM client_users cu 
            JOIN tenants t ON cu.tenant_id = t.id 
            WHERE cu.tenant_id = ? 
            ORDER BY cu.created_at DESC
        ");
        $stmt->execute([$tenantId]);
        
        return $stmt->fetchAll();
    }

    public static function deleteClientUser(int $userId, int $tenantId): bool
    {
        $pdo = DB::pdo();
        
        try {
            $stmt = $pdo->prepare("DELETE FROM client_users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function updateClientUser(int $userId, int $tenantId, array $data): bool
    {
        $pdo = DB::pdo();
        
        try {
            $fields = [];
            $values = [];
            
            if (isset($data['username'])) {
                $fields[] = 'username = ?';
                $values[] = $data['username'];
            }
            
            if (isset($data['email'])) {
                $fields[] = 'email = ?';
                $values[] = $data['email'];
            }
            
            if (isset($data['full_name'])) {
                $fields[] = 'full_name = ?';
                $values[] = $data['full_name'];
            }
            
            if (isset($data['password'])) {
                $fields[] = 'password_hash = ?';
                $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (isset($data['is_active'])) {
                $fields[] = 'is_active = ?';
                $values[] = $data['is_active'] ? 1 : 0;
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $userId;
            $values[] = $tenantId;
            
            $stmt = $pdo->prepare("
                UPDATE client_users 
                SET " . implode(', ', $fields) . " 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute($values);
            
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    private static function getClientIp(): string
    {
        // Log all available IP sources for debugging
        $ipSources = [
            'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'HTTP_X_REAL_IP' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
            'HTTP_CLIENT_IP' => $_SERVER['HTTP_CLIENT_IP'] ?? null,
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_CF_CONNECTING_IP' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null, // Cloudflare
            'HTTP_X_CLUSTER_CLIENT_IP' => $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] ?? null,
        ];
        
        // Log the IP sources for debugging
        error_log('IP Detection Sources: ' . json_encode($ipSources));
        
        // Priority order for IP detection
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare real IP
            'HTTP_X_REAL_IP',           // Nginx real IP
            'HTTP_X_FORWARDED_FOR',     // Standard forwarded IP
            'HTTP_CLIENT_IP',           // Client IP header
            'HTTP_X_CLUSTER_CLIENT_IP', // Cluster client IP
            'REMOTE_ADDR'               // Direct connection
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim($_SERVER[$header]);
                
                // For X-Forwarded-For, take the first IP (original client)
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    // Accept any valid IP (including private ranges for debugging)
                    error_log("Selected IP from {$header}: {$ip}");
                    return $ip;
                }
            }
        }
        
        error_log('No valid IP found, returning Unknown');
        return 'Unknown';
    }

    private static function getRealPublicIp(): ?string
    {
        // List of IP detection services
        $services = [
            'https://api.ipify.org',
            'https://ipv4.icanhazip.com',
            'https://checkip.amazonaws.com',
            'https://api.ip.sb/geoip'
        ];
        
        foreach ($services as $service) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'user_agent' => 'OpenVPN Manager IP Detection'
                    ]
                ]);
                
                $ip = file_get_contents($service, false, $context);
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    error_log("Real public IP detected from {$service}: {$ip}");
                    return $ip;
                }
            } catch (Exception $e) {
                error_log("Failed to get IP from {$service}: " . $e->getMessage());
                continue;
            }
        }
        
        error_log('Failed to get real public IP from any service');
        return null;
    }
}
