<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Billing\ChargebackAnalyst.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Billing\ChargebackAnalyst
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-3aa42e6aaec229a497e0e1d2421ed8bc03f683783bfc21994e7d8b40b5e2ba69',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Billing/ChargebackAnalyst.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Billing',
    'name' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
    'shortName' => 'ChargebackAnalyst',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Chargeback analytics (audit §5j-6): the reasons customers leave, clustered per product, per node and per theme
 * (performance, price, reliability, support, features, moving away). A cluster that crosses the threshold inside the
 * window opens one internal incident on the matching status component — a slow node or a broken template lands in
 * the incident queue, not only in the refund ledger. One incident per cluster while it is open.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 178,
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
      'THEMES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'name' => 'THEMES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'performance\' => \'/pomal|slow|lag|výkon|vykon|latenc|načít|nacit|timeout|přetíž|pretiz/iu\', \'reliability\' => \'/výpad|vypad|down|nefung|nejede|broken|chyb|error|pad[áa]|crash|nedostup/iu\', \'price\' => \'/cen[aeuy]|drah|price|expensive|levn|cheaper|slev|discount/iu\', \'support\' => \'/podpor|support|odpov|response|ticket|nikdo|help/iu\', \'features\' => \'/chyb[íi] |missing|funkc|feature|nepodporuj|not supported|verz|version/iu\', \'moving\' => \'/stěhuj|stehuj|jinam|konkurenc|jin[éy]ho poskytovatel|other provider|moving|přech[áa]z|prechaz|migr/iu\']',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 35,
            'startTokenPos' => 90,
            'startFilePos' => 1150,
            'endTokenPos' => 134,
            'endFilePos' => 1758,
          ),
        ),
        'docComment' => '/** theme => keyword patterns (Czech and English, case-insensitive) */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'incidents' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'name' => 'incidents',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Incidents\\IncidentService',
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
        'startColumn' => 33,
        'endColumn' => 75,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'outbox' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
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
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 78,
        'endColumn' => 117,
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
          'incidents' => 
          array (
            'name' => 'incidents',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Incidents\\IncidentService',
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
            'startColumn' => 33,
            'endColumn' => 75,
            'parameterIndex' => 0,
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 78,
            'endColumn' => 117,
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
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 121,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'aliasName' => NULL,
      ),
      'analytics' => 
      array (
        'name' => 'analytics',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => 
            array (
              'code' => '90',
              'attributes' => 
              array (
                'startLine' => 42,
                'endLine' => 42,
                'startTokenPos' => 178,
                'startFilePos' => 2151,
                'endTokenPos' => 178,
                'endFilePos' => 2152,
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
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 31,
            'endColumn' => 44,
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
 * @return array{days:int, total:int, refunded:Money|null, by_product:list<array<string,mixed>>, by_node:list<array<string,mixed>>, by_theme:list<array<string,mixed>>, clusters:list<array<string,mixed>>}
 */',
        'startLine' => 42,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 100,
                'endLine' => 100,
                'startTokenPos' => 1289,
                'startFilePos' => 5910,
                'endTokenPos' => 1289,
                'endFilePos' => 5913,
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
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 25,
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
        'docComment' => '/** Opens an internal incident for every cluster above the threshold that has none open yet. @return list<string> incident numbers opened */',
        'startLine' => 100,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'aliasName' => NULL,
      ),
      'threshold' => 
      array (
        'name' => 'threshold',
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
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'aliasName' => NULL,
      ),
      'theme' => 
      array (
        'name' => 'theme',
        'parameters' => 
        array (
          'reason' => 
          array (
            'name' => 'reason',
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
            'startLine' => 131,
            'endLine' => 131,
            'startColumn' => 34,
            'endColumn' => 47,
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
        'docComment' => NULL,
        'startLine' => 131,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'aliasName' => NULL,
      ),
      'top' => 
      array (
        'name' => 'top',
        'parameters' => 
        array (
          'themes' => 
          array (
            'name' => 'themes',
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
            'startLine' => 143,
            'endLine' => 143,
            'startColumn' => 33,
            'endColumn' => 45,
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
        'docComment' => '/** @param  array<string,int>  $themes */',
        'startLine' => 143,
        'endLine' => 148,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'aliasName' => NULL,
      ),
      'componentFor' => 
      array (
        'name' => 'componentFor',
        'parameters' => 
        array (
          'cluster' => 
          array (
            'name' => 'cluster',
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'analytics' => 
          array (
            'name' => 'analytics',
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 51,
            'endColumn' => 66,
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
        'docComment' => '/** @param  array<string,mixed>  $cluster @param  array<string,mixed>  $analytics */',
        'startLine' => 151,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Billing',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
        'currentClassName' => 'Onhost\\Domain\\Billing\\ChargebackAnalyst',
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