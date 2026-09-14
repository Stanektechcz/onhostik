<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Shell\ManagedDirectives.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Shell\ManagedDirectives
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-4bfbf9c65f55900e78bda92e3f98906721fd6cccf01a22a3dff629c5125c4bab',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Shell/ManagedDirectives.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Shell',
    'name' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
    'shortName' => 'ManagedDirectives',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The managed tools block of an ISPConfig site\'s web server directives: reverse proxies (a path forwarded to an
 * upstream URL) and the default documents (DirectoryIndex / index). ISPConfig has no API for either, so the platform
 * renders them into the site\'s Apache or nginx directives between markers, next to the customer\'s own directives and
 * the security block (SecurityRules). The state travels on the marker line as JSON, so reading it back is exact and
 * independent of the rendered syntax.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 125,
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
      'BEGIN' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'name' => 'BEGIN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'# ONHOST-TOOLS-BEGIN\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 33,
            'startFilePos' => 638,
            'endTokenPos' => 33,
            'endFilePos' => 659,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'END' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'name' => 'END',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'# ONHOST-TOOLS-END\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 44,
            'startFilePos' => 686,
            'endTokenPos' => 44,
            'endFilePos' => 705,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'parse' => 
      array (
        'name' => 'parse',
        'parameters' => 
        array (
          'directives' => 
          array (
            'name' => 'directives',
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 34,
            'endColumn' => 51,
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
        'docComment' => '/** @return array{proxies:list<array{name:string,path:string,target:string,cache:bool}>, index:list<string>} */',
        'startLine' => 21,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Shell',
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'currentClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'aliasName' => NULL,
      ),
      'path' => 
      array (
        'name' => 'path',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 33,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** A proxy path as one absolute prefix without a trailing slash ("/" for the root). */',
        'startLine' => 42,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Shell',
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'currentClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'aliasName' => NULL,
      ),
      'render' => 
      array (
        'name' => 'render',
        'parameters' => 
        array (
          'state' => 
          array (
            'name' => 'state',
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
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'server' => 
          array (
            'name' => 'server',
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
            'startColumn' => 49,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Renders the block for the node\'s web server; an empty state renders nothing (the block disappears).
 *
 * @param  array{proxies:list<array{name:string,path:string,target:string,cache:bool}>, index:list<string>}  $state
 */',
        'startLine' => 54,
        'endLine' => 101,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Shell',
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'currentClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'aliasName' => NULL,
      ),
      'splice' => 
      array (
        'name' => 'splice',
        'parameters' => 
        array (
          'directives' => 
          array (
            'name' => 'directives',
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
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 35,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'block' => 
          array (
            'name' => 'block',
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
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 55,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
        'docComment' => '/** Replaces (or appends, or removes when the block is empty) the managed block inside the site\'s directives. */',
        'startLine' => 104,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Shell',
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'currentClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'aliasName' => NULL,
      ),
      'strip' => 
      array (
        'name' => 'strip',
        'parameters' => 
        array (
          'directives' => 
          array (
            'name' => 'directives',
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
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 34,
            'endColumn' => 51,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The directives without the managed block. */',
        'startLine' => 115,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Shell',
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'currentClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'aliasName' => NULL,
      ),
      'extract' => 
      array (
        'name' => 'extract',
        'parameters' => 
        array (
          'directives' => 
          array (
            'name' => 'directives',
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
            'startLine' => 121,
            'endLine' => 121,
            'startColumn' => 36,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The managed block as it stands in the directives (\'\' when none). */',
        'startLine' => 121,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Providers\\Shell',
        'declaringClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'implementingClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
        'currentClassName' => 'Onhost\\Providers\\Shell\\ManagedDirectives',
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