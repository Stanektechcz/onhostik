<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Http\Controllers\Controller;
use App\Models\EntityNote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One controller for internal notes on any entity (audit 75).
 *
 * The entity is addressed by a SLUG, not a class name — a request can only
 * touch a type on this allow-list, so no attacker can craft a note against an
 * arbitrary Eloquent model by naming its class. Everything is admin-gated by
 * the route group.
 */
final class EntityNoteController extends Controller
{
    /**
     * Slug → model class. The only entities notes may be attached to via this
     * generic endpoint. Add a row here to let a new entity adopt notes.
     *
     * @var array<string, class-string<Model>>
     */
    private const NOTABLES = [
        'order'   => Order::class,
        'invoice' => Invoice::class,
    ];

    public function store(Request $request, string $type, string $id): RedirectResponse
    {
        $notable = $this->resolveNotable($type, $id);

        $validated = $request->validate([
            'body'      => ['required', 'string', 'max:5000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $notable->morphMany(EntityNote::class, 'notable')->create([
            'author_id' => $request->user()?->id,
            'body'      => $validated['body'],
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
        ]);

        return back()->with('status', 'Poznámka byla přidána.');
    }

    public function destroy(Request $request, string $type, string $id, EntityNote $note): RedirectResponse
    {
        $notable = $this->resolveNotable($type, $id);
        $this->assertBelongsTo($note, $notable);

        $note->delete();

        return back()->with('status', 'Poznámka byla smazána.');
    }

    public function pin(Request $request, string $type, string $id, EntityNote $note): RedirectResponse
    {
        $notable = $this->resolveNotable($type, $id);
        $this->assertBelongsTo($note, $notable);

        // Toggle — the same button pins and unpins.
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return back()->with('status', $note->is_pinned ? 'Poznámka připnuta.' : 'Poznámka odepnuta.');
    }

    private function resolveNotable(string $type, string $id): Model
    {
        $class = self::NOTABLES[$type] ?? abort(404);

        /** @var Model $model */
        $model = $class::query()->where((new $class)->getRouteKeyName(), $id)->firstOrFail();

        return $model;
    }

    /**
     * A note carries its own owner; refuse to act on one that does not belong
     * to the entity in the URL, so a valid note id cannot be moved or deleted
     * through the wrong parent.
     */
    private function assertBelongsTo(EntityNote $note, Model $notable): void
    {
        abort_unless(
            $note->notable_type === $notable->getMorphClass()
                && (string) $note->notable_id === (string) $notable->getKey(),
            404,
        );
    }
}
