<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-phardata
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'PharData',
        'filename' => 'phpstorm-stubs:Phar/Phar.stub',
        'extensionName' => 'Phar',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'PharData',
    'shortName' => 'PharData',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The PharData class provides a high-level interface to accessing and creating
 * non-executable tar and zip archives. Because these archives do not contain
 * a stub and cannot be executed by the phar extension, it is possible to create
 * and manipulate regular zip and tar files using the PharData class even if
 * phar.readonly php.ini setting is 1.
 * @link https://php.net/manual/en/class.phardata.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 1133,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => 'RecursiveDirectoryIterator',
    'implementsClassNames' => 
    array (
      0 => 'Countable',
      1 => 'ArrayAccess',
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
                      'startLine' => 36,
                      'endLine' => 36,
                      'startTokenPos' => 37,
                      'startFilePos' => 1508,
                      'endTokenPos' => 43,
                      'endFilePos' => 1526,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 36,
                      'endLine' => 36,
                      'startTokenPos' => 49,
                      'startFilePos' => 1538,
                      'endTokenPos' => 49,
                      'endFilePos' => 1539,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 36,
            'endLine' => 37,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\FilesystemIterator::SKIP_DOTS | \\FilesystemIterator::UNIX_PATHS',
              'attributes' => 
              array (
                'startLine' => 39,
                'endLine' => 39,
                'startTokenPos' => 83,
                'startFilePos' => 1696,
                'endTokenPos' => 91,
                'endFilePos' => 1757,
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
                      'startTokenPos' => 61,
                      'startFilePos' => 1639,
                      'endTokenPos' => 67,
                      'endFilePos' => 1654,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 38,
                      'endLine' => 38,
                      'startTokenPos' => 73,
                      'startFilePos' => 1666,
                      'endTokenPos' => 73,
                      'endFilePos' => 1667,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 38,
            'endLine' => 39,
            'startColumn' => 13,
            'endColumn' => 87,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'alias' => 
          array (
            'name' => 'alias',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 41,
                'endLine' => 41,
                'startTokenPos' => 121,
                'startFilePos' => 1899,
                'endTokenPos' => 121,
                'endFilePos' => 1902,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 40,
                      'endLine' => 40,
                      'startTokenPos' => 97,
                      'startFilePos' => 1826,
                      'endTokenPos' => 103,
                      'endFilePos' => 1849,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 40,
                      'endLine' => 40,
                      'startTokenPos' => 109,
                      'startFilePos' => 1861,
                      'endTokenPos' => 109,
                      'endFilePos' => 1862,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 40,
            'endLine' => 41,
            'startColumn' => 13,
            'endColumn' => 37,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 43,
                'endLine' => 43,
                'startTokenPos' => 149,
                'startFilePos' => 2029,
                'endTokenPos' => 149,
                'endFilePos' => 2029,
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
                      'startLine' => 42,
                      'endLine' => 42,
                      'startTokenPos' => 127,
                      'startFilePos' => 1971,
                      'endTokenPos' => 133,
                      'endFilePos' => 1986,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 42,
                      'endLine' => 42,
                      'startTokenPos' => 139,
                      'startFilePos' => 1998,
                      'endTokenPos' => 139,
                      'endFilePos' => 1999,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 42,
            'endLine' => 43,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Construct a non-executable tar or zip archive object
 * @link https://php.net/manual/en/phardata.construct.php
 * @param string $filename <p>
 * Path to an existing tar/zip archive or to-be-created archive
 * </p>
 * @param int $flags [optional] <p>
 * Flags to pass to <b>Phar</b> parent class
 * <b>RecursiveDirectoryIterator</b>.
 * </p>
 * @param string $alias [optional] <p>
 * Alias with which this Phar archive should be referred to in calls to stream
 * functionality.
 * </p>
 * @param int $format [optional] <p>
 * One of the
 * file format constants
 * available within the <b>Phar</b> class.
 * </p>
 */',
        'startLine' => 35,
        'endLine' => 46,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'offsetExists' => 
      array (
        'name' => 'offsetExists',
        'parameters' => 
        array (
          'localName' => 
          array (
            'name' => 'localName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 38,
            'endColumn' => 47,
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
 * @param string $localName
 * @return bool
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 52,
        'endLine' => 55,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'offsetGet' => 
      array (
        'name' => 'offsetGet',
        'parameters' => 
        array (
          'localName' => 
          array (
            'name' => 'localName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 62,
            'endLine' => 62,
            'startColumn' => 35,
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
            'name' => 'SplFileInfo',
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
 * @param string $localName
 * @return SplFileInfo
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
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'offsetSet' => 
      array (
        'name' => 'offsetSet',
        'parameters' => 
        array (
          'localName' => 
          array (
            'name' => 'localName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 47,
            'endColumn' => 52,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * set the contents of a file within the tar/zip to those of an external file or string
 * @link https://php.net/manual/en/phardata.offsetset.php
 * @param string $localName <p>
 * The filename (relative path) to modify in a tar or zip archive.
 * </p>
 * @param string $value <p>
 * Content of the file.
 * </p>
 * @return void No return values.
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
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'offsetUnset' => 
      array (
        'name' => 'offsetUnset',
        'parameters' => 
        array (
          'localName' => 
          array (
            'name' => 'localName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 37,
            'endColumn' => 46,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * remove a file from a tar/zip archive
 * @link https://php.net/manual/en/phardata.offsetunset.php
 * @param string $localName <p>
 * The filename (relative path) to modify in the tar/zip archive.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 92,
        'endLine' => 95,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
                'startLine' => 107,
                'endLine' => 107,
                'startTokenPos' => 286,
                'startFilePos' => 4411,
                'endTokenPos' => 286,
                'endFilePos' => 4415,
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
                      'startLine' => 106,
                      'endLine' => 106,
                      'startTokenPos' => 264,
                      'startFilePos' => 4347,
                      'endTokenPos' => 270,
                      'endFilePos' => 4363,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 106,
                      'endLine' => 106,
                      'startTokenPos' => 276,
                      'startFilePos' => 4375,
                      'endTokenPos' => 276,
                      'endFilePos' => 4376,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 106,
            'endLine' => 107,
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
        'startLine' => 104,
        'endLine' => 110,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
        'startLine' => 117,
        'endLine' => 120,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 127,
        'endLine' => 130,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 137,
        'endLine' => 140,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
        'startLine' => 148,
        'endLine' => 151,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
 * @return mixed The filename, file information, or $this depending on the set flags.
 * See the FilesystemIterator constants.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 159,
        'endLine' => 162,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
 * @return bool <b>TRUE</b> if the position is valid, otherwise <b>FALSE</b>
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 169,
        'endLine' => 172,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'addEmptyDir' => 
      array (
        'name' => 'addEmptyDir',
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
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'8.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 185,
                      'endLine' => 185,
                      'startTokenPos' => 446,
                      'startFilePos' => 7516,
                      'endTokenPos' => 446,
                      'endFilePos' => 7520,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 185,
            'endLine' => 186,
            'startColumn' => 13,
            'endColumn' => 29,
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
 * (Unknown)<br/>
 * Add an empty directory to the phar archive
 * @link https://www.php.net/manual/en/phardata.addemptydir.php
 * @param string $directory <p>
 * The name of the empty directory to create in the phar archive
 * </p>
 * @return void no return value, exception is thrown on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 183,
        'endLine' => 189,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'addFile' => 
      array (
        'name' => 'addFile',
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
                      'startLine' => 206,
                      'endLine' => 206,
                      'startTokenPos' => 479,
                      'startFilePos' => 8318,
                      'endTokenPos' => 485,
                      'endFilePos' => 8336,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 206,
                      'endLine' => 206,
                      'startTokenPos' => 491,
                      'startFilePos' => 8348,
                      'endTokenPos' => 491,
                      'endFilePos' => 8349,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 206,
            'endLine' => 207,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'localName' => 
          array (
            'name' => 'localName',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 209,
                'endLine' => 209,
                'startTokenPos' => 527,
                'startFilePos' => 8526,
                'endTokenPos' => 527,
                'endFilePos' => 8529,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 208,
                      'endLine' => 208,
                      'startTokenPos' => 503,
                      'startFilePos' => 8449,
                      'endTokenPos' => 509,
                      'endFilePos' => 8472,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 208,
                      'endLine' => 208,
                      'startTokenPos' => 515,
                      'startFilePos' => 8484,
                      'endTokenPos' => 515,
                      'endFilePos' => 8485,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 208,
            'endLine' => 209,
            'startColumn' => 13,
            'endColumn' => 41,
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
 * (Unknown)<br/>
 * Add a file from the filesystem to the phar archive
 * @link https://php.net/manual/en/phardata.addfile.php
 * @param string $filename <p>
 * Full or relative path to a file on disk to be added
 * to the phar archive.
 * </p>
 * @param string $localName [optional] <p>
 * Path that the file will be stored in the archive.
 * </p>
 * @return void no return value, exception is thrown on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 204,
        'endLine' => 212,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'addFromString' => 
      array (
        'name' => 'addFromString',
        'parameters' => 
        array (
          'localName' => 
          array (
            'name' => 'localName',
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
                      'startLine' => 228,
                      'endLine' => 228,
                      'startTokenPos' => 554,
                      'startFilePos' => 9239,
                      'endTokenPos' => 560,
                      'endFilePos' => 9257,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 228,
                      'endLine' => 228,
                      'startTokenPos' => 566,
                      'startFilePos' => 9269,
                      'endTokenPos' => 566,
                      'endFilePos' => 9270,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 228,
            'endLine' => 229,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'contents' => 
          array (
            'name' => 'contents',
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
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'8.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 230,
                      'endLine' => 230,
                      'startTokenPos' => 581,
                      'startFilePos' => 9384,
                      'endTokenPos' => 581,
                      'endFilePos' => 9388,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 230,
            'endLine' => 231,
            'startColumn' => 13,
            'endColumn' => 28,
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
 * (Unknown)<br/>
 * Add a file from the filesystem to the phar archive
 * @link https://php.net/manual/en/phardata.addfromstring.php
 * @param string $localName <p>
 * Path that the file will be stored in the archive.
 * </p>
 * @param string $contents <p>
 * The file contents to store
 * </p>
 * @return void no return value, exception is thrown on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 226,
        'endLine' => 234,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'buildFromDirectory' => 
      array (
        'name' => 'buildFromDirectory',
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
                      'startLine' => 255,
                      'endLine' => 255,
                      'startTokenPos' => 614,
                      'startFilePos' => 10507,
                      'endTokenPos' => 620,
                      'endFilePos' => 10525,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 255,
                      'endLine' => 255,
                      'startTokenPos' => 626,
                      'startFilePos' => 10537,
                      'endTokenPos' => 626,
                      'endFilePos' => 10538,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 255,
            'endLine' => 256,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'pattern' => 
          array (
            'name' => 'pattern',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 258,
                'endLine' => 258,
                'startTokenPos' => 660,
                'startFilePos' => 10704,
                'endTokenPos' => 660,
                'endFilePos' => 10705,
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
                      'startLine' => 257,
                      'endLine' => 257,
                      'startTokenPos' => 638,
                      'startFilePos' => 10639,
                      'endTokenPos' => 644,
                      'endFilePos' => 10657,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 257,
                      'endLine' => 257,
                      'startTokenPos' => 650,
                      'startFilePos' => 10669,
                      'endTokenPos' => 650,
                      'endFilePos' => 10670,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 257,
            'endLine' => 258,
            'startColumn' => 13,
            'endColumn' => 32,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Construct a phar archive from the files within a directory.
 * @link https://php.net/manual/en/phardata.buildfromdirectory.php
 * @param string $directory <p>
 * The full or relative path to the directory that contains all files
 * to add to the archive.
 * </p>
 * @param $pattern $regex [optional] <p>
 * An optional pcre regular expression that is used to filter the
 * list of files. Only file paths matching the regular expression
 * will be included in the archive.
 * </p>
 * @return array <b>Phar::buildFromDirectory</b> returns an associative array
 * mapping internal path of file to the full path of the file on the
 * filesystem.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 253,
        'endLine' => 261,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'buildFromIterator' => 
      array (
        'name' => 'buildFromIterator',
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
            'startLine' => 281,
            'endLine' => 281,
            'startColumn' => 13,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'baseDirectory' => 
          array (
            'name' => 'baseDirectory',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 283,
                'endLine' => 283,
                'startTokenPos' => 716,
                'startFilePos' => 11864,
                'endTokenPos' => 716,
                'endFilePos' => 11867,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 282,
                      'endLine' => 282,
                      'startTokenPos' => 692,
                      'startFilePos' => 11783,
                      'endTokenPos' => 698,
                      'endFilePos' => 11806,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 282,
                      'endLine' => 282,
                      'startTokenPos' => 704,
                      'startFilePos' => 11818,
                      'endTokenPos' => 704,
                      'endFilePos' => 11819,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 282,
            'endLine' => 283,
            'startColumn' => 13,
            'endColumn' => 45,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Construct a phar archive from an iterator.
 * @link https://php.net/manual/en/phardata.buildfromiterator.php
 * @param Traversable $iterator <p>
 * Any iterator that either associatively maps phar file to location or
 * returns SplFileInfo objects
 * </p>
 * @param string $baseDirectory [optional] <p>
 * For iterators that return SplFileInfo objects, the portion of each
 * file\'s full path to remove when adding to the phar archive
 * </p>
 * @return array <b>Phar::buildFromIterator</b> returns an associative array
 * mapping internal path of file to the full path of the file on the
 * filesystem.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 279,
        'endLine' => 286,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'compressFiles' => 
      array (
        'name' => 'compressFiles',
        'parameters' => 
        array (
          'compression' => 
          array (
            'name' => 'compression',
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
                      'startLine' => 301,
                      'endLine' => 301,
                      'startTokenPos' => 743,
                      'startFilePos' => 12562,
                      'endTokenPos' => 749,
                      'endFilePos' => 12577,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 301,
                      'endLine' => 301,
                      'startTokenPos' => 755,
                      'startFilePos' => 12589,
                      'endTokenPos' => 755,
                      'endFilePos' => 12590,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 301,
            'endLine' => 302,
            'startColumn' => 13,
            'endColumn' => 28,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Compresses all files in the current Phar archive
 * @link https://php.net/manual/en/phardata.compressfiles.php
 * @param int $compression <p>
 * Compression must be one of Phar::GZ,
 * Phar::BZ2 to add compression, or Phar::NONE
 * to remove compression.
 * </p>
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 299,
        'endLine' => 305,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'decompressFiles' => 
      array (
        'name' => 'decompressFiles',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 313,
                  'endLine' => 313,
                  'startTokenPos' => 777,
                  'startFilePos' => 13059,
                  'endTokenPos' => 783,
                  'endFilePos' => 13075,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 313,
                  'endLine' => 313,
                  'startTokenPos' => 789,
                  'startFilePos' => 13087,
                  'endTokenPos' => 789,
                  'endFilePos' => 13092,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Decompresses all files in the current Phar archive
 * @link https://php.net/manual/en/phardata.decompressfiles.php
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 313,
        'endLine' => 317,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'compress' => 
      array (
        'name' => 'compress',
        'parameters' => 
        array (
          'compression' => 
          array (
            'name' => 'compression',
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
                      'startLine' => 340,
                      'endLine' => 340,
                      'startTokenPos' => 844,
                      'startFilePos' => 14325,
                      'endTokenPos' => 850,
                      'endFilePos' => 14340,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 340,
                      'endLine' => 340,
                      'startTokenPos' => 856,
                      'startFilePos' => 14352,
                      'endTokenPos' => 856,
                      'endFilePos' => 14353,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 340,
            'endLine' => 341,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extension' => 
          array (
            'name' => 'extension',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 343,
                'endLine' => 343,
                'startTokenPos' => 892,
                'startFilePos' => 14530,
                'endTokenPos' => 892,
                'endFilePos' => 14533,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 342,
                      'endLine' => 342,
                      'startTokenPos' => 868,
                      'startFilePos' => 14453,
                      'endTokenPos' => 874,
                      'endFilePos' => 14476,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 342,
                      'endLine' => 342,
                      'startTokenPos' => 880,
                      'startFilePos' => 14488,
                      'endTokenPos' => 880,
                      'endFilePos' => 14489,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 342,
            'endLine' => 343,
            'startColumn' => 13,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
                'code' => '[\'8.0\' => \'static|null\']',
                'attributes' => 
                array (
                  'startLine' => 337,
                  'endLine' => 337,
                  'startTokenPos' => 814,
                  'startFilePos' => 14131,
                  'endTokenPos' => 820,
                  'endFilePos' => 14154,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 337,
                  'endLine' => 337,
                  'startTokenPos' => 826,
                  'startFilePos' => 14166,
                  'endTokenPos' => 826,
                  'endFilePos' => 14167,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Compresses the entire Phar archive using Gzip or Bzip2 compression
 * @link https://php.net/manual/en/phardata.compress.php
 * @param int $compression <p>
 * Compression must be one of Phar::GZ,
 * Phar::BZ2 to add compression, or Phar::NONE
 * to remove compression.
 * </p>
 * @param string $extension [optional] <p>
 * By default, the extension is .phar.gz
 * or .phar.bz2 for compressing phar archives, and
 * .phar.tar.gz or .phar.tar.bz2 for
 * compressing tar archives. For decompressing, the default file extensions
 * are .phar and .phar.tar.
 * </p>
 * @return static|null a <b>Phar</b> object.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 337,
        'endLine' => 346,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'decompress' => 
      array (
        'name' => 'decompress',
        'parameters' => 
        array (
          'extension' => 
          array (
            'name' => 'extension',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 365,
                'endLine' => 365,
                'startTokenPos' => 959,
                'startFilePos' => 15538,
                'endTokenPos' => 959,
                'endFilePos' => 15541,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 364,
                      'endLine' => 364,
                      'startTokenPos' => 935,
                      'startFilePos' => 15461,
                      'endTokenPos' => 941,
                      'endFilePos' => 15484,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 364,
                      'endLine' => 364,
                      'startTokenPos' => 947,
                      'startFilePos' => 15496,
                      'endTokenPos' => 947,
                      'endFilePos' => 15497,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 364,
            'endLine' => 365,
            'startColumn' => 13,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
                'code' => '[\'8.0\' => \'static|null\']',
                'attributes' => 
                array (
                  'startLine' => 361,
                  'endLine' => 361,
                  'startTokenPos' => 905,
                  'startFilePos' => 15265,
                  'endTokenPos' => 911,
                  'endFilePos' => 15288,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 361,
                  'endLine' => 361,
                  'startTokenPos' => 917,
                  'startFilePos' => 15300,
                  'endTokenPos' => 917,
                  'endFilePos' => 15301,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Decompresses the entire Phar archive
 * @link https://php.net/manual/en/phardata.decompress.php
 * @param string $extension [optional] <p>
 * For decompressing, the default file extensions
 * are .phar and .phar.tar.
 * Use this parameter to specify another file extension. Be aware
 * that all executable phar archives must contain .phar
 * in their filename.
 * </p>
 * @return static|null A <b>Phar</b> object is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 361,
        'endLine' => 368,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'convertToExecutable' => 
      array (
        'name' => 'convertToExecutable',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 404,
                'endLine' => 404,
                'startTokenPos' => 1007,
                'startFilePos' => 17414,
                'endTokenPos' => 1007,
                'endFilePos' => 17417,
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
                      'startLine' => 403,
                      'endLine' => 403,
                      'startTokenPos' => 983,
                      'startFilePos' => 17346,
                      'endTokenPos' => 989,
                      'endFilePos' => 17366,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 403,
                      'endLine' => 403,
                      'startTokenPos' => 995,
                      'startFilePos' => 17378,
                      'endTokenPos' => 995,
                      'endFilePos' => 17379,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 403,
            'endLine' => 404,
            'startColumn' => 13,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'compression' => 
          array (
            'name' => 'compression',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 406,
                'endLine' => 406,
                'startTokenPos' => 1037,
                'startFilePos' => 17559,
                'endTokenPos' => 1037,
                'endFilePos' => 17562,
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
                      'startLine' => 405,
                      'endLine' => 405,
                      'startTokenPos' => 1013,
                      'startFilePos' => 17486,
                      'endTokenPos' => 1019,
                      'endFilePos' => 17506,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 405,
                      'endLine' => 405,
                      'startTokenPos' => 1025,
                      'startFilePos' => 17518,
                      'endTokenPos' => 1025,
                      'endFilePos' => 17519,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 405,
            'endLine' => 406,
            'startColumn' => 13,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extension' => 
          array (
            'name' => 'extension',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 408,
                'endLine' => 408,
                'startTokenPos' => 1067,
                'startFilePos' => 17708,
                'endTokenPos' => 1067,
                'endFilePos' => 17711,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 407,
                      'endLine' => 407,
                      'startTokenPos' => 1043,
                      'startFilePos' => 17631,
                      'endTokenPos' => 1049,
                      'endFilePos' => 17654,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 407,
                      'endLine' => 407,
                      'startTokenPos' => 1055,
                      'startFilePos' => 17666,
                      'endTokenPos' => 1055,
                      'endFilePos' => 17667,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 407,
            'endLine' => 408,
            'startColumn' => 13,
            'endColumn' => 41,
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
                  'name' => 'Phar',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Convert a phar archive to another executable phar archive file format
 * @link https://php.net/manual/en/phardata.converttoexecutable.php
 * @param int $format [optional] <p>
 * This should be one of Phar::PHAR, Phar::TAR,
 * or Phar::ZIP. If set to <b>NULL</b>, the existing file format
 * will be preserved.
 * </p>
 * @param int $compression [optional] <p>
 * This should be one of Phar::NONE for no whole-archive
 * compression, Phar::GZ for zlib-based compression, and
 * Phar::BZ2 for bzip-based compression.
 * </p>
 * @param string $extension [optional] <p>
 * This parameter is used to override the default file extension for a
 * converted archive. Note that all zip- and tar-based phar archives must contain
 * .phar in their file extension in order to be processed as a
 * phar archive.
 * </p>
 * <p>
 * If converting to a phar-based archive, the default extensions are
 * .phar, .phar.gz, or .phar.bz2
 * depending on the specified compression. For tar-based phar archives, the
 * default extensions are .phar.tar, .phar.tar.gz,
 * and .phar.tar.bz2. For zip-based phar archives, the
 * default extension is .phar.zip.
 * </p>
 * @return Phar|null The method returns a <b>Phar</b> object on success and throws an
 * exception on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 401,
        'endLine' => 411,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'convertToData' => 
      array (
        'name' => 'convertToData',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 445,
                'endLine' => 445,
                'startTokenPos' => 1119,
                'startFilePos' => 19393,
                'endTokenPos' => 1119,
                'endFilePos' => 19396,
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
                      'startLine' => 444,
                      'endLine' => 444,
                      'startTokenPos' => 1095,
                      'startFilePos' => 19325,
                      'endTokenPos' => 1101,
                      'endFilePos' => 19345,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 444,
                      'endLine' => 444,
                      'startTokenPos' => 1107,
                      'startFilePos' => 19357,
                      'endTokenPos' => 1107,
                      'endFilePos' => 19358,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 444,
            'endLine' => 445,
            'startColumn' => 13,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'compression' => 
          array (
            'name' => 'compression',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 447,
                'endLine' => 447,
                'startTokenPos' => 1149,
                'startFilePos' => 19538,
                'endTokenPos' => 1149,
                'endFilePos' => 19541,
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
                      'startLine' => 446,
                      'endLine' => 446,
                      'startTokenPos' => 1125,
                      'startFilePos' => 19465,
                      'endTokenPos' => 1131,
                      'endFilePos' => 19485,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 446,
                      'endLine' => 446,
                      'startTokenPos' => 1137,
                      'startFilePos' => 19497,
                      'endTokenPos' => 1137,
                      'endFilePos' => 19498,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 446,
            'endLine' => 447,
            'startColumn' => 13,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extension' => 
          array (
            'name' => 'extension',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 449,
                'endLine' => 449,
                'startTokenPos' => 1179,
                'startFilePos' => 19687,
                'endTokenPos' => 1179,
                'endFilePos' => 19690,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 448,
                      'endLine' => 448,
                      'startTokenPos' => 1155,
                      'startFilePos' => 19610,
                      'endTokenPos' => 1161,
                      'endFilePos' => 19633,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 448,
                      'endLine' => 448,
                      'startTokenPos' => 1167,
                      'startFilePos' => 19645,
                      'endTokenPos' => 1167,
                      'endFilePos' => 19646,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 448,
            'endLine' => 449,
            'startColumn' => 13,
            'endColumn' => 41,
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
                  'name' => 'PharData',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Convert a phar archive to a non-executable tar or zip file
 * @link https://php.net/manual/en/phardata.converttodata.php
 * @param int $format [optional] <p>
 * This should be one of Phar::TAR
 * or Phar::ZIP. If set to <b>NULL</b>, the existing file format
 * will be preserved.
 * </p>
 * @param int $compression [optional] <p>
 * This should be one of Phar::NONE for no whole-archive
 * compression, Phar::GZ for zlib-based compression, and
 * Phar::BZ2 for bzip-based compression.
 * </p>
 * @param string $extension [optional] <p>
 * This parameter is used to override the default file extension for a
 * converted archive. Note that .phar cannot be used
 * anywhere in the filename for a non-executable tar or zip archive.
 * </p>
 * <p>
 * If converting to a tar-based phar archive, the
 * default extensions are .tar, .tar.gz,
 * and .tar.bz2 depending on specified compression.
 * For zip-based archives, the
 * default extension is .zip.
 * </p>
 * @return PharData|null The method returns a <b>PharData</b> object on success and throws an
 * exception on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 442,
        'endLine' => 452,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'copy' => 
      array (
        'name' => 'copy',
        'parameters' => 
        array (
          'from' => 
          array (
            'name' => 'from',
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
                      'startLine' => 466,
                      'endLine' => 466,
                      'startTokenPos' => 1226,
                      'startFilePos' => 20493,
                      'endTokenPos' => 1232,
                      'endFilePos' => 20511,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 466,
                      'endLine' => 466,
                      'startTokenPos' => 1238,
                      'startFilePos' => 20523,
                      'endTokenPos' => 1238,
                      'endFilePos' => 20524,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 466,
            'endLine' => 467,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'to' => 
          array (
            'name' => 'to',
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
                      'startLine' => 468,
                      'endLine' => 468,
                      'startTokenPos' => 1250,
                      'startFilePos' => 20620,
                      'endTokenPos' => 1256,
                      'endFilePos' => 20638,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 468,
                      'endLine' => 468,
                      'startTokenPos' => 1262,
                      'startFilePos' => 20650,
                      'endTokenPos' => 1262,
                      'endFilePos' => 20651,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 468,
            'endLine' => 469,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 463,
                  'endLine' => 463,
                  'startTokenPos' => 1196,
                  'startFilePos' => 20306,
                  'endTokenPos' => 1202,
                  'endFilePos' => 20322,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 463,
                  'endLine' => 463,
                  'startTokenPos' => 1208,
                  'startFilePos' => 20334,
                  'endTokenPos' => 1208,
                  'endFilePos' => 20339,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Copy a file internal to the phar archive to another new file within the phar
 * @link https://php.net/manual/en/phardata.copy.php
 * @param string $from
 * @param string $to
 * @return bool returns <b>TRUE</b> on success, but it is safer to encase method call in a
 * try/catch block and assume success if no exception is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 463,
        'endLine' => 472,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'count' => 
      array (
        'name' => 'count',
        'parameters' => 
        array (
          'mode' => 
          array (
            'name' => 'mode',
            'default' => 
            array (
              'code' => '\\COUNT_NORMAL',
              'attributes' => 
              array (
                'startLine' => 485,
                'endLine' => 485,
                'startTokenPos' => 1305,
                'startFilePos' => 21324,
                'endTokenPos' => 1305,
                'endFilePos' => 21335,
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
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'8.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 484,
                      'endLine' => 484,
                      'startTokenPos' => 1295,
                      'startFilePos' => 21292,
                      'endTokenPos' => 1295,
                      'endFilePos' => 21296,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 484,
            'endLine' => 485,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Returns the number of entries (files) in the Phar archive
 * @link https://php.net/manual/en/phardata.count.php
 * @param int $mode [optional]
 * @return int<0,max> The number of files contained within this phar, or 0 (the number zero)
 * if none.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 482,
        'endLine' => 488,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
          'localName' => 
          array (
            'name' => 'localName',
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
                      'startLine' => 503,
                      'endLine' => 503,
                      'startTokenPos' => 1351,
                      'startFilePos' => 22128,
                      'endTokenPos' => 1357,
                      'endFilePos' => 22146,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 503,
                      'endLine' => 503,
                      'startTokenPos' => 1363,
                      'startFilePos' => 22158,
                      'endTokenPos' => 1363,
                      'endFilePos' => 22159,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 503,
            'endLine' => 504,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 500,
                  'endLine' => 500,
                  'startTokenPos' => 1321,
                  'startFilePos' => 21939,
                  'endTokenPos' => 1327,
                  'endFilePos' => 21955,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 500,
                  'endLine' => 500,
                  'startTokenPos' => 1333,
                  'startFilePos' => 21967,
                  'endTokenPos' => 1333,
                  'endFilePos' => 21972,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Delete a file within a phar archive
 * @link https://php.net/manual/en/phardata.delete.php
 * @param string $localName <p>
 * Path within an archive to the file to delete.
 * </p>
 * @return bool returns <b>TRUE</b> on success, but it is better to check for thrown exception,
 * and assume success if none is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 500,
        'endLine' => 507,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'delMetadata' => 
      array (
        'name' => 'delMetadata',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 516,
                  'endLine' => 516,
                  'startTokenPos' => 1382,
                  'startFilePos' => 22686,
                  'endTokenPos' => 1388,
                  'endFilePos' => 22702,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 516,
                  'endLine' => 516,
                  'startTokenPos' => 1394,
                  'startFilePos' => 22714,
                  'endTokenPos' => 1394,
                  'endFilePos' => 22719,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.2.0)<br/>
 * Deletes the global metadata of the phar
 * @link https://php.net/manual/en/phardata.delmetadata.php
 * @return bool returns <b>TRUE</b> on success, but it is better to check for thrown exception,
 * and assume success if none is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 516,
        'endLine' => 520,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'extractTo' => 
      array (
        'name' => 'extractTo',
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
                      'startLine' => 540,
                      'endLine' => 540,
                      'startTokenPos' => 1430,
                      'startFilePos' => 23790,
                      'endTokenPos' => 1436,
                      'endFilePos' => 23808,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 540,
                      'endLine' => 540,
                      'startTokenPos' => 1442,
                      'startFilePos' => 23820,
                      'endTokenPos' => 1442,
                      'endFilePos' => 23821,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 540,
            'endLine' => 541,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 543,
                'endLine' => 543,
                'startTokenPos' => 1480,
                'startFilePos' => 24007,
                'endTokenPos' => 1480,
                'endFilePos' => 24010,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
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
                    'code' => '[\'8.0\' => \'array|string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 542,
                      'endLine' => 542,
                      'startTokenPos' => 1454,
                      'startFilePos' => 23922,
                      'endTokenPos' => 1460,
                      'endFilePos' => 23951,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 542,
                      'endLine' => 542,
                      'startTokenPos' => 1466,
                      'startFilePos' => 23963,
                      'endTokenPos' => 1466,
                      'endFilePos' => 23964,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 542,
            'endLine' => 543,
            'startColumn' => 13,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'overwrite' => 
          array (
            'name' => 'overwrite',
            'default' => 
            array (
              'code' => '\\false',
              'attributes' => 
              array (
                'startLine' => 545,
                'endLine' => 545,
                'startTokenPos' => 1508,
                'startFilePos' => 24142,
                'endTokenPos' => 1508,
                'endFilePos' => 24146,
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
                      'startLine' => 544,
                      'endLine' => 544,
                      'startTokenPos' => 1486,
                      'startFilePos' => 24079,
                      'endTokenPos' => 1492,
                      'endFilePos' => 24095,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 544,
                      'endLine' => 544,
                      'startTokenPos' => 1498,
                      'startFilePos' => 24107,
                      'endTokenPos' => 1498,
                      'endFilePos' => 24108,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 544,
            'endLine' => 545,
            'startColumn' => 13,
            'endColumn' => 35,
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
 * (Unknown)<br/>
 * Extract the contents of a phar archive to a directory
 * @link https://php.net/manual/en/phardata.extractto.php
 * @param string $directory <p>
 * Path within an archive to the file to delete.
 * </p>
 * @param string|array|null $files [optional] <p>
 * The name of a file or directory to extract, or an array of files/directories to extract
 * </p>
 * @param bool $overwrite [optional] <p>
 * Set to <b>TRUE</b> to enable overwriting existing files
 * </p>
 * @return bool returns <b>TRUE</b> on success, but it is better to check for thrown exception,
 * and assume success if none is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 538,
        'endLine' => 548,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'setAlias' => 
      array (
        'name' => 'setAlias',
        'parameters' => 
        array (
          'alias' => 
          array (
            'name' => 'alias',
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
                      'startLine' => 563,
                      'endLine' => 563,
                      'startTokenPos' => 1554,
                      'startFilePos' => 24858,
                      'endTokenPos' => 1560,
                      'endFilePos' => 24876,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 563,
                      'endLine' => 563,
                      'startTokenPos' => 1566,
                      'startFilePos' => 24888,
                      'endTokenPos' => 1566,
                      'endFilePos' => 24889,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 563,
            'endLine' => 564,
            'startColumn' => 13,
            'endColumn' => 25,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 561,
                  'endLine' => 561,
                  'startTokenPos' => 1528,
                  'startFilePos' => 24721,
                  'endTokenPos' => 1534,
                  'endFilePos' => 24737,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 561,
                  'endLine' => 561,
                  'startTokenPos' => 1540,
                  'startFilePos' => 24749,
                  'endTokenPos' => 1540,
                  'endFilePos' => 24754,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.2.1)<br/>
 * Set the alias for the Phar archive
 * @link https://php.net/manual/en/phar.setalias.php
 * @param string $alias <p>
 * A shorthand string that this archive can be referred to in phar
 * stream wrapper access.
 * </p>
 * @return bool
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 560,
        'endLine' => 567,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getAlias' => 
      array (
        'name' => 'getAlias',
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
 * @return string|null
 * @see setAlias
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 573,
        'endLine' => 576,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getPath' => 
      array (
        'name' => 'getPath',
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
 * Gets the path without filename
 * @return string the path to the file.
 * @since 5.3
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 583,
        'endLine' => 586,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getMetadata' => 
      array (
        'name' => 'getMetadata',
        'parameters' => 
        array (
          'unserializeOptions' => 
          array (
            'name' => 'unserializeOptions',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 600,
                'endLine' => 600,
                'startTokenPos' => 1652,
                'startFilePos' => 26313,
                'endTokenPos' => 1653,
                'endFilePos' => 26314,
              ),
            ),
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'8.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 599,
                      'endLine' => 599,
                      'startTokenPos' => 1642,
                      'startFilePos' => 26265,
                      'endTokenPos' => 1642,
                      'endFilePos' => 26269,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 599,
            'endLine' => 600,
            'startColumn' => 13,
            'endColumn' => 42,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Returns phar archive meta-data
 * @link https://php.net/manual/en/phar.getmetadata.php
 * @param array $unserializeOptions [optional] if is set to anything other than the default,
 * the resulting metadata won\'t be cached and this won\'t return the value from the cache
 * @return mixed any PHP variable that can be serialized and is stored as meta-data for the Phar archive,
 * or <b>NULL</b> if no meta-data is stored.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 597,
        'endLine' => 603,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getModified' => 
      array (
        'name' => 'getModified',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Return whether phar was modified
 * @link https://php.net/manual/en/phar.getmodified.php
 * @return bool <b>TRUE</b> if the phar has been modified since opened, <b>FALSE</b> if not.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 611,
        'endLine' => 614,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getSignature' => 
      array (
        'name' => 'getSignature',
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
                  'name' => 'array',
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
            'name' => 'JetBrains\\PhpStorm\\ArrayShape',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '["hash" => "string", "hash_type" => "string"]',
                'attributes' => 
                array (
                  'startLine' => 629,
                  'endLine' => 629,
                  'startTokenPos' => 1690,
                  'startFilePos' => 27586,
                  'endTokenPos' => 1703,
                  'endFilePos' => 27630,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Return MD5/SHA1/SHA256/SHA512/OpenSSL signature of a Phar archive
 * @link https://php.net/manual/en/phar.getsignature.php
 * @return array Array with the opened archive\'s signature in hash key and MD5,
 * SHA-1,
 * SHA-256, SHA-512, or OpenSSL
 * in hash_type. This signature is a hash calculated on the
 * entire phar\'s contents, and may be used to verify the integrity of the archive.
 * A valid signature is absolutely required of all executable phar archives if the
 * phar.require_hash INI variable
 * is set to true.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 629,
        'endLine' => 633,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getStub' => 
      array (
        'name' => 'getStub',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Return the PHP loader or bootstrap stub of a Phar archive
 * @link https://php.net/manual/en/phar.getstub.php
 * @return string a string containing the contents of the bootstrap loader (stub) of
 * the current Phar archive.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 642,
        'endLine' => 645,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getVersion' => 
      array (
        'name' => 'getVersion',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Return version info of Phar archive
 * @link https://php.net/manual/en/phar.getversion.php
 * @return string The opened archive\'s API version. This is not to be confused with
 * the API version that the loaded phar extension will use to create
 * new phars. Each Phar archive has the API version hard-coded into
 * its manifest. See Phar file format
 * documentation for more information.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 657,
        'endLine' => 660,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'hasMetadata' => 
      array (
        'name' => 'hasMetadata',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.2.0)<br/>
 * Returns whether phar has global meta-data
 * @link https://php.net/manual/en/phar.hasmetadata.php
 * @return bool <b>TRUE</b> if meta-data has been set, and <b>FALSE</b> if not.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 668,
        'endLine' => 671,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'isBuffering' => 
      array (
        'name' => 'isBuffering',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Used to determine whether Phar write operations are being buffered, or are flushing directly to disk
 * @link https://php.net/manual/en/phar.isbuffering.php
 * @return bool <b>TRUE</b> if the write operations are being buffer, <b>FALSE</b> otherwise.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 679,
        'endLine' => 682,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'isCompressed' => 
      array (
        'name' => 'isCompressed',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Returns Phar::GZ or PHAR::BZ2 if the entire phar archive is compressed (.tar.gz/tar.bz and so on)
 * @link https://php.net/manual/en/phar.iscompressed.php
 * @return mixed Phar::GZ, Phar::BZ2 or <b>FALSE</b>
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 690,
        'endLine' => 693,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'isFileFormat' => 
      array (
        'name' => 'isFileFormat',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
                      'startLine' => 707,
                      'endLine' => 707,
                      'startTokenPos' => 1851,
                      'startFilePos' => 31150,
                      'endTokenPos' => 1857,
                      'endFilePos' => 31165,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 707,
                      'endLine' => 707,
                      'startTokenPos' => 1863,
                      'startFilePos' => 31177,
                      'endTokenPos' => 1863,
                      'endFilePos' => 31178,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 707,
            'endLine' => 708,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Returns true if the phar archive is based on the tar/phar/zip file format depending on the parameter
 * @link https://php.net/manual/en/phar.isfileformat.php
 * @param int $format <p>
 * Either Phar::PHAR, Phar::TAR, or
 * Phar::ZIP to test for the format of the archive.
 * </p>
 * @return bool <b>TRUE</b> if the phar archive matches the file format requested by the parameter
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 705,
        'endLine' => 711,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'isWritable' => 
      array (
        'name' => 'isWritable',
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
 * (Unknown)<br/>
 * Returns true if the phar archive can be modified
 * @link https://php.net/manual/en/phar.iswritable.php
 * @return bool <b>TRUE</b> if the phar archive can be modified
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 719,
        'endLine' => 722,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'setDefaultStub' => 
      array (
        'name' => 'setDefaultStub',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 740,
                'endLine' => 740,
                'startTokenPos' => 1960,
                'startFilePos' => 32627,
                'endTokenPos' => 1960,
                'endFilePos' => 32630,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 739,
                      'endLine' => 739,
                      'startTokenPos' => 1936,
                      'startFilePos' => 32554,
                      'endTokenPos' => 1942,
                      'endFilePos' => 32577,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 739,
                      'endLine' => 739,
                      'startTokenPos' => 1948,
                      'startFilePos' => 32589,
                      'endTokenPos' => 1948,
                      'endFilePos' => 32590,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 739,
            'endLine' => 740,
            'startColumn' => 13,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'webIndex' => 
          array (
            'name' => 'webIndex',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 742,
                'endLine' => 742,
                'startTokenPos' => 1990,
                'startFilePos' => 32775,
                'endTokenPos' => 1990,
                'endFilePos' => 32778,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 741,
                      'endLine' => 741,
                      'startTokenPos' => 1966,
                      'startFilePos' => 32699,
                      'endTokenPos' => 1972,
                      'endFilePos' => 32722,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 741,
                      'endLine' => 741,
                      'startTokenPos' => 1978,
                      'startFilePos' => 32734,
                      'endTokenPos' => 1978,
                      'endFilePos' => 32735,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 741,
            'endLine' => 742,
            'startColumn' => 13,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 737,
                  'endLine' => 737,
                  'startTokenPos' => 1910,
                  'startFilePos' => 32411,
                  'endTokenPos' => 1916,
                  'endFilePos' => 32427,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 737,
                  'endLine' => 737,
                  'startTokenPos' => 1922,
                  'startFilePos' => 32439,
                  'endTokenPos' => 1922,
                  'endFilePos' => 32444,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (Unknown)<br/>
 * Used to set the PHP loader or bootstrap stub of a Phar archive to the default loader
 * @link https://php.net/manual/en/phar.setdefaultstub.php
 * @param string $index [optional] <p>
 * Relative path within the phar archive to run if accessed on the command-line
 * </p>
 * @param string $webIndex [optional] <p>
 * Relative path within the phar archive to run if accessed through a web browser
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 736,
        'endLine' => 745,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'setMetadata' => 
      array (
        'name' => 'setMetadata',
        'parameters' => 
        array (
          'metadata' => 
          array (
            'name' => 'metadata',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 758,
                      'endLine' => 758,
                      'startTokenPos' => 2014,
                      'startFilePos' => 33391,
                      'endTokenPos' => 2020,
                      'endFilePos' => 33408,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 758,
                      'endLine' => 758,
                      'startTokenPos' => 2026,
                      'startFilePos' => 33420,
                      'endTokenPos' => 2026,
                      'endFilePos' => 33421,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 758,
            'endLine' => 759,
            'startColumn' => 13,
            'endColumn' => 27,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Sets phar archive meta-data
 * @link https://php.net/manual/en/phar.setmetadata.php
 * @param mixed $metadata <p>
 * Any PHP variable containing information to store that describes the phar archive
 * </p>
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 756,
        'endLine' => 762,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'setSignatureAlgorithm' => 
      array (
        'name' => 'setSignatureAlgorithm',
        'parameters' => 
        array (
          'algo' => 
          array (
            'name' => 'algo',
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
                      'startLine' => 789,
                      'endLine' => 789,
                      'startTokenPos' => 2059,
                      'startFilePos' => 34666,
                      'endTokenPos' => 2065,
                      'endFilePos' => 34681,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 789,
                      'endLine' => 789,
                      'startTokenPos' => 2071,
                      'startFilePos' => 34693,
                      'endTokenPos' => 2071,
                      'endFilePos' => 34694,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 789,
            'endLine' => 790,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'privateKey' => 
          array (
            'name' => 'privateKey',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 792,
                'endLine' => 792,
                'startTokenPos' => 2107,
                'startFilePos' => 34865,
                'endTokenPos' => 2107,
                'endFilePos' => 34868,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 791,
                      'endLine' => 791,
                      'startTokenPos' => 2083,
                      'startFilePos' => 34787,
                      'endTokenPos' => 2089,
                      'endFilePos' => 34810,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 791,
                      'endLine' => 791,
                      'startTokenPos' => 2095,
                      'startFilePos' => 34822,
                      'endTokenPos' => 2095,
                      'endFilePos' => 34823,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 791,
            'endLine' => 792,
            'startColumn' => 13,
            'endColumn' => 42,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.1.0)<br/>
 * set the signature algorithm for a phar and apply it.
 * @link https://php.net/manual/en/phar.setsignaturealgorithm.php
 * @param int $algo <p>
 * One of Phar::MD5,
 * Phar::SHA1, Phar::SHA256,
 * Phar::SHA512, or Phar::OPENSSL
 * </p>
 * @param string $privateKey [optional] <p>
 * The contents of an OpenSSL private key, as extracted from a certificate or
 * OpenSSL key file:
 * <code>
 * $private = openssl_get_privatekey(file_get_contents(\'private.pem\'));
 * $pkey = \'\';
 * openssl_pkey_export($private, $pkey);
 * $p->setSignatureAlgorithm(Phar::OPENSSL, $pkey);
 * </code>
 * See phar introduction for instructions on
 * naming and placement of the public key file.
 * </p>
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 787,
        'endLine' => 795,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'setStub' => 
      array (
        'name' => 'setStub',
        'parameters' => 
        array (
          'stub' => 
          array (
            'name' => 'stub',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 810,
            'endLine' => 810,
            'startColumn' => 13,
            'endColumn' => 17,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'length' => 
          array (
            'name' => 'length',
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
                      'startLine' => 811,
                      'endLine' => 811,
                      'startTokenPos' => 2152,
                      'startFilePos' => 35634,
                      'endTokenPos' => 2158,
                      'endFilePos' => 35649,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 811,
                      'endLine' => 811,
                      'startTokenPos' => 2164,
                      'startFilePos' => 35661,
                      'endTokenPos' => 2164,
                      'endFilePos' => 35662,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 811,
            'endLine' => 812,
            'startColumn' => 13,
            'endColumn' => 23,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
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
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 808,
                  'endLine' => 808,
                  'startTokenPos' => 2123,
                  'startFilePos' => 35479,
                  'endTokenPos' => 2129,
                  'endFilePos' => 35495,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 808,
                  'endLine' => 808,
                  'startTokenPos' => 2135,
                  'startFilePos' => 35507,
                  'endTokenPos' => 2135,
                  'endFilePos' => 35512,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Used to set the PHP loader or bootstrap stub of a Phar archive
 * @link https://php.net/manual/en/phar.setstub.php
 * @param string $stub <p>
 * A string or an open stream handle to use as the executable stub for this
 * phar archive.
 * </p>
 * @param int $length [optional] <p>
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */',
        'startLine' => 808,
        'endLine' => 815,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'startBuffering' => 
      array (
        'name' => 'startBuffering',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Start buffering Phar write operations, do not modify the Phar object on disk
 * @link https://php.net/manual/en/phar.startbuffering.php
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 823,
        'endLine' => 826,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'stopBuffering' => 
      array (
        'name' => 'stopBuffering',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Stop buffering write requests to the Phar archive, and save changes to disk
 * @link https://php.net/manual/en/phar.stopbuffering.php
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 834,
        'endLine' => 837,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'apiVersion' => 
      array (
        'name' => 'apiVersion',
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
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Returns the api version
 * @link https://php.net/manual/en/phar.apiversion.php
 * @return string The API version string as in &#x00022;1.0.0&#x00022;.
 */',
        'startLine' => 844,
        'endLine' => 846,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'canCompress' => 
      array (
        'name' => 'canCompress',
        'parameters' => 
        array (
          'compression' => 
          array (
            'name' => 'compression',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 858,
                'endLine' => 858,
                'startTokenPos' => 2262,
                'startFilePos' => 37600,
                'endTokenPos' => 2262,
                'endFilePos' => 37600,
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
            'startLine' => 858,
            'endLine' => 858,
            'startColumn' => 50,
            'endColumn' => 69,
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
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Returns whether phar extension supports compression using either zlib or bzip2
 * @link https://php.net/manual/en/phar.cancompress.php
 * @param int $compression [optional] <p>
 * Either Phar::GZ or Phar::BZ2 can be
 * used to test whether compression is possible with a specific compression
 * algorithm (zlib or bzip2).
 * </p>
 * @return bool <b>TRUE</b> if compression/decompression is available, <b>FALSE</b> if not.
 */',
        'startLine' => 858,
        'endLine' => 860,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'canWrite' => 
      array (
        'name' => 'canWrite',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Returns whether phar extension supports writing and creating phars
 * @link https://php.net/manual/en/phar.canwrite.php
 * @return bool <b>TRUE</b> if write access is enabled, <b>FALSE</b> if it is disabled.
 */',
        'startLine' => 867,
        'endLine' => 869,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'createDefaultStub' => 
      array (
        'name' => 'createDefaultStub',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 880,
                'endLine' => 880,
                'startTokenPos' => 2312,
                'startFilePos' => 38571,
                'endTokenPos' => 2312,
                'endFilePos' => 38574,
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
            'startLine' => 880,
            'endLine' => 880,
            'startColumn' => 56,
            'endColumn' => 76,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'webIndex' => 
          array (
            'name' => 'webIndex',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 880,
                'endLine' => 880,
                'startTokenPos' => 2322,
                'startFilePos' => 38597,
                'endTokenPos' => 2322,
                'endFilePos' => 38600,
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
            'startLine' => 880,
            'endLine' => 880,
            'startColumn' => 79,
            'endColumn' => 102,
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
 * (Unknown)<br/>
 * Create a phar-file format specific stub
 * @link https://php.net/manual/en/phar.createdefaultstub.php
 * @param string|null $index [optional]
 * @param string|null $webIndex [optional]
 * @return string a string containing the contents of a customized bootstrap loader (stub)
 * that allows the created Phar archive to work with or without the Phar extension
 * enabled.
 */',
        'startLine' => 880,
        'endLine' => 882,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getSupportedCompression' => 
      array (
        'name' => 'getSupportedCompression',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.2.0)<br/>
 * Return array of supported compression algorithms
 * @link https://php.net/manual/en/phar.getsupportedcompression.php
 * @return string[] an array containing any of "GZ" or
 * "BZ2", depending on the availability of
 * the zlib extension or the
 * bz2 extension.
 */',
        'startLine' => 892,
        'endLine' => 894,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'getSupportedSignatures' => 
      array (
        'name' => 'getSupportedSignatures',
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.1.0)<br/>
 * Return array of supported signature types
 * @link https://php.net/manual/en/phar.getsupportedsignatures.php
 * @return string[] an array containing any of "MD5", "SHA-1",
 * "SHA-256", "SHA-512", or "OpenSSL".
 */',
        'startLine' => 902,
        'endLine' => 904,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'interceptFileFuncs' => 
      array (
        'name' => 'interceptFileFuncs',
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
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * instructs phar to intercept fopen, file_get_contents, opendir, and all of the stat-related functions
 * @link https://php.net/manual/en/phar.interceptfilefuncs.php
 * @return void
 */',
        'startLine' => 911,
        'endLine' => 913,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'isValidPharFilename' => 
      array (
        'name' => 'isValidPharFilename',
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
            'startLine' => 927,
            'endLine' => 927,
            'startColumn' => 58,
            'endColumn' => 73,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'executable' => 
          array (
            'name' => 'executable',
            'default' => 
            array (
              'code' => '\\true',
              'attributes' => 
              array (
                'startLine' => 927,
                'endLine' => 927,
                'startTokenPos' => 2418,
                'startFilePos' => 40642,
                'endTokenPos' => 2418,
                'endFilePos' => 40645,
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
            'startLine' => 927,
            'endLine' => 927,
            'startColumn' => 76,
            'endColumn' => 98,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.2.0)<br/>
 * Returns whether the given filename is a valid phar filename
 * @link https://php.net/manual/en/phar.isvalidpharfilename.php
 * @param string $filename <p>
 * The name or full path to a phar archive not yet created
 * </p>
 * @param bool $executable [optional] <p>
 * This parameter determines whether the filename should be treated as
 * a phar executable archive, or a data non-executable archive
 * </p>
 * @return bool <b>TRUE</b> if the filename is valid, <b>FALSE</b> if not.
 */',
        'startLine' => 927,
        'endLine' => 929,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'loadPhar' => 
      array (
        'name' => 'loadPhar',
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
            'startLine' => 945,
            'endLine' => 945,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'alias' => 
          array (
            'name' => 'alias',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 945,
                'endLine' => 945,
                'startTokenPos' => 2452,
                'startFilePos' => 41473,
                'endTokenPos' => 2452,
                'endFilePos' => 41476,
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
            'startLine' => 945,
            'endLine' => 945,
            'startColumn' => 65,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Loads any phar archive with an alias
 * @link https://php.net/manual/en/phar.loadphar.php
 * @param string $filename <p>
 * the full or relative path to the phar archive to open
 * </p>
 * @param string|null $alias [optional] <p>
 * The alias that may be used to refer to the phar archive. Note
 * that many phar archives specify an explicit alias inside the
 * phar archive, and a <b>PharException</b> will be thrown if
 * a new alias is specified in this case.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */',
        'startLine' => 945,
        'endLine' => 947,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'mapPhar' => 
      array (
        'name' => 'mapPhar',
        'parameters' => 
        array (
          'alias' => 
          array (
            'name' => 'alias',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 961,
                'endLine' => 961,
                'startTokenPos' => 2481,
                'startFilePos' => 42187,
                'endTokenPos' => 2481,
                'endFilePos' => 42190,
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
            'startLine' => 961,
            'endLine' => 961,
            'startColumn' => 46,
            'endColumn' => 66,
            'parameterIndex' => 0,
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
                'startLine' => 961,
                'endLine' => 961,
                'startTokenPos' => 2490,
                'startFilePos' => 42207,
                'endTokenPos' => 2490,
                'endFilePos' => 42207,
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
            'startLine' => 961,
            'endLine' => 961,
            'startColumn' => 69,
            'endColumn' => 83,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Reads the currently executed file (a phar) and registers its manifest
 * @link https://php.net/manual/en/phar.mapphar.php
 * @param string|null $alias [optional] <p>
 * The alias that can be used in phar:// URLs to
 * refer to this archive, rather than its full path.
 * </p>
 * @param int $offset [optional] <p>
 * Unused variable, here for compatibility with PEAR\'s PHP_Archive.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */',
        'startLine' => 961,
        'endLine' => 963,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'running' => 
      array (
        'name' => 'running',
        'parameters' => 
        array (
          'returnPhar' => 
          array (
            'name' => 'returnPhar',
            'default' => 
            array (
              'code' => '\\true',
              'attributes' => 
              array (
                'startLine' => 976,
                'endLine' => 976,
                'startTokenPos' => 2529,
                'startFilePos' => 42906,
                'endTokenPos' => 2529,
                'endFilePos' => 42909,
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
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'7.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 975,
                      'endLine' => 975,
                      'startTokenPos' => 2519,
                      'startFilePos' => 42867,
                      'endTokenPos' => 2519,
                      'endFilePos' => 42871,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 975,
            'endLine' => 976,
            'startColumn' => 13,
            'endColumn' => 35,
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
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Returns the full path on disk or full phar URL to the currently executing Phar archive
 * @link https://php.net/manual/en/phar.running.php
 * @param bool $returnPhar <p>
 * If <b>FALSE</b>, the full path on disk to the phar
 * archive is returned. If <b>TRUE</b>, a full phar URL is returned.
 * </p>
 * @return string the filename if valid, empty string otherwise.
 */',
        'startLine' => 974,
        'endLine' => 979,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'mount' => 
      array (
        'name' => 'mount',
        'parameters' => 
        array (
          'pharPath' => 
          array (
            'name' => 'pharPath',
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
            'startLine' => 993,
            'endLine' => 993,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'externalPath' => 
          array (
            'name' => 'externalPath',
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
            'startLine' => 993,
            'endLine' => 993,
            'startColumn' => 62,
            'endColumn' => 81,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Mount an external path or file to a virtual location within the phar archive
 * @link https://php.net/manual/en/phar.mount.php
 * @param string $pharPath <p>
 * The internal path within the phar archive to use as the mounted path location.
 * This must be a relative path within the phar archive, and must not already exist.
 * </p>
 * @param string $externalPath <p>
 * A path or URL to an external file or directory to mount within the phar archive
 * </p>
 * @return void No return. <b>PharException</b> is thrown on failure.
 */',
        'startLine' => 993,
        'endLine' => 995,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'mungServer' => 
      array (
        'name' => 'mungServer',
        'parameters' => 
        array (
          'variables' => 
          array (
            'name' => 'variables',
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
            'startLine' => 1009,
            'endLine' => 1009,
            'startColumn' => 49,
            'endColumn' => 64,
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
        'docComment' => '/**
 * (Unknown)<br/>
 * Defines a list of up to 4 $_SERVER variables that should be modified for execution
 * @link https://php.net/manual/en/phar.mungserver.php
 * @param array $variables <p>
 * an array containing as string indices any of
 * REQUEST_URI, PHP_SELF,
 * SCRIPT_NAME and SCRIPT_FILENAME.
 * Other values trigger an exception, and <b>Phar::mungServer</b>
 * is case-sensitive.
 * </p>
 * @return void No return.
 */',
        'startLine' => 1009,
        'endLine' => 1011,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'unlinkArchive' => 
      array (
        'name' => 'unlinkArchive',
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
            'startLine' => 1023,
            'endLine' => 1023,
            'startColumn' => 52,
            'endColumn' => 67,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.4\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 1022,
                  'endLine' => 1022,
                  'startTokenPos' => 2598,
                  'startFilePos' => 44869,
                  'endTokenPos' => 2604,
                  'endFilePos' => 44885,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 1022,
                  'endLine' => 1022,
                  'startTokenPos' => 2610,
                  'startFilePos' => 44897,
                  'endTokenPos' => 2610,
                  'endFilePos' => 44902,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Completely remove a phar archive from disk and from memory
 * @link https://php.net/manual/en/phar.unlinkarchive.php
 * @param string $filename <p>
 * The path on disk to the phar archive.
 * </p>
 * @throws PharException
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */',
        'startLine' => 1022,
        'endLine' => 1025,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      'webPhar' => 
      array (
        'name' => 'webPhar',
        'parameters' => 
        array (
          'alias' => 
          array (
            'name' => 'alias',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1105,
                'endLine' => 1105,
                'startTokenPos' => 2656,
                'startFilePos' => 48351,
                'endTokenPos' => 2656,
                'endFilePos' => 48354,
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
            'startLine' => 1105,
            'endLine' => 1105,
            'startColumn' => 13,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'index' => 
          array (
            'name' => 'index',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1106,
                'endLine' => 1106,
                'startTokenPos' => 2666,
                'startFilePos' => 48386,
                'endTokenPos' => 2666,
                'endFilePos' => 48389,
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
            'startLine' => 1106,
            'endLine' => 1106,
            'startColumn' => 13,
            'endColumn' => 33,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'fileNotFoundScript' => 
          array (
            'name' => 'fileNotFoundScript',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1108,
                'endLine' => 1108,
                'startTokenPos' => 2696,
                'startFilePos' => 48550,
                'endTokenPos' => 2696,
                'endFilePos' => 48553,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 1107,
                      'endLine' => 1107,
                      'startTokenPos' => 2672,
                      'startFilePos' => 48458,
                      'endTokenPos' => 2678,
                      'endFilePos' => 48481,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'string\'',
                    'attributes' => 
                    array (
                      'startLine' => 1107,
                      'endLine' => 1107,
                      'startTokenPos' => 2684,
                      'startFilePos' => 48493,
                      'endTokenPos' => 2684,
                      'endFilePos' => 48500,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1107,
            'endLine' => 1108,
            'startColumn' => 13,
            'endColumn' => 50,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'mimeTypes' => 
          array (
            'name' => 'mimeTypes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1109,
                'endLine' => 1109,
                'startTokenPos' => 2705,
                'startFilePos' => 48587,
                'endTokenPos' => 2706,
                'endFilePos' => 48588,
              ),
            ),
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
            'startLine' => 1109,
            'endLine' => 1109,
            'startColumn' => 13,
            'endColumn' => 33,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'rewrite' => 
          array (
            'name' => 'rewrite',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1110,
                'endLine' => 1110,
                'startTokenPos' => 2716,
                'startFilePos' => 48624,
                'endTokenPos' => 2716,
                'endFilePos' => 48627,
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
                      'name' => 'callable',
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
            'startLine' => 1110,
            'endLine' => 1110,
            'startColumn' => 13,
            'endColumn' => 37,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * mapPhar for web-based phars. front controller for web applications
 * @link https://php.net/manual/en/phar.webphar.php
 * @param null|string $alias [optional] <p>
 * The alias that can be used in phar:// URLs to
 * refer to this archive, rather than its full path.
 * </p>
 * @param string|null $index [optional] <p>
 * The location within the phar of the directory index.
 * </p>
 * @param null|string $fileNotFoundScript [optional] <p>
 * The location of the script to run when a file is not found. This
 * script should output the proper HTTP 404 headers.
 * </p>
 * @param null|array $mimeTypes [optional] <p>
 * An array mapping additional file extensions to MIME type.
 * If the default mapping is sufficient, pass an empty array.
 * By default, these extensions are mapped to these MIME types:
 * <code>
 * $mimes = array(
 * \'phps\' => Phar::PHPS, // pass to highlight_file()
 * \'c\' => \'text/plain\',
 * \'cc\' => \'text/plain\',
 * \'cpp\' => \'text/plain\',
 * \'c++\' => \'text/plain\',
 * \'dtd\' => \'text/plain\',
 * \'h\' => \'text/plain\',
 * \'log\' => \'text/plain\',
 * \'rng\' => \'text/plain\',
 * \'txt\' => \'text/plain\',
 * \'xsd\' => \'text/plain\',
 * \'php\' => Phar::PHP, // parse as PHP
 * \'inc\' => Phar::PHP, // parse as PHP
 * \'avi\' => \'video/avi\',
 * \'bmp\' => \'image/bmp\',
 * \'css\' => \'text/css\',
 * \'gif\' => \'image/gif\',
 * \'htm\' => \'text/html\',
 * \'html\' => \'text/html\',
 * \'htmls\' => \'text/html\',
 * \'ico\' => \'image/x-ico\',
 * \'jpe\' => \'image/jpeg\',
 * \'jpg\' => \'image/jpeg\',
 * \'jpeg\' => \'image/jpeg\',
 * \'js\' => \'application/x-javascript\',
 * \'midi\' => \'audio/midi\',
 * \'mid\' => \'audio/midi\',
 * \'mod\' => \'audio/mod\',
 * \'mov\' => \'movie/quicktime\',
 * \'mp3\' => \'audio/mp3\',
 * \'mpg\' => \'video/mpeg\',
 * \'mpeg\' => \'video/mpeg\',
 * \'pdf\' => \'application/pdf\',
 * \'png\' => \'image/png\',
 * \'swf\' => \'application/shockwave-flash\',
 * \'tif\' => \'image/tiff\',
 * \'tiff\' => \'image/tiff\',
 * \'wav\' => \'audio/wav\',
 * \'xbm\' => \'image/xbm\',
 * \'xml\' => \'text/xml\',
 * );
 * </code>
 * </p>
 * @param null|callable $rewrite [optional] <p>
 * The rewrites function is passed a string as its only parameter and must return a string or <b>FALSE</b>.
 * </p>
 * <p>
 * If you are using fast-cgi or cgi then the parameter passed to the function is the value of the
 * $_SERVER[\'PATH_INFO\'] variable. Otherwise, the parameter passed to the function is the value
 * of the $_SERVER[\'REQUEST_URI\'] variable.
 * </p>
 * <p>
 * If a string is returned it is used as the internal file path. If <b>FALSE</b> is returned then webPhar() will
 * send a HTTP 403 Denied Code.
 * </p>
 * @return void No value is returned.
 */',
        'startLine' => 1104,
        'endLine' => 1113,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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
                      'startLine' => 1125,
                      'endLine' => 1125,
                      'startTokenPos' => 2743,
                      'startFilePos' => 49152,
                      'endTokenPos' => 2749,
                      'endFilePos' => 49167,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1125,
                      'endLine' => 1125,
                      'startTokenPos' => 2755,
                      'startFilePos' => 49179,
                      'endTokenPos' => 2755,
                      'endFilePos' => 49180,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1125,
            'endLine' => 1126,
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
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1123,
        'endLine' => 1129,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
        'aliasName' => NULL,
      ),
      '__destruct' => 
      array (
        'name' => '__destruct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1130,
        'endLine' => 1132,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'PharData',
        'implementingClassName' => 'PharData',
        'currentClassName' => 'PharData',
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