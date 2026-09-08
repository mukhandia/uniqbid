<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LiveAuctionOrderingService
{
    /**
     * @return Builder<Auction>
     */
    public function liveQuery(): Builder
    {
        return Auction::query()->with('images')->liveOnAuctionChannel();
    }

    /**
     * @return Collection<int, Auction>
     */
    public function orderedLive(?int $limit = null): Collection
    {
        $settings = SiteSetting::current();
        $auctions = $this->liveQuery()->orderBy('id')->get();
        $ordered = $this->autoShuffle($auctions, $settings->auctionShuffleIntervalSeconds());

        return $limit === null ? $ordered : $ordered->take($limit)->values();
    }

    /**
     * @param  Collection<int, Auction>  $auctions
     * @return Collection<int, Auction>
     */
    public function autoShuffle(Collection $auctions, int $intervalSeconds): Collection
    {
        $intervalSeconds = max(5, $intervalSeconds);
        $bucket = intdiv(now()->getTimestamp(), $intervalSeconds);
        $items = $auctions->values();
        $count = $items->count();

        if ($count <= 1) {
            return $items;
        }

        return $items
            ->sortBy(function (Auction $auction) use ($bucket): string {
                $rank = crc32($auction->id.'|'.$bucket);

                return sprintf('%010u-%010d', $rank, $auction->id);
            })
            ->values();
    }
}
