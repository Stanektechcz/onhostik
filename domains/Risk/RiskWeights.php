<?php

declare(strict_types=1);

namespace Onhost\Domain\Risk;

use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Settings\SettingsStore;

/**
 * One risk model (audit §5n-4): the order check and the referral check keep their own thresholds but read and teach one
 * weight table. A signal that appears in both loops (a disposable mailbox, the cross signals) learns from every staff
 * decision in either loop; a reject makes the signals that fired heavier, a release makes them lighter, one step per
 * decision, within bounds. The table lives in system settings under `risk.weights`; the legacy per-loop tables are read
 * once and merged so a tuned installation keeps what it learned.
 */
final class RiskWeights
{
    public const SETTING = 'risk.weights';

    public const FEEDBACK_SETTING = 'risk.feedback';

    /** the per-loop tables before §5n-4; merged into the shared one on first read */
    public const LEGACY = ['orders.risk.weights', 'loyalty.referral.weights'];

    public const MIN_WEIGHT = 5;

    public const MAX_WEIGHT = 100;

    /** Every signal of every loop with its default weight. */
    public const DEFAULTS = [
        // order check (§5g-4)
        'referral_flagged' => 40, 'new_account' => 25, 'disposable_email' => 50, 'free_mail_company' => 10, 'rapid_orders' => 30, 'large_first_order' => 25, 'failed_payments' => 30, 'vat_country_mismatch' => 15, 'ip_country_mismatch' => 20, 'turnstile_failed' => 35,
        // referral check (§5l-4)
        'same_email_domain' => 100, 'risk_hold' => 100, 'chargeback' => 100, 'same_address' => 60, 'referrer_risk' => 40, 'refused_history' => 30, 'rapid_signup' => 25, 'many_pending' => 20,
    ];

    public function __construct(private readonly SettingsStore $settings) {}

    /**
     * The weights in force for the given signals (all of them when null), defaults moved by what staff taught.
     *
     * @param  array<string,int>|null  $subset  signal => default
     * @return array<string,int>
     */
    public function weights(?array $subset = null): array
    {
        $learned = $this->stored();
        $out = [];
        foreach ($subset ?? self::DEFAULTS as $signal => $default) {
            $out[$signal] = self::clamp((int) ($learned[$signal] ?? self::DEFAULTS[$signal] ?? $default));
        }

        return $out;
    }

    /** Staff set weights by hand (the console's tuning); unknown signals are refused. @param array<string,int> $weights */
    public function set(array $weights, ?string $by = null): array
    {
        $current = $this->stored();
        foreach ($weights as $signal => $value) {
            if (! array_key_exists((string) $signal, self::DEFAULTS)) {
                throw new DomainError('risk_signal_unknown', 'Unknown risk signal: '.$signal.'. Known: '.implode(', ', array_keys(self::DEFAULTS)).'.', 422, ['field' => 'weights']);
            }
            $current[(string) $signal] = self::clamp((int) $value);
        }
        if ($weights !== []) {
            $this->settings->set(self::SETTING, $current, $by);
        }

        return $this->weights();
    }

    public function reset(): void
    {
        $this->settings->forget(self::SETTING);
        $this->settings->forget(self::FEEDBACK_SETTING);
        foreach (self::LEGACY as $key) {
            $this->settings->forget($key);
        }
    }

    /**
     * A staff decision teaches the signals that fired: release −step, reject +step, within bounds; the feedback counts
     * per signal (released / rejected) tell the model review how precise each signal is.
     *
     * @param  list<string>  $signals
     * @return array<string,int> the whole table now in force
     */
    public function learn(array $signals, string $decision, int $step = 5, ?string $by = null): array
    {
        if (! in_array($decision, ['release', 'reject'], true) || $step <= 0) {
            return $this->weights();
        }
        $current = $this->stored();
        $feedback = $this->feedback();
        $touched = false;
        foreach (array_unique(array_map('strval', $signals)) as $signal) {
            if (! array_key_exists($signal, self::DEFAULTS)) {
                continue;
            }
            $now = self::clamp((int) ($current[$signal] ?? self::DEFAULTS[$signal]));
            $current[$signal] = self::clamp($decision === 'release' ? $now - $step : $now + $step);
            $feedback[$signal] = ['released' => (int) ($feedback[$signal]['released'] ?? 0) + ($decision === 'release' ? 1 : 0), 'rejected' => (int) ($feedback[$signal]['rejected'] ?? 0) + ($decision === 'reject' ? 1 : 0)];
            $touched = true;
        }
        if ($touched) {
            $this->settings->set(self::SETTING, $current, $by ?? 'risk.learn');
            $this->settings->set(self::FEEDBACK_SETTING, $feedback, $by ?? 'risk.learn');
        }

        return $this->weights();
    }

    /** What staff taught so far, per signal. @return array<string,array{released:int,rejected:int}> */
    public function feedback(): array
    {
        $stored = $this->settings->get(self::FEEDBACK_SETTING);
        if (! is_array($stored)) {
            $legacy = $this->settings->get('orders.risk.feedback');
            $stored = is_array($legacy) ? $legacy : [];
        }

        return $stored;
    }

    public static function clamp(int $weight): int
    {
        return max(self::MIN_WEIGHT, min(self::MAX_WEIGHT, $weight));
    }

    /** @return array<string,int> the stored table; legacy per-loop tables merged in once */
    private function stored(): array
    {
        $stored = $this->settings->get(self::SETTING);
        if (is_array($stored)) {
            return array_map('intval', $stored);
        }
        $merged = [];
        foreach (self::LEGACY as $key) {
            $legacy = $this->settings->get($key);
            if (is_array($legacy)) {
                foreach ($legacy as $signal => $value) {
                    if (array_key_exists((string) $signal, self::DEFAULTS)) {
                        $merged[(string) $signal] = self::clamp((int) $value);
                    }
                }
            }
        }
        if ($merged !== []) {
            $this->settings->set(self::SETTING, $merged, 'risk.migrate');
        }

        return $merged;
    }
}
