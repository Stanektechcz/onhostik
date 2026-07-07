<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\Locale;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;

class CustomerImportController extends Controller
{
    private const MAX_ROWS  = 500;
    private const DELIMITER = ',';

    public function create(): View
    {
        return view('admin.customer-import');
    }

    public function store(Request $request): RedirectResponse|View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => 'Soubor nelze číst.']);
        }

        // Read header row
        $rawHeaders = fgetcsv($handle, 0, self::DELIMITER);

        if ($rawHeaders === false) {
            fclose($handle);
            return back()->withErrors(['file' => 'CSV soubor je prázdný nebo nemá záhlaví.']);
        }

        $headers = array_map(fn (string $h) => mb_strtolower(trim($h)), $rawHeaders);

        $nameIdx        = array_search('name', $headers, true);
        $emailIdx       = array_search('email', $headers, true);
        $companyIdx     = array_search('company_name', $headers, true);
        $countryIdx     = array_search('country_code', $headers, true);
        $phoneIdx       = array_search('phone', $headers, true);
        $typeIdx        = array_search('type', $headers, true);

        if ($nameIdx === false || $emailIdx === false) {
            fclose($handle);
            return back()->withErrors(['file' => 'CSV musí obsahovat sloupce "name" a "email".']);
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
            $rowNum++;

            if ($rowNum > self::MAX_ROWS + 1) {
                $errors[] = "Importováno pouze prvních " . self::MAX_ROWS . " řádků.";
                break;
            }

            $name        = trim((string) ($row[$nameIdx]    ?? ''));
            $email       = trim((string) ($row[$emailIdx]   ?? ''));
            $companyName = $companyIdx !== false ? trim((string) ($row[$companyIdx] ?? '')) : '';
            $countryCode = $countryIdx !== false ? strtoupper(trim((string) ($row[$countryIdx] ?? 'CZ'))) : 'CZ';
            $phone       = $phoneIdx !== false ? trim((string) ($row[$phoneIdx] ?? '')) : '';
            $type        = $typeIdx !== false ? trim((string) ($row[$typeIdx] ?? 'person')) : 'person';

            if ($name === '' || $email === '') {
                $errors[] = "Řádek {$rowNum}: prázdné jméno nebo e-mail — přeskočeno.";
                $skipped++;
                continue;
            }

            $v = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
            if ($v->fails()) {
                $errors[] = "Řádek {$rowNum}: neplatný e-mail '{$email}' - přeskočeno.";
                $skipped++;
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($name, $email, $companyName, $countryCode, $phone, $type): void {
                    $user = User::create([
                        'name'      => $name,
                        'email'     => $email,
                        'password'  => Hash::make(Str::random(32)),
                        'locale'    => 'cs',
                        'is_active' => true,
                    ]);

                    Customer::create([
                        'user_id'            => $user->id,
                        'email'              => $email,
                        'company_name'       => $companyName ?: null,
                        'phone'              => $phone ?: null,
                        'type'               => in_array($type, ['person', 'company'], true) ? $type : 'person',
                        'country_code'       => $countryCode ?: 'CZ',
                        'preferred_currency' => Currency::CZK,
                        'preferred_locale'   => Locale::Czech,
                    ]);
                });

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Řádek {$rowNum}: chyba při vytváření záznamu — " . Str::limit($e->getMessage(), 80);
                $skipped++;
            }
        }

        fclose($handle);

        return view('admin.customer-import', compact('imported', 'skipped', 'errors'));
    }
}
