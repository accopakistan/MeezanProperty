<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DealerDocument;
use App\Services\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin view of a dealer's verification document; every view is audited. */
class DealerDocumentController extends Controller
{
    public function show(DealerDocument $document): StreamedResponse
    {
        AuditLogger::record('dealer.document_viewed', $document->profile, ['document_id' => $document->id, 'type' => $document->type]);

        return $document->streamResponse();
    }
}
