<?php
class JWTConfig {
    // This will automatically generate a secure key on first use
    public static function getSecretKey() {
        $keyFile = __DIR__ . '/jwt_secret.key';
        if (!file_exists($keyFile)) {
            $key = bin2hex(random_bytes(32));
            file_put_contents($keyFile, $key);
            return $key;
        }
        return file_get_contents($keyFile);
    }

    public static function getRefreshKey() {
        $keyFile = __DIR__ . '/jwt_refresh.key';
        if (!file_exists($keyFile)) {
            $key = bin2hex(random_bytes(32));
            file_put_contents($keyFile, $key);
            return $key;
        }
        return file_get_contents($keyFile);
    }
}
