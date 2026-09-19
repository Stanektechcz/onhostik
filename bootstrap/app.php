<?php

use App\Http\Middleware\RememberReferral;
use App\Http\Middleware\RequestMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ShedUnderLoad;
use App\Http\Middleware\StatusHost;
use App\Http\Middleware\TokenRouteScope;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Http\Middleware\CorrelationId;
use Onhost\Platform\Http\Middleware\IdempotencyKey;
use Onhost\Platform\Observability\ErrorReporter;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Platform\StateMachine\InvalidTransitionException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trimStrings(except: ['params.content']); // file contents and directives keep their whitespace
        $middleware->prepend(CorrelationId::class);
        $middleware->append(SetLocale::class); // validation messages in the visitor's language (cs / en)
        $middleware->append(RequestMetrics::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->prepend(StatusHost::class); // the customer's own status host (audit §5k-3) — global, so `/badge.svg` on that host is served before routing
        $middleware->web(append: [RememberReferral::class]); // the remembered invite code (§5k-4)
        $middleware->encryptCookies(except: [RememberReferral::COOKIE]); // the API reads the invite cookie without the web cookie encryption
        $middleware->alias(['idempotency' => IdempotencyKey::class, 'shed' => ShedUnderLoad::class, 'token.scope' => TokenRouteScope::class]); // one call: alias() replaces, it does not merge. `shed`: reports and overviews give way under overload (H139)
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', (string) env('TRUSTED_PROXIES')) : null);
        // Surfaces are HTML: guests go to the sign-in surface. Scripts, API and relay endpoints answer 401 JSON instead.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('v1/*') || $request->is('surfaces/*') || $request->is('console/*') || $request->expectsJson() ? null : '/prihlaseni?next='.urlencode($request->getRequestUri()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('v1/*') || $request->is('v1') || $request->is('surfaces/*') || $request->is('console/*') || $request->is('metrics') || $request->is('healthz') || $request->expectsJson();
        $exceptions->shouldRenderJsonWhen($isApi);
        $exceptions->report(fn (Throwable $e) => app(ErrorReporter::class)->report($e)); // error tracking (audit §5q-2): unexpected exceptions only, redacted, tagged with the correlation id

        // Domain errors carry a machine slug, an HTTP status and optional field info for form rendering.
        $exceptions->render(function (DomainError $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }
            $problem = $e->toProblem();
            if (isset($problem['field'])) {
                $problem['errors'] = [$problem['field'] => [$e->getMessage()]];
            }
            $response = response()->json($problem, $e->status);
            if (isset($problem['retry_after'])) {
                $response->header('Retry-After', (string) $problem['retry_after']);
            }

            return $response;
        });

        // Validation failures follow the same contract as domain errors: a slug, a message, per-field errors.
        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json(['error' => 'validation_failed', 'message' => $e->getMessage(), 'status' => 422, 'errors' => $e->errors()], 422);
        });

        // Provider failures never leak vendor payloads; the taxonomy code decides the HTTP status.
        $exceptions->render(function (ProviderException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }
            $status = match ($e->errorCode) {
                ProviderErrorCode::RATE_LIMIT, ProviderErrorCode::CIRCUIT_OPEN, ProviderErrorCode::TRANSIENT, ProviderErrorCode::CAPACITY => 503,
                ProviderErrorCode::VALIDATION => 422,
                ProviderErrorCode::NOT_FOUND => 404,
                ProviderErrorCode::CONFLICT => 409,
                default => 502,
            };
            $response = response()->json(['error' => 'provider_'.strtolower($e->errorCode->value), 'provider' => $e->provider, 'message' => app(Redactor::class)->redactString($e->getMessage()), 'status' => $status, 'retryable' => $e->isRetryable()], $status);
            if ($e->retryAfterSeconds !== null) {
                $response->header('Retry-After', (string) $e->retryAfterSeconds);
            }

            return $response;
        });

        $exceptions->render(function (InvalidTransitionException $e, Request $request) use ($isApi) {
            return $isApi($request) ? response()->json(['error' => 'invalid_transition', 'message' => $e->getMessage(), 'status' => 409], 409) : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            return $isApi($request) ? response()->json(['error' => 'unauthenticated', 'message' => 'Sign in to continue.', 'status' => 401], 401) : null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request) || $e->getStatusCode() === 422) {
                return null;
            }
            $status = $e->getStatusCode();
            $slug = match ($status) {
                404 => 'not_found', 403 => 'forbidden', 405 => 'method_not_allowed', 429 => 'rate_limited', 419 => 'csrf_token_mismatch', default => 'http_'.$status
            };

            return response()->json(['error' => $slug, 'message' => $e->getMessage() ?: $slug, 'status' => $status], $status, $e->getHeaders());
        });
    })->create();
