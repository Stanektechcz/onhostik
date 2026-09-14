<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Notifications\WebhookDispatcher.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Notifications\WebhookDispatcher
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-bb91b897f85eb18763fd826458dd1fd67853632c2c92d887b6f6ccab268779b2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Notifications/WebhookDispatcher.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Notifications',
    'name' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
    'shortName' => 'WebhookDispatcher',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Customer webhooks (blueprint §72): every organization-scoped event fans out to
 * subscribed endpoints, signed with HMAC-SHA256 over the raw body, retried with
 * backoff and paused after repeated failures. Internal-only events never leave.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 124,
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
      'BACKOFF_MINUTES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'name' => 'BACKOFF_MINUTES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[1, 5, 30, 120, 720]',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 77,
            'startFilePos' => 736,
            'endTokenPos' => 91,
            'endFilePos' => 755,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
      'CUSTOMER_EVENTS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'name' => 'CUSTOMER_EVENTS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'order.\', \'service.\', \'invoice.\', \'wallet.\', \'domain.\', \'dns.\', \'subscription.\', \'dunning.\', \'ticket.\', \'incident.\', \'maintenance.\', \'app.\', \'backup.\', \'sla.\', \'monitoring.\', \'deploy.\', \'staging.\', \'import.\', \'certificate.\', \'cdn.\']',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 102,
            'startFilePos' => 794,
            'endTokenPos' => 161,
            'endFilePos' => 1026,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 269,
      ),
    ),
    'immediateProperties' => 
    array (
      'http' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'name' => 'http',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Http\\Client\\Factory',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 33,
        'endColumn' => 66,
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
          'http' => 
          array (
            'name' => 'http',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Client\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 33,
            'endColumn' => 66,
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
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 70,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 28,
            'endColumn' => 55,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'aliasName' => NULL,
      ),
      'retryDue' => 
      array (
        'name' => 'retryDue',
        'parameters' => 
        array (
          'limit' => 
          array (
            'name' => 'limit',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 47,
                'endLine' => 47,
                'startTokenPos' => 429,
                'startFilePos' => 2151,
                'endTokenPos' => 429,
                'endFilePos' => 2153,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 30,
            'endColumn' => 45,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Retry due deliveries (scheduler). @return array{delivered:int, failed:int, dead:int} */',
        'startLine' => 47,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'aliasName' => NULL,
      ),
      'deliver' => 
      array (
        'name' => 'deliver',
        'parameters' => 
        array (
          'delivery' => 
          array (
            'name' => 'delivery',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Notifications\\Models\\WebhookDelivery',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 29,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endpoint' => 
          array (
            'name' => 'endpoint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Notifications\\Models\\WebhookEndpoint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 56,
            'endColumn' => 80,
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
        'docComment' => '/** @return \'delivered\'|\'failed\'|\'dead\' */',
        'startLine' => 67,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'aliasName' => NULL,
      ),
      'createEndpoint' => 
      array (
        'name' => 'createEndpoint',
        'parameters' => 
        array (
          'organizationId' => 
          array (
            'name' => 'organizationId',
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
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 36,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'url' => 
          array (
            'name' => 'url',
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
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 60,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'events' => 
          array (
            'name' => 'events',
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
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 73,
            'endColumn' => 85,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'createdBy' => 
          array (
            'name' => 'createdBy',
            'default' => NULL,
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
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 88,
            'endColumn' => 105,
            'parameterIndex' => 3,
            'isOptional' => false,
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
        'docComment' => '/** Creates an endpoint; the plaintext secret is returned exactly once. @return array{endpoint:WebhookEndpoint, secret:string} */',
        'startLine' => 101,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'aliasName' => NULL,
      ),
      'customerVisible' => 
      array (
        'name' => 'customerVisible',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 38,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 109,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'aliasName' => NULL,
      ),
      'payload' => 
      array (
        'name' => 'payload',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Outbox\\OutboxMessage',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 120,
            'endLine' => 120,
            'startColumn' => 30,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 120,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\WebhookDispatcher',
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