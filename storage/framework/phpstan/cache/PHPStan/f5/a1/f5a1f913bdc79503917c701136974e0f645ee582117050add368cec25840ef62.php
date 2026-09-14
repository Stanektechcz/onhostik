<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Orders\OrderRiskService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Orders\OrderRiskService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-1d0b1357b2faa9eaf1f7385042ed531933c30ef7c0c4bff4d0504a4e59507a65',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Orders/OrderRiskService.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Orders',
    'name' => 'Onhost\\Domain\\Orders\\OrderRiskService',
    'shortName' => 'OrderRiskService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Order intake pre-check (audit §5f-8): a handful of signals the platform already has — a brand-new account, a
 * disposable or free mailbox behind a company, several orders in minutes, a large first order, failed payments
 * behind it, a VAT id from another country, an address from another country than the customer claims (§5g-4) —
 * add up to a score. Below the hold threshold nothing changes; above it the order is still placed and paid but its
 * fulfilment waits for a staff decision (release or reject), so a fraudulent card or a stolen account never gets a
 * server provisioned in the ninety seconds before anyone looks. Automatic orders (plan upgrades the platform itself
 * places) are never scored. Staff decisions feed back (§5g-4): every release lowers the weight of the signals that
 * held the order, every reject raises them, within bounds — the check learns what this customer base looks like.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 32,
    'endLine' => 189,
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
      'HOLD_SCORE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'HOLD_SCORE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '60',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 103,
            'startFilePos' => 1648,
            'endTokenPos' => 103,
            'endFilePos' => 1649,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'WEIGHTS_SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'WEIGHTS_SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\\Onhost\\Domain\\Risk\\RiskWeights::SETTING',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 114,
            'startFilePos' => 1688,
            'endTokenPos' => 116,
            'endFilePos' => 1707,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
      'FEEDBACK_SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'FEEDBACK_SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\\Onhost\\Domain\\Risk\\RiskWeights::FEEDBACK_SETTING',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 129,
            'startFilePos' => 1783,
            'endTokenPos' => 131,
            'endFilePos' => 1811,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 66,
      ),
      'HOLD_SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'HOLD_SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'orders.risk.hold_score\'',
          'attributes' => 
          array (
            'startLine' => 40,
            'endLine' => 40,
            'startTokenPos' => 142,
            'startFilePos' => 1847,
            'endTokenPos' => 142,
            'endFilePos' => 1870,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 57,
      ),
      'WEIGHTS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'WEIGHTS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'referral_flagged\' => 40, \'new_account\' => 25, \'disposable_email\' => 50, \'free_mail_company\' => 10, \'rapid_orders\' => 30, \'large_first_order\' => 25, \'failed_payments\' => 30, \'vat_country_mismatch\' => 15, \'ip_country_mismatch\' => 20, \'turnstile_failed\' => 35]',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 155,
            'startFilePos' => 2005,
            'endTokenPos' => 224,
            'endFilePos' => 2263,
          ),
        ),
        'docComment' => '/** Default weight of every signal; staff feedback moves them between MIN_WEIGHT and MAX_WEIGHT. */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 287,
      ),
      'MIN_WEIGHT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'MIN_WEIGHT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 235,
            'startFilePos' => 2297,
            'endTokenPos' => 235,
            'endFilePos' => 2297,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'MAX_WEIGHT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'MAX_WEIGHT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '100',
          'attributes' => 
          array (
            'startLine' => 47,
            'endLine' => 47,
            'startTokenPos' => 246,
            'startFilePos' => 2331,
            'endTokenPos' => 246,
            'endFilePos' => 2333,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
    ),
    'immediateProperties' => 
    array (
      'ledger' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'ledger',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 33,
        'endColumn' => 73,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'settings' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'settings',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Settings\\SettingsStore',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 76,
        'endColumn' => 115,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'geo' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'geo',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 118,
        'endColumn' => 152,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'risk' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'risk',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Risk\\RiskWeights',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 155,
        'endColumn' => 188,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'turnstile' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'name' => 'turnstile',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Risk\\Turnstile',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 191,
        'endColumn' => 227,
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
          'ledger' => 
          array (
            'name' => 'ledger',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
                'isIdentifier' => false,
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
            'startColumn' => 33,
            'endColumn' => 73,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'settings' => 
          array (
            'name' => 'settings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Settings\\SettingsStore',
                'isIdentifier' => false,
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
            'startColumn' => 76,
            'endColumn' => 115,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'geo' => 
          array (
            'name' => 'geo',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
                'isIdentifier' => false,
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
            'startColumn' => 118,
            'endColumn' => 152,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'risk' => 
          array (
            'name' => 'risk',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Risk\\RiskWeights',
                'isIdentifier' => false,
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
            'startColumn' => 155,
            'endColumn' => 188,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'turnstile' => 
          array (
            'name' => 'turnstile',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Risk\\Turnstile',
                'isIdentifier' => false,
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
            'startColumn' => 191,
            'endColumn' => 227,
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
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 231,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'weights' => 
      array (
        'name' => 'weights',
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
        'docComment' => '/** The weights in force: the defaults moved by staff feedback. @return array<string,int> */',
        'startLine' => 52,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'assess' => 
      array (
        'name' => 'assess',
        'parameters' => 
        array (
          'quote' => 
          array (
            'name' => 'quote',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Orders\\Models\\Quote',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 28,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 42,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'user' => 
          array (
            'name' => 'user',
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
                      'name' => 'Onhost\\Domain\\Identity\\Models\\User',
                      'isIdentifier' => false,
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
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 70,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Commands\\CommandContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 83,
            'endColumn' => 105,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'source' => 
          array (
            'name' => 'source',
            'default' => 
            array (
              'code' => '\'web\'',
              'attributes' => 
              array (
                'startLine' => 58,
                'endLine' => 58,
                'startTokenPos' => 371,
                'startFilePos' => 3011,
                'endTokenPos' => 371,
                'endFilePos' => 3015,
              ),
            ),
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
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 108,
            'endColumn' => 129,
            'parameterIndex' => 4,
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
        'docComment' => '/** @return array{score:int, reasons:list<string>, hold:bool} */',
        'startLine' => 58,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'holdScore' => 
      array (
        'name' => 'holdScore',
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
        'docComment' => '/** The hold threshold in force: what staff set in the console, else the configured default. */',
        'startLine' => 127,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'tune' => 
      array (
        'name' => 'tune',
        'parameters' => 
        array (
          'weights' => 
          array (
            'name' => 'weights',
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
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 26,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'holdScore' => 
          array (
            'name' => 'holdScore',
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
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 42,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'reset' => 
          array (
            'name' => 'reset',
            'default' => NULL,
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
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 59,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'by' => 
          array (
            'name' => 'by',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 141,
                'endLine' => 141,
                'startTokenPos' => 1476,
                'startFilePos' => 7717,
                'endTokenPos' => 1476,
                'endFilePos' => 7720,
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
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 72,
            'endColumn' => 89,
            'parameterIndex' => 3,
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
 * Staff tune the check from the console (audit §5h-4): weights per signal within bounds, the hold threshold, or a
 * reset to the defaults (which also forgets the feedback counters).
 *
 * @param  array<string,int|string>  $weights
 * @return array{weights:array<string,int>, hold_score:int, defaults:array<string,int>, feedback:array<string,array{released:int,rejected:int}>}
 */',
        'startLine' => 141,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'tuning' => 
      array (
        'name' => 'tuning',
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
        'docComment' => '/** @return array{weights:array<string,int>, hold_score:int, defaults:array<string,int>, feedback:array<string,array{released:int,rejected:int}>} */',
        'startLine' => 158,
        'endLine' => 161,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'learn' => 
      array (
        'name' => 'learn',
        'parameters' => 
        array (
          'order' => 
          array (
            'name' => 'order',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Orders\\Models\\Order',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 169,
            'endLine' => 169,
            'startColumn' => 27,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'decision' => 
          array (
            'name' => 'decision',
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
            'startLine' => 169,
            'endLine' => 169,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'by' => 
          array (
            'name' => 'by',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 169,
                'endLine' => 169,
                'startTokenPos' => 1721,
                'startFilePos' => 9035,
                'endTokenPos' => 1721,
                'endFilePos' => 9038,
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
            'startLine' => 169,
            'endLine' => 169,
            'startColumn' => 59,
            'endColumn' => 76,
            'parameterIndex' => 2,
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
 * Staff decided about a held order (§5g-4): the signals that held it get lighter after a release and heavier after
 * a reject, one step per decision, within bounds. Returns the weights now in force.
 *
 * @return array<string,int>
 */',
        'startLine' => 169,
        'endLine' => 182,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'aliasName' => NULL,
      ),
      'feedback' => 
      array (
        'name' => 'feedback',
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
        'docComment' => '/** What staff taught the check so far. @return array<string,array{released:int,rejected:int}> */',
        'startLine' => 185,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
        'currentClassName' => 'Onhost\\Domain\\Orders\\OrderRiskService',
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