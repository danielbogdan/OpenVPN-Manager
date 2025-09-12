<?php
namespace App;

class Util
{
    /** HTML escape helper */
    public static function h($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Detectează IP-ul public (best-effort).
     * Întâi ifconfig.io, apoi ipify; altfel 0.0.0.0.
     */
    public static function detectPublicIP(): string
    {
        $cmds = [
            'curl -fsS --max-time 3 https://ifconfig.io',
            'curl -fsS --max-time 3 https://api.ipify.org',
        ];

        foreach ($cmds as $cmd) {
            $ip = trim((string)@shell_exec($cmd));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }

    /**
     * Alege următorul port UDP liber:
     *  - pornește de la MAX(listen_port) din DB (sau 1193 ca să iasă 1194 prima dată)
     *  - verifică să nu fie deja în DB
     *  - verifică și pe host dacă portul se poate binda (UDP)
     */
    public static function allocatePort(int $defaultFirst = 1194, int $max = 65535): int
    {
        $pdo   = DB::pdo();
        $maxDb = (int)$pdo->query("SELECT COALESCE(MAX(listen_port), 0) FROM tenants")->fetchColumn();

        // vrem să începem de la 1194 dacă nu există nimic
        $start = $maxDb > 0 ? $maxDb : ($defaultFirst - 1);

        // nu coborâm sub 1024
        $p = max($start + 1, 1024);

        for (; $p <= $max; $p++) {
            // 1) nu există deja în DB
            $st = $pdo->prepare("SELECT 1 FROM tenants WHERE listen_port = ? LIMIT 1");
            $st->execute([$p]);
            if ($st->fetch()) {
                continue;
            }

            // 2) best-effort: să pară liber pe host
            if (self::isUdpPortFree($p)) {
                return $p;
            }
        }

        throw new \RuntimeException("Nu am găsit niciun port UDP liber > $start până la $max.");
    }

    /**
     * Verificare rapidă dacă un port UDP este liber pe host.
     * Se încearcă bind pe 0.0.0.0:$port; dacă reușește, îl considerăm liber.
     */
    private static function isUdpPortFree(int $port): bool
    {
        $ctx  = stream_context_create(['socket' => ['so_reuseaddr' => true]]);
        $sock = @stream_socket_server("udp://0.0.0.0:$port", $errno, $errstr, STREAM_SERVER_BIND, $ctx);
        if ($sock) {
            fclose($sock);
            return true;
        }
        return false;
    }

    /**
     * Generează o subrețea /26 unică.
     * Strategie: scanează determinist 10.10.0.0/16 (mai „ordonat” pentru primele),
     * apoi, dacă se epuizează, continuă în 10.0.0.0/8.
     */
    public static function allocateSubnet(): string
    {
        $pdo = DB::pdo();

        // 1) întâi în 10.10.0.0/16
        for ($b = 10; $b <= 10; $b++) {
            for ($c = 0; $c <= 255; $c++) {
                $cidr = "10.$b.$c.0/26";
                if (!self::subnetExists($pdo, $cidr)) {
                    return $cidr;
                }
            }
        }

        // 2) apoi tot 10.0.0.0/8 (fallback)
        for ($b = 0; $b <= 255; $b++) {
            for ($c = 0; $c <= 255; $c++) {
                // sărim peste 10.10.x.x care a fost verificat deja
                if ($b === 10) continue;
                $cidr = "10.$b.$c.0/26";
                if (!self::subnetExists($pdo, $cidr)) {
                    return $cidr;
                }
            }
        }

        throw new \RuntimeException("Nu mai am /26 disponibile în 10.0.0.0/8.");
    }

    /** Verifică dacă subnetul există în oricare din tabelele relevante. */
    private static function subnetExists(\PDO $pdo, string $cidr): bool
    {
        $q = $pdo->prepare(
            "SELECT 1 FROM tenants WHERE subnet_cidr = ? 
             UNION SELECT 1 FROM tenant_networks WHERE subnet_cidr = ? 
             LIMIT 1"
        );
        $q->execute([$cidr, $cidr]);
        return (bool)$q->fetchColumn();
    }
}
