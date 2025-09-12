<?php
namespace App;

class GeoIP {
  public static function lookup(string $ip): array {
    // Simplu: ip-api.com (fără cheie, limitat). Înlocuiește cu MaxMind local dacă vrei.
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return [null,null];
    $json = @file_get_contents("http://ip-api.com/json/".urlencode($ip)."?fields=status,country,city");
    if ($json) {
      $o = json_decode($json,true);
      if (($o['status']??'')==='success') return [$o['country']??null, $o['city']??null];
    }
    return [null,null];
  }
}
