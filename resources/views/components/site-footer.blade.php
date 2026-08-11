<footer class="mt-16 border-t border-border bg-navy text-navy-foreground">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="grid h-8 w-8 place-items-center rounded-md bg-gold text-gold-foreground">
                    <flux:icon.scale class="size-4" />
                </div>
                <div>
                    <div class="text-base font-bold">UniqBid</div>
                    <div class="text-xs opacity-70">Licensed Auctioneer · Kenya</div>
                </div>
            </div>
            <p class="mt-4 text-sm opacity-80">
                Transparent lowest-unique-bid auctions and a curated accessories shop. Registered under the Auctioneers Act.
            </p>
            <div class="mt-4 flex items-center gap-2 text-xs opacity-80">
                <flux:icon.shield-check class="size-4 text-gold" />
                Licence No. AUC/2024/00847
            </div>
        </div>

        <div>
            <div class="mb-3 text-xs font-semibold uppercase tracking-wider opacity-70">Auctions</div>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('auctions.index') }}" class="opacity-90 hover:text-gold">Live Auctions</a></li>
                <li><a href="{{ route('how-it-works') }}" class="opacity-90 hover:text-gold">How It Works</a></li>
                <li><a href="{{ route('past-sales') }}" class="opacity-90 hover:text-gold">Past Sales</a></li>
                <li><a href="{{ route('testimonials') }}" class="opacity-90 hover:text-gold">Testimonials</a></li>
            </ul>
        </div>

        <div>
            <div class="mb-3 text-xs font-semibold uppercase tracking-wider opacity-70">Shop & Support</div>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('shop.index') }}" class="opacity-90 hover:text-gold">Shop</a></li>
                <li><a href="{{ route('account') }}" class="opacity-90 hover:text-gold">My Account</a></li>
                <li><a href="{{ route('contact') }}" class="opacity-90 hover:text-gold">Contact Support</a></li>
                <li><a href="{{ route('legal') }}" class="opacity-90 hover:text-gold">Terms & Privacy</a></li>
            </ul>
        </div>

        <div>
            <div class="mb-3 text-xs font-semibold uppercase tracking-wider opacity-70">Get in touch</div>
            <a href="tel:+254700000000" class="flex items-center gap-2 text-sm opacity-90 hover:text-gold">
                <flux:icon.phone class="size-4" />
                +254 700 000 000
            </a>
            <a href="https://wa.me/254700000000" class="mt-2 flex items-center gap-2 text-sm opacity-90 hover:text-gold">
                <flux:icon.chat-bubble-left-right class="size-4" />
                WhatsApp Support
            </a>
            <p class="mt-3 text-xs opacity-70">Mon–Sat, 8:00 – 18:00 EAT</p>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-2 px-4 py-4 text-xs opacity-70 sm:flex-row sm:items-center">
            <div>&copy; {{ now()->year }} UniqBid Auction Ltd. All rights reserved.</div>
            <div>Data protected under the Kenya Data Protection Act, 2019.</div>
        </div>
    </div>
</footer>
