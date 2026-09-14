<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../symfony/yaml/Parser.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Symfony\Component\Yaml\Parser
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-ac3efb19eca3abc94620dd7a96df8560fb938bb8f6ce5ed6b85783d93fbc28b1-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Symfony\\Component\\Yaml\\Parser',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../symfony/yaml/Parser.php',
      ),
    ),
    'namespace' => 'Symfony\\Component\\Yaml',
    'name' => 'Symfony\\Component\\Yaml\\Parser',
    'shortName' => 'Parser',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Parser parses YAML strings to convert them to PHP arrays.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 1353,
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
      'TAG_PATTERN' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'TAG_PATTERN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'(?P<tag>![\\w!.\\/:-]+)\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 35,
            'startFilePos' => 551,
            'endTokenPos' => 35,
            'endFilePos' => 573,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 55,
      ),
      'BLOCK_SCALAR_HEADER_PATTERN' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'BLOCK_SCALAR_HEADER_PATTERN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'(?P<separator>\\||>)(?P<modifiers>\\+|\\-|\\d+|\\+\\d+|\\-\\d+|\\d+\\+|\\d+\\-)?(?P<comments> +#.*)?\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 46,
            'startFilePos' => 623,
            'endTokenPos' => 46,
            'endFilePos' => 712,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 138,
      ),
      'REFERENCE_PATTERN' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'REFERENCE_PATTERN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'#^&(?P<ref>[^ ]++) *+(?P<value>.*)#u\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 57,
            'startFilePos' => 752,
            'endTokenPos' => 57,
            'endFilePos' => 789,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 76,
      ),
      'DEFAULT_MAX_NESTING_LEVEL' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'DEFAULT_MAX_NESTING_LEVEL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '128',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 68,
            'startFilePos' => 837,
            'endTokenPos' => 68,
            'endFilePos' => 839,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 49,
      ),
      'DEFAULT_MAX_ALIASES_FOR_COLLECTIONS' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'DEFAULT_MAX_ALIASES_FOR_COLLECTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '128',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 79,
            'startFilePos' => 897,
            'endTokenPos' => 79,
            'endFilePos' => 899,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 59,
      ),
    ),
    'immediateProperties' => 
    array (
      'filename' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'filename',
        'modifiers' => 4,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 91,
            'startFilePos' => 935,
            'endTokenPos' => 91,
            'endFilePos' => 938,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'offset' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'offset',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 102,
            'startFilePos' => 967,
            'endTokenPos' => 102,
            'endFilePos' => 967,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'numberOfParsedLines' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'numberOfParsedLines',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 113,
            'startFilePos' => 1009,
            'endTokenPos' => 113,
            'endFilePos' => 1009,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'totalNumberOfLines' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'totalNumberOfLines',
        'modifiers' => 4,
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
                  'name' => 'int',
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 125,
            'startFilePos' => 1051,
            'endTokenPos' => 125,
            'endFilePos' => 1054,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lines' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'lines',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 136,
            'startFilePos' => 1084,
            'endTokenPos' => 137,
            'endFilePos' => 1085,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'currentLineNb' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'currentLineNb',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 148,
            'startFilePos' => 1121,
            'endTokenPos' => 149,
            'endFilePos' => 1122,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'currentLine' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'currentLine',
        'modifiers' => 4,
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
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 160,
            'startFilePos' => 1159,
            'endTokenPos' => 160,
            'endFilePos' => 1160,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'refs' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'refs',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 171,
            'startFilePos' => 1189,
            'endTokenPos' => 172,
            'endFilePos' => 1190,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'skippedLineNumbers' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'skippedLineNumbers',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 40,
            'endLine' => 40,
            'startTokenPos' => 183,
            'startFilePos' => 1233,
            'endTokenPos' => 184,
            'endFilePos' => 1234,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'locallySkippedLineNumbers' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'locallySkippedLineNumbers',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 195,
            'startFilePos' => 1284,
            'endTokenPos' => 196,
            'endFilePos' => 1285,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 50,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'refsBeingParsed' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'refsBeingParsed',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 42,
            'startTokenPos' => 207,
            'startFilePos' => 1325,
            'endTokenPos' => 208,
            'endFilePos' => 1326,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'state' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'name' => 'state',
        'modifiers' => 4,
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
                  'name' => 'Symfony\\Component\\Yaml\\ParserState',
                  'isIdentifier' => false,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 220,
            'startFilePos' => 1363,
            'endTokenPos' => 220,
            'endFilePos' => 1366,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 39,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'maxNestingLevel' => 
          array (
            'name' => 'maxNestingLevel',
            'default' => 
            array (
              'code' => 'self::DEFAULT_MAX_NESTING_LEVEL',
              'attributes' => 
              array (
                'startLine' => 45,
                'endLine' => 45,
                'startTokenPos' => 235,
                'startFilePos' => 1425,
                'endTokenPos' => 237,
                'endFilePos' => 1455,
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 33,
            'endColumn' => 86,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'maxAliasesForCollections' => 
          array (
            'name' => 'maxAliasesForCollections',
            'default' => 
            array (
              'code' => 'self::DEFAULT_MAX_ALIASES_FOR_COLLECTIONS',
              'attributes' => 
              array (
                'startLine' => 45,
                'endLine' => 45,
                'startTokenPos' => 246,
                'startFilePos' => 1490,
                'endTokenPos' => 248,
                'endFilePos' => 1530,
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 89,
            'endColumn' => 161,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 45,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'parseFile' => 
      array (
        'name' => 'parseFile',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
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
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 31,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 67,
                'endLine' => 67,
                'startTokenPos' => 348,
                'startFilePos' => 2462,
                'endTokenPos' => 348,
                'endFilePos' => 2462,
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
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 49,
            'endColumn' => 62,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parses a YAML file into a PHP value.
 *
 * @param string                     $filename The path to the YAML file to be parsed
 * @param int-mask-of<Yaml::PARSE_*> $flags    A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
 *
 * @throws ParseException If the file could not be read or the YAML is not valid
 */',
        'startLine' => 67,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'parse' => 
      array (
        'name' => 'parse',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 27,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 94,
                'endLine' => 94,
                'startTokenPos' => 483,
                'startFilePos' => 3316,
                'endTokenPos' => 483,
                'endFilePos' => 3316,
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
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 42,
            'endColumn' => 55,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parses a YAML string to a PHP value.
 *
 * @param string                     $value A YAML string
 * @param int-mask-of<Yaml::PARSE_*> $flags A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
 *
 * @throws ParseException If the YAML is not valid
 */',
        'startLine' => 94,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'getState' => 
      array (
        'name' => 'getState',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\Yaml\\ParserState',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 123,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'doParse' => 
      array (
        'name' => 'doParse',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 128,
            'endLine' => 128,
            'startColumn' => 30,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => NULL,
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
            'startLine' => 128,
            'endLine' => 128,
            'startColumn' => 45,
            'endColumn' => 54,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 128,
        'endLine' => 546,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'parseBlock' => 
      array (
        'name' => 'parseBlock',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
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
            'startLine' => 548,
            'endLine' => 548,
            'startColumn' => 33,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'yaml' => 
          array (
            'name' => 'yaml',
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
            'startLine' => 548,
            'endLine' => 548,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => NULL,
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
            'startLine' => 548,
            'endLine' => 548,
            'startColumn' => 60,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 548,
        'endLine' => 575,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'getRealCurrentLineNb' => 
      array (
        'name' => 'getRealCurrentLineNb',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Returns the current line number (takes the offset into account).
 *
 * @internal
 */',
        'startLine' => 582,
        'endLine' => 595,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'getCurrentLineIndentation' => 
      array (
        'name' => 'getCurrentLineIndentation',
        'parameters' => 
        array (
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
        'startLine' => 597,
        'endLine' => 604,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'getNextEmbedBlock' => 
      array (
        'name' => 'getNextEmbedBlock',
        'parameters' => 
        array (
          'indentation' => 
          array (
            'name' => 'indentation',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 614,
                'endLine' => 614,
                'startTokenPos' => 5556,
                'startFilePos' => 29120,
                'endTokenPos' => 5556,
                'endFilePos' => 29123,
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
                      'name' => 'int',
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
            'startLine' => 614,
            'endLine' => 614,
            'startColumn' => 40,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'inSequence' => 
          array (
            'name' => 'inSequence',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 614,
                'endLine' => 614,
                'startTokenPos' => 5565,
                'startFilePos' => 29145,
                'endTokenPos' => 5565,
                'endFilePos' => 29149,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 614,
            'endLine' => 614,
            'startColumn' => 66,
            'endColumn' => 89,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the next embed block of YAML.
 *
 * @param int|null $indentation The indent level at which the block is to be read, or null for default
 * @param bool     $inSequence  True if the enclosing data structure is a sequence
 *
 * @throws ParseException When indentation problem are detected
 */',
        'startLine' => 614,
        'endLine' => 709,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'hasMoreLines' => 
      array (
        'name' => 'hasMoreLines',
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
        'startLine' => 711,
        'endLine' => 714,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'moveToNextLine' => 
      array (
        'name' => 'moveToNextLine',
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
        'docComment' => '/**
 * Moves the parser to the next line.
 */',
        'startLine' => 719,
        'endLine' => 728,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'moveToPreviousLine' => 
      array (
        'name' => 'moveToPreviousLine',
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
        'docComment' => '/**
 * Moves the parser to the previous line.
 */',
        'startLine' => 733,
        'endLine' => 742,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'parseValue' => 
      array (
        'name' => 'parseValue',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 753,
            'endLine' => 753,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => NULL,
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
            'startLine' => 753,
            'endLine' => 753,
            'startColumn' => 48,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'context' => 
          array (
            'name' => 'context',
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
            'startLine' => 753,
            'endLine' => 753,
            'startColumn' => 60,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parses a YAML value.
 *
 * @param string $value   A YAML value
 * @param int    $flags   A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
 * @param string $context The parser context (either sequence or mapping)
 *
 * @throws ParseException When reference does not exist
 */',
        'startLine' => 753,
        'endLine' => 865,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'parseBlockScalar' => 
      array (
        'name' => 'parseBlockScalar',
        'parameters' => 
        array (
          'style' => 
          array (
            'name' => 'style',
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
            'startLine' => 874,
            'endLine' => 874,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'chomping' => 
          array (
            'name' => 'chomping',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 874,
                'endLine' => 874,
                'startTokenPos' => 7763,
                'startFilePos' => 39480,
                'endTokenPos' => 7763,
                'endFilePos' => 39481,
              ),
            ),
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
            'startLine' => 874,
            'endLine' => 874,
            'startColumn' => 54,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'indentation' => 
          array (
            'name' => 'indentation',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 874,
                'endLine' => 874,
                'startTokenPos' => 7772,
                'startFilePos' => 39503,
                'endTokenPos' => 7772,
                'endFilePos' => 39503,
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
            'startLine' => 874,
            'endLine' => 874,
            'startColumn' => 77,
            'endColumn' => 96,
            'parameterIndex' => 2,
            'isOptional' => true,
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
 * Parses a block scalar.
 *
 * @param string $style       The style indicator that was used to begin this block scalar (| or >)
 * @param string $chomping    The chomping indicator that was used to begin this block scalar (+ or -)
 * @param int    $indentation The indentation indicator that was used to begin this block scalar
 */',
        'startLine' => 874,
        'endLine' => 976,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isNextLineIndented' => 
      array (
        'name' => 'isNextLineIndented',
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
        'docComment' => '/**
 * Returns true if the next line is indented.
 */',
        'startLine' => 981,
        'endLine' => 1009,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isCurrentLineEmpty' => 
      array (
        'name' => 'isCurrentLineEmpty',
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
        'startLine' => 1011,
        'endLine' => 1014,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isCurrentLineBlank' => 
      array (
        'name' => 'isCurrentLineBlank',
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
        'startLine' => 1016,
        'endLine' => 1019,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isCurrentLineComment' => 
      array (
        'name' => 'isCurrentLineComment',
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
        'startLine' => 1021,
        'endLine' => 1027,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isCurrentLineLastLineInDocument' => 
      array (
        'name' => 'isCurrentLineLastLineInDocument',
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
        'startLine' => 1029,
        'endLine' => 1032,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'cleanup' => 
      array (
        'name' => 'cleanup',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 1034,
            'endLine' => 1034,
            'startColumn' => 30,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1034,
        'endLine' => 1063,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isNextLineUnIndentedCollection' => 
      array (
        'name' => 'isNextLineUnIndentedCollection',
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
        'startLine' => 1065,
        'endLine' => 1089,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'isStringUnIndentedCollectionItem' => 
      array (
        'name' => 'isStringUnIndentedCollectionItem',
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
        'startLine' => 1091,
        'endLine' => 1094,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'preg_match' => 
      array (
        'name' => 'preg_match',
        'parameters' => 
        array (
          'pattern' => 
          array (
            'name' => 'pattern',
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
            'startLine' => 1107,
            'endLine' => 1107,
            'startColumn' => 39,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'subject' => 
          array (
            'name' => 'subject',
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
            'startLine' => 1107,
            'endLine' => 1107,
            'startColumn' => 56,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'matches' => 
          array (
            'name' => 'matches',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1107,
                'endLine' => 1107,
                'startTokenPos' => 9422,
                'startFilePos' => 47094,
                'endTokenPos' => 9422,
                'endFilePos' => 47097,
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
                      'name' => 'array',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1107,
            'endLine' => 1107,
            'startColumn' => 73,
            'endColumn' => 95,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1107,
                'endLine' => 1107,
                'startTokenPos' => 9431,
                'startFilePos' => 47113,
                'endTokenPos' => 9431,
                'endFilePos' => 47113,
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
            'startLine' => 1107,
            'endLine' => 1107,
            'startColumn' => 98,
            'endColumn' => 111,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1107,
                'endLine' => 1107,
                'startTokenPos' => 9440,
                'startFilePos' => 47130,
                'endTokenPos' => 9440,
                'endFilePos' => 47130,
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
            'startLine' => 1107,
            'endLine' => 1107,
            'startColumn' => 114,
            'endColumn' => 128,
            'parameterIndex' => 4,
            'isOptional' => true,
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
        'docComment' => '/**
 * A local wrapper for "preg_match" which will throw a ParseException if there
 * is an internal error in the PCRE engine.
 *
 * This avoids us needing to check for "false" every time PCRE is used
 * in the YAML engine
 *
 * @throws ParseException on a PCRE internal error
 *
 * @internal
 */',
        'startLine' => 1107,
        'endLine' => 1114,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'trimTag' => 
      array (
        'name' => 'trimTag',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 1122,
            'endLine' => 1122,
            'startColumn' => 30,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Trim the tag on top of the value.
 *
 * Prevent values such as "!foo {quz: bar}" to be considered as
 * a mapping block.
 */',
        'startLine' => 1122,
        'endLine' => 1129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'getLineTag' => 
      array (
        'name' => 'getLineTag',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 1131,
            'endLine' => 1131,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => NULL,
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
            'startLine' => 1131,
            'endLine' => 1131,
            'startColumn' => 48,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'nextLineCheck' => 
          array (
            'name' => 'nextLineCheck',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1131,
                'endLine' => 1131,
                'startTokenPos' => 9593,
                'startFilePos' => 47782,
                'endTokenPos' => 9593,
                'endFilePos' => 47785,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1131,
            'endLine' => 1131,
            'startColumn' => 60,
            'endColumn' => 85,
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
        'docComment' => NULL,
        'startLine' => 1131,
        'endLine' => 1153,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'lexInlineQuotedString' => 
      array (
        'name' => 'lexInlineQuotedString',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1155,
                'endLine' => 1155,
                'startTokenPos' => 9829,
                'startFilePos' => 48712,
                'endTokenPos' => 9829,
                'endFilePos' => 48712,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1155,
            'endLine' => 1155,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => true,
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
        'docComment' => NULL,
        'startLine' => 1155,
        'endLine' => 1217,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'lexUnquotedString' => 
      array (
        'name' => 'lexUnquotedString',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1219,
            'endLine' => 1219,
            'startColumn' => 40,
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
        'docComment' => NULL,
        'startLine' => 1219,
        'endLine' => 1240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'lexInlineAnchorOrAlias' => 
      array (
        'name' => 'lexInlineAnchorOrAlias',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1242,
            'endLine' => 1242,
            'startColumn' => 45,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1242,
        'endLine' => 1252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'lexInlineMapping' => 
      array (
        'name' => 'lexInlineMapping',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1254,
                'endLine' => 1254,
                'startTokenPos' => 10617,
                'startFilePos' => 52104,
                'endTokenPos' => 10617,
                'endFilePos' => 52104,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1254,
            'endLine' => 1254,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'consumeUntilEol' => 
          array (
            'name' => 'consumeUntilEol',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1254,
                'endLine' => 1254,
                'startTokenPos' => 10626,
                'startFilePos' => 52131,
                'endTokenPos' => 10626,
                'endFilePos' => 52134,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1254,
            'endLine' => 1254,
            'startColumn' => 57,
            'endColumn' => 84,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1254,
        'endLine' => 1257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'lexInlineSequence' => 
      array (
        'name' => 'lexInlineSequence',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1259,
                'endLine' => 1259,
                'startTokenPos' => 10665,
                'startFilePos' => 52286,
                'endTokenPos' => 10665,
                'endFilePos' => 52286,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1259,
            'endLine' => 1259,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'consumeUntilEol' => 
          array (
            'name' => 'consumeUntilEol',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1259,
                'endLine' => 1259,
                'startTokenPos' => 10674,
                'startFilePos' => 52313,
                'endTokenPos' => 10674,
                'endFilePos' => 52316,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1259,
            'endLine' => 1259,
            'startColumn' => 58,
            'endColumn' => 85,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1259,
        'endLine' => 1262,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'lexInlineStructure' => 
      array (
        'name' => 'lexInlineStructure',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1264,
            'endLine' => 1264,
            'startColumn' => 41,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'closingTag' => 
          array (
            'name' => 'closingTag',
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
            'startLine' => 1264,
            'endLine' => 1264,
            'startColumn' => 55,
            'endColumn' => 72,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'consumeUntilEol' => 
          array (
            'name' => 'consumeUntilEol',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1264,
                'endLine' => 1264,
                'startTokenPos' => 10723,
                'startFilePos' => 52512,
                'endTokenPos' => 10723,
                'endFilePos' => 52515,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1264,
            'endLine' => 1264,
            'startColumn' => 75,
            'endColumn' => 102,
            'parameterIndex' => 2,
            'isOptional' => true,
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
        'docComment' => NULL,
        'startLine' => 1264,
        'endLine' => 1274,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'doLexInlineStructure' => 
      array (
        'name' => 'doLexInlineStructure',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1276,
            'endLine' => 1276,
            'startColumn' => 43,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'closingTag' => 
          array (
            'name' => 'closingTag',
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
            'startLine' => 1276,
            'endLine' => 1276,
            'startColumn' => 57,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'consumeUntilEol' => 
          array (
            'name' => 'consumeUntilEol',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1276,
                'endLine' => 1276,
                'startTokenPos' => 10828,
                'startFilePos' => 52956,
                'endTokenPos' => 10828,
                'endFilePos' => 52959,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1276,
            'endLine' => 1276,
            'startColumn' => 77,
            'endColumn' => 104,
            'parameterIndex' => 2,
            'isOptional' => true,
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
        'docComment' => NULL,
        'startLine' => 1276,
        'endLine' => 1331,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'aliasName' => NULL,
      ),
      'consumeWhitespaces' => 
      array (
        'name' => 'consumeWhitespaces',
        'parameters' => 
        array (
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1333,
            'endLine' => 1333,
            'startColumn' => 41,
            'endColumn' => 52,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1333,
        'endLine' => 1352,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Yaml',
        'declaringClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'implementingClassName' => 'Symfony\\Component\\Yaml\\Parser',
        'currentClassName' => 'Symfony\\Component\\Yaml\\Parser',
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