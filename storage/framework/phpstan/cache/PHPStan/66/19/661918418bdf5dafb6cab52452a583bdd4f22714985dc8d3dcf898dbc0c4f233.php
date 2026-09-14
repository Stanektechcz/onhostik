<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\WalletLedger\WalletForecast.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\WalletLedger\WalletForecast
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-319b815ab51d3081cc03bbea7c96a666bb9928a3d0702faa972b39217184fdb2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/WalletLedger/WalletForecast.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\WalletLedger',
    'name' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
    'shortName' => 'WalletForecast',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Credit runway: the renewals of the organization\'s subscriptions are walked in date order (gross, with the customer\'s
 * VAT) against the available credit; the first renewal the credit cannot cover is the day the credit runs out. A daily
 * pass warns organizations two weeks ahead (`wallet.runway.low`, once per day) unless automatic top-ups are on.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 175,
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
      'HORIZON_DAYS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'HORIZON_DAYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '120',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 78,
            'startFilePos' => 875,
            'endTokenPos' => 78,
            'endFilePos' => 877,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'WARN_DAYS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'WARN_DAYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '14',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 89,
            'startFilePos' => 910,
            'endTokenPos' => 89,
            'endFilePos' => 911,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'GUARD_DAYS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'GUARD_DAYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '7',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 100,
            'startFilePos' => 945,
            'endTokenPos' => 100,
            'endFilePos' => 945,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
    ),
    'immediateProperties' => 
    array (
      'wallets' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'wallets',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 33,
        'endColumn' => 71,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tax' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'tax',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 74,
        'endColumn' => 104,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'outbox' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'outbox',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 107,
        'endColumn' => 146,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'autoTopup' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'name' => 'autoTopup',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 149,
        'endColumn' => 185,
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
          'wallets' => 
          array (
            'name' => 'wallets',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\WalletLedger\\WalletService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 33,
            'endColumn' => 71,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'tax' => 
          array (
            'name' => 'tax',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Tax\\TaxEngine',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 74,
            'endColumn' => 104,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 107,
            'endColumn' => 146,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'autoTopup' => 
          array (
            'name' => 'autoTopup',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 149,
            'endColumn' => 185,
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
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 189,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'aliasName' => NULL,
      ),
      'forecast' => 
      array (
        'name' => 'forecast',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 30,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'currency' => 
          array (
            'name' => 'currency',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 58,
            'endColumn' => 73,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array{currency:string, available:Money, monthly_burn:Money, renewals_30d:Money, shortfall_30d:Money, depletes_at:?string, days:?int, next_renewal:?array{at:string,amount:Money,service_id:?string,domain_id:?string}, auto_topup:bool, subscriptions:int}
 */',
        'startLine' => 35,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'aliasName' => NULL,
      ),
      'renewalGuard' => 
      array (
        'name' => 'renewalGuard',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => 
            array (
              'code' => 'self::GUARD_DAYS',
              'attributes' => 
              array (
                'startLine' => 90,
                'endLine' => 90,
                'startTokenPos' => 874,
                'startFilePos' => 4555,
                'endTokenPos' => 876,
                'endFilePos' => 4570,
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 34,
            'endColumn' => 61,
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
        'docComment' => '/**
 * Renewal guard (audit §5e-2), daily: every organization whose renewals of the next GUARD_DAYS days outrun the
 * available credit either gets its credit topped up automatically (opt-in, a provider that charges stored methods)
 * or hears exactly how much is missing and by when — `billing.renewal.underfunded`, once per day.
 *
 * @return array{checked:int, underfunded:int, topped_up:int, notified:int}
 */',
        'startLine' => 90,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'aliasName' => NULL,
      ),
      'gross' => 
      array (
        'name' => 'gross',
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
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 27,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'subscription' => 
          array (
            'name' => 'subscription',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 55,
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
            'name' => 'Onhost\\Platform\\Money\\Money',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The renewal as the wallet will be charged: net renewal amount plus the customer\'s VAT. */',
        'startLine' => 137,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'aliasName' => NULL,
      ),
      'warnLow' => 
      array (
        'name' => 'warnLow',
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
        'docComment' => '/**
 * Daily pass: every organization with renewals ahead whose credit runs out within WARN_DAYS gets `wallet.runway.low`
 * (notification + mail through the router), once per calendar day; organizations with automatic top-ups are left alone.
 *
 * @return array{checked:int, warned:int}
 */',
        'startLine' => 151,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\WalletForecast',
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