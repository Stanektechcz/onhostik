<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Catalog\PanelNavigation.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Catalog\PanelNavigation
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-85676e8e3de1bcadf8020158211ba9d3e9d966e538a876f898de40b0a845b5ad',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Catalog/PanelNavigation.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Catalog',
    'name' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
    'shortName' => 'PanelNavigation',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The customer panel\'s sidebar follows the offer: a service category is listed when staff keep it switched on and
 * the catalogue sells something in it — and always when the organization already owns a service there (a switched-off
 * or sold-out category never hides what a customer runs). Staff edit the order, the labels and the switches in the
 * system settings (`panel.nav`); the panel and the order wizard read the effective result.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 236,
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
      'SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'name' => 'SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'panel.nav\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 63,
            'startFilePos' => 842,
            'endTokenPos' => 63,
            'endFilePos' => 852,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'CATEGORIES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'name' => 'CATEGORIES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'domain\' => [\'Domény a DNS\', \'Domains and DNS\', \'Domény\', \'Domains\'], \'web\' => [\'Webhosting\', \'Web hosting\', \'Webhosting\', \'Web hosting\'], \'game\' => [\'Herní servery\', \'Game servers\', \'Hry\', \'Games\'], \'vps\' => [\'Servery a VPS\', \'Servers and VPS\', \'Servery\', \'Servers\'], \'mail\' => [\'Emailing\', \'Email\', \'Pošta\', \'Mail\'], \'bucket\' => [\'Objektové úložiště\', \'Object storage\', \'Úložiště\', \'Storage\'], \'housing\' => [\'Housing a racky\', \'Housing and racks\', \'Housing\', \'Housing\']]',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 33,
            'startTokenPos' => 76,
            'startFilePos' => 1003,
            'endTokenPos' => 204,
            'endFilePos' => 1551,
          ),
        ),
        'docComment' => '/** category => [label cs, label en, crumb cs, crumb en] — the keys are the panel\'s service-desk categories */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'LINKS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'name' => 'LINKS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'kb\' => [\'Znalostní báze\', \'Knowledge base\', true], \'status\' => [\'Stav služeb\', \'Service status\', true], \'team\' => [\'Tým a práva\', \'Team and roles\', true], \'api\' => [\'API klíče a webhooky\', \'API keys and webhooks\', true], \'projects\' => [\'Projekty\', \'Projects\', true], \'registrars\' => [\'Připojené registrátory (WEDOS API)\', \'Connected registrars (WEDOS API)\', true], \'audit\' => [\'Oznámení a audit\', \'Notifications and audit\', true], \'windows\' => [\'Servisní okna\', \'Maintenance windows\', true], \'costs\' => [\'Náklady\', \'Costs\', true], \'privacy\' => [\'Osobní údaje a odchod\', \'Personal data and leaving\', true], \'monitoring\' => [\'Monitoring\', \'Monitoring\', true], \'backups\' => [\'Zálohy\', \'Backups\', true]]',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 49,
            'startTokenPos' => 217,
            'startFilePos' => 1673,
            'endTokenPos' => 399,
            'endFilePos' => 2493,
          ),
        ),
        'docComment' => '/** optional sidebar links staff can switch off: key => [label cs, label en, default] */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'settings' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'name' => 'settings',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Settings\\SettingsStore',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 33,
        'endColumn' => 72,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'catalog' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'name' => 'catalog',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 75,
        'endColumn' => 114,
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
          'settings' => 
          array (
            'name' => 'settings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Settings\\SettingsStore',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 51,
            'endLine' => 51,
            'startColumn' => 33,
            'endColumn' => 72,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'catalog' => 
          array (
            'name' => 'catalog',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Catalog\\CatalogService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 51,
            'endLine' => 51,
            'startColumn' => 75,
            'endColumn' => 114,
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
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 118,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'categoryFor' => 
      array (
        'name' => 'categoryFor',
        'parameters' => 
        array (
          'family' => 
          array (
            'name' => 'family',
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
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 40,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'productKey' => 
          array (
            'name' => 'productKey',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 54,
                'endLine' => 54,
                'startTokenPos' => 451,
                'startFilePos' => 2809,
                'endTokenPos' => 451,
                'endFilePos' => 2812,
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
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 56,
            'endColumn' => 81,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'executor' => 
          array (
            'name' => 'executor',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 54,
                'endLine' => 54,
                'startTokenPos' => 461,
                'startFilePos' => 2835,
                'endTokenPos' => 461,
                'endFilePos' => 2838,
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
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 84,
            'endColumn' => 107,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
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
        'docComment' => '/** The sidebar category of a product or service; null for add-ons, which live inside their parent service. */',
        'startLine' => 54,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'config' => 
      array (
        'name' => 'config',
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
 * Stored configuration merged over the defaults, categories in their configured order.
 *
 * @return array{categories: array<string, array{enabled: bool, order: int, label: array{cs: string, en: string}}>, links: array<string, bool>}
 */',
        'startLine' => 78,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'save' => 
      array (
        'name' => 'save',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 26,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'by' => 
          array (
            'name' => 'by',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 108,
                'endLine' => 108,
                'startTokenPos' => 1004,
                'startFilePos' => 5155,
                'endTokenPos' => 1004,
                'endFilePos' => 5158,
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
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 40,
            'endColumn' => 57,
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
 * Staff configuration write (through the catalogue command). Unknown keys are ignored, labels are capped.
 *
 * @param  array<string,mixed>  $input
 * @return array{categories: array<string, array{enabled: bool, order: int, label: array{cs: string, en: string}}>, links: array<string, bool>}
 */',
        'startLine' => 108,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'offered' => 
      array (
        'name' => 'offered',
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
 * Categories the catalogue sells something in right now (active products; domains when a TLD is on offer).
 *
 * @return array<string,bool>
 */',
        'startLine' => 139,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'owned' => 
      array (
        'name' => 'owned',
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
            'startLine' => 161,
            'endLine' => 161,
            'startColumn' => 27,
            'endColumn' => 49,
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
 * How many services (domains for `domain`) the organization runs per category.
 *
 * @return array<string,int>
 */',
        'startLine' => 161,
        'endLine' => 178,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'effective' => 
      array (
        'name' => 'effective',
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
            'startLine' => 185,
            'endLine' => 185,
            'startColumn' => 31,
            'endColumn' => 53,
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
 * The sidebar one organization sees: every category with its visibility and whether new orders are possible.
 *
 * @return array{categories: list<array{key: string, label: array{cs: string, en: string}, crumb: array{cs: string, en: string}, enabled: bool, offered: bool, owned: int, visible: bool, orderable: bool}>, links: array<string, bool>}
 */',
        'startLine' => 185,
        'endLine' => 202,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'aliasName' => NULL,
      ),
      'overview' => 
      array (
        'name' => 'overview',
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
 * Staff view: the configuration plus what the catalogue offers and how many organizations own something per category.
 *
 * @return array{config: array<string,mixed>, categories: list<array<string,mixed>>, links: list<array{key: string, label: array{cs: string, en: string}, enabled: bool}>}
 */',
        'startLine' => 209,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Catalog',
        'declaringClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'implementingClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
        'currentClassName' => 'Onhost\\Domain\\Catalog\\PanelNavigation',
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