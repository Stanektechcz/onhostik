<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * W7-1: the e-invoice delivery of one invoice (one row per invoice). Holds no document, no recipient and no provider text.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $invoice_id
 * @property string $invoice_number
 * @property string $driver
 * @property string|null $delivery_id
 * @property string $state
 * @property int $attempts
 * @property string|null $error_code
 */
final class EInvoiceDelivery extends Model
{
    use HasUuids;

    public const QUEUED = 'queued';

    public const SKIPPED = 'skipped';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    /** States after which a delivery is not attempted again. */
    public const FINAL = [self::SENT, self::DELIVERED, self::SKIPPED];

    protected $table = 'einvoice_deliveries';

    protected $guarded = [];

    protected $casts = ['attempts' => 'integer'];
}
