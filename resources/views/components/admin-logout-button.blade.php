@props([
    'wide' => true,
])

<form method="POST" action="{{ route('logout') }}" {{ $attributes }}>
    @csrf
    <flux:button
        type="submit"
        variant="ghost"
        icon="arrow-right-start-on-rectangle"
        class="cursor-pointer {{ $wide ? 'w-full' : 'px-2' }}"
        data-test="logout-button"
    >
        Log out
    </flux:button>
</form>
