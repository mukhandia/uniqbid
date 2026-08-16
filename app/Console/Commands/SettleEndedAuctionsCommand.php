<?php

namespace App\Console\Commands;

use App\Services\AuctionSettlementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('auctions:settle-ended')]
#[Description('Settle ended UniqBid sales using the lowest unique accepted bid')]
class SettleEndedAuctionsCommand extends Command
{
    public function handle(AuctionSettlementService $settlement): int
    {
        $count = $settlement->settleEnded();

        $this->info("Settled {$count} ended sale(s).");

        return self::SUCCESS;
    }
}
