<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\CapacityForecast.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\CapacityForecast
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-7c0102e62bd341fc2e14d8c4bd3c954197d86cfd0f0e47b193655623d70641a2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/CapacityForecast.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning',
    'name' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
    'shortName' => 'CapacityForecast',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Trend-driven pre-provisioning (audit §5m-7): per role and region the sellable RAM (N+1 view) against what is sold
 * and how fast the measured load grows (the daily slope of every node\'s 7-day trend); the days left before the pool
 * is sold out reach operations before the scheduler refuses an order. Once a day per pool while it is short.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 136,
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
      'ROLES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'name' => 'ROLES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'compute\', \'game\', \'web\', \'managed\', \'mail\']',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 82,
            'startFilePos' => 919,
            'endTokenPos' => 96,
            'endFilePos' => 963,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 71,
      ),
    ),
    'immediateProperties' => 
    array (
      'scheduler' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'name' => 'scheduler',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 33,
        'endColumn' => 73,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'rebalancer' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'name' => 'rebalancer',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeRebalancer',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 76,
        'endColumn' => 118,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'outbox' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
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
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 121,
        'endColumn' => 160,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cache' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
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
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 163,
        'endColumn' => 201,
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
          'scheduler' => 
          array (
            'name' => 'scheduler',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 33,
            'endColumn' => 73,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'rebalancer' => 
          array (
            'name' => 'rebalancer',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeRebalancer',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 76,
            'endColumn' => 118,
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 121,
            'endColumn' => 160,
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 163,
            'endColumn' => 201,
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
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 205,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'aliasName' => NULL,
      ),
      'forecast' => 
      array (
        'name' => 'forecast',
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
 * @return list<array{role:string, region:?string, nodes:int, sellable_mb:int, sold_mb:int, used_mb:int, headroom_mb:int, growth_mb_per_day:int, days_left:?int, low:bool, basis:string}>
 */',
        'startLine' => 31,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'aliasName' => NULL,
      ),
      'budget' => 
      array (
        'name' => 'budget',
        'parameters' => 
        array (
          'horizonDays' => 
          array (
            'name' => 'horizonDays',
            'default' => 
            array (
              'code' => '30',
              'attributes' => 
              array (
                'startLine' => 75,
                'endLine' => 75,
                'startTokenPos' => 800,
                'startFilePos' => 4104,
                'endTokenPos' => 800,
                'endFilePos' => 4105,
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
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 28,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'pools' => 
          array (
            'name' => 'pools',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 75,
                'endLine' => 75,
                'startTokenPos' => 810,
                'startFilePos' => 4124,
                'endTokenPos' => 810,
                'endFilePos' => 4127,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 51,
            'endColumn' => 70,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * The budget view of the forecast (audit §5r-5): per pool the RAM the 30-day trend needs beyond today\'s headroom,
 * the whole nodes that takes (the pool\'s average node size) and their monthly price — the configured
 * `node_monthly_minor.{role}`, else the average vendor cost of the role\'s past orders — summed against the monthly
 * cap. Pools without a known price count their nodes but no money (`unpriced`).
 *
 * @return array{month:string, currency:string, budget_minor:int, total_minor:int, nodes:int, over:bool, unpriced:int, pools:list<array<string,mixed>>}
 */',
        'startLine' => 75,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'aliasName' => NULL,
      ),
      'warn' => 
      array (
        'name' => 'warn',
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
        'docComment' => '/** Tells operations about pools that run short (once a day per pool). @return list<string> pools warned now */',
        'startLine' => 111,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\CapacityForecast',
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