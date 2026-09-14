<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Invoicing\Listeners\SettleInvoicePayment.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Invoicing\Listeners\SettleInvoicePayment
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-f3f35ac64ea5c5cfe733fa119d73feebd2c7ee9e10de1c8abb6047efb0289c4d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Invoicing/Listeners/SettleInvoicePayment.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Invoicing\\Listeners',
    'name' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
    'shortName' => 'SettleInvoicePayment',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A verified bank transfer or gateway payment made for an invoice (postpaid documents paid "by bank" or "by card"
 * from the panel): the settled amount was credited to the wallet by PaymentService::settle(), so the invoice is paid
 * from the wallet the same way an explicit "pay from credit" would be — one ledger path for every payment method.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 41,
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
      'invoices' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'name' => 'invoices',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 33,
        'endColumn' => 73,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'wallets' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
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
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 76,
        'endColumn' => 114,
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
          'invoices' => 
          array (
            'name' => 'invoices',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 33,
            'endColumn' => 73,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 76,
            'endColumn' => 114,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 118,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Listeners',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
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
                'name' => 'Onhost\\Domain\\Payments\\Events\\PaymentSucceeded',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
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
        'startLine' => 22,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Listeners',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Listeners\\SettleInvoicePayment',
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