<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Billing\Listeners\SettleBillingAfterPayment.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Billing\Listeners\SettleBillingAfterPayment
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-9758dd362c101dd87046bb5f24a314e61bcfcdab8cb92e8902ecf274b386c9f5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Billing/Listeners/SettleBillingAfterPayment.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Billing\\Listeners',
    'name' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
    'shortName' => 'SettleBillingAfterPayment',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Money arrived (`onhost.invoice.paid`, `onhost.wallet.topup.completed`): close the
 * matching dunning cases, charge deferred usage and retry past-due renewals.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 36,
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
    ),
    'immediateProperties' => 
    array (
      'dunning' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'name' => 'dunning',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Billing\\DunningService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 33,
        'endColumn' => 72,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'rating' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'name' => 'rating',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Billing\\RatingService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 75,
        'endColumn' => 112,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'subscriptions' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'name' => 'subscriptions',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Billing\\SubscriptionService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 115,
        'endColumn' => 165,
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
          'dunning' => 
          array (
            'name' => 'dunning',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Billing\\DunningService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 33,
            'endColumn' => 72,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'rating' => 
          array (
            'name' => 'rating',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Billing\\RatingService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 75,
            'endColumn' => 112,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'subscriptions' => 
          array (
            'name' => 'subscriptions',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Billing\\SubscriptionService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 115,
            'endColumn' => 165,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 169,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing\\Listeners',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'currentClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 28,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing\\Listeners',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
        'currentClassName' => 'Onhost\\Domain\\Billing\\Listeners\\SettleBillingAfterPayment',
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