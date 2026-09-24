<?php
namespace App\Helper;

class IpHelper{
    public static function getClientIp(): string {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            // X-Forwarded-For can contain a comma-separated list; first is the client.
            $ips = explode(',', $_SERVER[$key]);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public static function isValid(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function isPrivate(string $ip): bool {
        if (!self::isValid($ip)) return false;

        // filter_var with FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        // returns false for private/reserved addresses.
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    public static function anonymize(string $ip): string {
        if (!self::isValid($ip)) {
            return '0.0.0.0';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        // IPv6: keep first 48 bits (3 groups) and zero the rest.
        $packed = inet_pton($ip);
        if ($packed === false) {
            return '::';
        }

        // Zero out the last 10 bytes (80 bits) -> keep first 6 bytes (48 bits)
        for ($i = 6; $i < 16; $i++) {
            $packed[$i] = "\x00";
        }

        $anonymized = inet_ntop($packed);
        return $anonymized !== false ? $anonymized : '::';
    }
    
    public static function getDeviceFingerprint(array $data): string {
        $normalized = [
            'user_agent' => strtolower(trim((string)($data['user_agent'] ?? ''))),
            'accept'     => strtolower(trim((string)($data['accept'] ?? ''))),
            'language'   => strtolower(trim((string)($data['language'] ?? ''))),
            'encoding'   => strtolower(trim((string)($data['encoding'] ?? ''))),
            'platform'   => strtolower(trim((string)($data['platform'] ?? ''))),
            'screen'     => strtolower(trim((string)($data['screen'] ?? ''))),
            'timezone'   => strtolower(trim((string)($data['timezone'] ?? ''))),
        ];

        // Sort keys to guarantee a consistent order.
        ksort($normalized);

        $payload = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // HMAC with a server-side secret makes the fingerprint hard to forge.
        $secret = getenv('APP_FINGERPRINT_SECRET') ?: 'default-insecure-secret';

        return hash_hmac('sha256', $payload, $secret);
    }
}