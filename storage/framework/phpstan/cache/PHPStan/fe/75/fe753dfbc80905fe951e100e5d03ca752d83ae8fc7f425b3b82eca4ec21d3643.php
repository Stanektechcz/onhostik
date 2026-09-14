<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Wedos\WapiGateway.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Wedos\WapiGateway
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-ce57adf853fe3333818d6d4f16477ec90aa2db9c8ad104b8f1ecbf96b67c77a5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Wedos/WapiGateway.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Wedos',
    'name' => 'Onhost\\Providers\\Wedos\\WapiGateway',
    'shortName' => 'WapiGateway',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The only path to WEDOS WAPI (blueprint §45.5). Responsibilities:
 *  - schema validation (command allow-list + required fields) before anything is sent,
 *  - token buckets: all requests 1000/h and the domain family 100/h, each with a 15 % reserve
 *    for renewals/reconcile (`critical` calls may use the reserve),
 *  - hourly SHA-1 auth in Europe/Prague, regenerated for every attempt (never cached across the hour),
 *  - clock health gate: |offset| > 1 s or two consecutive auth errors opens the circuit (S33),
 *  - invalid-request circuit breaker (>10 invalid requests in a window) to avoid the WAPI penalty,
 *  - clTRID correlation on every command, vendor error normalisation, redaction (auth, AUTH-ID).
 * Nothing here retries `domain-create` blindly: a timeout after create is resolved by the caller with `domain-info` (S34).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 29,
    'endLine' => 190,
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
      'DOMAIN_FAMILY' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'DOMAIN_FAMILY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'domain-check\', \'domain-create\', \'domain-transfer\', \'domain-transfer-check\', \'domain-tld-period-check\']',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 87,
            'startFilePos' => 1439,
            'endTokenPos' => 101,
            'endFilePos' => 1542,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 138,
      ),
      'SCHEMA' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'SCHEMA',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'ping\' => [], \'domain-check\' => [\'name\'], \'domain-info\' => [\'name\'], \'domains-list\' => [], \'domain-create\' => [\'name\', \'period\', \'owner_c\', \'admin_c\'], \'domain-renew\' => [\'name\', \'period\'], \'domain-update-ns\' => [\'name\'], \'domain-transfer-check\' => [\'name\'], \'domain-transfer\' => [\'name\', \'auth_info\'], \'domain-send-auth-info\' => [\'name\'], \'domain-update-keyset\' => [\'name\'], \'domain-tld-period-check\' => [\'tld\', \'period\'], \'contact-check\' => [\'tld\', \'cname\'], \'contact-info\' => [\'tld\', \'cname\'], \'contact-create\' => [\'tld\'], \'contact-update\' => [\'tld\', \'cname\'], \'contact-transfer\' => [\'tld\', \'cname\', \'auth_info\'], \'contact-send-auth-info\' => [\'tld\', \'cname\'], \'nsset-check\' => [\'nsset\'], \'nsset-info\' => [\'nsset\'], \'nsset-create\' => [\'nsset\', \'dns\'], \'nsset-update\' => [\'nsset\'], \'nsset-transfer\' => [\'nsset\', \'auth_info\'], \'nsset-send-auth-info\' => [\'nsset\'], \'dns-domains-list\' => [], \'dns-domain-info\' => [\'name\'], \'dns-domain-add\' => [\'name\'], \'dns-domain-update\' => [\'name\'], \'dns-domain-delete\' => [\'name\'], \'dns-domain-axfr-run\' => [\'name\'], \'dns-domain-axfr-tsig\' => [\'name\'], \'dns-domain-copy\' => [\'name\', \'name_from\'], \'dns-domain-commit\' => [\'name\'], \'dns-rows-list\' => [\'domain\'], \'dns-row-detail\' => [\'domain\', \'row_id\'], \'dns-row-add\' => [\'domain\', \'name\', \'ttl\', \'type\', \'rdata\'], \'dns-row-update\' => [\'domain\', \'row_id\'], \'dns-row-delete\' => [\'domain\', \'row_id\'], \'credit-info\' => [], \'account-list\' => [], \'poll-req\' => [], \'poll-ack\' => [\'id\']]',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 43,
            'startTokenPos' => 114,
            'startFilePos' => 1649,
            'endTokenPos' => 554,
            'endFilePos' => 3185,
          ),
        ),
        'docComment' => '/** @var array<string, list<string>> command => required data fields */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'consecutiveAuthErrors' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'consecutiveAuthErrors',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 565,
            'startFilePos' => 3230,
            'endTokenPos' => 565,
            'endFilePos' => 3230,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'instance' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'instance',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 9,
        'endColumn' => 51,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'credentials' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'credentials',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 9,
        'endColumn' => 43,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'http' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'http',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 9,
        'endColumn' => 49,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cache' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'cache',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Contracts\\Cache\\Repository',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 9,
        'endColumn' => 47,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'clock' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'name' => 'clock',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Clock\\Clock',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 9,
        'endColumn' => 37,
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
          'instance' => 
          array (
            'name' => 'instance',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 9,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'credentials' => 
          array (
            'name' => 'credentials',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'http' => 
          array (
            'name' => 'http',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 9,
            'endColumn' => 49,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'cache' => 
          array (
            'name' => 'cache',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Cache\\Repository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 51,
            'endLine' => 51,
            'startColumn' => 9,
            'endColumn' => 47,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'clock' => 
          array (
            'name' => 'clock',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Clock\\Clock',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'instance' => 
      array (
        'name' => 'instance',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 59,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'command' => 
      array (
        'name' => 'command',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 29,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 68,
                'endLine' => 68,
                'startTokenPos' => 761,
                'startFilePos' => 4196,
                'endTokenPos' => 762,
                'endFilePos' => 4197,
              ),
            ),
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
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'clTrid' => 
          array (
            'name' => 'clTrid',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 68,
                'endLine' => 68,
                'startTokenPos' => 772,
                'startFilePos' => 4218,
                'endTokenPos' => 772,
                'endFilePos' => 4221,
              ),
            ),
            'type' => 
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 64,
            'endColumn' => 85,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'critical' => 
          array (
            'name' => 'critical',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 68,
                'endLine' => 68,
                'startTokenPos' => 781,
                'startFilePos' => 4241,
                'endTokenPos' => 781,
                'endFilePos' => 4245,
              ),
            ),
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
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 88,
            'endColumn' => 109,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'testMode' => 
          array (
            'name' => 'testMode',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 68,
                'endLine' => 68,
                'startTokenPos' => 791,
                'startFilePos' => 4266,
                'endTokenPos' => 791,
                'endFilePos' => 4269,
              ),
            ),
            'type' => 
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
                      'name' => 'bool',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 112,
            'endColumn' => 133,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'operationId' => 
          array (
            'name' => 'operationId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 68,
                'endLine' => 68,
                'startTokenPos' => 801,
                'startFilePos' => 4295,
                'endTokenPos' => 801,
                'endFilePos' => 4298,
              ),
            ),
            'type' => 
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 136,
            'endColumn' => 162,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<string,mixed>  $data
 * @return array{code:int, result:string, data:array<string,mixed>, clTRID:string, svTRID:?string, raw:array<string,mixed>}
 */',
        'startLine' => 68,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'clTrid' => 
      array (
        'name' => 'clTrid',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 129,
            'endLine' => 129,
            'startColumn' => 35,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operationId' => 
          array (
            'name' => 'operationId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 129,
                'endLine' => 129,
                'startTokenPos' => 1751,
                'startFilePos' => 8216,
                'endTokenPos' => 1751,
                'endFilePos' => 8219,
              ),
            ),
            'type' => 
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 129,
            'endLine' => 129,
            'startColumn' => 52,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => true,
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
        'docComment' => '/** `onhost:v4:<command>:<op id or ulid>` — the correlation/idempotency anchor (§45.6). */',
        'startLine' => 129,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'auth' => 
      array (
        'name' => 'auth',
        'parameters' => 
        array (
          'timestamp' => 
          array (
            'name' => 'timestamp',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 135,
                'endLine' => 135,
                'startTokenPos' => 1802,
                'startFilePos' => 8478,
                'endTokenPos' => 1802,
                'endFilePos' => 8481,
              ),
            ),
            'type' => 
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
                      'name' => 'int',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 26,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
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
        'docComment' => '/** sha1(login . sha1(password) . H) where H is the current hour in Europe/Prague (§45.2). */',
        'startLine' => 135,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'quota' => 
      array (
        'name' => 'quota',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return array{used_all:int, remaining_all:int, used_domain:int, remaining_domain:int, reset_in:int} */',
        'startLine' => 148,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'validate' => 
      array (
        'name' => 'validate',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 31,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 48,
            'endColumn' => 58,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 156,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'assertClockHealthy' => 
      array (
        'name' => 'assertClockHealthy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 176,
        'endLine' => 184,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'aliasName' => NULL,
      ),
      'invalidRequestBreaker' => 
      array (
        'name' => 'invalidRequestBreaker',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 186,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WapiGateway',
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