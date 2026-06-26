# Legal Review Checklist

**Před prvním prodejem musí právní zástupce schválit obsah těchto stránek.**

Tento checklist je interní — zákazníci ho nevidí.

---

## Stránky k právní kontrole

| Stránka | URL | Obsah | Stav | Poznámka |
|---------|-----|-------|------|----------|
| Obchodní podmínky | /obchodni-podminky | 8 sekcí, GDPR reference, odpovědnost | ⏳ ČEKÁ | Ověřit soulad s §1751 an. NOZ |
| Ochrana osobních údajů (GDPR) | /gdpr | Zpracování dat, práva subjektů | ⏳ ČEKÁ | Soulad s GDPR Art. 13-14 |
| Zásady cookies | /cookies | Cookie typy, souhlas | ⏳ ČEKÁ | ePrivacy Directive |
| SLA | /sla | 99.9% uptime, kompenzace | ⏳ ČEKÁ | Reálné hodnoty vs. garantované |
| Reklamační řád | /refundace | Podmínky vrácení peněz | ⏳ ČEKÁ | §19 an. z. 634/1992 Sb. |

---

## Fakturační a daňové požadavky

- [ ] IČO je vyplněno v `.env` (BILLING_COMPANY_IC)
- [ ] DIČ je vyplněno, pokud jste plátce DPH (BILLING_COMPANY_DIC)
- [ ] Adresa odpovídá registrovanému sídlu firmy/podnikatele
- [ ] Bankovní účet je správný a odpovídá fakturační entitě
- [ ] Fakturační e-mail (MAIL_FROM_ADDRESS) odpovídá podnikatelskému e-mailu

---

## Technické ověření (bez právní garance)

✅ Legal pages neobsahují lorem ipsum  
✅ Legal pages neobsahují Cuba demo text  
✅ Internal "vyžaduje právní kontrolu" poznámka není veřejně viditelná  
✅ Kontaktní údaje jsou konfigurovatelné přes config/SiteContent  

---

## Postup schválení

1. Exportujte HTML obsah každé stránky
2. Předejte právnímu zástupci k revizi
3. Po schválení označte příslušné položky jako ✅ SCHVÁLENO
4. Datum schválení uložte do tohoto dokumentu

---

## ⚠️ Upozornění

Systém byl postaven s rozumným výchozím obsahem, ale **žádný z textů na právních stránkách
nepředstavuje právní poradenství ani garantovanou správnost**. Finální zodpovědnost za
soulad s právními předpisy nese provozovatel systému.
