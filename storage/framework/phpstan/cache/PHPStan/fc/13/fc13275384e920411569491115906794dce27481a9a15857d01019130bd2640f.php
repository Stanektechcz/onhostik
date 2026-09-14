<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\Scheduling\NodeScheduler.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\Scheduling\NodeScheduler
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-5223d1a02a98ab5c60faba2167428712351e00877764bb69b74e3b4eef64687c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/Scheduling/NodeScheduler.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning\\Scheduling',
    'name' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
    'shortName' => 'NodeScheduler',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Blueprint §5.3: hard constraints first (region, role, state, provider
 * usability, capacity after placement, anti-affinity), then a weighted soft score:
 *   0.30 memory headroom + 0.25 cpu headroom + 0.20 storage headroom
 *   + 0.10 io health + 0.10 failure-domain affinity + 0.05 network health.
 * Sellable capacity is the N+1 view: the largest node\'s capacity is treated as reserve.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 126,
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
      'sandboxFor' => 
      array (
        'name' => 'sandboxFor',
        'parameters' => 
        array (
          'organizationId' => 
          array (
            'name' => 'organizationId',
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 39,
            'endColumn' => 61,
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
        'docComment' => '/** Whether an organization is a sandbox tenant (`feature_flags.sandbox`): its services are placed on lab instances (`options.sandbox`). */',
        'startLine' => 26,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Scheduling',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'aliasName' => NULL,
      ),
      'pick' => 
      array (
        'name' => 'pick',
        'parameters' => 
        array (
          'constraints' => 
          array (
            'name' => 'constraints',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 26,
            'endColumn' => 43,
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
        'startLine' => 35,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Scheduling',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'aliasName' => NULL,
      ),
      'sellableCapacity' => 
      array (
        'name' => 'sellableCapacity',
        'parameters' => 
        array (
          'role' => 
          array (
            'name' => 'role',
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
            'startLine' => 110,
            'endLine' => 110,
            'startColumn' => 38,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'region' => 
          array (
            'name' => 'region',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 110,
                'endLine' => 110,
                'startTokenPos' => 1372,
                'startFilePos' => 6338,
                'endTokenPos' => 1372,
                'endFilePos' => 6341,
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
            'startLine' => 110,
            'endLine' => 110,
            'startColumn' => 52,
            'endColumn' => 73,
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
        'docComment' => '/** N+1 sellable capacity of a role/region: total minus the largest node, times the sell ratio. @return array{ram_mb:int, cpu_cores:int, disk_gb:int, nodes:int, largest_node:?string} */',
        'startLine' => 110,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Scheduling',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Scheduling\\NodeScheduler',
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