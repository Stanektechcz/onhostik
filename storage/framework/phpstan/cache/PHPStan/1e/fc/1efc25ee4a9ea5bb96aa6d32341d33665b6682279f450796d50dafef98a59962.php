<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Contracts\RegistrarPricingProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Contracts\RegistrarPricingProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-25037632946b1d6f5ad75bc7abe15955d2342d8e633c6cbe654df0ceb25dd65b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Contracts\\RegistrarPricingProvider',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Contracts/RegistrarPricingProvider.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Contracts',
    'name' => 'Onhost\\Providers\\Contracts\\RegistrarPricingProvider',
    'shortName' => 'RegistrarPricingProvider',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Registrars that publish their wholesale (cost) prices through their API. The
 * platform compares these prices across registrars and registers each domain
 * with the cheapest one (`RegistrarSelector`); registrars without a price API
 * keep a manually maintained cost list.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 20,
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
      'costPrices' => 
      array (
        'name' => 'costPrices',
        'parameters' => 
        array (
          'tlds' => 
          array (
            'name' => 'tlds',
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
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 32,
            'endColumn' => 42,
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
 * @param  list<string>  $tlds  bare TLDs (`cz`, `com`)
 * @return array<string, array{currency:string, register:?string, renew:?string, transfer:?string, restore:?string}> decimal amounts per TLD; TLDs the registrar does not sell are omitted
 */',
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Contracts',
        'declaringClassName' => 'Onhost\\Providers\\Contracts\\RegistrarPricingProvider',
        'implementingClassName' => 'Onhost\\Providers\\Contracts\\RegistrarPricingProvider',
        'currentClassName' => 'Onhost\\Providers\\Contracts\\RegistrarPricingProvider',
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