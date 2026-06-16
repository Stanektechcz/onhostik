<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Ai\Models\AiPromptTemplate;
use Illuminate\Database\Seeder;

class AiPromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // -------- customer features
            ['key' => 'plan_recommendation', 'audience' => 'customer', 'name' => 'Doporučení tarifu',
                'template' => 'Doporuč hostingový tarif pro: {{requirements}}. Vybírej pouze z aktivního ceníku OnHost.'],
            ['key' => 'dns_explanation', 'audience' => 'customer', 'name' => 'Vysvětlení DNS',
                'template' => 'Srozumitelně česky vysvětli DNS dotaz zákazníka: {{text}}.'],
            ['key' => 'invoice_explanation', 'audience' => 'customer', 'name' => 'Vysvětlení faktury',
                'template' => 'Vysvětli zákazníkovi položky a stav faktury: {{text}}. Proforma není daňový doklad.'],
            ['key' => 'support_draft', 'audience' => 'customer', 'name' => 'Návrh zprávy na podporu',
                'template' => 'Pomoz zákazníkovi formulovat požadavek na podporu: {{text}}.'],
            ['key' => 'website_brief', 'audience' => 'customer', 'name' => 'Zadání nového webu',
                'template' => 'Navrhni strukturu a první kroky webu podle popisu: {{text}}.'],

            // -------- admin features
            ['key' => 'incident_summary', 'audience' => 'admin', 'name' => 'Shrnutí incidentu',
                'template' => 'Shrň incident pro interní záznam: {{text}}.'],
            ['key' => 'provisioning_failure_explanation', 'audience' => 'admin', 'name' => 'Vysvětlení selhání provisioningu',
                'template' => 'Vysvětli pravděpodobnou příčinu selhání provisioning úlohy: {{text}}.'],
            ['key' => 'ticket_summary', 'audience' => 'admin', 'name' => 'Shrnutí ticketu',
                'template' => 'Shrň vlákno ticketu a navrhni další krok: {{text}}.'],
            ['key' => 'reply_draft', 'audience' => 'admin', 'name' => 'Návrh odpovědi zákazníkovi',
                'template' => 'Navrhni profesionální odpověď podpory na: {{text}}.'],
            ['key' => 'audit_summary', 'audience' => 'admin', 'name' => 'Shrnutí audit logu',
                'template' => 'Shrň klíčové události z audit logu: {{text}}.'],
        ];

        foreach ($templates as $template) {
            AiPromptTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                $template + ['is_active' => true],
            );
        }
    }
}
