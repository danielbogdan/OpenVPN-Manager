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
