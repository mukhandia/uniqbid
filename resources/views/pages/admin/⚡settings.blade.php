<?php

use App\Models\SiteSetting;
use App\Support\MapEmbedUrl;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Settings')]
class extends Component {
    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $address = '';

    public string $hours = '';

    public string $mapUrl = '';

    public function mount(): void
    {
        $settings = SiteSetting::current();

        $this->phone = $settings->phone;
        $this->whatsapp = $settings->whatsapp;
        $this->email = $settings->email;
        $this->address = $settings->address;
        $this->hours = $settings->hours;
        $this->mapUrl = (string) $settings->map_url;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'phone' => ['required', 'string', 'max:50'],
            'whatsapp' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'hours' => ['required', 'string', 'max:120'],
            'mapUrl' => ['nullable', 'string', 'max:4000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! MapEmbedUrl::isAllowedShare(is_string($value) ? $value : null)) {
                    $fail('Paste a Google Maps or OpenStreetMap share or embed URL.');
                }
            }],
        ]);

        $settings = SiteSetting::current();
        $settings->update([
            'phone' => $validated['phone'],
            'whatsapp' => $validated['whatsapp'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'hours' => $validated['hours'],
            'map_url' => filled($validated['mapUrl'] ?? null) ? $validated['mapUrl'] : null,
        ]);

        Flux::toast(variant: 'success', text: 'Settings saved.');
    }

    public function with(): array
    {
        return [
            'mapPreviewUrl' => MapEmbedUrl::embed($this->mapUrl, $this->address),
        ];
    }
}; ?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl">Settings</flux:heading>
        <flux:text class="mt-1">Company contact details appear on the public Contact page and footer. Your login profile, password, and appearance are separate.</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Contact & location</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Phone</flux:label>
                <flux:input wire:model="phone" />
                <flux:error name="phone" />
            </flux:field>
            <flux:field>
                <flux:label>WhatsApp number</flux:label>
                <flux:input wire:model="whatsapp" />
                <flux:description>Digits with country code, for example 254700000000.</flux:description>
                <flux:error name="whatsapp" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>Support email</flux:label>
                <flux:input type="email" wire:model="email" />
                <flux:error name="email" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>Office address</flux:label>
                <flux:textarea wire:model.live.debounce.400ms="address" rows="3" />
                <flux:error name="address" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>Office hours</flux:label>
                <flux:input wire:model="hours" />
                <flux:error name="hours" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>Map share URL</flux:label>
                <flux:input wire:model.live.debounce.400ms="mapUrl" placeholder="https://maps.app.goo.gl/… or Google Maps embed URL" />
                <flux:description>Paste a Google Maps or OpenStreetMap share link, or the iframe embed HTML. Short links still show a map using the office address.</flux:description>
                <flux:error name="mapUrl" />
            </flux:field>
        </div>

        <div>
            <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Map preview</p>
            <x-office-map :embed-url="$mapPreviewUrl" class="max-w-3xl" />
        </div>

        <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save settings</flux:button>
    </form>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Your account</flux:heading>
        <flux:text class="mt-1">These pages update the signed-in admin user, not the public contact details above.</flux:text>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <a href="{{ route('profile.edit') }}" wire:navigate class="rounded-lg border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700">
                <div class="font-medium">Profile</div>
                <div class="mt-1 text-sm text-zinc-500">Name and email</div>
            </a>
            <a href="{{ route('security.edit') }}" wire:navigate class="rounded-lg border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700">
                <div class="font-medium">Security</div>
                <div class="mt-1 text-sm text-zinc-500">Password</div>
            </a>
            <a href="{{ route('appearance.edit') }}" wire:navigate class="rounded-lg border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700">
                <div class="font-medium">Appearance</div>
                <div class="mt-1 text-sm text-zinc-500">Light or dark mode</div>
            </a>
        </div>
    </div>
</div>
