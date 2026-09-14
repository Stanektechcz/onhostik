<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Contracts\NodeOrderProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Contracts\NodeOrderProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-5291fb36a5b82ece44bb764908035cf51a6ee9748563fa576519f9ee45aeb582',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Contracts/NodeOrderProvider.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Contracts',
    'name' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
    'shortName' => 'NodeOrderProvider',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Orders a new host from an infrastructure vendor (audit §5n-7): the capacity planner calls it when a pool runs short and
 * the pool\'s provider instance says how nodes are bought (`options.node_order`). The adapter returns the vendor\'s id and
 * the name the node will carry; provisioning of the hypervisor on it stays with the operator\'s playbooks.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 22,
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
      'order' => 
      array (
        'name' => 'order',
        'parameters' => 
        array (
          'spec' => 
          array (
            'name' => 'spec',
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
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 27,
            'endColumn' => 37,
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
        'docComment' => '/**
 * @param  array{name:string, ram_mb:int, cpu_cores:int, disk_gb:int, region:?string, options:array<string,mixed>}  $spec
 * @return array{remote_id:string, name:string, ip:?string, type:?string, cost_minor?:?int, currency?:?string} cost = the vendor\'s monthly price of the type (audit §5q-5)
 */',
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Contracts',
        'declaringClassName' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
        'implementingClassName' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
        'currentClassName' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
        'aliasName' => NULL,
      ),
      'catalogue' => 
      array (
        'name' => 'catalogue',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 31,
            'endColumn' => 44,
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
        'docComment' => '/** @return list<array{type:string, ram_mb:int, cpu_cores:int, disk_gb:int, price_monthly_minor?:?int, currency?:?string}> what can be ordered, smallest first (empty when unknown); the price feeds the monthly budget cap (audit §5q-5) */',
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 53,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Contracts',
        'declaringClassName' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
        'implementingClassName' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
        'currentClassName' => 'Onhost\\Providers\\Contracts\\NodeOrderProvider',
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