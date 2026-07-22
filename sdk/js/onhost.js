// Official OnHost REST API client (JavaScript / TypeScript).
// Zero dependencies — uses the platform `fetch`. Node >= 18 or any browser.
//
//   import { OnHost } from './onhost.js';
//   const onhost = new OnHost('pat_xxx');
//   const services = await onhost.services();

export class OnHost {
  /**
   * @param {string} token   Personal access token (panel → Účet → API tokeny)
   * @param {object} [opts]
   * @param {string} [opts.baseUri='https://onhost.cz/api/']
   * @param {string} [opts.version='v1']
   */
  constructor(token, opts = {}) {
    const base = (opts.baseUri || 'https://onhost.cz/api/').replace(/\/+$/, '');
    const version = (opts.version || 'v1').replace(/^\/+|\/+$/g, '');
    this._base = `${base}/${version}/`;
    this._token = token;
  }

  // ── reads ──────────────────────────────────────────────────────────────────
  profile()       { return this._get('profile'); }
  services()      { return this._get('services'); }
  service(id)     { return this._get(`services/${encodeURIComponent(id)}`); }
  invoices()      { return this._get('invoices'); }
  domains()       { return this._get('domains'); }
  creditBalance() { return this._get('billing/credit'); }
  tickets()       { return this._get('support/tickets'); }

  // ── writes (idempotent) ──────────────────────────────────────────────────────
  topUpCredit(amountMinor, currency = 'CZK') {
    return this._post('billing/credit/topup', { amount: amountMinor, currency });
  }
  createTicket(subject, body) {
    return this._post('support/tickets', { subject, body });
  }
  replyTicket(ticketId, body) {
    return this._post(`support/tickets/${encodeURIComponent(ticketId)}/reply`, { body });
  }

  // ── transport ────────────────────────────────────────────────────────────────
  async _get(path) {
    return this._request('GET', path);
  }
  async _post(path, payload) {
    return this._request('POST', path, payload);
  }
  async _request(method, path, payload) {
    const headers = {
      Authorization: `Bearer ${this._token}`,
      Accept: 'application/json',
    };
    const init = { method, headers };
    if (payload !== undefined) {
      headers['Content-Type'] = 'application/json';
      // A retried write must not repeat its effect.
      headers['Idempotency-Key'] = this._idempotencyKey();
      init.body = JSON.stringify(payload);
    }
    const res = await fetch(this._base + path, init);
    if (!res.ok) {
      throw new Error(`OnHost API ${res.status}: ${await res.text()}`);
    }
    return res.status === 204 ? null : res.json();
  }
  _idempotencyKey() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }
}
