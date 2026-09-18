<?php

use App\Models\Faq;
use App\Models\LegalSection;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access content admin pages', function () {
    foreach (['admin.testimonials', 'admin.legal', 'admin.how-it-works', 'admin.faqs'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
});

test('admin sidebar shows content modules', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Content')
        ->assertSee('Testimonials')
        ->assertSee('Legal')
        ->assertSee('How it works')
        ->assertSee('FAQs');
});

test('admin can create update and delete testimonials', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.testimonials')
        ->call('openCreate')
        ->set('name', 'Jane Doe')
        ->set('location', 'Nairobi')
        ->set('role', 'Shop customer')
        ->set('item', 'Gift hamper')
        ->set('quote', 'Great shop experience from start to finish.')
        ->set('rating', 5)
        ->call('save')
        ->assertHasNoErrors();

    $testimonial = Testimonial::query()->where('name', 'Jane Doe')->first();
    expect($testimonial)->not->toBeNull();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.testimonials')
        ->call('edit', $testimonial->id)
        ->set('quote', 'Updated quote text.')
        ->call('save')
        ->assertHasNoErrors();

    expect($testimonial->fresh()->quote)->toBe('Updated quote text.');

    Livewire::actingAs($this->admin)
        ->test('pages::admin.testimonials')
        ->call('delete', $testimonial->id);

    expect(Testimonial::query()->whereKey($testimonial->id)->exists())->toBeFalse();
});

test('admin can manage faqs', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.faqs')
        ->call('openCreate')
        ->set('category', Faq::CATEGORY_GENERAL)
        ->set('questionEn', 'How do I contact UniqBid?')
        ->set('answerEn', 'Use the Contact page or WhatsApp support.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('How do I contact UniqBid?');
});

test('admin can update legal settings and clauses', function () {
    $section = LegalSection::query()->where('slug', 'terms')->firstOrFail();
    $clause = $section->clauses()->firstOrFail();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.legal')
        ->set('version', '2.2')
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->call('selectSection', $section->id)
        ->call('editClause', $clause->id)
        ->set('clauseBody', 'Updated legal clause body for testing.')
        ->call('saveClause')
        ->assertHasNoErrors();

    expect($clause->fresh()->body)->toBe('Updated legal clause body for testing.');

    $this->get(route('legal'))
        ->assertOk()
        ->assertSee('Updated legal clause body for testing.');
});

test('admin can update how it works copy', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.how-it-works')
        ->set('introEn', 'Updated intro for how UniqBid works.')
        ->call('saveSettings')
        ->assertHasNoErrors();

    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertSee('Updated intro for how UniqBid works.');
});

test('public testimonials only show published items', function () {
    $hidden = Testimonial::query()->firstOrFail();
    $hidden->update(['is_published' => false]);

    $this->get(route('testimonials'))
        ->assertOk()
        ->assertDontSee($hidden->name);
});

test('re-running content seeder syncs the promotions privacy clause without failing', function () {
    $clause = \App\Models\LegalClause::query()
        ->where('reference', '3.3–3.6 Purpose, payments and sharing')
        ->firstOrFail();

    $clause->update(['body' => 'Stale privacy body.']);

    $this->seed(ContentSeeder::class);

    expect($clause->fresh()->body)->toContain('You may manage preferences later in your account.');
});
