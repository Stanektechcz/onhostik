<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The signed download of a data export (audit §5j-7): no session, the signature and the hashed token decide. */
final class DataExportController extends Controller
{
    public function download(ComplianceService $compliance, string $dataRequest, string $token): StreamedResponse
    {
        $request = DataRequest::query()->find($dataRequest);
        if ($request === null) {
            abort(404);
        }
        try {
            $path = $compliance->redeemLink($request, $token);
        } catch (DomainError $e) {
            abort($e->status, $e->getMessage());
        }

        return Storage::disk('local')->download($path, "onhost-export-{$request->id}.json", ['Content-Type' => 'application/json', 'X-Robots-Tag' => 'noindex']);
    }
}
