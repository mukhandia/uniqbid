<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_KEY = 'uniqbid.cart';

    /**
     * @return array<int, array{product_id: int, qty: int}>
     */
    public function items(): array
    {
        /** @var array<int, array{product_id: int, qty: int}> $items */
        $items = Session::get(self::SESSION_KEY, []);

        return $items;
    }

    public function count(): int
    {
        return collect($this->items())->sum('qty');
    }

    public function add(int $productId, int $qty = 1): void
    {
        $items = $this->items();
        $found = false;

        foreach ($items as &$item) {
            if ($item['product_id'] === $productId) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            $items[] = ['product_id' => $productId, 'qty' => max(1, $qty)];
        }

        Session::put(self::SESSION_KEY, $items);
    }

    public function update(int $productId, int $qty): void
    {
        $items = collect($this->items())
            ->map(function (array $item) use ($productId, $qty): ?array {
                if ($item['product_id'] !== $productId) {
                    return $item;
                }

                if ($qty < 1) {
                    return null;
                }

                $item['qty'] = $qty;

                return $item;
            })
            ->filter()
            ->values()
            ->all();

        Session::put(self::SESSION_KEY, $items);
    }

    public function remove(int $productId): void
    {
        $items = collect($this->items())
            ->reject(fn (array $item): bool => $item['product_id'] === $productId)
            ->values()
            ->all();

        Session::put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, array{product: Product, qty: int, line_total: int}>
     */
    public function lines(): Collection
    {
        $items = $this->items();
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products): ?array {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    return null;
                }

                return [
                    'product' => $product,
                    'qty' => $item['qty'],
                    'line_total' => $product->price * $item['qty'],
                ];
            })
            ->filter()
            ->values();
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum('line_total');
    }
}
