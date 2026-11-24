<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerVerificationDocument;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerVerificationDocumentController extends Controller
{
    public function __invoke(Request $request, CustomerVerificationDocument $document): StreamedResponse
    {
        $disk = Storage::disk(CustomerVerificationDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        $download = $request->boolean('download');

        AuditLogger::log('customers', $download ? 'verification document downloaded' : 'verification document viewed', $download ? 'verification_document_downloaded' : 'verification_document_viewed', $document->user, [
            'document_id' => $document->id,
        ]);

        $headers = ['Content-Type' => $document->mime_type];

        return $download
            ? $disk->download($document->path, $document->original_name, $headers)
            : $disk->response($document->path, $document->original_name, $headers);
    }
}
