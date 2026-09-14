<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Orders\CommerceHousekeeping.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Orders\CommerceHousekeeping
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-6390ab9345a4da3b37069a196dd91c23b35f0f69081a015a31bcb9b0ab688cad',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Orders\\CommerceHousekeeping',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Orders/CommerceHousekeeping.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Orders',
    'name' => 'Onhost\\Domain\\Orders\\CommerceHousekeeping',
    'shortName' => 'CommerceHousekeeping',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Commerce housekeeping: every cart change with the drawer open produces a quote (valid two hours) and every visitor
 * gets a server cart, so the tables grow with browsing, not with orders. The nightly prune drops open quotes whose
 * validity ended more than a day ago and that no order references, and open carts that expired more than a week ago
 * and were never converted. Accepted quotes and converted carts are the order\'s paper trail and stay.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 35,
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
      'prune' => 
      array (
        'name' => 'prune',
        'parameters' => 
        array (
          'quoteGraceHours' => 
          array (
            'name' => 'quoteGraceHours',
            'default' => 
            array (
              'code' => '24',
              'attributes' => 
              array (
                'startLine' => 20,
                'endLine' => 20,
                'startTokenPos' => 54,
                'startFilePos' => 773,
                'endTokenPos' => 54,
                'endFilePos' => 774,
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 27,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'cartGraceDays' => 
          array (
            'name' => 'cartGraceDays',
            'default' => 
            array (
              'code' => '7',
              'attributes' => 
              array (
                'startLine' => 20,
                'endLine' => 20,
                'startTokenPos' => 63,
                'startFilePos' => 798,
                'endTokenPos' => 63,
                'endFilePos' => 798,
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 54,
            'endColumn' => 75,
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
        'docComment' => '/** @return array{quotes:int, carts:int} */',
        'startLine' => 20,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Orders',
        'declaringClassName' => 'Onhost\\Domain\\Orders\\CommerceHousekeeping',
        'implementingClassName' => 'Onhost\\Domain\\Orders\\CommerceHousekeeping',
        'currentClassName' => 'Onhost\\Domain\\Orders\\CommerceHousekeeping',
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