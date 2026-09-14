<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Marketplace\Models\MarketplaceListing.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Marketplace\Models\MarketplaceListing
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-f843929da6387776d92755198bee7c5fc6036403be500fae4ce7317e8e8fc98e',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Marketplace/Models/MarketplaceListing.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Marketplace\\Models',
    'name' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
    'shortName' => 'MarketplaceListing',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** A partner\'s service on the marketplace (audit §5j-1): published by staff, ordered by customers, fulfilled by the partner. */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 32,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'DRAFT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'DRAFT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'draft\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 42,
            'startFilePos' => 317,
            'endTokenPos' => 42,
            'endFilePos' => 323,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 12,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'PUBLISHED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'PUBLISHED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'published\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 53,
            'startFilePos' => 356,
            'endTokenPos' => 53,
            'endFilePos' => 366,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'PAUSED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'PAUSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'paused\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 64,
            'startFilePos' => 396,
            'endTokenPos' => 64,
            'endFilePos' => 403,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'RETIRED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'RETIRED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'retired\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 75,
            'startFilePos' => 434,
            'endTokenPos' => 75,
            'endFilePos' => 442,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'STATES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::DRAFT, self::PUBLISHED, self::PAUSED, self::RETIRED]',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 86,
            'startFilePos' => 472,
            'endTokenPos' => 105,
            'endFilePos' => 530,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 86,
      ),
      'BILLING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'BILLING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'oneoff\', \'monthly\']',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 116,
            'startFilePos' => 561,
            'endTokenPos' => 121,
            'endFilePos' => 581,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 49,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'idPrefix',
        'modifiers' => 18,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '\'mkl\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 134,
            'startFilePos' => 625,
            'endTokenPos' => 134,
            'endFilePos' => 629,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'table' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'marketplace_listings\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 143,
            'startFilePos' => 656,
            'endTokenPos' => 143,
            'endFilePos' => 677,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'casts' => 
      array (
        'name' => 'casts',
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
        'docComment' => NULL,
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Marketplace\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'implementingClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
        'currentClassName' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
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