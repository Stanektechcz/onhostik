<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ServiceContractController extends Controller
{
    public function download(Service $service): Response
    {
        $service->load('customer', 'product', 'orderItem.pricingPlan');

        $pdf = Pdf::loadView('pdf.service-contract', compact('service'));

        $filename = 'smlouva-' . str_replace(['/', ' '], '-', $service->label) . '-' . $service->id . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
