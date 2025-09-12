<?php
namespace App;

class OpenVPNManager
{
    /**
     * Creează un tenant OpenVPN:
     *  1) INSERT "stub" în DB ca să obținem id AUTO_INCREMENT (folosind placeholder-uri unice pentru docker_*)
     *  2) Creează resursele Docker (volume/network), genconfig + initpki, pornește containerul
     *  3) Adaugă NAT (opțional)
     *  4) UPDATE rândul cu numele reale ale resurselor Docker
     */
    public static function initTenant(
        int $_unusedTenantId,
        string $name,
        ?string $publicIp = null,
        ?int $port = null,
        ?string $subnet = null,
        bool $nat = true
    ): int {
        $pdo = DB::pdo();

        $publicIp = $publicIp ?: Util::detectPublicIP();
        $port     = $port ?? Util::allocatePort();
        $subnet   = $subnet ?? Util::allocateSubnet();

        // 1) INSERT stub cu placeholder-uri UNICE (pentru că docker_* sunt NOT NULL + UNIQUE)
        $ph = 'pending_' . bin2hex(random_bytes(6));
        $stmt = $pdo->prepare(
            "INSERT INTO tenants (name,public_ip,listen_port,subnet_cidr,nat_enabled,
             docker_volume,docker_container,docker_network)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $name, $publicIp, $port, $subnet, $nat ? 1 : 0,
            "$ph-vol", "$ph-ctr", "$ph-net"
        ]);
        $tenantId = (int)$pdo->lastInsertId();

        // 2) Numele reale ale resurselor, bazate pe ID
        $vol = "ovpn_data_tenant_$tenantId";
        $net = "net_tenant_$tenantId";
        $ctr = "vpn_tenant_$tenantId";

        // Platform override (ex. Apple Silicon -> amd64)
        $platform      = getenv('OVPN_PLATFORM') ?: '';
        $platformFlag  = $platform ? "--platform $platform" : '';

        // Pentru cleanup în caz de excepție:
        $createdVolume   = false;
        $createdNetwork  = false;
        $createdContainer= false;

        try {
            // 3) Volume + network (idempotent)
            DockerCLI::createVolume($vol);  $createdVolume  = true;
            
            // Create Docker network (standard configuration)
            DockerCLI::createNetwork($net);
            $createdNetwork = true;

            // 4) Generează config (fără -p push; le adăugăm manual, compatibil cu ovpn 2.4)
            $dns1 = DEFAULT_DNS1;
            $dns2 = DEFAULT_DNS2;
            $url  = "udp://{$publicIp}:{$port}";

            $genconfig = sprintf(
                "docker run --rm %s -v %s:/etc/openvpn %s ovpn_genconfig -u %s -s %s -C AES-256-GCM -a SHA256",
                $platformFlag,
                escapeshellarg($vol),
                escapeshellarg(OVPN_IMAGE),
                escapeshellarg($url),
                escapeshellarg($subnet)
            );
            DockerCLI::run($genconfig);

            // 5) PKI non-interactiv (EASYRSA_BATCH=1) + yes pipe
            $initpki = sprintf(
                "docker run --rm -i %s -e EASYRSA_BATCH=1 -v %s:/etc/openvpn %s ovpn_initpki nopass",
                $platformFlag,
                escapeshellarg($vol),
                escapeshellarg(OVPN_IMAGE)
            );
            DockerCLI::run("yes | " . $initpki);

            // 6) Adaugă liniile push direct în openvpn.conf (evită quoting în 2.4.x)
            foreach ([
                'push "redirect-gateway def1 bypass-dhcp"',
                'push "dhcp-option DNS ' . addslashes($dns1) . '"',
                'push "dhcp-option DNS ' . addslashes($dns2) . '"',
                'status /tmp/openvpn-status.log',
                'status-version 2',
            ] as $line) {
                $cmd = sprintf(
                    "docker run --rm -v %s:/etc/openvpn busybox sh -lc %s",
                    escapeshellarg($vol),
                    // printf "%s\n" "<line>" >> /etc/openvpn/openvpn.conf
                    escapeshellarg('printf "%s\n" ' . escapeshellarg($line) . ' >> /etc/openvpn/openvpn.conf')
                );
                DockerCLI::run($cmd);
            }

            // 7) Pornește serverul
            $priv   = (getenv('OVPN_PRIVILEGED') === '1') ? '--privileged' : '';
            $sysctl = implode(' ', [
                '--sysctl net.ipv4.ip_forward=1',
                '--sysctl net.ipv6.conf.all.forwarding=1',
                '--sysctl net.ipv6.conf.default.forwarding=1',
            ]);

            $run = sprintf(
                "docker run -d --name %s --network %s %s %s ".
                "--cap-add=NET_ADMIN --device /dev/net/tun %s ".
                "-e TZ='%s' -p %d:1194/udp -v %s:/etc/openvpn %s",
                escapeshellarg($ctr),
                escapeshellarg($net),
                $platformFlag,
                $priv,
                $sysctl,
                escapeshellarg(date_default_timezone_get()),
                $port,
                escapeshellarg($vol),
                escapeshellarg(OVPN_IMAGE)
            );
            DockerCLI::run($run);
            $createdContainer = true;

            // Așteaptă să devină running (scoate log-urile dacă iese)
            self::waitForRunning($ctr);
            usleep(500000); // mică pauză pentru cazurile de emulare amd64 pe ARM

            // 8) NAT (opțional)
            if ($nat) {
                self::ensureNat($ctr, [$subnet]);
            }

            // 9) UPDATE rândul cu numele reale ale resurselor Docker
            $upd = $pdo->prepare(
                "UPDATE tenants SET docker_volume=?, docker_container=?, docker_network=? WHERE id=?"
            );
            $upd->execute([$vol, $ctr, $net, $tenantId]);

            return $tenantId;
        } catch (\Throwable $e) {
            // Cleanup „best effort"
            if ($createdContainer) {
                try { DockerCLI::rm($ctr); } catch (\Throwable $_) {}
            }
            if ($createdVolume) {
                try { DockerCLI::run('docker volume rm ' . escapeshellarg($vol) . ' || true'); } catch (\Throwable $_) {}
            }
            if ($createdNetwork) {
                try { 
                    DockerCLI::run('docker network rm ' . escapeshellarg($net) . ' || true'); 
                } catch (\Throwable $_) {}
            }
            try { $pdo->prepare("DELETE FROM tenants WHERE id=?")->execute([$tenantId]); } catch (\Throwable $_) {}

            throw $e;
        }
    }


    /**
     * Copiază scripts/nat-ensure.sh în container și aplică MASQUERADE pentru subrețele.
     */
    public static function ensureNat(string $container, array $subnets): void
    {
        $src = __DIR__ . '/../scripts/nat-ensure.sh';
        if (!is_file($src)) {
            throw new \RuntimeException("Lipsește scripts/nat-ensure.sh");
        }

        $tmp = sys_get_temp_dir() . '/nat-ensure.sh';
        if (!copy($src, $tmp)) {
            throw new \RuntimeException("Nu pot copia nat-ensure.sh în tmp");
        }

        DockerCLI::run("docker cp " . escapeshellarg($tmp) . " " . escapeshellarg("$container:/usr/local/bin/nat-ensure.sh"));
        DockerCLI::exec($container, "chmod +x /usr/local/bin/nat-ensure.sh");

        foreach ($subnets as $s) {
            $s = trim($s);
            if ($s === '') continue;
            DockerCLI::exec($container, "/usr/local/bin/nat-ensure.sh " . escapeshellarg($s) . " eth0");
        }
    }

    public static function addSubnet(int $tenantId, string $cidr): void
    {
        $pdo = DB::pdo();
        $t = self::getTenant($tenantId);
        if (!$t) throw new \RuntimeException("Tenant inexistent");

        $ins = $pdo->prepare("INSERT IGNORE INTO tenant_networks (tenant_id,subnet_cidr) VALUES (?,?)");
        $ins->execute([$tenantId, $cidr]);

        if ($t['nat_enabled']) {
            self::ensureNat($t['docker_container'], [$cidr]);
        }
    }

    public static function getTenant(int $id): ?array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM tenants WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function toggleNat(int $tenantId, bool $enable): void
    {
        $pdo = DB::pdo();
        $t = self::getTenant($tenantId);
        if (!$t) throw new \RuntimeException("Tenant inexistent");

        $pdo->prepare("UPDATE tenants SET nat_enabled=? WHERE id=?")
            ->execute([$enable ? 1 : 0, $tenantId]);

        if ($enable) {
            $subs = [$t['subnet_cidr']];
            $q = $pdo->prepare("SELECT subnet_cidr FROM tenant_networks WHERE tenant_id=?");
            $q->execute([$tenantId]);
            foreach ($q as $r) $subs[] = $r['subnet_cidr'];
            self::ensureNat($t['docker_container'], array_unique($subs));
        }
    }

    public static function createUserCert(int $tenantId, string $username, bool $nopass = true): void
    {
        $t = self::getTenant($tenantId);
        if (!$t) throw new \RuntimeException("Tenant inexistent");
        $vol = $t['docker_volume'];

        $platform     = getenv('OVPN_PLATFORM') ?: '';
        $platformFlag = $platform ? "--platform $platform" : '';

        // fără -t (TTY) pentru rulare non-interactivă robustă
        $cmd = sprintf(
            "docker run --rm -i %s -v %s:/etc/openvpn %s easyrsa build-client-full %s %s",
            $platformFlag,
            escapeshellarg($vol),
            escapeshellarg(OVPN_IMAGE),
            escapeshellarg($username),
            $nopass ? 'nopass' : ''
        );
        DockerCLI::run("yes | " . $cmd);

        DB::pdo()->prepare("INSERT IGNORE INTO vpn_users(tenant_id,username,status) VALUES (?,?, 'active')")
            ->execute([$tenantId, $username]);
    }

    public static function revokeUserCert(int $tenantId, string $username): void
    {
        $t = self::getTenant($tenantId);
        if (!$t) throw new \RuntimeException("Tenant inexistent");
        $vol = $t['docker_volume'];

        $platform     = getenv('OVPN_PLATFORM') ?: '';
        $platformFlag = $platform ? "--platform $platform" : '';

        $cmd = sprintf(
            "docker run --rm -i %s -v %s:/etc/openvpn %s easyrsa revoke %s && " .
            "docker run --rm %s -v %s:/etc/openvpn %s easyrsa gen-crl",
            $platformFlag, escapeshellarg($vol), escapeshellarg(OVPN_IMAGE), escapeshellarg($username),
            $platformFlag, escapeshellarg($vol), escapeshellarg(OVPN_IMAGE)
        );
        DockerCLI::run("yes | " . $cmd);

        DB::pdo()->prepare("UPDATE vpn_users SET status='revoked' WHERE tenant_id=? AND username=?")
            ->execute([$tenantId, $username]);
    }

    public static function exportOvpn(int $tenantId, string $username): string
    {
        $t = self::getTenant($tenantId);
        if (!$t) throw new \RuntimeException("Tenant inexistent");
        $vol = $t['docker_volume'];

        $platform     = getenv('OVPN_PLATFORM') ?: '';
        $platformFlag = $platform ? "--platform $platform" : '';

        $cmd = sprintf(
            "docker run --rm %s -v %s:/etc/openvpn %s ovpn_getclient %s",
            $platformFlag, escapeshellarg($vol), escapeshellarg(OVPN_IMAGE), escapeshellarg($username)
        );
        $lines = DockerCLI::run($cmd);
        return implode("\n", $lines);
    }

    /**
     * Citește openvpn-status.log din container și actualizează tabela sessions (snapshot).
     */
    public static function refreshSessions(int $tenantId): void
    {
        $pdo = DB::pdo();
        $t   = self::getTenant($tenantId);
        if (!$t) {
            throw new \RuntimeException("Tenant with ID $tenantId not found");
        }
        $ctr = $t['docker_container'];
        if (!$ctr) {
            throw new \RuntimeException("Docker container not found for tenant $tenantId");
        }
        $statusFile = $t['status_path'] ?: '/tmp/openvpn-status.log';

        $out = DockerCLI::exec($ctr, "test -f " . escapeshellarg($statusFile) . " && cat " . escapeshellarg($statusFile) . " || true");
        $raw = implode("\n", $out);

        $pdo->prepare("DELETE FROM sessions WHERE tenant_id=?")->execute([$tenantId]);
        if (!$raw) return;

        $lines   = explode("\n", $raw);
        $stage   = '';
        $clients = [];
        $routes  = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'HEADER,CLIENT_LIST,Common Name,Real Address,') === 0) { $stage = 'clients'; continue; }
            if (strpos($line, 'HEADER,ROUTING_TABLE,Virtual Address,Common Name,') === 0) { $stage = 'routes';  continue; }
            if (strpos($line, 'GLOBAL_STATS,') === 0 || $line === 'END') { $stage=''; continue; }

            if ($stage === 'clients' && strpos($line, 'CLIENT_LIST,') === 0) {
                $parts = explode(',', $line);
                if (count($parts) >= 8) {
                    $cn = $parts[1];
                    $real = $parts[2];
                    $br = (int)$parts[5];
                    $bs = (int)$parts[6];
                    $since = $parts[7];
                    $clients[$cn] = [
                        'real'  => $real,
                        'br'    => $br,
                        'bs'    => $bs,
                        'since' => strtotime($since) ?: null
                    ];
                }
            }
            if ($stage === 'routes' && strpos($line, 'ROUTING_TABLE,') === 0) {
                $parts = explode(',', $line);
                if (count($parts) >= 3) {
                    $vip = $parts[1];
                    $cn = $parts[2];
                    $routes[$cn] = $vip;
                }
            }
        }

        foreach ($clients as $cn => $c) {
            [$ip] = explode(':', $c['real'], 2);
            [$country, $city] = GeoIP::lookup($ip);

            $vip   = $routes[$cn] ?? null;
            $since = $c['since'] ? date('Y-m-d H:i:s', $c['since']) : null;

            // Get user_id for this common_name
            $userStmt = $pdo->prepare("SELECT id FROM vpn_users WHERE tenant_id = ? AND username = ?");
            $userStmt->execute([$tenantId, $cn]);
            $user = $userStmt->fetch();
            $userId = $user ? $user['id'] : null;

            $stmt = $pdo->prepare(
                "INSERT INTO sessions(tenant_id,user_id,common_name,real_address,virtual_address,
                 bytes_received,bytes_sent,since,geo_country,geo_city,last_seen)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            $stmt->execute([$tenantId, $userId, $cn, $c['real'], $vip, $c['br'], $c['bs'], $since, $country, $city]);
        }
    }
      
      public static function pauseTenant(int $tenantId): void
      {
          $pdo = DB::pdo();
          $t   = self::getTenant($tenantId);
          if (!$t) throw new \RuntimeException("Tenant inexistent");

          if (DockerCLI::existsContainer($t['docker_container'])) {
              DockerCLI::stop($t['docker_container']);
          }

          $pdo->prepare("UPDATE tenants SET status='paused' WHERE id=?")->execute([$tenantId]);
      }

      public static function resumeTenant(int $tenantId): void
      {
          $pdo = DB::pdo();
          $t   = self::getTenant($tenantId);
          if (!$t) throw new \RuntimeException("Tenant inexistent");

          if (DockerCLI::existsContainer($t['docker_container'])) {
              DockerCLI::start($t['docker_container']);
              self::waitForRunning($t['docker_container']);
          }

          $pdo->prepare("UPDATE tenants SET status='running' WHERE id=?")->execute([$tenantId]);
      }


      public static function deleteTenant(int $tenantId): void
      {
          $pdo = DB::pdo();
          $t = self::getTenant($tenantId);
          if (!$t) { return; } // deja șters

          // Log cleanup information
          error_log("Deleting tenant ID: $tenantId, Name: {$t['name']}");

          // Count related records before deletion
          $clientUsersCount = $pdo->prepare("SELECT COUNT(*) FROM client_users WHERE tenant_id = ?");
          $clientUsersCount->execute([$tenantId]);
          $clientUsersCount = $clientUsersCount->fetchColumn();

          $vpnUsersCount = $pdo->prepare("SELECT COUNT(*) FROM vpn_users WHERE tenant_id = ?");
          $vpnUsersCount->execute([$tenantId]);
          $vpnUsersCount = $vpnUsersCount->fetchColumn();

          $sessionsCount = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ?");
          $sessionsCount->execute([$tenantId]);
          $sessionsCount = $sessionsCount->fetchColumn();

          error_log("Tenant cleanup - Client users: $clientUsersCount, VPN users: $vpnUsersCount, Sessions: $sessionsCount");

          // 1) oprește & șterge containerul dacă există
          if (DockerCLI::existsContainer($t['docker_container'])) {
              try { DockerCLI::stop($t['docker_container']); } catch (\Throwable $e) {}
              try { DockerCLI::rm($t['docker_container']); }   catch (\Throwable $e) {}
          }

          // 2) șterge volumul și rețeaua (idempotent)
          try { DockerCLI::removeVolume($t['docker_volume']); }   catch (\Throwable $e) {}
          try { DockerCLI::removeNetwork($t['docker_network']); } catch (\Throwable $e) {}

          // 3) șterge rândul din DB (FK-urile cascaded curăță copilul)
          // This will automatically delete:
          // - client_users (ON DELETE CASCADE)
          // - vpn_users (ON DELETE CASCADE) 
          // - sessions (ON DELETE CASCADE)
          // - tenant_networks (ON DELETE CASCADE)
          // - traffic_logs (ON DELETE CASCADE)
          // - analytics_data (ON DELETE CASCADE)
          $pdo->prepare("DELETE FROM tenants WHERE id=?")->execute([$tenantId]);

          error_log("Tenant ID: $tenantId successfully deleted with all related data");
      }


    /**
     * Așteaptă ca un container să ajungă în starea "running". Dacă iese sau nu pornește la timp, aruncă excepție cu loguri.
     */
    private static function waitForRunning(string $ctr): void
    {
        for ($i = 0; $i < 20; $i++) {
            $status = trim(implode("", DockerCLI::run("docker inspect -f '{{.State.Status}}' " . escapeshellarg($ctr))));
            if ($status === 'running') return;
            if ($status === 'exited' || $status === 'dead') {
                $log = implode("\n", DockerCLI::run("docker logs " . escapeshellarg($ctr) . " 2>&1 || true"));
                throw new \RuntimeException("Containerul $ctr a ieșit ($status). Loguri:\n" . $log);
            }
            usleep(300000); // 300ms
        }
        $log = implode("\n", DockerCLI::run("docker logs " . escapeshellarg($ctr) . " 2>&1 || true"));
        throw new \RuntimeException("Containerul $ctr nu a pornit la timp. Loguri:\n" . $log);
    }
}
