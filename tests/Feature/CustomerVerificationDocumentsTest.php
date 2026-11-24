<?php

use App\Models\CustomerVerificationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(CustomerVerificationDocument::DISK);

    $this->customer = User::factory()->create([
        'name' => 'Wanjiru Kamau',
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);
    $this->admin = User::factory()->admin()->create();
});

test('customers can upload pdf and image documents for verification', function () {
    Livewire::actingAs($this->customer)
        ->test('pages::account')
        ->assertSee('ID / KRA verification')
        ->assertSee('Not submitted')
        ->set('verificationFiles', [
            UploadedFile::fake()->create('national-id.pdf', 800, 'application/pdf'),
            UploadedFile::fake()->image('kra-pin.jpg'),
        ])
        ->call('submitVerification')
        ->assertHasNoErrors()
        ->assertSee('national-id.pdf')
        ->assertSee('kra-pin.jpg')
        ->assertSee('Submitted, awaiting review');

    $documents = $this->customer->verificationDocuments()->get();

    expect($documents)->toHaveCount(2)
        ->and($this->customer->customerControl()->first()->verification_status)->toBe('submitted')
        ->and($this->customer->customerControl()->first()->verification_submitted_at)->not->toBeNull();

    $documents->each(fn (CustomerVerificationDocument $document) => Storage::disk(CustomerVerificationDocument::DISK)->assertExists($document->path));
});

test('verification uploads reject other file types, large files and too many files', function (array $files, string $errorKey) {
    Livewire::actingAs($this->customer)
        ->test('pages::account')
        ->set('verificationFiles', $files)
        ->call('submitVerification')
        ->assertHasErrors([$errorKey]);

    expect($this->customer->verificationDocuments()->count())->toBe(0);
})->with([
    'word document' => fn () => [[UploadedFile::fake()->create('id.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')], 'verificationFiles.0'],
    'over 5 MB' => fn () => [[UploadedFile::fake()->create('id.pdf', 6000, 'application/pdf')], 'verificationFiles.0'],
    'more than 4 files' => fn () => [array_map(fn (int $i) => UploadedFile::fake()->image("doc-{$i}.png"), range(1, 5)), 'verificationFiles'],
    'nothing chosen' => fn () => [[], 'verificationFiles'],
]);

test('customers can remove a document before it is verified', function () {
    $component = Livewire::actingAs($this->customer)
        ->test('pages::account')
        ->set('verificationFiles', [UploadedFile::fake()->image('blurry-id.jpg')])
        ->call('submitVerification');

    $document = $this->customer->verificationDocuments()->sole();

    $component->call('removeVerificationDocument', $document->id)
        ->assertDontSee('blurry-id.jpg');

    expect(CustomerVerificationDocument::query()->count())->toBe(0)
        ->and($this->customer->customerControl()->first()->verification_status)->toBe('not_requested');
    Storage::disk(CustomerVerificationDocument::DISK)->assertMissing($document->path);
});

test('verified customers no longer see the upload form', function () {
    $this->customer->customerControl()->create(['verification_status' => 'verified']);

    Livewire::actingAs($this->customer)
        ->test('pages::account')
        ->assertSee('Verified')
        ->assertDontSee('Submit for verification');
});

test('admins see submitted documents and can view and download them', function () {
    Livewire::actingAs($this->customer)
        ->test('pages::account')
        ->set('verificationFiles', [UploadedFile::fake()->create('national-id.pdf', 200, 'application/pdf')])
        ->call('submitVerification');

    $document = $this->customer->verificationDocuments()->sole();

    $this->actingAs($this->admin)
        ->get(route('admin.customer-verifications'))
        ->assertOk()
        ->assertSee('Wanjiru Kamau')
        ->assertSee('national-id.pdf')
        ->assertSee(route('admin.verification-documents.show', $document), false)
        ->assertDontSee('← Sales');

    $this->get(route('admin.verification-documents.show', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('admin.verification-documents.show', ['document' => $document, 'download' => 1]))
        ->assertOk()
        ->assertDownload('national-id.pdf');
});

test('customers and guests cannot open verification documents', function () {
    $document = $this->customer->verificationDocuments()->create([
        'path' => 'verification-documents/'.$this->customer->id.'/id.pdf',
        'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1000,
    ]);
    Storage::disk(CustomerVerificationDocument::DISK)->put($document->path, 'pdf');

    $this->get(route('admin.verification-documents.show', $document))->assertRedirect(route('login'));

    $this->actingAs($this->customer)
        ->get(route('admin.verification-documents.show', $document))
        ->assertForbidden();
});

test('submitted customers are listed first and can be filtered', function () {
    $waiting = User::factory()->create(['name' => 'Waiting Review', 'phone' => '254733333333']);
    $waiting->customerControl()->create(['verification_status' => 'submitted', 'verification_submitted_at' => now()]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer-verifications')
        ->assertSeeInOrder(['Waiting Review', 'Wanjiru Kamau'])
        ->set('status', 'submitted')
        ->assertSee('Waiting Review')
        ->assertDontSee('Wanjiru Kamau');
});

test('admin sidebar shows customer verification instead of subscribers', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.customers'))
        ->assertOk()
        ->assertSee('Customer verification')
        ->assertDontSee('Subscribers')
        ->assertDontSee('← Sales');
});
