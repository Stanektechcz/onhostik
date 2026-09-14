<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** Registrant/admin/tech contact synced to the registrar (per-TLD schema, blueprint §46.1). */
final class RegistrarContact extends Model
{
    protected static string $idPrefix = 'rct';

    protected $table = 'registrar_contacts';

    protected function casts(): array
    {
        return ['registry_fields' => 'array'];
    }

    /**
     * Contact handles are registrar-specific: the copy of this contact synced at `$provider`
     * (created on first use, linked through `source_contact_id`).
     */
    public function siblingFor(string $provider): self
    {
        if ($this->registrar_provider === $provider) {
            return $this;
        }
        $rootId = $this->source_contact_id ?: $this->id;
        $existing = self::query()->where('registrar_provider', $provider)->where(fn ($q) => $q->where('id', $rootId)->orWhere('source_contact_id', $rootId))->orderBy('created_at')->first();
        if ($existing !== null) {
            return $existing;
        }

        $source = $this->fresh() ?? $this; // column defaults (schema, privacy, country) are only known after a reload
        $copy = array_intersect_key($source->getAttributes(), array_flip(['organization_id', 'schema', 'kind', 'name', 'organization_name', 'email', 'phone', 'street', 'city', 'postal_code', 'country', 'ico', 'dic', 'privacy']));

        return self::query()->create($copy + ['registrar_provider' => $provider, 'source_contact_id' => $rootId, 'state' => 'draft', 'remote_id' => null, 'registry_fields' => $source->registry_fields]);
    }

    public function isSynced(): bool
    {
        return $this->state === 'synced' && $this->remote_id !== null;
    }

    /** Provider-facing payload (mapped in the adapter to WAPI fields). */
    public function toProviderContact(string $tld): array
    {
        $parts = preg_split('/\s+/', trim($this->name), 2) ?: [$this->name];

        return array_merge([
            'tld' => $tld, 'handle' => $this->remote_id, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? $parts[0], 'organization' => $this->organization_name,
            'email' => $this->email, 'phone' => $this->phone, 'street' => $this->street, 'city' => $this->city, 'postal_code' => $this->postal_code, 'country' => $this->country,
            'ico' => $this->ico, 'dic' => $this->dic, 'disclose' => $this->privacy === 'public' ? 1 : 0,
        ], (array) $this->registry_fields);
    }
}
