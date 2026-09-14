<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Http\Support\SurfaceRenderer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Support\SurfaceRenderer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-cf1eaf3f64f8df9986a91a9ba8ce20ca9ca85e577d668d1c527f3b0dbaeb7fda',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Support\\SurfaceRenderer',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Http/Support/SurfaceRenderer.php',
      ),
    ),
    'namespace' => 'App\\Http\\Support',
    'name' => 'App\\Http\\Support\\SurfaceRenderer',
    'shortName' => 'SurfaceRenderer',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Serves the pristine prototype surfaces from `apps/surfaces` with only the data seams the
 * template inventory allows (docs/ui/template-inventory.md §6):
 *  1. relative asset URLs → `/surfaces/…` (the files themselves are byte-identical to the prototype);
 *  2. `onhost-store.js` → `api/onhost-store.api.js`, `onhost-integrations.js`/`onhost-domains.js` → API-backed variants;
 *  3. `onhost-data.js` → server-generated `/surfaces/onhost-data.js`;
 *  4. injected `window.ONHOST = { apiBase, csrf, user, surface, demo }` + session bridge before `onhost-shell.js`;
 *  5. panel only: `SVC_DATA().services` / `state.servers` read `window.ONHOST_PANEL`, `liveSimulation` defaults to false.
 * Nothing visual is touched: typography, tokens, spacing and components stay as designed.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 1346,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'SURFACES' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'SURFACES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'public\' => \'Onhost.dc.html\', \'panel\' => \'Onhost-app.dc.html\', \'admin\' => \'Onhost-admin.dc.html\', \'partner\' => \'Onhost-partner.dc.html\', \'mobile\' => \'Onhost-mobil.dc.html\', \'widgets\' => \'Onhost-widgets.dc.html\']',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 25,
            'startTokenPos' => 43,
            'startFilePos' => 997,
            'endTokenPos' => 87,
            'endFilePos' => 1231,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'BOOT' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'BOOT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'<!--__ONHOST_BOOT__-->\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 98,
            'startFilePos' => 1260,
            'endTokenPos' => 98,
            'endFilePos' => 1283,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'PARTNER_TERMS_MARKUP' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'PARTNER_TERMS_MARKUP',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '<<<\'HTML\'
        <sc-if value="{{ termsLive }}" hint-placeholder-val="{{ false }}">
          <div style="margin-top:16px;border:2px solid #201e1d;background:#f3f2f2;padding:12px 18px;max-width:62em">
            <div style="font-family:var(--font-heading,Archivo);font-weight:900;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.6)">{{ termsTitle }}</div>
            <sc-for list="{{ terms }}" as="tm" hint-placeholder-count="4">
              <div style="display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid rgba(32,30,29,.12);font-size:13px">
                <span><b style="font-family:var(--font-heading,Archivo);font-weight:800">{{ tm.label }}</b> · {{ tm.value }} <span style="color:rgba(32,30,29,.6)">{{ tm.note }}</span></span>
                <button onClick="{{ tm.on }}" style="{{ tm.style }}">{{ tm.action }}</button>
              </div>
            </sc-for>
          </div>
        </sc-if>

