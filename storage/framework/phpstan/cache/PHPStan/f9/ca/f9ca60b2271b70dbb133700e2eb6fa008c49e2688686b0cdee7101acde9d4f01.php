<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Catalog\Commands\CatalogCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Catalog\Commands\CatalogCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-5ef61524dbf5cbf9694a4b2270d8a734cafbb18d8a47d4994a056bffe93792a7',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Catalog/Commands/CatalogCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
    'name' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
    'shortName' => 'CatalogCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Staff pricing controls (Nastavení systému → Slevy a doplňky), dispatched by `op`:
 *  pricing.commit_discounts.set{config} · pricing.domain_discount.set{tld,discount} · pricing.domain_discount.delete{tld} ·
 *  pricing.addon_products.set{product_key,addon_products} · promo.upsert{promo} · promo.delete{code} ·
 *  option.upsert{product_key,option} · option.delete{product_key,key}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 52,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Commands\\GlobalCommand',
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Domain\\Identity\\Authorization\\RiskAwareCommand',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'pricing.commit_discounts.set\', \'pricing.domain_discount.set\', \'pricing.domain_discount.delete\', \'pricing.addon_products.set\', \'promo.upsert\', \'promo.delete\', \'option.upsert\', \'option.delete\', \'panel_nav.set\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 56,
            'startFilePos' => 747,
            'endTokenPos' => 82,
            'endFilePos' => 956,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 234,
      ),
      'AUDIT_STRIP' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'name' => 'AUDIT_STRIP',
        'modifiers' => 2,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 93,
            'startFilePos' => 994,
            'endTokenPos' => 94,
            'endFilePos' => 995,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'op' => 
      array (
        'name' => 'op',
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
        'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'aliasName' => NULL,
      ),
      'permission' => 
      array (
        'name' => 'permission',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'aliasName' => NULL,
      ),
      'name' => 
      array (
        'name' => 'name',
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
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'aliasName' => NULL,
      ),
      'riskLevel' => 
      array (
        'name' => 'riskLevel',
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
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'aliasName' => NULL,
      ),
      'requiresStepUp' => 
      array (
        'name' => 'requiresStepUp',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'aliasName' => NULL,
      ),
      'requiresApproval' => 
      array (
        'name' => 'requiresApproval',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 48,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\Commands\\CatalogCommand',
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