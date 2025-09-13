<?php
namespace App;

class GeoIP {
  public static function lookup(string $ip): array {
    // Enhanced: ip-api.com with coordinates for precise location
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return [null, null, null, null];
    $json = @file_get_contents("http://ip-api.com/json/".urlencode($ip)."?fields=status,country,city,lat,lon");
    if ($json) {
      $o = json_decode($json, true);
      if (($o['status'] ?? '') === 'success') {
        return [
          $o['country'] ?? null, 
          $o['city'] ?? null,
          $o['lat'] ?? null,
          $o['lon'] ?? null
        ];
      }
    }
    return [null, null, null, null];
  }
}
