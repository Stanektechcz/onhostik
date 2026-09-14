<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Files\FileStore;
use Symfony\Component\HttpFoundation\Response;

/** The signed download of a data export (audit §5j-7): no session, the signature and the hashed token decide. */
final class DataExportController extends Controller
{
    public function download(ComplianceService $compliance, FileStore $files, string $dataRequest, string $token): Response
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

        return $files->download($path, "onhost-export-{$request->id}.json", 'application/json', ['X-Robots-Tag' => 'noindex']); // a signed S3 link or a stream (audit §5q-4)
    }
}
