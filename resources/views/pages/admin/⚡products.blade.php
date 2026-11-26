<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\CreateSaleFromProductService;
use App\Support\AuditLogger;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Products')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public string $filterCategoryId = '';

    public string $filterChannel = 'all';

    public ?int $saleProductId = null;

    public ?int $saleBidFee = null;

    public int $saleMinBid = 1;

    public int $saleMaxBid = 5000;

    public int $saleEndsInHours = 24;

    public bool $saleReserveStock = true;

    public bool $saleFeatureOnHomepage = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterChannel(): void
    {
        $this->resetPage();
    }

    public function delete(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        if ($product->reserved_stock > 0) {
            Flux::toast(variant: 'danger', text: 'Cannot delete while units are reserved for live sales.');

            return;
        }

        AuditLogger::log('products', 'product deleted', 'deleted', $product, [
            'product_id' => $product->id,
            'name' => $product->name,
        ]);
        $product->delete();
        Flux::toast(variant: 'success', text: 'Product deleted.');
    }

    public function openSaleForm(int $productId): void
    {
        $product = Product::query()->with('currentSale')->findOrFail($productId);
        $currentSale = $product->currentSale;

        if ($currentSale !== null) {
            if ($currentSale->isOpenLive()) {
                Flux::toast(variant: 'warning', text: 'This product already has a live sale.');
                $this->redirect(route('admin.auction-bids', $currentSale), navigate: true);

                return;
            }

            Flux::toast(variant: 'warning', text: 'Settle the ended sale before creating a new one.');
            $this->redirect(route('admin.auctions', [
                'status' => 'needs_settle',
                'search' => $product->name,
            ]), navigate: true);

            return;
        }

        $this->saleProductId = $product->id;
        $pricing = CreateSaleFromProductService::pricingFromProduct($product);
        $this->saleBidFee = $pricing['bid_fee'];
        $this->saleMinBid = $pricing['min_bid'];
        $this->saleMaxBid = $pricing['max_bid'];
        $this->saleEndsInHours = 24;
        $this->saleReserveStock = true;
        $this->saleFeatureOnHomepage = false;
        $this->resetValidation();

        $this->modal('create-sale')->show();
    }

    public function createSale(CreateSaleFromProductService $service): void
    {
        $this->validate([
            'saleProductId' => ['required', 'exists:products,id'],
            'saleBidFee' => ['nullable', 'integer', 'min:'.CreateSaleFromProductService::MINIMUM_BID_FEE, 'max:100000'],
            'saleEndsInHours' => ['required', 'integer', 'min:1', 'max:168'],
            'saleReserveStock' => ['boolean'],
            'saleFeatureOnHomepage' => ['boolean'],
        ]);

        $product = Product::query()->findOrFail($this->saleProductId);
        $pricing = CreateSaleFromProductService::pricingFromProduct($product);

        try {
            $auction = $service->handle($product, [
                'bid_fee' => $this->saleBidFee ?? $pricing['bid_fee'],
                'min_bid' => $pricing['min_bid'],
                'max_bid' => $pricing['max_bid'],
                'ends_in_hours' => $this->saleEndsInHours,
                'reserve_stock' => $this->saleReserveStock,
                'is_featured' => $this->saleFeatureOnHomepage,
            ]);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to create sale.');

            throw $exception;
        }

        $this->saleProductId = null;
        Flux::toast(variant: 'success', text: 'Sale created: '.$auction->name);
        $this->redirect(route('admin.auctions'), navigate: true);
    }

    public function cancelSaleForm(): void
    {
        $this->saleProductId = null;
    }

    public function moveToShop(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);
        $product->assignToShop();
        AuditLogger::log('products', 'product added to shop', 'channel_shop', $product->fresh(), [
            'channel' => $product->fresh()->channel,
        ]);
        Flux::toast(variant: 'success', text: 'Product is listed in the shop'.($product->isInAuctionChannel() ? ' and remains available for auction.' : '.'));
    }

    public function removeFromShop(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);
        $product->removeFromShop();
        AuditLogger::log('products', 'product removed from shop', 'channel_shop_removed', $product->fresh(), [
            'channel' => $product->fresh()->channel,
        ]);
        Flux::toast(variant: 'success', text: 'Product removed from the shop.');
    }

    public function removeFromAuctionChannel(int $productId): void
    {
        $product = Product::query()->with('currentSale')->findOrFail($productId);

        if ($product->currentSale !== null) {
            Flux::toast(variant: 'danger', text: 'Settle or void the active sale before removing this product from auction.');

            return;
        }

        $product->removeFromAuctionChannel();
        AuditLogger::log('products', 'product removed from auction channel', 'channel_auction_removed', $product->fresh(), [
            'channel' => $product->fresh()->channel,
        ]);
        Flux::toast(variant: 'success', text: 'Product removed from the auction channel.');
    }

    public function with(): array
    {
        $query = Product::query()
            ->with(['subcategoryRelation.category', 'currentSale'])
            ->search($this->search !== '' ? $this->search : null)
            ->latest();

        if ($this->filterCategoryId !== '') {
            $query->whereHas('subcategoryRelation', fn ($q) => $q->where('category_id', $this->filterCategoryId));
        }

        match ($this->filterChannel) {
            'shop' => $query->inShop(),
            'auction' => $query->inAuctionChannel(),
            'both' => $query->where('channel', Product::CHANNEL_BOTH),
            'none' => $query->where('channel', Product::CHANNEL_NONE),
            default => null,
        };

        return [
            'products' => $query->paginate(15),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'saleProduct' => $this->saleProductId
                ? Product::query()->find($this->saleProductId)
                : null,
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Products</flux:heading>
            <flux:text class="mt-1">Products start unassigned. Assign them to Shop, Auction, or both. Create a sale when you want an auction product live.</flux:text>
        </div>
        <flux:button href="{{ route('admin.products.create') }}" wire:navigate class="bg-accent! text-accent-foreground!">
            Add product
        </flux:button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search products…" class="sm:max-w-sm" />
        <flux:select wire:model.live="filterCategoryId" class="sm:max-w-xs">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterChannel" class="sm:max-w-xs">
            <flux:select.option value="all">All channels</flux:select.option>
            <flux:select.option value="shop">Shop products</flux:select.option>
            <flux:select.option value="auction">Auction products</flux:select.option>
            <flux:select.option value="both">Shop &amp; Auction</flux:select.option>
            <flux:select.option value="none">Unassigned</flux:select.option>
        </flux:select>
        <flux:text class="sm:ms-auto">
            {{ $products->total() }} {{ $products->total() === 1 ? 'product' : 'products' }}
        </flux:text>
    </div>

    <flux:modal name="create-sale" wire:close="cancelSaleForm" class="w-full md:max-w-2xl">
        @if ($saleProduct)
        <form wire:submit="createSale" class="space-y-5">
            <div>
                <flux:heading size="lg">Create sale from product</flux:heading>
                <flux:text class="mt-1">{{ $saleProduct->name }} · {{ $saleProduct->stock }} in stock · {{ $saleProduct->reserved_stock }} reserved · retail {{ Money::kes($saleProduct->price) }}</flux:text>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>Bid fee</flux:label>
                    <flux:input type="number" wire:model="saleBidFee" min="{{ CreateSaleFromProductService::MINIMUM_BID_FEE }}" inputmode="numeric" />
                    <flux:description>Minimum {{ Money::kes(CreateSaleFromProductService::MINIMUM_BID_FEE) }}. Leave blank to use 0.1% of the product price ({{ Money::kes(CreateSaleFromProductService::pricingFromProduct($saleProduct)['bid_fee']) }}).</flux:description>
                    <flux:error name="saleBidFee" />
                </flux:field>
                <div class="rounded-lg border border-amber-200/80 bg-white/70 px-3 py-2 dark:border-amber-900 dark:bg-zinc-900/50">
                    <div class="text-xs text-zinc-500">Min bid</div>
                    <div class="mt-1 text-sm font-semibold tabular-nums">{{ Money::kes($saleMinBid) }}</div>
                </div>
                <div class="rounded-lg border border-amber-200/80 bg-white/70 px-3 py-2 dark:border-amber-900 dark:bg-zinc-900/50">
                    <div class="text-xs text-zinc-500">Max bid (10%)</div>
                    <div class="mt-1 text-sm font-semibold tabular-nums">{{ Money::kes($saleMaxBid) }}</div>
                </div>
                <flux:field>
                    <flux:label>Ends in (hours)</flux:label>
                    <flux:input type="number" wire:model="saleEndsInHours" min="1" max="168" />
                    <flux:error name="saleEndsInHours" />
                </flux:field>
            </div>
            <flux:checkbox wire:model="saleReserveStock" label="Reserve 1 unit of shop stock while the sale is live" />
            <flux:checkbox wire:model="saleFeatureOnHomepage" label="Show as homepage hero (replaces any current homepage sale)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Create live sale</flux:button>
            </div>
        </form>
        @endif
    </flux:modal>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Product</th>
                    <th class="px-4 py-3 font-medium">Channel</th>
                    <th class="px-4 py-3 font-medium">Category</th>
                    <th class="px-4 py-3 font-medium">Price</th>
                    <th class="px-4 py-3 font-medium">Stock</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($products as $product)
                    @php
                        $channelColor = match ($product->channel) {
                            Product::CHANNEL_SHOP => 'sky',
                            Product::CHANNEL_AUCTION => 'amber',
                            Product::CHANNEL_BOTH => 'green',
                            default => 'zinc',
                        };
                    @endphp
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->image }}" alt="" class="size-10 rounded object-cover" />
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $product->subcategory }}</div>
                                    @if ($product->is_featured)
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">Featured in shop</span>
                                    @endif
                                    @unless ($product->is_returnable)
                                        <span class="mt-1 inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">Non-returnable</span>
                                    @endunless
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$channelColor">{{ $product->channelLabel() }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $product->category }}</td>
                        <td class="px-4 py-3">{{ Money::kes($product->price) }}</td>
                        <td class="px-4 py-3 text-xs">
                            <div class="font-medium tabular-nums">{{ $product->stock }} in stock</div>
                            @if ($product->reserved_stock > 0)
                                <div class="text-amber-700">{{ $product->reserved_stock }} reserved</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                @unless ($product->isInShop())
                                    <flux:button size="sm" variant="outline" wire:click="moveToShop({{ $product->id }})">
                                        Add to shop
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" wire:click="removeFromShop({{ $product->id }})">
                                        Remove shop
                                    </flux:button>
                                @endunless
                                @if ($product->isInAuctionChannel() && $product->currentSale === null)
                                    <flux:button size="sm" variant="ghost" wire:click="removeFromAuctionChannel({{ $product->id }})">
                                        Remove auction
                                    </flux:button>
                                @endif
                                @if ($product->currentSale?->isOpenLive())
                                    <flux:button size="sm" variant="outline" href="{{ route('admin.auction-bids', $product->currentSale) }}">
                                        View sale
                                    </flux:button>
                                @elseif ($product->currentSale?->needsSettle())
                                    <flux:button size="sm" variant="outline" href="{{ route('admin.auctions', ['status' => 'needs_settle', 'search' => $product->name]) }}">
                                        Settle first
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="outline" wire:click="openSaleForm({{ $product->id }})">
                                        Create sale
                                    </flux:button>
                                @endif
                                <flux:button size="sm" variant="ghost" href="{{ route('admin.products.edit', $product) }}" wire:navigate>Edit</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="delete({{ $product->id }})" wire:confirm="Delete this product?">Del</flux:button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>{{ $products->links() }}</div>
</div>
