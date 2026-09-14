<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Console\Commands\GamePanelCommands.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\GamePanelCommands
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-6fcd1211a42b3c2da94cfa850a95eb2b1adc6dc5a379b95443a04ca1025df766',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\GamePanelCommands',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Console/Commands/GamePanelCommands.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\GamePanelCommands',
    'shortName' => 'GamePanelCommands',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * `onhost:game:bootstrap <instance>` — connection test, node discovery, template mapping, prerequisites, port ranges and
 * the plan placement in one run (audit §5g-1); `--eggs-only` maps the templates and nothing else. Run it after the
 * keys are stored (`onhost:integrations:secret`) and again whenever the panel gains eggs or nodes.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 82,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
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
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'implementingClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'onhost:game:bootstrap {instance : Game panel instance key (e.g. pterodactyl-gamepanel)} {--product=game : Catalogue product to place on the panel} {--eggs-only : Only map the catalogue templates onto the panel eggs} {--force : Re-map templates that are already mapped}\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 55,
            'startFilePos' => 675,
            'endTokenPos' => 55,
            'endFilePos' => 944,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 298,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'implementingClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Bring a game panel into service: probe, discover nodes, map catalogue templates onto eggs, prerequisites, port ranges, plan placement\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 64,
            'startFilePos' => 977,
            'endTokenPos' => 64,
            'endFilePos' => 1111,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 165,
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
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'bootstrap' => 
          array (
            'name' => 'bootstrap',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\GamePanelBootstrap',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 28,
            'endColumn' => 56,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 23,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'implementingClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'currentClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'aliasName' => NULL,
      ),
      'printEggs' => 
      array (
        'name' => 'printEggs',
        'parameters' => 
        array (
          'eggs' => 
          array (
            'name' => 'eggs',
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
            'startLine' => 69,
            'endLine' => 69,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param  array{mapped:array<string,array{nest:int,egg:int,name:string}>, kept:list<string>, unmapped:array<string,string>, eggs:int}  $eggs */',
        'startLine' => 69,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'implementingClassName' => 'App\\Console\\Commands\\GamePanelCommands',
        'currentClassName' => 'App\\Console\\Commands\\GamePanelCommands',
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