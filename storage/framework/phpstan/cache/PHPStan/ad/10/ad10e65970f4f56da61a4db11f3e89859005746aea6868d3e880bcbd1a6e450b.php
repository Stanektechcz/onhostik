<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-directoryiterator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'DirectoryIterator',
        'filename' => 'phpstorm-stubs:SPL/SPL_c1.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'DirectoryIterator',
    'shortName' => 'DirectoryIterator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The DirectoryIterator class provides a simple interface for viewing
 * the contents of filesystem directories.
 * @link https://php.net/manual/en/class.directoryiterator.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 101,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => 'SplFileInfo',
    'implementsClassNames' => 
    array (
      0 => 'SeekableIterator',
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'directory' => 
          array (
            'name' => 'directory',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 19,
                      'endLine' => 19,
                      'startTokenPos' => 34,
                      'startFilePos' => 747,
                      'endTokenPos' => 40,
                      'endFilePos' => 765,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 19,
                      'endLine' => 19,
                      'startTokenPos' => 46,
                      'startFilePos' => 777,
                      'endTokenPos' => 46,
                      'endFilePos' => 778,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 19,
            'endLine' => 20,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Constructs a new directory iterator from a path
 * @link https://php.net/manual/en/directoryiterator.construct.php
 * @param string $directory
 * @throws UnexpectedValueException if the path cannot be opened.
 * @throws RuntimeException if the path is an empty string.
 */',
        'startLine' => 18,
        'endLine' => 23,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
        'aliasName' => NULL,
      ),
      'isDot' => 
      array (
        'name' => 'isDot',
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
 * Determine if current DirectoryIterator item is \'.\' or \'..\'
 * @link https://php.net/manual/en/directoryiterator.isdot.php
 * @return bool true if the entry is . or ..,
 * otherwise false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
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
 * Rewind the DirectoryIterator back to the start
 * @link https://php.net/manual/en/directoryiterator.rewind.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
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
 * Check whether current DirectoryIterator position is a valid file
 * @link https://php.net/manual/en/directoryiterator.valid.php
 * @return bool true if the position is valid, otherwise false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
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
 * Return the key for the current DirectoryIterator item
 * @link https://php.net/manual/en/directoryiterator.key.php
 * @return string The key for the current <b>DirectoryIterator</b> item.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
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
 * Return the current DirectoryIterator item.
 * @link https://php.net/manual/en/directoryiterator.current.php
 * @return DirectoryIterator The current <b>DirectoryIterator</b> item.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
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
 * Move forward to next DirectoryIterator item
 * @link https://php.net/manual/en/directoryiterator.next.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 81,
        'endLine' => 84,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
        'aliasName' => NULL,
      ),
      'seek' => 
      array (
        'name' => 'seek',
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
                      'startLine' => 96,
                      'endLine' => 96,
                      'startTokenPos' => 202,
                      'startFilePos' => 3578,
                      'endTokenPos' => 208,
                      'endFilePos' => 3593,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 96,
                      'endLine' => 96,
                      'startTokenPos' => 214,
                      'startFilePos' => 3605,
                      'endTokenPos' => 214,
                      'endFilePos' => 3606,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 96,
            'endLine' => 97,
            'startColumn' => 13,
            'endColumn' => 23,
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
 * Seek to a DirectoryIterator item
 * @link https://php.net/manual/en/directoryiterator.seek.php
 * @param int $offset <p>
 * The zero-based numeric position to seek to.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 94,
        'endLine' => 100,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DirectoryIterator',
        'implementingClassName' => 'DirectoryIterator',
        'currentClassName' => 'DirectoryIterator',
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