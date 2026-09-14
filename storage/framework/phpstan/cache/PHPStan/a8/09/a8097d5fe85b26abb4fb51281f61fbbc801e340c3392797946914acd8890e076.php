<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Wedos\WedosPublicPriceList.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Wedos\WedosPublicPriceList
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-008bedfdc9173eb080a99807cc3e86d6fa3f245cac6225cc8776cac0ce930772',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Wedos/WedosPublicPriceList.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Wedos',
    'name' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
    'shortName' => 'WedosPublicPriceList',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Public domain price list of the WEDOS/Vedos registrar (https://vedos.cz/domeny/cenik/): one table row per TLD,
 * cells `.tld | registrace | prodloužení [| transfer]` where every price cell starts with the net CZK amount per year
 * ("160 Kč/rok 7,70 €/rok … 193,60 Kč/rok s DPH …"). Columns are located by the header row. Retail prices —
 * an upper bound of the wholesale cost.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 63,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Providers\\Contracts\\PublicPriceListScraper',
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
      'registrarKey' => 
      array (
        'name' => 'registrarKey',
        'parameters' => 
        array (
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
        'startLine' => 18,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'aliasName' => NULL,
      ),
      'url' => 
      array (
        'name' => 'url',
        'parameters' => 
        array (
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
        'startLine' => 23,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'aliasName' => NULL,
      ),
      'parse' => 
      array (
        'name' => 'parse',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
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
            'startLine' => 28,
            'endLine' => 28,
            'startColumn' => 27,
            'endColumn' => 38,
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
        'startLine' => 28,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Wedos',
        'declaringClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'implementingClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
        'currentClassName' => 'Onhost\\Providers\\Wedos\\WedosPublicPriceList',
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