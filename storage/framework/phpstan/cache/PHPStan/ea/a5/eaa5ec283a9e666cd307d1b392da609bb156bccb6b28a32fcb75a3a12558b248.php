<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Subreg\SubregPublicPriceList.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Subreg\SubregPublicPriceList
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-7a57b4e0758fe3924a5ade8c076eecbec3cdbe5953c85b84f0cb70a0454bf645',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Subreg/SubregPublicPriceList.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Subreg',
    'name' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
    'shortName' => 'SubregPublicPriceList',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Public domain price list of Subreg.CZ (https://subreg.cz/cz/cenik-domen/): rows
 * `.tld | info | min. years | new | renew | transfer`, prices as "177 Kč * (213,82 Kč)" — net first,
 * the asterisk marks a first-year promotion, the bracket the amount with VAT. Columns are located
 * by the header row so a reordered table still parses.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 66,
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
        'namespace' => 'Onhost\\Providers\\Subreg',
        'declaringClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'implementingClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'currentClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
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
        'namespace' => 'Onhost\\Providers\\Subreg',
        'declaringClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'implementingClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'currentClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
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
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Subreg',
        'declaringClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'implementingClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
        'currentClassName' => 'Onhost\\Providers\\Subreg\\SubregPublicPriceList',
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