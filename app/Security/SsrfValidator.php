<?php

namespace App\Security;

use InvalidArgumentException;

class SsrfValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** Ports commonly used by internal services — never a valid webhook destination. */
    private const BLOCKED_PORTS = [22, 25, 53, 110, 143, 389, 445, 1433, 3306, 5432, 6379, 8080, 11211, 27017];

    /** @var callable|null Overridable DNS resolver — injectable in tests. */
    private static $dnsResolver = null;

    /** Override DNS resolution in tests: SsrfValidator::setDnsResolver(fn($host) => ['1.2.3.4']). */
    public static function setDnsResolver(?callable $resolver): void
    {
        self::$dnsResolver = $resolver;
    }

    /**
     * Validate that a URL is safe to use as a webhook target.
     *
     * Resolves the hostname to its IP(s) and rejects any that fall in private,
     * loopback, link-local, or reserved ranges — blocking SSRF attacks that
     * target cloud metadata APIs, databases, or other internal services.
     *
     * @throws InvalidArgumentException when the URL is unsafe or unresolvable.
     */
    public static function validate(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            throw new InvalidArgumentException('URL de webhook inválida.');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new InvalidArgumentException("Esquema no permitido: {$scheme}. Solo se permite HTTPS.");
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (in_array((int) $port, self::BLOCKED_PORTS, true)) {
            throw new InvalidArgumentException("Puerto {$port} no está permitido para webhooks.");
        }

        foreach (self::resolveHost($host) as $ip) {
            if (self::isPrivateOrReserved($ip)) {
                throw new InvalidArgumentException(
                    'La URL del webhook apunta a una dirección privada o reservada y no está permitida.'
                );
            }
        }
    }

    /** @return string[] Resolved IP addresses */
    private static function resolveHost(string $host): array
    {
        // Strip IPv6 brackets: [::1] → ::1
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return [substr($host, 1, -1)];
        }

        // Bare IP — return directly without DNS lookup
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $resolver = self::$dnsResolver ?? static fn (string $h) => gethostbynamel($h);
        $ips = $resolver($host);

        if ($ips === false || empty($ips)) {
            throw new InvalidArgumentException("No se pudo resolver el host: {$host}");
        }

        return $ips;
    }

    private static function isPrivateOrReserved(string $ip): bool
    {
        // PHP's built-in covers: loopback (127/8), private (10/8, 172.16/12, 192.168/16),
        // link-local (169.254/16), and reserved ranges.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        // Shared address space (RFC 6598) — not caught by PHP's built-in flags
        if (self::ipInCidr($ip, '100.64.0.0', 10)) {
            return true;
        }

        return false;
    }

    private static function ipInCidr(string $ip, string $network, int $prefix): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $netLong = ip2long($network);
        $mask = ~((1 << (32 - $prefix)) - 1);

        return ($ipLong & $mask) === ($netLong & $mask);
    }
}
