<?php

use App\Models\SupportTicket;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Contact & Support — UniqBid')]
class extends Component {
    public string $name = '';

    public string $contact = '';

    public string $subject = '';

    public string $message = '';

    public ?string $ticketRef = null;

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2'],
            'contact' => ['required', 'string', 'min:5'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10'],
        ]);

        $isEmail = str_contains($this->contact, '@');

        $ticket = SupportTicket::query()->create([
            'name' => $this->name,
            'email' => $isEmail ? $this->contact : null,
            'phone' => $isEmail ? null : $this->contact,
            'subject' => filled($this->subject) ? $this->subject : 'General enquiry',
            'message' => $this->message,
            'status' => 'open',
        ]);

        $this->ticketRef = 'TKT'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT);

        Flux::toast(variant: 'success', text: 'Support ticket '.$this->ticketRef.' created.');
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'contact', 'subject', 'message', 'ticketRef']);
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-10">
    <h1 class="text-3xl font-bold text-navy">Contact & Support</h1>
    <p class="mt-2 text-sm text-muted-foreground">Reach the UniqBid team directly. We reply within one business day.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="rounded-2xl border border-border bg-white p-6">
            @if ($ticketRef)
                <div class="py-6 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-success/15">
                        <flux:icon.check-circle class="size-6 text-success" />
                    </div>
                    <h2 class="mt-3 text-lg font-bold text-navy">Support ticket created</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Your reference is <span class="font-mono font-semibold text-zinc-900">{{ $ticketRef }}</span>.
                    </p>
                    <flux:button wire:click="resetForm" class="mt-6 bg-accent! text-accent-foreground!">Send another message</flux:button>
                </div>
            @else
                <h2 class="text-base font-semibold">Send us a message</h2>
                <form wire:submit="submit" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <flux:field class="sm:col-span-1">
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>
                    <flux:field class="sm:col-span-1">
                        <flux:label>Email or phone</flux:label>
                        <flux:input wire:model="contact" placeholder="e.g. 0712345678 or you@example.com" />
                        <flux:error name="contact" />
                    </flux:field>
                    <flux:field class="sm:col-span-2">
                        <flux:label>Subject</flux:label>
                        <flux:input wire:model="subject" placeholder="What's this about?" />
                    </flux:field>
                    <flux:field class="sm:col-span-2">
                        <flux:label>Message</flux:label>
                        <flux:textarea wire:model="message" rows="5" />
                        <flux:error name="message" />
                    </flux:field>
                    <div class="sm:col-span-2">
                        <flux:button type="submit" class="bg-accent! text-accent-foreground!">Create support ticket</flux:button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-border bg-white p-5">
                <h3 class="text-sm font-semibold">Quick contact</h3>
                <a href="tel:+254700000000" class="mt-3 flex items-center gap-3 text-sm hover:text-navy">
                    <flux:icon.phone class="size-4 text-navy" /> +254 700 000 000
                </a>
                <a href="https://wa.me/254700000000" class="mt-2 flex items-center gap-3 text-sm hover:text-navy">
                    <flux:icon.chat-bubble-left-right class="size-4 text-success" /> WhatsApp Support
                </a>
                <a href="mailto:support@uniqbid.co.ke" class="mt-2 flex items-center gap-3 text-sm hover:text-navy">
                    <flux:icon.envelope class="size-4 text-navy" /> support@uniqbid.co.ke
                </a>
            </div>
            <div class="rounded-2xl border border-border bg-white p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold">
                    <flux:icon.map-pin class="size-4 text-navy" /> Office
                </h3>
                <p class="mt-2 text-sm text-zinc-800">Westlands Business Centre, 3rd Floor<br>Waiyaki Way, Nairobi</p>
                <p class="mt-2 text-xs text-muted-foreground">Mon–Sat, 8:00 – 18:00 EAT</p>
                <div class="mt-3 aspect-[4/3] w-full overflow-hidden rounded-lg border border-border bg-gradient-to-br from-navy/10 via-muted to-gold/20">
                    <div class="grid h-full place-items-center text-xs text-muted-foreground">Map placeholder</div>
                </div>
            </div>
        </aside>
    </div>
</div>
