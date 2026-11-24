<?php

test('login screen uses the uniqbid brand mark', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('/images/brand/uniqbid-icon.jpeg', false)
        ->assertDontSee('M17.2 5.633', false);
});
