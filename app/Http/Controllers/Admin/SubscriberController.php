<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $filter = $request->string('status')->toString();

        $subscribers = Subscriber::query()
            ->when($search !== '', fn ($q) => $q->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            ->when($filter === 'active', fn ($q) => $q->active())
            ->when($filter === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $totalCount       = Subscriber::count();
        $activeCount      = Subscriber::active()->count();
        $unsubscribedCount = Subscriber::whereNotNull('unsubscribed_at')->count();

        return view('admin.subscribers', compact(
            'subscribers', 'search', 'filter',
            'totalCount', 'activeCount', 'unsubscribedCount'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'  => ['required', 'email', 'max:180', 'unique:subscribers,email'],
            'name'   => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'in:cs,en'],
        ]);

        Subscriber::create([
            'email'        => strtolower($validated['email']),
            'name'         => $validated['name'] ?? null,
            'locale'       => $validated['locale'] ?? 'cs',
            'source'       => 'admin',
            'confirmed_at' => now(),
            'is_active'    => true,
        ]);

        return back()->with('status', "Odběratel {$validated['email']} byl přidán.");
    }

    public function toggle(Subscriber $subscriber): RedirectResponse
    {
        if ($subscriber->is_active) {
            $subscriber->update(['is_active' => false, 'unsubscribed_at' => now()]);
            $msg = "Odběratel {$subscriber->email} byl odhlášen.";
        } else {
            $subscriber->update(['is_active' => true, 'unsubscribed_at' => null]);
            $msg = "Odběratel {$subscriber->email} byl znovu aktivován.";
        }

        return back()->with('status', $msg);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;
        $subscriber->delete();

        return back()->with('status', "Odběratel {$email} byl smazán.");
    }

    public function export(): StreamedResponse
    {
        $filename = 'odberatele-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) return;

            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['E-mail', 'Jméno', 'Jazyk', 'Aktivní', 'Potvrzen', 'Zdroj', 'Vytvořen'], ';');

            Subscriber::orderBy('email')->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->email,
                        $row->name ?? '',
                        $row->locale,
                        $row->is_active ? 'ano' : 'ne',
                        $row->confirmed_at?->format('d.m.Y') ?? '',
                        $row->source,
                        $row->created_at?->format('d.m.Y') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
