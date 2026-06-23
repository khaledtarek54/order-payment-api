<?php

declare(strict_types=1);

namespace App\Modules\Order\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tag-less cache invalidation for order lists.
 *
 * The `database`/`file` cache stores don't support tags, so instead we keep a
 * per-user version counter. List cache keys embed the current version; bumping
 * the version (on any write) instantly invalidates every cached list for that
 * user without having to enumerate keys. Swap in Redis and you could use tags
 * directly — but this works on every driver.
 */
final class OrderCache
{
    public static function version(int $userId): int
    {
        return (int) Cache::get(self::versionKey($userId), 1);
    }

    public static function bump(int $userId): void
    {
        $key = self::versionKey($userId);

        // Atomic bump: seed the key if absent (no-op if present), then increment.
        // Avoids the lost-update race of a get-then-set under concurrent writes.
        Cache::add($key, 1);
        Cache::increment($key);
    }

    public static function listKey(int $userId, string $fingerprint): string
    {
        return sprintf('orders:%d:v%d:%s', $userId, self::version($userId), $fingerprint);
    }

    private static function versionKey(int $userId): string
    {
        return "orders:version:{$userId}";
    }
}
