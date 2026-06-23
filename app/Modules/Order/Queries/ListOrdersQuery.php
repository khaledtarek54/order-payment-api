<?php

declare(strict_types=1);

namespace App\Modules\Order\Queries;

use App\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Support\OrderCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Read-side query object for order listings (the read half of the CQRS-lite
 * split — writes live in App\Modules\Order\Actions).
 *
 * Results are cached per user + query fingerprint and invalidated on any write
 * via OrderObserver bumping the user's cache version.
 */
final class ListOrdersQuery
{
    private const LIST_TTL = 30;

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function execute(User $user, int $perPage, string $fingerprint): LengthAwarePaginator
    {
        $key = OrderCache::listKey((int) $user->getKey(), $fingerprint);

        return Cache::remember($key, self::LIST_TTL, fn (): LengthAwarePaginator => QueryBuilder::for(Order::class)
            ->allowedFilters(AllowedFilter::exact('status'))
            ->allowedSorts('created_at', 'total', 'status')
            ->defaultSort('-created_at')
            ->where('user_id', $user->getKey())
            ->with('items')
            ->paginate($perPage)
            ->appends(request()->query()));
    }
}
