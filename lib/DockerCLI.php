<?php
namespace App;

final class DockerCLI
{
    /**
     * Rulează o comandă pe host (în containerul web) într-un shell POSIX.
     * Returnează ieșirea ca array de linii; aruncă excepție dacă exit code != 0.
     */
    public static function run(string $cmd): array
    {
        // Folosim /bin/sh -lc pentru portabilitate (nu toate imaginile au bash)
        $full = 'sh -lc ' . escapeshellarg($cmd);

        $descriptors = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $proc = proc_open($full, $descriptors, $pipes);
        if (!\is_resource($proc)) {
            throw new \RuntimeException("Nu pot porni shell pentru: $cmd");
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $p) {
            if (\is_resource($p)) @fclose($p);
        }

        $code = proc_close($proc);

        if ($code !== 0) {
            $msg = trim($stderr) !== '' ? $stderr : $stdout;
            throw new \RuntimeException("Docker CLI error ($code): $cmd\n".$msg);
        }

        $lines = preg_split('/\R/', rtrim($stdout), -1, PREG_SPLIT_NO_EMPTY);
        return $lines ?: [];
    }

    /**
     * Execută o comandă în interiorul unui container Docker.
     * NU include "sh -lc" în $innerCmd; îl adăugăm noi aici.
     */
    public static function exec(string $container, string $innerCmd): array
    {
        $cmd = sprintf(
            "docker exec %s sh -lc %s",
            escapeshellarg($container),
            escapeshellarg($innerCmd)
        );
        return self::run($cmd);
    }

    public static function existsContainer(string $name): bool
    {
        $out = self::run("docker ps -a --format '{{.Names}}'");
        return \in_array($name, $out, true);
    }

    public static function start(string $name): void
    {
        self::run('docker start ' . escapeshellarg($name));
    }

    public static function stop(string $name): void
    {
        self::run('docker stop ' . escapeshellarg($name));
    }

    public static function rm(string $name): void
    {
        self::run('docker rm -f ' . escapeshellarg($name));
    }

    /** Creează rețeaua dacă nu există deja (idempotent). */
    public static function createNetwork(string $name): void
    {
        self::run(
            'docker network inspect ' . escapeshellarg($name) .
            ' >/dev/null 2>&1 || docker network create ' . escapeshellarg($name)
        );
    }

    /** Creează rețeaua cu configurație bridge personalizată pentru VMware integration. */
    public static function createBridgeNetwork(string $name, string $subnet, string $bridgeName): void
    {
        // Check if network already exists
        try {
            self::run('docker network inspect ' . escapeshellarg($name) . ' >/dev/null 2>&1');
            return; // Network already exists
        } catch (\RuntimeException $e) {
            // Network doesn't exist, create it
        }

        $gateway = explode('/', $subnet)[0];
        $cmd = sprintf(
            'docker network create --driver bridge --subnet=%s --gateway=%s --opt com.docker.network.bridge.name=%s %s',
            escapeshellarg($subnet),
            escapeshellarg($gateway),
            escapeshellarg($bridgeName),
            escapeshellarg($name)
        );
        
        self::run($cmd);
    }

    /** Creează interfața bridge pentru VMware integration. */
    public static function createBridgeInterface(string $bridgeName, string $subnet): void
    {
        $gateway = explode('/', $subnet)[0];
        $cidr = explode('/', $subnet)[1];
        
        $commands = [
            "ip link add name $bridgeName type bridge",
            "ip addr add $gateway/$cidr dev $bridgeName",
            "ip link set $bridgeName up"
        ];
        
        foreach ($commands as $cmd) {
            self::run($cmd);
        }
    }

    /** Verifică dacă interfața bridge există. */
    public static function bridgeInterfaceExists(string $bridgeName): bool
    {
        try {
            self::run("ip link show $bridgeName >/dev/null 2>&1");
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /** Șterge interfața bridge. */
    public static function removeBridgeInterface(string $bridgeName): void
    {
        try {
            self::run("ip link set $bridgeName down");
            self::run("ip link delete $bridgeName");
        } catch (\RuntimeException $e) {
            // Bridge might not exist, ignore error
        }
    }

    /** Configurează routing și NAT pentru bridge. */
    public static function configureBridgeRouting(string $bridgeName, string $subnet, string $externalInterface = 'eth0'): void
    {
        // Check if iptables is available
        try {
            self::run("which iptables >/dev/null 2>&1");
        } catch (\RuntimeException $e) {
            // iptables not available, skip routing configuration
            // Docker will handle the networking
            return;
        }
        
        $commands = [
            "iptables -t nat -A POSTROUTING -s $subnet -o $externalInterface -j MASQUERADE",
            "iptables -A FORWARD -i $bridgeName -o $externalInterface -j ACCEPT",
            "iptables -A FORWARD -i $externalInterface -o $bridgeName -m state --state RELATED,ESTABLISHED -j ACCEPT"
        ];
        
        foreach ($commands as $cmd) {
            try {
                self::run($cmd);
            } catch (\RuntimeException $e) {
                // Rule might already exist, continue
            }
        }
    }

    /** Creează volumul dacă nu există deja (idempotent). */
    public static function createVolume(string $name): void
    {
        self::run(
            'docker volume inspect ' . escapeshellarg($name) .
            ' >/dev/null 2>&1 || docker volume create ' . escapeshellarg($name)
        );
    }

    public static function removeNetwork(string $name): void
    {
        // șterge doar dacă există; nu arunca dacă nu există
        self::run(
            'sh -lc ' . escapeshellarg(
                'docker network inspect ' . escapeshellarg($name) .
                ' >/dev/null 2>&1 && docker network rm ' . escapeshellarg($name) . ' || true'
            )
        );
    }

    public static function removeVolume(string $name): void
    {
        self::run(
            'sh -lc ' . escapeshellarg(
                'docker volume inspect ' . escapeshellarg($name) .
                ' >/dev/null 2>&1 && docker volume rm ' . escapeshellarg($name) . ' || true'
            )
        );
    }

}
