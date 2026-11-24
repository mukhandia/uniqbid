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

    public function quantityOf(int $productId): int
    {
        foreach ($this->items() as $item) {
            if ($item['product_id'] === $productId) {
                return (int) $item['qty'];
            }
        }

        return 0;
    }

    /**
     * Add the requested quantity. Stock is not enforced — overselling is allowed.
     */
    public function add(int $productId, int $qty = 1): int
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return 0;
        }

        $toAdd = max(0, $qty);

        if ($toAdd < 1) {
            return 0;
        }

        $current = $this->quantityOf($productId);
        $items = $this->items();
        $found = false;

        foreach ($items as &$item) {
            if ($item['product_id'] === $productId) {
                $item['qty'] = $current + $toAdd;
                $found = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            $items[] = ['product_id' => $productId, 'qty' => $toAdd];
        }

        Session::put(self::SESSION_KEY, $items);

        return $toAdd;
    }

    /**
     * Set quantity. Qty below 1 removes the line. Stock is not enforced.
     */
    public function update(int $productId, int $qty): void
    {
        if ($qty < 1) {
            $this->remove($productId);

            return;
        }

        if (Product::query()->whereKey($productId)->doesntExist()) {
            $this->remove($productId);

            return;
        }

        $items = collect($this->items())
            ->map(function (array $item) use ($productId, $qty): array {
                if ($item['product_id'] !== $productId) {
                    return $item;
                }

                $item['qty'] = $qty;

                return $item;
            })
            ->values()
            ->all();

        if (! collect($items)->contains(fn (array $item): bool => $item['product_id'] === $productId)) {
            $items[] = ['product_id' => $productId, 'qty' => $qty];
        }

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

                $qty = max(1, (int) $item['qty']);

                return [
                    'product' => $product,
                    'qty' => $qty,
                    'line_total' => $product->price * $qty,
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
