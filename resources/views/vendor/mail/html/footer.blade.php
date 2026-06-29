<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center" style="padding: 20px; border-top: 2px solid #7366FF;">
<p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; font-family: Arial, sans-serif;">
<strong style="color: #7366FF;">OnHost.cz</strong> — Moderní hosting pro váš web
</p>
<p style="margin: 0 0 6px; font-size: 12px; color: #9ca3af; font-family: Arial, sans-serif;">
<a href="{{ route('panel.billing.invoices') }}" style="color: #7366FF; text-decoration: none;">Fakturace</a> &nbsp;·&nbsp;
<a href="{{ route('panel.support.index') }}" style="color: #7366FF; text-decoration: none;">Podpora</a> &nbsp;·&nbsp;
<a href="{{ route('front.contact') }}" style="color: #7366FF; text-decoration: none;">Kontakt</a>
</p>
{{ Illuminate\Mail\Markdown::parse($slot) }}
<p style="margin: 8px 0 0; font-size: 11px; color: #d1d5db; font-family: Arial, sans-serif;">
{{ config('billing.supplier.name') }} · IČ {{ config('billing.supplier.ic') }} · {{ config('billing.supplier.city') }}
</p>
</td>
</tr>
</table>
</td>
</tr>