HTML',
          'attributes' => 
          array (
            'startLine' => 165,
            'endLine' => 178,
            'startTokenPos' => 1370,
            'startFilePos' => 15505,
            'endTokenPos' => 1372,
            'endFilePos' => 16524,
          ),
        ),
        'docComment' => '/** The marketplace tab markup (seam #46): two tables — listings and jobs — with up to two actions per row. */',
        'attributes' => 
        array (
        ),
        'startLine' => 165,
        'endLine' => 178,
        'startColumn' => 5,
        'endColumn' => 5,
      ),
      'PARTNER_MARKETPLACE_MARKUP' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'PARTNER_MARKETPLACE_MARKUP',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '<<<\'HTML\'
    <sc-if value="{{ isMarketplace }}" hint-placeholder-val="{{ false }}">
      <div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:30px">
          <button onClick="{{ mkt.newListing }}" style="background:#ec3013;border:none;color:#f3f2f2;font-family:var(--font-heading,Archivo);font-weight:800;font-size:12px;letter-spacing:.05em;text-transform:uppercase;padding:10px 16px;cursor:pointer">{{ mkt.t.newListing }}</button>
          <button onClick="{{ mkt.reload }}" style="background:transparent;border:2px solid #201e1d;color:#201e1d;font-family:var(--font-heading,Archivo);font-weight:800;font-size:12px;letter-spacing:.05em;text-transform:uppercase;padding:8px 14px;cursor:pointer">{{ mkt.t.reload }}</button>
          <span style="font-size:13px;color:rgba(32,30,29,.66);max-width:44em">{{ mkt.note }}</span>
        </div>
        <div style="margin-top:20px;overflow:auto;border:2px solid #201e1d;background:#f3f2f2">
          <table style="width:100%;border-collapse:collapse;font-size:14px;min-width:760px">
            <thead>
              <tr>
                <sc-for list="{{ mkt.listingHead }}" as="h" hint-placeholder-count="4">
                  <th style="{{ h.style }}">{{ h.label }}</th>
                </sc-for>
              </tr>
            </thead>
            <tbody>
              <sc-for list="{{ mkt.listings }}" as="r" hint-placeholder-count="3">
                <tr>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18)"><div style="font-family:var(--font-heading,Archivo);font-weight:800">{{ r.title }}</div><div style="font-size:12px;color:rgba(32,30,29,.6)">{{ r.sub }}</div></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap">{{ r.price }}</td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);white-space:nowrap"><span style="{{ r.stateStyle }}">{{ r.state }}</span></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap"><button onClick="{{ r.a1On }}" style="{{ r.a1Style }}">{{ r.a1Label }}</button><button onClick="{{ r.a2On }}" style="{{ r.a2Style }}">{{ r.a2Label }}</button></td>
                </tr>
              </sc-for>
            </tbody>
          </table>
        </div>
        <div style="margin-top:24px;font-family:var(--font-heading,Archivo);font-weight:900;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.6)">{{ mkt.t.orders }}</div>
        <div style="margin-top:10px;overflow:auto;border:2px solid #201e1d;background:#f3f2f2">
          <table style="width:100%;border-collapse:collapse;font-size:14px;min-width:760px">
            <thead>
              <tr>
                <sc-for list="{{ mkt.orderHead }}" as="h" hint-placeholder-count="5">
                  <th style="{{ h.style }}">{{ h.label }}</th>
                </sc-for>
              </tr>
            </thead>
            <tbody>
              <sc-for list="{{ mkt.orders }}" as="o" hint-placeholder-count="3">
                <tr>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18)"><div style="font-family:var(--font-heading,Archivo);font-weight:800">{{ o.title }}</div><div style="font-size:12px;color:rgba(32,30,29,.6)">{{ o.brief }}</div></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);white-space:nowrap">{{ o.due }}</td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap;font-family:var(--font-heading,Archivo);font-weight:800;color:#ae1800">{{ o.share }}</td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);white-space:nowrap"><span style="{{ o.stateStyle }}">{{ o.state }}</span></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap"><button onClick="{{ o.a1On }}" style="{{ o.a1Style }}">{{ o.a1Label }}</button><button onClick="{{ o.a2On }}" style="{{ o.a2Style }}">{{ o.a2Label }}</button></td>
                </tr>
              </sc-for>
            </tbody>
          </table>
        </div>
      </div>
    </sc-if>

HTML',
          'attributes' => 
          array (
            'startLine' => 180,
            'endLine' => 235,
            'startTokenPos' => 1383,
            'startFilePos' => 16575,
            'endTokenPos' => 1385,
            'endFilePos' => 20948,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 180,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
      ),
      'CHECKOUT_UPSELLS' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'CHECKOUT_UPSELLS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '<<<\'HTML\'
              <sc-for list="{{ coUpsells }}" as="cu4" hint-placeholder-count="4">
                <label style="display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);cursor:pointer;font-size:15px">
                  <input type="checkbox" checked="{{ cu4.on }}" onChange="{{ cu4.toggle }}" style="width:18px;height:18px;accent-color:var(--acc,#ec3013)">
                  <span style="font-weight:600">{{ cu4.name }}</span>
                  <span style="margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:14px;white-space:nowrap">{{ cu4.priceLabel }}</span>
                </label>
              </sc-for>
