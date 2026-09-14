<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Billing\ReportService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Billing\ReportService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-5752db6efde37bf45d6c41e8ed8f3e9d80f9fc94e314b6c17f5521b92a30258d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Billing\\ReportService',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Billing/ReportService.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Billing',
    'name' => 'Onhost\\Domain\\Billing\\ReportService',
    'shortName' => 'ReportService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Finance reports computed from subscriptions, invoices and billing periods — never from UI state (handoff §10). */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 86,
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
    ),
    'immediateMethods' => 
    array (
      'mrr' => 
      array (
        'name' => 'mrr',
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
        'docComment' => '/** @return array<string, array{mrr:Money, arr:Money, subscriptions:int, metered_last_month:Money}> per currency */',
        'startLine' => 18,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'aliasName' => NULL,
      ),
      'collections' => 
      array (
        'name' => 'collections',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => 
            array (
              'code' => '30',
              'attributes' => 
              array (
                'startLine' => 35,
                'endLine' => 35,
                'startTokenPos' => 328,
                'startFilePos' => 1601,
                'endTokenPos' => 328,
                'endFilePos' => 1602,
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 33,
            'endColumn' => 46,
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
        'docComment' => '/** Collections and receivables per currency for the last N days, with ageing buckets. */',
        'startLine' => 35,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'aliasName' => NULL,
      ),
      'churn' => 
      array (
        'name' => 'churn',
        'parameters' => 
        array (
          'months' => 
          array (
            'name' => 'months',
            'default' => 
            array (
              'code' => '6',
              'attributes' => 
              array (
                'startLine' => 61,
                'endLine' => 61,
                'startTokenPos' => 974,
                'startFilePos' => 3918,
                'endTokenPos' => 974,
                'endFilePos' => 3918,
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
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 27,
            'endColumn' => 41,
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
        'docComment' => '/** Monthly churn: subscriptions cancelled in month / active at month start. @return list<array{month:string,active_start:int,cancelled:int,new:int,churn_pct:float}> */',
        'startLine' => 61,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'aliasName' => NULL,
      ),
      'revenueByMonth' => 
      array (
        'name' => 'revenueByMonth',
        'parameters' => 
        array (
          'months' => 
          array (
            'name' => 'months',
            'default' => 
            array (
              'code' => '12',
              'attributes' => 
              array (
                'startLine' => 77,
                'endLine' => 77,
                'startTokenPos' => 1287,
                'startFilePos' => 5030,
                'endTokenPos' => 1287,
                'endFilePos' => 5031,
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
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 36,
            'endColumn' => 51,
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
        'docComment' => '/** Revenue by month from the ledger revenue accounts (net of VAT). @return list<array{month:string, currency:string, net:Money}> */',
        'startLine' => 77,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ReportService',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ReportService',
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