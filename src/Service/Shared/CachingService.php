<?php

namespace App\Service\Shared;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * CachingService - Centralized caching for payroll calculations
 */
final class CachingService
{
    private const CACHE_TTL = 3600; // 1 hour cache

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Cache a value with automatic expiration
     *
     * @param callable $callback Function that computes the value
     * @param int|null $ttl Cache time to live in seconds (default: 1 hour)
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? self::CACHE_TTL;

        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl);
            return $callback();
        });
    }

    /**
     * Invalidate a cache key
     */
    public function forget(string $key): void
    {
        $this->cache->delete($key);
    }

    /**
     * Invalidate all cache keys matching a pattern
     *
     * @param string $pattern Pattern to match (e.g., "payroll:*", "employee:123:*")
     */
    public function forgetPattern(string $pattern): void
    {
        // Since Symfony Cache doesn't support pattern deletion by default,
        // we'll use this method as a placeholder for manual invalidation
        // In production, you might want to use Redis tags or other cache stores
    }

    /**
     * Generate cache key for payroll stats
     */
    public static function payrollStatsKey(int $rhId, ?int $mois = null, ?int $annee = null): string
    {
        $key = "payroll:stats:rh:{$rhId}";
        if ($mois !== null && $annee !== null) {
            $key .= ":{$mois}:{$annee}";
        }
        return $key;
    }

    /**
     * Generate cache key for employee fiches
     */
    public static function employeeFichesKey(int $employeeId): string
    {
        return "payroll:fiches:employee:{$employeeId}";
    }

    /**
     * Generate cache key for employee bonuses
     */
    public static function employeePrimesKey(int $employeeId): string
    {
        return "payroll:primes:employee:{$employeeId}";
    }

    /**
     * Generate cache key for employee deductions
     */
    public static function employeeDeductionsKey(int $employeeId): string
    {
        return "payroll:deductions:employee:{$employeeId}";
    }

    /**
     * Clear all payroll-related cache for an employee
     */
    public static function clearEmployeeCache(int $employeeId): array
    {
        return [
            self::employeeFichesKey($employeeId),
            self::employeePrimesKey($employeeId),
            self::employeeDeductionsKey($employeeId),
        ];
    }
}