HTML',
          'attributes' => 
          array (
            'startLine' => 1127,
            'endLine' => 1135,
            'startTokenPos' => 6006,
            'startFilePos' => 141455,
            'endTokenPos' => 6008,
            'endFilePos' => 142179,
          ),
        ),
        'docComment' => '/** The prototype\'s generic checkout upsells (four fixed checkboxes for the whole cart). */',
        'attributes' => 
        array (
        ),
        'startLine' => 1127,
        'endLine' => 1135,
        'startColumn' => 5,
        'endColumn' => 5,
      ),
      'CHECKOUT_ITEM_ADDONS' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'CHECKOUT_ITEM_ADDONS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '<<<\'HTML\'
              <sc-for list="{{ coItemAddons }}" as="cia" hint-placeholder-count="2">
                <div style="padding:12px 0 6px;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)">
                  <div style="display:flex;align-items:baseline;gap:10px">
                    <span style="font-family:var(--font-heading);font-weight:800;font-size:15px">{{ cia.name }}</span>
                    <span style="margin-left:auto;font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent);white-space:nowrap">{{ cia.meta }}</span>
                  </div>
                  <sc-if value="{{ cia.none }}" hint-placeholder-val="{{ false }}">
                    <div style="font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent);padding:8px 0 6px">{{ cia.noneLabel }}</div>
                  </sc-if>
                  <sc-for list="{{ cia.addons }}" as="cua" hint-placeholder-count="4">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:10px 0 10px 14px;border-top:1px solid color-mix(in srgb,var(--fg,#201e1d) 12%,transparent);font-size:14px">
                      <sc-if value="{{ cua.isCheck }}" hint-placeholder-val="{{ false }}">
                        <input type="checkbox" checked="{{ cua.on }}" onChange="{{ cua.toggle }}" style="width:18px;height:18px;accent-color:var(--acc,#ec3013)">
                      </sc-if>
                      <span style="font-weight:600">{{ cua.name }}</span>
                      <span style="font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent)">{{ cua.desc }}</span>
                      <sc-if value="{{ cua.isRange }}" hint-placeholder-val="{{ false }}">
                        <span style="display:flex;align-items:center;gap:8px;flex:1 1 220px">
                          <input type="range" min="{{ cua.min }}" max="{{ cua.max }}" step="{{ cua.step }}" value="{{ cua.raw }}" onChange="{{ cua.change }}" style="flex:1;accent-color:var(--acc,#ec3013)" />
                          <span style="font-family:var(--font-heading);font-weight:800;font-size:13px;white-space:nowrap">{{ cua.value }}</span>
                        </span>
                      </sc-if>
                      <sc-if value="{{ cua.isChips }}" hint-placeholder-val="{{ false }}">
                        <span style="display:flex;flex-wrap:wrap;gap:6px">
                          <sc-for list="{{ cua.choices }}" as="cch" hint-placeholder-count="3">
                            <button onClick="{{ cch.on }}" style="{{ cch.style }}">{{ cch.label }}</button>
                          </sc-for>
                        </span>
                      </sc-if>
                      <span style="margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:14px;white-space:nowrap">{{ cua.priceLabel }}</span>
                    </div>
                  </sc-for>
                </div>
              </sc-for>
HTML',
          'attributes' => 
          array (
            'startLine' => 1138,
            'endLine' => 1173,
            'startTokenPos' => 6021,
            'startFilePos' => 142372,
            'endTokenPos' => 6023,
            'endFilePos' => 145364,
          ),
        ),
        'docComment' => '/** Every cart line followed by its own add-ons (switches, sliders, choices, add-on products) — `coItemAddons` from OnhostCart.itemAddons. */',
        'attributes' => 
        array (
        ),
        'startLine' => 1138,
        'endLine' => 1173,
        'startColumn' => 5,
        'endColumn' => 5,
      ),
    ),
    'immediateProperties' => 
    array (
      'root' => 
      array (
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'name' => 'root',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 33,
        'endColumn' => 61,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'root' => 
          array (
            'name' => 'root',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 33,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 65,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'root' => 
      array (
        'name' => 'root',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'render' => 
      array (
        'name' => 'render',
        'parameters' => 
        array (
          'surface' => 
          array (
            'name' => 'surface',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 28,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boot' => 
          array (
            'name' => 'boot',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 45,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'demo' => 
          array (
            'name' => 'demo',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 58,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param array<string,mixed> $boot the `window.ONHOST` object */',
        'startLine' => 37,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'partnerSeams' => 
      array (
        'name' => 'partnerSeams',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 106,
            'endLine' => 106,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Seam #46 (api/onhost-partner.api.js): the partner portal gains one API-backed tab — the marketplace (listings, jobs,
 * deliveries). The prototype\'s tab table, the view flags and the page titles get one more entry each; the markup of the
 * tab is inserted before the payouts block. Every hook keeps the literal in place when the anchor is missing (logged).
 */',
        'startLine' => 106,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'adminSeams' => 
      array (
        'name' => 'adminSeams',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 40,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 237,
        'endLine' => 341,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'signOutSeams' => 
      array (
        'name' => 'signOutSeams',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The public account box, the panel user menu and the admin user menu sign out through the API (bridge-patched OnhostSession.signOut). */',
        'startLine' => 345,
        'endLine' => 362,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'identity' => 
      array (
        'name' => 'identity',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 31,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 45,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'surface' => 
          array (
            'name' => 'surface',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 58,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 364,
        'endLine' => 386,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'transform' => 
      array (
        'name' => 'transform',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 389,
            'endLine' => 389,
            'startColumn' => 31,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'surface' => 
          array (
            'name' => 'surface',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 389,
            'endLine' => 389,
            'startColumn' => 45,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'demo' => 
          array (
            'name' => 'demo',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 389,
            'endLine' => 389,
            'startColumn' => 62,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Pure text transform of the prototype HTML (cached per file version). */',
        'startLine' => 389,
        'endLine' => 933,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'panelSeams' => 
      array (
        'name' => 'panelSeams',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 943,
            'endLine' => 943,
            'startColumn' => 39,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Panel/admin: haléře when an amount has them; the web-order banner reports a stalled fulfilment instead of the usual 90 seconds. */',
        'startLine' => 943,
        'endLine' => 962,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'cartSeams' => 
      array (
        'name' => 'cartSeams',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 964,
            'endLine' => 964,
            'startColumn' => 38,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 964,
        'endLine' => 1050,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'checkoutSeams' => 
      array (
        'name' => 'checkoutSeams',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1057,
            'endLine' => 1057,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Seam #24 (checkout): the confirmation reflects the real order (awaiting a bank transfer → the payment instructions
 * and the total, paid → provisioning), the details step collects the company name and the billing address the
 * invoice needs, and both reach the guest account.
 */',
        'startLine' => 1057,
        'endLine' => 1124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'extraBlocks' => 
      array (
        'name' => 'extraBlocks',
        'parameters' => 
        array (
          'p' => 
          array (
            'name' => 'p',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1176,
            'endLine' => 1176,
            'startColumn' => 41,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'withCompare' => 
          array (
            'name' => 'withCompare',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1176,
            'endLine' => 1176,
            'startColumn' => 52,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Extra product page blocks (complete parameters, add-ons, configurator; optionally the comparison table) bound to `{P}.*`. */',
        'startLine' => 1176,
        'endLine' => 1226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'neutralizeVendors' => 
      array (
        'name' => 'neutralizeVendors',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1229,
            'endLine' => 1229,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'surface' => 
          array (
            'name' => 'surface',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1229,
            'endLine' => 1229,
            'startColumn' => 60,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Prototype literals that name WEDOS (a competitor in the migration copy, ONhost\'s registrar behind the scenes) → neutral wording; a final sweep catches anything new. */',
        'startLine' => 1229,
        'endLine' => 1291,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'assetVersion' => 
      array (
        'name' => 'assetVersion',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Version stamp for prototype scripts: newest mtime of support.js, the shell and the API seams. */',
        'startLine' => 1294,
        'endLine' => 1307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'assetPath' => 
      array (
        'name' => 'assetPath',
        'parameters' => 
        array (
          'relative' => 
          array (
            'name' => 'relative',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1310,
            'endLine' => 1310,
            'startColumn' => 31,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Safe path inside apps/surfaces for the static asset route. */',
        'startLine' => 1310,
        'endLine' => 1324,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
      'mime' => 
      array (
        'name' => 'mime',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1326,
            'endLine' => 1326,
            'startColumn' => 33,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1326,
        'endLine' => 1345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Http\\Support',
        'declaringClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'implementingClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'currentClassName' => 'App\\Http\\Support\\SurfaceRenderer',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));