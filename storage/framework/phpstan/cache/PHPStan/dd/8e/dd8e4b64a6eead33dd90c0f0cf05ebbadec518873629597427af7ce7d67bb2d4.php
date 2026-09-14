<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-recursivedirectoryiterator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'RecursiveDirectoryIterator',
        'filename' => 'phpstorm-stubs:SPL/SPL_c1.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'RecursiveDirectoryIterator',
    'shortName' => 'RecursiveDirectoryIterator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The <b>RecursiveDirectoryIterator</b> provides
 * an interface for iterating recursively over filesystem directories.
 * @link https://php.net/manual/en/class.recursivedirectoryiterator.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 114,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => 'FilesystemIterator',
    'implementsClassNames' => 
    array (
      0 => 'RecursiveIterator',
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
                      'startLine' => 20,
                      'endLine' => 20,
                      'startTokenPos' => 34,
                      'startFilePos' => 796,
                      'endTokenPos' => 40,
                      'endFilePos' => 814,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 20,
                      'endLine' => 20,
                      'startTokenPos' => 46,
                      'startFilePos' => 826,
                      'endTokenPos' => 46,
                      'endFilePos' => 827,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 20,
            'endLine' => 21,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\FilesystemIterator::KEY_AS_PATHNAME | \\FilesystemIterator::CURRENT_AS_FILEINFO',
              'attributes' => 
              array (
                'startLine' => 23,
                'endLine' => 23,
                'startTokenPos' => 80,
                'startFilePos' => 985,
                'endTokenPos' => 88,
                'endFilePos' => 1061,
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
                      'startLine' => 22,
                      'endLine' => 22,
                      'startTokenPos' => 58,
                      'startFilePos' => 928,
                      'endTokenPos' => 64,
                      'endFilePos' => 943,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 22,
                      'endLine' => 22,
                      'startTokenPos' => 70,
                      'startFilePos' => 955,
                      'endTokenPos' => 70,
                      'endFilePos' => 956,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 22,
            'endLine' => 23,
            'startColumn' => 13,
            'endColumn' => 102,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Constructs a RecursiveDirectoryIterator
 * @link https://php.net/manual/en/recursivedirectoryiterator.construct.php
 * @param string $directory
 * @param int $flags [optional]
 * @throws UnexpectedValueException if the path cannot be found or is not a directory.
 * @since 5.1
 */',
        'startLine' => 19,
        'endLine' => 26,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
        'aliasName' => NULL,
      ),
      'hasChildren' => 
      array (
        'name' => 'hasChildren',
        'parameters' => 
        array (
          'allowLinks' => 
          array (
            'name' => 'allowLinks',
            'default' => 
            array (
              'code' => '\\false',
              'attributes' => 
              array (
                'startLine' => 38,
                'endLine' => 38,
                'startTokenPos' => 134,
                'startFilePos' => 1700,
                'endTokenPos' => 134,
                'endFilePos' => 1704,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'bool\']',
                    'attributes' => 
                    array (
                      'startLine' => 37,
                      'endLine' => 37,
                      'startTokenPos' => 112,
                      'startFilePos' => 1636,
                      'endTokenPos' => 118,
                      'endFilePos' => 1652,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 37,
                      'endLine' => 37,
                      'startTokenPos' => 124,
                      'startFilePos' => 1664,
                      'endTokenPos' => 124,
                      'endFilePos' => 1665,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 37,
            'endLine' => 38,
            'startColumn' => 13,
            'endColumn' => 36,
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
 * Returns whether current entry is a directory and not \'.\' or \'..\'
 * @link https://php.net/manual/en/recursivedirectoryiterator.haschildren.php
 * @param bool $allowLinks [optional] <p>
 * </p>
 * @return bool whether the current entry is a directory, but not \'.\' or \'..\'
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 35,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
        'aliasName' => NULL,
      ),
      'getChildren' => 
      array (
        'name' => 'getChildren',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'RecursiveDirectoryIterator',
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
 * Returns an iterator for the current entry if it is a directory
 * @link https://php.net/manual/en/recursivedirectoryiterator.getchildren.php
 * @return RecursiveDirectoryIterator An iterator for the current entry, if it is a directory.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 48,
        'endLine' => 51,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
        'aliasName' => NULL,
      ),
      'getSubPath' => 
      array (
        'name' => 'getSubPath',
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
 * Get sub path
 * @link https://php.net/manual/en/recursivedirectoryiterator.getsubpath.php
 * @return string The sub path (sub directory).
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 58,
        'endLine' => 61,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
        'aliasName' => NULL,
      ),
      'getSubPathname' => 
      array (
        'name' => 'getSubPathname',
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
 * Get sub path and name
 * @link https://php.net/manual/en/recursivedirectoryiterator.getsubpathname.php
 * @return string The sub path (sub directory) and filename.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 68,
        'endLine' => 71,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
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
 * Rewinds back to the beginning
 * @link https://php.net/manual/en/filesystemiterator.rewind.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 78,
        'endLine' => 81,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
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
 * Move to the next file
 * @link https://php.net/manual/en/filesystemiterator.next.php
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 88,
        'endLine' => 91,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
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
            'name' => 'string',
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
 * Retrieve the key for the current file
 * @link https://php.net/manual/en/filesystemiterator.key.php
 * @return string the pathname or filename depending on the set flags.
 * See the FilesystemIterator constants.
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
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
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
                  'name' => 'SplFileInfo',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'FilesystemIterator',
                  'isIdentifier' => false,
                ),
              ),
              2 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'string',
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
 * The current file
 * @link https://php.net/manual/en/filesystemiterator.current.php
 * @return string|SplFileInfo|self The filename, file information, or $this depending on the set flags.
 * See the FilesystemIterator constants.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 110,
        'endLine' => 113,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveDirectoryIterator',
        'implementingClassName' => 'RecursiveDirectoryIterator',
        'currentClassName' => 'RecursiveDirectoryIterator',
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