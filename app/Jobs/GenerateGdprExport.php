<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GdprExportRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use ZipArchive;

class GenerateGdprExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private readonly GdprExportRequest $exportRequest) {}

    public function handle(): void
    {
        $this->exportRequest->update(['status' => 'processing']);

        try {
            /** @var User $user */
            $user     = $this->exportRequest->user;
            $customer = $user->customer;

            $dir = storage_path('app/gdpr');
            if (! is_dir($dir)) {
                mkdir($dir, 0750, true);
            }

            $path = $dir . '/' . $this->exportRequest->id . '.zip';
            $zip  = new ZipArchive();
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $zip->addFromString('profile.json', (string) json_encode([
                'name'       => $user->name,
                'email'      => $user->email,
                'created_at' => $user->created_at?->toISOString(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($customer !== null) {
                $invoices = $customer->invoices()->latest()->get()->map(fn ($i) => [
                    'number'  => $i->number,
                    'total'   => (string) $i->total,
                    'due_date' => $i->due_date?->toDateString(),
                    'status'  => $i->status->value,
                ]);
                $zip->addFromString('invoices.json', (string) json_encode($invoices, JSON_PRETTY_PRINT));

                $services = $customer->services()->get()->map(fn ($s) => [
                    'label'  => $s->label,
                    'status' => $s->status->value,
                ]);
                $zip->addFromString('services.json', (string) json_encode($services, JSON_PRETTY_PRINT));
            }

            $zip->close();

            $token = Str::random(64);
            $this->exportRequest->update([
                'status'         => 'ready',
                'download_token' => $token,
                'expires_at'     => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            $this->exportRequest->update(['status' => 'failed']);
            throw $e;
        }
    }
}
