<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicSeoMetadata
{
    /** @return array{title: string, description: string, canonical: string} */
    public static function forRequest(Request $request): array
    {
        $route = $request->route();
        $name = $route->getName() ?? '';
        $title = match ($name) {
            'home' => 'UniqBid Kenya | Lowest-Unique Auctions & Online Shop',
            'auctions.index' => 'Live Auctions in Kenya | UniqBid',
            'shop.index' => 'Shop Online in Kenya | UniqBid',
            'past-sales' => 'Past Auction Sales & Results | UniqBid Kenya',
            'how-it-works' => 'How Lowest-Unique Auctions Work | UniqBid Kenya',
            'faqs' => 'FAQs: Auctions, Payments & Shopping | UniqBid',
            'contact' => 'Contact UniqBid Kenya | Customer Support',
            'sell' => 'Sell on UniqBid | Online Marketplace Kenya',
            'testimonials' => 'Customer Stories | UniqBid Kenya',
            'legal' => 'Legal, Terms & Privacy | UniqBid Kenya',
            'auctions.show' => self::auctionTitle($route->parameter('auction')),
            'shop.show' => self::productTitle($route->parameter('product')),
            default => trim((string) ($request->attributes->get('page_title') ?? config('app.name', 'UniqBid'))),
        };

        $description = match ($name) {
            'home' => 'Discover lowest-unique auctions and shop online in Kenya. Explore live sales, learn how participation works, and browse products on UniqBid.',
            'auctions.index' => 'Browse live lowest-unique auctions in Kenya. View item details, participation fees, closing times, and auction rules.',
            'shop.index' => 'Browse products available from the UniqBid online shop. Explore product details, prices, and ordering information.',
            'past-sales' => 'Browse past UniqBid sales and results, with completed auction records and sale outcomes.',
            'how-it-works' => 'Learn how UniqBid lowest-unique auctions work, including entry fees, M-Pesa payment, winner selection, and fulfilment.',
            'faqs' => 'Find answers about UniqBid auctions, participation fees, M-Pesa payments, orders, delivery, and support.',
            'contact' => 'Contact UniqBid for help with auctions, shop orders, payments, or selling on the marketplace.',
            'sell' => 'Learn how to list products for sale on UniqBid and review seller requirements and marketplace information.',
            'testimonials' => 'Read customer experiences with UniqBid auctions and online shopping in Kenya.',
            'legal' => 'Read UniqBid terms, privacy information, and legal and compliance policies.',
            'auctions.show' => self::auctionDescription($route->parameter('auction')),
            'shop.show' => self::productDescription($route->parameter('product')),
            default => 'Explore auctions, products, and services from UniqBid Kenya.',
        };

        $canonical = match ($name) {
            'home' => route('home'),
            'auctions.index' => route('auctions.index'),
            'auctions.show' => $route->parameter('auction') instanceof Auction ? route('auctions.show', $route->parameter('auction')) : $request->url(),
            'shop.index' => route('shop.index'),
            'shop.show' => $route->parameter('product') instanceof Product ? route('shop.show', $route->parameter('product')) : $request->url(),
            'past-sales', 'how-it-works', 'faqs', 'contact', 'sell', 'testimonials', 'legal' => route($name),
            default => $request->url(),
        };

        return ['title' => $title, 'description' => $description, 'canonical' => $canonical];
    }

    private static function auctionTitle(mixed $auction): string
    {
        return $auction instanceof Auction
            ? $auction->name.' | Lowest-Unique Auction in Kenya | UniqBid'
            : 'Auction | UniqBid Kenya';
    }

    private static function auctionDescription(mixed $auction): string
    {
        if (! $auction instanceof Auction) {
            return 'View auction details and participation information on UniqBid Kenya.';
        }

        $state = $auction->settled_at ? 'View the completed sale record' : 'Explore this live auction';

        return str($state.' for '.$auction->name.'. Review the item details, participation fee, auction rules, and sale information on UniqBid.')
            ->limit(160, '')
            ->toString();
    }

    private static function productTitle(mixed $product): string
    {
        return $product instanceof Product ? $product->name.' | Buy Online in Kenya | UniqBid Shop' : 'Product | UniqBid Shop Kenya';
    }

    private static function productDescription(mixed $product): string
    {
        if (! $product instanceof Product) {
            return 'View product details and ordering information from the UniqBid online shop.';
        }

        return str($product->name.'. '.$product->description.' Shop online in Kenya with UniqBid.')
            ->squish()
            ->limit(160, '')
            ->toString();
    }
}
