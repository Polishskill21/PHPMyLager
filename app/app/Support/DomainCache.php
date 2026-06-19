<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Cache;

/**
 * Thin wrapper around Laravel's tagged cache for domain-scoped invalidation.
 *
 * Each read is stored under a domain tag (e.g. "products"); each write flushes
 * only the affected domain(s), so creating a product invalidates the product
 * cache without touching orders, customers, etc.
 *
 * Requires a tag-capable cache store (redis / memcached). 
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
     * Remember a value under a domain tag.
     *
     */
    public static function remember(string $domain, string $key, Closure $callback): mixed
    {
        return Cache::tags([$domain])->remember($key, self::TTL, static function () use ($callback) {
            $value = $callback();

            return $value instanceof Arrayable ? $value->toArray() : $value;
        });
    }

    /**
     * Invalidate every cache entry for the given domain(s).
     */
    public static function flush(string ...$domains): void
    {
        foreach ($domains as $domain) {
            Cache::tags([$domain])->flush();
        }
    }
}
