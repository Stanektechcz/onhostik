<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Commands;

use Onhost\Domain\Invoicing\InvoiceNumberAllocator;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Domain\WalletLedger\BudgetService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

final class WalletCommandHandler implements CommandHandler
{
    public function __construct(private readonly PaymentService $payments, private readonly InvoiceNumberAllocator $numbers, private readonly AutoTopup $autoTopup) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof BudgetCommand) {
            return ['data' => app(BudgetService::class)->set(Organization::query()->findOrFail($command->organizationId), $command->payload, $context)];
        }
        if ($command instanceof AutoTopupCommand) {
            return $this->autoTopup->configure(Organization::query()->findOrFail($command->organizationId), $command->payload, $context);
        }
        if ($command instanceof RemovePaymentMethodCommand) {
            $organization = Organization::query()->findOrFail($command->organizationId);
            $this->payments->removeMethod($organization, (string) $command->get('payment_method_id'), $context);

            return ['removed' => true, 'methods' => $this->payments->methods($organization), 'auto_topup' => $this->autoTopup->settings($organization)];
        }
        if (! $command instanceof TopUpWalletCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);
        $currency = strtoupper((string) $command->get('currency', $organization->currency));
        $amount = Money::decimal((string) $command->get('amount', '0'), $currency);
        $min = Money::decimal((string) config('onhost.billing.min_topup.'.$currency, '100'), $currency);
        if ($amount->lessThan($min)) {
            throw new DomainError('topup_too_small', "Minimum top-up is {$min->format()}.", 422, ['field' => 'amount']);
        }
        $provider = $command->get('provider') ?? (in_array((string) $command->get('method'), ['bank', 'transfer', 'bank_transfer'], true) ? 'bank' : null); // "by transfer" is the bank provider, whatever the client calls it
        $reference = $command->get('reference');
        if ($reference === null && $provider === 'bank') {
            // Bank transfers are matched by variable symbol. Top-ups get their own numeric series ("9" + year + sequence),
            // which can never collide with the proforma/invoice symbols (year + sequence) or with another top-up.
            $reference = '9'.InvoiceNumberAllocator::variableSymbol($this->numbers->allocate((string) config('onhost.billing.legal_entity', 'onhost-cz'), 'TU')['number']);
        }
        $intent = $this->payments->createIntent($organization, $amount, 'topup', 'wallet', $organization->id, $context, [
            'provider' => $provider, 'method' => $command->get('method'), 'return_urls' => (array) $command->get('return_urls', []), 'description' => "Dobití kreditu {$organization->name}",
            'email' => $organization->billing_email, 'reference' => $reference, 'save_method' => filter_var($command->get('save_method', false), FILTER_VALIDATE_BOOLEAN), // keep the card for automatic top-ups (audit §5f-1)
            'idempotency_key' => 'topup:'.$organization->id.':'.$command->idempotencyKey(), // every top-up attempt is its own payment
        ]);

        return ['payment_intent_id' => $intent->id, 'provider' => $intent->provider, 'state' => $intent->state, 'redirect_url' => $intent->redirect_url, 'amount' => $amount, 'instructions' => $intent->raw['instructions'] ?? null];
    }
}
