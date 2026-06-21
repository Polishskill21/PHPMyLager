<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Cache;

/**
 * Thin wrapper around Laravel's cache for domain-scoped invalidation.
 *
 * Each read is stored under a versioned, domain-namespaced key (e.g.
 * "products:v3:products:index"); each write bumps only the affected domain's
 * version counter, so creating a product invalidates the product cache without
 * touching orders, customers, etc. Orphaned old-version keys lapse via the TTL.
 *
 * This versioning scheme works on any cache store (database, file, redis, …) —
 * it does not rely on cache tagging, which the database/file drivers lack.
 */
final class DomainCache
{
    public const PRODUCTS         = 'products';
    public const ORDERS           = 'orders';
    public const CUSTOMERS        = 'customers';
    public const SUPPLIERS        = 'suppliers';
    public const PURCHASE_ORDERS  = 'purchase-orders';
    public const WAREHOUSE_GROUPS = 'warehouse-groups';

    /**
     * TTL (seconds) acting only as a safety backstop — invalidation is
     * event-driven via flush() on every write.
     */
    private const TTL = 600;

    /**
     * Remember a value under the domain's current version namespace.
     */
    public static function remember(string $domain, string $key, Closure $callback): mixed
    {
        $namespacedKey = self::namespacedKey($domain, $key);

        return Cache::remember($namespacedKey, self::TTL, static function () use ($callback) {
            $value = $callback();

            return $value instanceof Arrayable ? $value->toArray() : $value;
        });
    }

    /**
     * Invalidate every cache entry for the given domain(s) by bumping their
     * version counters; existing entries become unreachable at once.
     */
    public static function flush(string ...$domains): void
    {
        foreach ($domains as $domain) {
            $versionKey = self::versionKey($domain);

            Cache::forever($versionKey, self::version($domain) + 1);
        }
    }

    /**
     * Build the version-namespaced cache key for a domain entry.
     */
    private static function namespacedKey(string $domain, string $key): string
    {
        return "{$domain}:v" . self::version($domain) . ":{$key}";
    }

    /**
     * Current version counter for a domain (defaults to 1 when unset).
     */
    private static function version(string $domain): int
    {
        return (int) Cache::get(self::versionKey($domain), 1);
    }

    private static function versionKey(string $domain): string
    {
        return "domain-version:{$domain}";
    }
}
