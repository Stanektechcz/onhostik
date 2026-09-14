<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Notifications\DigestService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Notifications\DigestService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-a606fcc768816c66f5ce597b0b4c46a5b6b60477badea99e9e133dbfe480a815',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Notifications\\DigestService',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Notifications/DigestService.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Notifications',
    'name' => 'Onhost\\Domain\\Notifications\\DigestService',
    'shortName' => 'DigestService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Digests (audit §5e-5): one weekly summary per customer organization — what renews and expires in the next two weeks,
 * what the credit covers, how the backups and monitors did, what the plans are using up — and one daily summary for
 * staff — what is stuck, what failed, who is behind on payment, which nodes and integrations need a look. Both are
 * assembled from the records the platform already keeps; nothing is measured anew. The customer digest is a
 * preference kind (`digest`) the customer can switch off; the staff digest goes to the addresses in
 * `onhost.notifications.staff_digest_to` and to the internal feed.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 32,
    'endLine' => 157,
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
      'FREQUENCIES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'name' => 'FREQUENCIES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'weekly\', \'monthly\', \'off\']',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 161,
            'startFilePos' => 1744,
            'endTokenPos' => 169,
            'endFilePos' => 1771,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 60,
      ),
    ),
    'immediateProperties' => 
    array (
      'notifications' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'name' => 'notifications',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Notifications\\NotificationService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 9,
        'endColumn' => 59,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'calendar' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'name' => 'calendar',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Notifications\\CalendarFeed',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 9,
        'endColumn' => 47,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'forecast' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'name' => 'forecast',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 9,
        'endColumn' => 49,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'board' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'name' => 'board',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 9,
        'endColumn' => 47,
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
          'notifications' => 
          array (
            'name' => 'notifications',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Notifications\\NotificationService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 9,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'calendar' => 
          array (
            'name' => 'calendar',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Notifications\\CalendarFeed',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 9,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'forecast' => 
          array (
            'name' => 'forecast',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 9,
            'endColumn' => 49,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'board' => 
          array (
            'name' => 'board',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 9,
            'endColumn' => 47,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 34,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'aliasName' => NULL,
      ),
      'frequency' => 
      array (
        'name' => 'frequency',
        'parameters' => 
        array (
          'organization' => 
          array (
            'name' => 'organization',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 38,
            'endColumn' => 63,
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
        'docComment' => '/** The organization\'s digest frequency (audit §5f-5): weekly (default), monthly (the first digest run of the month) or off. */',
        'startLine' => 44,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'aliasName' => NULL,
      ),
      'weekly' => 
      array (
        'name' => 'weekly',
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
        'docComment' => '/** Every organization with at least one service: a notification and a mail per organization. @return array{organizations:int, sent:int, skipped:int} */',
        'startLine' => 52,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'aliasName' => NULL,
      ),
      'customerWeekly' => 
      array (
        'name' => 'customerWeekly',
        'parameters' => 
        array (
          'organization' => 
          array (
            'name' => 'organization',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 36,
            'endColumn' => 61,
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
                  'name' => 'array',
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
        'docComment' => '/** @return array{title:string, body:string, text:string, sections:array<string,list<string>>}|null */',
        'startLine' => 79,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'aliasName' => NULL,
      ),
      'staffDaily' => 
      array (
        'name' => 'staffDaily',
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
        'docComment' => '/** Daily staff summary: the internal feed plus the configured addresses. @return array{title:string, lines:list<string>, recipients:int} */',
        'startLine' => 133,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Notifications',
        'declaringClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'implementingClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
        'currentClassName' => 'Onhost\\Domain\\Notifications\\DigestService',
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