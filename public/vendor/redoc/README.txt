Redoc 2.5.4 (MIT, see LICENSE), bundles/redoc.standalone.js from the npm package "redoc@2.5.4".
sha256 dcaf76612bc4a3fbcc923a8966dee2f6146a5f32e5ce1b6f02dd60cbbf89500b
Served from our own host only; /dokumentace/api sets a CSP without unsafe-eval (tests/Feature/Http/ApiDocsTest.php, tests/e2e/api-docs.spec.js).
The bundle contains two `new Function` uses (Ajv code generation for validators and a global-object lookup) and no eval() call.
Neither ran in the Playwright smoke: no securitypolicyviolation except the vendor logo image. The test pins the count at 2.
api-docs.js (ours) starts it and filters the error index; an update of the bundle = replace the file, adjust this note, run both tests.
