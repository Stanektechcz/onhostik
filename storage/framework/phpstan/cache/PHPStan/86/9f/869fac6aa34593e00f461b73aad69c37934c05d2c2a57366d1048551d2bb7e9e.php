<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Outbox\RelayOutboxJob.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Outbox\RelayOutboxJob
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-111dc7ed8b4eb7cb09c8cff63f9f7dc254eb1a35b640596f8889849219fcb8ae',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Outbox/RelayOutboxJob.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Outbox',
    'name' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
    'shortName' => 'RelayOutboxJob',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Delivers pending outbox events right after they were published (order paid → fulfilment, payment → invoices closed,
 * notifications and mails) instead of waiting for the minute scheduler. One job per burst: the publisher debounces
 * dispatches for a few seconds and the handler runs under a short lock, so many events in one request cost one relay.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 29,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
      1 => 'Illuminate\\Bus\\Queueable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'tries' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
        'implementingClassName' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
        'name' => 'tries',
        'modifiers' => 1,
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
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 62,
            'startFilePos' => 671,
            'endTokenPos' => 62,
            'endFilePos' => 671,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'outbox' => 
          array (
            'name' => 'outbox',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 28,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 23,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Outbox',
        'declaringClassName' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
        'implementingClassName' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
        'currentClassName' => 'Onhost\\Platform\\Outbox\\RelayOutboxJob',
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