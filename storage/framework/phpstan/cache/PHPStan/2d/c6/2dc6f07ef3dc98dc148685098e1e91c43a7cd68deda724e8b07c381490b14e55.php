<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Http\Controllers\Web\HealthController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\Web\HealthController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-e01c595da032cc7873dd5571ffe248690932ad0c0eed268eee252e3cb00149e2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\Web\\HealthController',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Http/Controllers/Web/HealthController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers\\Web',
    'name' => 'App\\Http\\Controllers\\Web\\HealthController',
    'shortName' => 'HealthController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Readiness (`/healthz`) for load balancers and the Prometheus exporter (`/metrics`) that feeds
 * infra/monitoring/slo-alerts.yml. Both are unauthenticated but the exporter requires the metrics
 * token (`ONHOST_METRICS_TOKEN`) unless the caller is on the allow-list.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 132,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\Controller',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'healthz' => 
      array (
        'name' => 'healthz',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 30,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Web',
        'declaringClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'aliasName' => NULL,
      ),
      'metrics' => 
      array (
        'name' => 'metrics',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 51,
            'endLine' => 51,
            'startColumn' => 29,
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
            'name' => 'Illuminate\\Http\\Response',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 51,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Web',
        'declaringClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'aliasName' => NULL,
      ),
      'outboxLagSeconds' => 
      array (
        'name' => 'outboxLagSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 126,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Web',
        'declaringClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Web\\HealthController',
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