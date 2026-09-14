<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-recursiveiteratoriterator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'RecursiveIteratorIterator',
        'filename' => 'phpstorm-stubs:SPL/SPL.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'RecursiveIteratorIterator',
    'shortName' => 'RecursiveIteratorIterator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Can be used to iterate through recursive iterators.
 * @link https://php.net/manual/en/class.recursiveiteratoriterator.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 224,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'OuterIterator',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'LEAVES_ONLY' => 
      array (
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'name' => 'LEAVES_ONLY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 28,
            'startFilePos' => 352,
            'endTokenPos' => 28,
            'endFilePos' => 352,
          ),
        ),
        'docComment' => '/**
 * The default. Lists only leaves in iteration.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'SELF_FIRST' => 
      array (
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'name' => 'SELF_FIRST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 41,
            'startFilePos' => 489,
            'endTokenPos' => 41,
            'endFilePos' => 489,
          ),
        ),
        'docComment' => '/**
 * Lists leaves and parents in iteration with parents coming first.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'CHILD_FIRST' => 
      array (
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'name' => 'CHILD_FIRST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 54,
            'startFilePos' => 626,
            'endTokenPos' => 54,
            'endFilePos' => 626,
          ),
        ),
        'docComment' => '/**
 * Lists leaves and parents in iteration with leaves coming first.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CATCH_GET_CHILD' => 
      array (
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'name' => 'CATCH_GET_CHILD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 67,
            'startFilePos' => 765,
            'endTokenPos' => 67,
            'endFilePos' => 766,
          ),
        ),
        'docComment' => '/**
 * Special flag: Ignore exceptions thrown in accessing children.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 9,
        'endColumn' => 42,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'iterator' => 
          array (
            'name' => 'iterator',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Traversable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 13,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => 
            array (
              'code' => 'self::LEAVES_ONLY',
              'attributes' => 
              array (
                'startLine' => 37,
                'endLine' => 37,
                'startTokenPos' => 109,
                'startFilePos' => 1375,
                'endTokenPos' => 111,
                'endFilePos' => 1391,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 36,
                      'endLine' => 36,
                      'startTokenPos' => 87,
                      'startFilePos' => 1319,
                      'endTokenPos' => 93,
                      'endFilePos' => 1334,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 36,
                      'endLine' => 36,
                      'startTokenPos' => 99,
                      'startFilePos' => 1346,
                      'endTokenPos' => 99,
                      'endFilePos' => 1347,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 36,
            'endLine' => 37,
            'startColumn' => 13,
            'endColumn' => 41,
            'parameterIndex' => 1,
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
                'startLine' => 39,
                'endLine' => 39,
                'startTokenPos' => 139,
                'startFilePos' => 1517,
                'endTokenPos' => 139,
                'endFilePos' => 1517,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 38,
                      'endLine' => 38,
                      'startTokenPos' => 117,
                      'startFilePos' => 1460,
                      'endTokenPos' => 123,
                      'endFilePos' => 1475,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 38,
                      'endLine' => 38,
                      'startTokenPos' => 129,
                      'startFilePos' => 1487,
                      'endTokenPos' => 129,
                      'endFilePos' => 1488,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 38,
            'endLine' => 39,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Construct a RecursiveIteratorIterator
 * @link https://php.net/manual/en/recursiveiteratoriterator.construct.php
 * @param Traversable $iterator
 * @param int $mode [optional] The operation mode. See class constants for details.
 * @param int $flags [optional] A bitmask of special flags. See class constants for details.
 * @since 5.1
 */',
        'startLine' => 34,
        'endLine' => 42,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'rewind' => 
      array (
        'name' => 'rewind',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Rewind the iterator to the first element of the top level inner iterator
 * @link https://php.net/manual/en/recursiveiteratoriterator.rewind.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'valid' => 
      array (
        'name' => 'valid',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Check whether the current position is valid
 * @link https://php.net/manual/en/recursiveiteratoriterator.valid.php
 * @return bool true if the current position is valid, otherwise false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 59,
        'endLine' => 62,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'key' => 
      array (
        'name' => 'key',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Access the current key
 * @link https://php.net/manual/en/recursiveiteratoriterator.key.php
 * @return mixed The key of the current element.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 69,
        'endLine' => 72,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'current' => 
      array (
        'name' => 'current',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Access the current element value
 * @link https://php.net/manual/en/recursiveiteratoriterator.current.php
 * @return mixed The current elements value.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 79,
        'endLine' => 82,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'next' => 
      array (
        'name' => 'next',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Move forward to the next element
 * @link https://php.net/manual/en/recursiveiteratoriterator.next.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 89,
        'endLine' => 92,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'getDepth' => 
      array (
        'name' => 'getDepth',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Get the current depth of the recursive iteration
 * @link https://php.net/manual/en/recursiveiteratoriterator.getdepth.php
 * @return int The current depth of the recursive iteration.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 99,
        'endLine' => 102,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'getSubIterator' => 
      array (
        'name' => 'getSubIterator',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 113,
                'endLine' => 113,
                'startTokenPos' => 313,
                'startFilePos' => 4306,
                'endTokenPos' => 313,
                'endFilePos' => 4309,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 112,
                      'endLine' => 112,
                      'startTokenPos' => 289,
                      'startFilePos' => 4239,
                      'endTokenPos' => 295,
                      'endFilePos' => 4259,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 112,
                      'endLine' => 112,
                      'startTokenPos' => 301,
                      'startFilePos' => 4271,
                      'endTokenPos' => 301,
                      'endFilePos' => 4272,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 112,
            'endLine' => 113,
            'startColumn' => 13,
            'endColumn' => 34,
            'parameterIndex' => 0,
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
                  'name' => 'RecursiveIterator',
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
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * The current active sub iterator
 * @link https://php.net/manual/en/recursiveiteratoriterator.getsubiterator.php
 * @param int $level [optional]
 * @return RecursiveIterator|null The current active sub iterator.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 110,
        'endLine' => 116,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'getInnerIterator' => 
      array (
        'name' => 'getInnerIterator',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'RecursiveIterator',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Get inner iterator
 * @link https://php.net/manual/en/recursiveiteratoriterator.getinneriterator.php
 * @return RecursiveIterator The current active sub iterator.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 123,
        'endLine' => 126,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'beginIteration' => 
      array (
        'name' => 'beginIteration',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Begin Iteration
 * @link https://php.net/manual/en/recursiveiteratoriterator.beginiteration.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 133,
        'endLine' => 136,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'endIteration' => 
      array (
        'name' => 'endIteration',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * End Iteration
 * @link https://php.net/manual/en/recursiveiteratoriterator.enditeration.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 143,
        'endLine' => 146,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'callHasChildren' => 
      array (
        'name' => 'callHasChildren',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Has children
 * @link https://php.net/manual/en/recursiveiteratoriterator.callhaschildren.php
 * @return bool true if the element has children, otherwise false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 153,
        'endLine' => 156,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'callGetChildren' => 
      array (
        'name' => 'callGetChildren',
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
                  'name' => 'RecursiveIterator',
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
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Get children
 * @link https://php.net/manual/en/recursiveiteratoriterator.callgetchildren.php
 * @return RecursiveIterator|null A <b>RecursiveIterator</b>.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 163,
        'endLine' => 166,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'beginChildren' => 
      array (
        'name' => 'beginChildren',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Begin children
 * @link https://php.net/manual/en/recursiveiteratoriterator.beginchildren.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 173,
        'endLine' => 176,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'endChildren' => 
      array (
        'name' => 'endChildren',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * End children
 * @link https://php.net/manual/en/recursiveiteratoriterator.endchildren.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 183,
        'endLine' => 186,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'nextElement' => 
      array (
        'name' => 'nextElement',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Next element
 * @link https://php.net/manual/en/recursiveiteratoriterator.nextelement.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 193,
        'endLine' => 196,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'setMaxDepth' => 
      array (
        'name' => 'setMaxDepth',
        'parameters' => 
        array (
          'maxDepth' => 
          array (
            'name' => 'maxDepth',
            'default' => 
            array (
              'code' => '-1',
              'attributes' => 
              array (
                'startLine' => 210,
                'endLine' => 210,
                'startTokenPos' => 532,
                'startFilePos' => 7736,
                'endTokenPos' => 533,
                'endFilePos' => 7737,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 209,
                      'endLine' => 209,
                      'startTokenPos' => 510,
                      'startFilePos' => 7676,
                      'endTokenPos' => 516,
                      'endFilePos' => 7691,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 209,
                      'endLine' => 209,
                      'startTokenPos' => 522,
                      'startFilePos' => 7703,
                      'endTokenPos' => 522,
                      'endFilePos' => 7704,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 209,
            'endLine' => 210,
            'startColumn' => 13,
            'endColumn' => 30,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set max depth
 * @link https://php.net/manual/en/recursiveiteratoriterator.setmaxdepth.php
 * @param int $maxDepth [optional] <p>
 * The maximum allowed depth. Default -1 is used
 * for any depth.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 207,
        'endLine' => 213,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
        'aliasName' => NULL,
      ),
      'getMaxDepth' => 
      array (
        'name' => 'getMaxDepth',
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
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Get max depth
 * @link https://php.net/manual/en/recursiveiteratoriterator.getmaxdepth.php
 * @return int|false The maximum accepted depth, or false if any depth is allowed.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 220,
        'endLine' => 223,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveIteratorIterator',
        'implementingClassName' => 'RecursiveIteratorIterator',
        'currentClassName' => 'RecursiveIteratorIterator',
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