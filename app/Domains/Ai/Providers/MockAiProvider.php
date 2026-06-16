<?php

declare(strict_types=1);

namespace App\Domains\Ai\Providers;

use App\Domains\Ai\Contracts\AiProviderInterface;
use App\Domains\Ai\DTOs\AiResponse;
use App\Domains\Products\Models\PricingPlan;

/**
 * Deterministic MOCK AI provider — no network I/O, stable outputs so the
 * whole assistant UX and the approval pipeline are testable end to end.
 */
final class MockAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    /** @param array<string, mixed> $context */
    public function chat(string $prompt, array $context = []): AiResponse
    {
        $topic = is_string($context['topic'] ?? null) ? $context['topic'] : 'obecný dotaz';

        return $this->respond(
            "[MOCK AI] Odpověď na téma „{$topic}“:\n\n"
            . $this->cannedAnswer($topic, $prompt),
            $prompt,
        );
    }

    /** @param list<string> $labels */
    public function classify(string $text, array $labels): AiResponse
    {
        // Deterministic: pick the label whose hash matches the text hash.
        $label = $labels === [] ? 'unknown' : $labels[crc32($text) % count($labels)];

        return $this->respond("[MOCK AI] Klasifikace: {$label}", $text);
    }

    public function summarize(string $text): AiResponse
    {
        $summary = mb_substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 220);

        return $this->respond(
            "[MOCK AI] Shrnutí: {$summary}" . (mb_strlen($text) > 220 ? '…' : ''),
            $text,
        );
    }

    /** @param array<string, mixed> $requirements */
    public function recommendPlan(array $requirements): AiResponse
    {
        $visits  = is_numeric($requirements['monthly_visits'] ?? null) ? (int) $requirements['monthly_visits'] : 1_000;
        $sites   = is_numeric($requirements['sites'] ?? null) ? (int) $requirements['sites'] : 1;
        $wantsWp = ($requirements['wordpress'] ?? false) === true;

        $planName = match (true) {
            $wantsWp          => 'Managed WordPress',
            $visits > 100_000 => 'Pro',
            $visits > 10_000 || $sites > 3 => 'Business',
            default           => 'Start',
        };

        $plan = PricingPlan::query()
            ->where('name->cs', $planName)
            ->orWhere('name->en', $planName)
            ->first();

        return $this->respond(
            "[MOCK AI] Doporučený tarif: **{$planName}**.\n"
            . "Důvod: ~{$visits} návštěv/měsíc, {$sites} web(ů)"
            . ($wantsWp ? ', požadavek na WordPress' : '') . '.'
            . ($plan !== null ? "\nTarif je v nabídce — můžete rovnou objednat." : ''),
            (string) json_encode($requirements),
            $plan !== null ? ['recommended_plan_id' => $plan->id] : null,
        );
    }

    /** @param array<string, mixed> $context */
    public function explainError(string $error, array $context = []): AiResponse
    {
        return $this->respond(
            "[MOCK AI] Vysvětlení chyby:\n\n„{$error}“\n\n"
            . 'Tato chyba pochází z mock vrstvy. V ostrém provozu by zde bylo vysvětlení '
            . 'pravděpodobné příčiny (kapacita serveru, špatné přihlašovací údaje, rate limit) '
            . 'a doporučený další krok (opakovat úlohu, zkontrolovat credentials, eskalovat).',
            $error,
        );
    }

    /** @param array<string, mixed> $context */
    public function draftSupportReply(string $question, array $context = []): AiResponse
    {
        return $this->respond(
            "[MOCK AI] Návrh odpovědi podpory (zkontrolujte před odesláním):\n\n"
            . "Dobrý den,\n\nděkujeme za Vaši zprávu. K dotazu „"
            . mb_substr($question, 0, 120)
            . '“ — prověřili jsme stav Vaší služby a vše je v pořádku. '
            . "Pokud problém přetrvává, pošlete nám prosím přesný čas a chybovou hlášku.\n\n"
            . 'S pozdravem, tým OnHost',
            $question,
        );
    }

    /** @param array<string, mixed> $payload */
    public function requestToolAction(string $action, array $payload): AiResponse
    {
        // The provider only DESCRIBES the action. Execution never happens
        // here — the assistant service parks it as an approval record.
        return $this->respond(
            "[MOCK AI] Akce „{$action}“ vyžaduje schválení administrátorem. "
            . 'Byl vytvořen požadavek ke schválení — žádná změna nebyla provedena.',
            $action,
            ['action' => $action, 'payload' => $payload, 'requires_approval' => true],
        );
    }

    // ---------------------------------------------------------------- internals

    private function cannedAnswer(string $topic, string $prompt): string
    {
        return match ($topic) {
            'dns' => 'Doména funguje jako adresář: A záznam míří na IP adresu serveru, '
                . 'CNAME je alias, MX směruje e-maily a TXT slouží k ověření (SPF/DKIM). '
                . 'U OnHost hostingu stačí nasměrovat nameservery na ns1/ns2.onhost.cz.',
            'invoice' => 'Zálohová faktura (proforma) není daňový doklad — slouží k úhradě. '
                . 'Daňový doklad se vystavuje automaticky po přijetí platby a najdete jej u faktury.',
            'website_brief' => 'Návrh struktury webu: 1) úvodní stránka s jasným sdělením, '
                . '2) služby/produkty, 3) reference, 4) kontakt. Doporučený start: šablona + WordPress.',
            default => 'Toto je deterministická mock odpověď pro vývoj a testy. '
                . 'Reálný AI provider (Claude/OpenAI) se aktivuje až s API klíči a schválením.',
        };
    }

    /** @param array<string, mixed>|null $toolCall */
    private function respond(string $content, string $input, ?array $toolCall = null): AiResponse
    {
        return new AiResponse(
            content: $content,
            tokensIn: (int) ceil(mb_strlen($input) / 4),
            tokensOut: (int) ceil(mb_strlen($content) / 4),
            toolCall: $toolCall,
        );
    }
}
