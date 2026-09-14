<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-phar
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'Phar',
        'filename' => 'phpstorm-stubs:Phar/Phar.stub',
        'extensionName' => 'Phar',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'Phar',
    'shortName' => 'Phar',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The Phar class provides a high-level interface to accessing and creating
 * phar archives.
 * @link https://php.net/manual/en/class.phar.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 1146,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => 'RecursiveDirectoryIterator',
    'implementsClassNames' => 
    array (
      0 => 'RecursiveIterator',
      1 => 'SeekableIterator',
      2 => 'Countable',
      3 => 'ArrayAccess',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'BZ2' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'BZ2',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8192',
          'attributes' => 
          array (
            'startLine' => 11,
            'endLine' => 11,
            'startTokenPos' => 39,
            'startFilePos' => 350,
            'endTokenPos' => 39,
            'endFilePos' => 353,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 11,
        'endLine' => 11,
        'startColumn' => 9,
        'endColumn' => 32,
      ),
      'GZ' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'GZ',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4096',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 50,
            'startFilePos' => 382,
            'endTokenPos' => 50,
            'endFilePos' => 385,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 12,
        'startColumn' => 9,
        'endColumn' => 31,
      ),
      'NONE' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'NONE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 61,
            'startFilePos' => 416,
            'endTokenPos' => 61,
            'endFilePos' => 416,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 9,
        'endColumn' => 30,
      ),
      'PHAR' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'PHAR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 72,
            'startFilePos' => 447,
            'endTokenPos' => 72,
            'endFilePos' => 447,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 9,
        'endColumn' => 30,
      ),
      'TAR' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'TAR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 83,
            'startFilePos' => 477,
            'endTokenPos' => 83,
            'endFilePos' => 477,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 9,
        'endColumn' => 29,
      ),
      'ZIP' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'ZIP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 94,
            'startFilePos' => 507,
            'endTokenPos' => 94,
            'endFilePos' => 507,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 9,
        'endColumn' => 29,
      ),
      'COMPRESSED' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'COMPRESSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '61440',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 105,
            'startFilePos' => 544,
            'endTokenPos' => 105,
            'endFilePos' => 548,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
      'PHP' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'PHP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 116,
            'startFilePos' => 578,
            'endTokenPos' => 116,
            'endFilePos' => 578,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 9,
        'endColumn' => 29,
      ),
      'PHPS' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'PHPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 127,
            'startFilePos' => 609,
            'endTokenPos' => 127,
            'endFilePos' => 609,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 9,
        'endColumn' => 30,
      ),
      'MD5' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'MD5',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 138,
            'startFilePos' => 639,
            'endTokenPos' => 138,
            'endFilePos' => 639,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 9,
        'endColumn' => 29,
      ),
      'OPENSSL' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'OPENSSL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 149,
            'startFilePos' => 673,
            'endTokenPos' => 149,
            'endFilePos' => 674,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'SHA1' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'SHA1',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 160,
            'startFilePos' => 705,
            'endTokenPos' => 160,
            'endFilePos' => 705,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 30,
      ),
      'SHA256' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'SHA256',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 171,
            'startFilePos' => 738,
            'endTokenPos' => 171,
            'endFilePos' => 738,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 9,
        'endColumn' => 32,
      ),
      'SHA512' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'SHA512',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 182,
            'startFilePos' => 771,
            'endTokenPos' => 182,
            'endFilePos' => 771,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 9,
        'endColumn' => 32,
      ),
      'OPENSSL_SHA256' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'OPENSSL_SHA256',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '17',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 195,
            'startFilePos' => 838,
            'endTokenPos' => 195,
            'endFilePos' => 839,
          ),
        ),
        'docComment' => '/** @since 8.1 */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'OPENSSL_SHA512' => 
      array (
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'name' => 'OPENSSL_SHA512',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '18',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 208,
            'startFilePos' => 906,
            'endTokenPos' => 208,
            'endFilePos' => 907,
          ),
        ),
        'docComment' => '/** @since 8.1 */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 9,
        'endColumn' => 41,
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
                      'startLine' => 48,
                      'endLine' => 48,
                      'startTokenPos' => 223,
                      'startFilePos' => 1833,
                      'endTokenPos' => 229,
                      'endFilePos' => 1851,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 48,
                      'endLine' => 48,
                      'startTokenPos' => 235,
                      'startFilePos' => 1863,
                      'endTokenPos' => 235,
                      'endFilePos' => 1864,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 48,
            'endLine' => 49,
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
                'startLine' => 51,
                'endLine' => 51,
                'startTokenPos' => 269,
                'startFilePos' => 2021,
                'endTokenPos' => 277,
                'endFilePos' => 2082,
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
                      'startLine' => 50,
                      'endLine' => 50,
                      'startTokenPos' => 247,
                      'startFilePos' => 1964,
                      'endTokenPos' => 253,
                      'endFilePos' => 1979,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 50,
                      'endLine' => 50,
                      'startTokenPos' => 259,
                      'startFilePos' => 1991,
                      'endTokenPos' => 259,
                      'endFilePos' => 1992,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 50,
            'endLine' => 51,
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
                'startLine' => 53,
                'endLine' => 53,
                'startTokenPos' => 307,
                'startFilePos' => 2224,
                'endTokenPos' => 307,
                'endFilePos' => 2227,
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
                      'startLine' => 52,
                      'endLine' => 52,
                      'startTokenPos' => 283,
                      'startFilePos' => 2151,
                      'endTokenPos' => 289,
                      'endFilePos' => 2174,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 52,
                      'endLine' => 52,
                      'startTokenPos' => 295,
                      'startFilePos' => 2186,
                      'endTokenPos' => 295,
                      'endFilePos' => 2187,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 52,
            'endLine' => 53,
            'startColumn' => 13,
            'endColumn' => 37,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Construct a Phar archive object
 * @link https://php.net/manual/en/phar.construct.php
 * @param string $filename <p>
 * Path to an existing Phar archive or to-be-created archive. The file name\'s
 * extension must contain .phar.
 * </p>
 * @param int $flags [optional] <p>
 * Flags to pass to parent class <b>RecursiveDirectoryIterator</b>.
 * </p>
 * @param string $alias [optional] <p>
 * Alias with which this Phar archive should be referred to in calls to stream
 * functionality.
 * </p>
 * @throws BadMethodCallException If called twice.
 * @throws UnexpectedValueException If the phar archive can\'t be opened.
 */',
        'startLine' => 47,
        'endLine' => 56,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 57,
        'endLine' => 59,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 72,
                      'endLine' => 72,
                      'startTokenPos' => 346,
                      'startFilePos' => 2904,
                      'endTokenPos' => 346,
                      'endFilePos' => 2908,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 72,
            'endLine' => 73,
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
 * @link https://php.net/manual/en/phar.addemptydir.php
 * @param string $directory <p>
 * The name of the empty directory to create in the phar archive
 * </p>
 * @return void no return value, exception is thrown on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 70,
        'endLine' => 76,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 93,
                      'endLine' => 93,
                      'startTokenPos' => 379,
                      'startFilePos' => 3702,
                      'endTokenPos' => 385,
                      'endFilePos' => 3720,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 93,
                      'endLine' => 93,
                      'startTokenPos' => 391,
                      'startFilePos' => 3732,
                      'endTokenPos' => 391,
                      'endFilePos' => 3733,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 93,
            'endLine' => 94,
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
                'startLine' => 96,
                'endLine' => 96,
                'startTokenPos' => 427,
                'startFilePos' => 3910,
                'endTokenPos' => 427,
                'endFilePos' => 3913,
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
                      'startLine' => 95,
                      'endLine' => 95,
                      'startTokenPos' => 403,
                      'startFilePos' => 3833,
                      'endTokenPos' => 409,
                      'endFilePos' => 3856,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 95,
                      'endLine' => 95,
                      'startTokenPos' => 415,
                      'startFilePos' => 3868,
                      'endTokenPos' => 415,
                      'endFilePos' => 3869,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 95,
            'endLine' => 96,
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
 * @link https://php.net/manual/en/phar.addfile.php
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
        'startLine' => 91,
        'endLine' => 99,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 115,
                      'endLine' => 115,
                      'startTokenPos' => 454,
                      'startFilePos' => 4619,
                      'endTokenPos' => 460,
                      'endFilePos' => 4637,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 115,
                      'endLine' => 115,
                      'startTokenPos' => 466,
                      'startFilePos' => 4649,
                      'endTokenPos' => 466,
                      'endFilePos' => 4650,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 115,
            'endLine' => 116,
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
                      'startLine' => 117,
                      'endLine' => 117,
                      'startTokenPos' => 481,
                      'startFilePos' => 4764,
                      'endTokenPos' => 481,
                      'endFilePos' => 4768,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 117,
            'endLine' => 118,
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
 * @link https://php.net/manual/en/phar.addfromstring.php
 * @param string $localName <p>
 * Path that the file will be stored in the archive.
 * </p>
 * @param string $contents <p>
 * The file contents to store
 * </p>
 * @return void no return value, exception is thrown on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 113,
        'endLine' => 121,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 142,
                      'endLine' => 142,
                      'startTokenPos' => 514,
                      'startFilePos' => 5883,
                      'endTokenPos' => 520,
                      'endFilePos' => 5901,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 142,
                      'endLine' => 142,
                      'startTokenPos' => 526,
                      'startFilePos' => 5913,
                      'endTokenPos' => 526,
                      'endFilePos' => 5914,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 142,
            'endLine' => 143,
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
                'startLine' => 145,
                'endLine' => 145,
                'startTokenPos' => 560,
                'startFilePos' => 6080,
                'endTokenPos' => 560,
                'endFilePos' => 6081,
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
                      'startLine' => 144,
                      'endLine' => 144,
                      'startTokenPos' => 538,
                      'startFilePos' => 6015,
                      'endTokenPos' => 544,
                      'endFilePos' => 6033,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 144,
                      'endLine' => 144,
                      'startTokenPos' => 550,
                      'startFilePos' => 6045,
                      'endTokenPos' => 550,
                      'endFilePos' => 6046,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 144,
            'endLine' => 145,
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
 * @link https://php.net/manual/en/phar.buildfromdirectory.php
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
        'startLine' => 140,
        'endLine' => 148,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 168,
            'endLine' => 168,
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
                'startLine' => 170,
                'endLine' => 170,
                'startTokenPos' => 616,
                'startFilePos' => 7236,
                'endTokenPos' => 616,
                'endFilePos' => 7239,
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
                      'startLine' => 169,
                      'endLine' => 169,
                      'startTokenPos' => 592,
                      'startFilePos' => 7155,
                      'endTokenPos' => 598,
                      'endFilePos' => 7178,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 169,
                      'endLine' => 169,
                      'startTokenPos' => 604,
                      'startFilePos' => 7190,
                      'endTokenPos' => 604,
                      'endFilePos' => 7191,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 169,
            'endLine' => 170,
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
 * @link https://php.net/manual/en/phar.buildfromiterator.php
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
        'startLine' => 166,
        'endLine' => 173,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 188,
                      'endLine' => 188,
                      'startTokenPos' => 643,
                      'startFilePos' => 7930,
                      'endTokenPos' => 649,
                      'endFilePos' => 7945,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 188,
                      'endLine' => 188,
                      'startTokenPos' => 655,
                      'startFilePos' => 7957,
                      'endTokenPos' => 655,
                      'endFilePos' => 7958,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 188,
            'endLine' => 189,
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
 * @link https://php.net/manual/en/phar.compressfiles.php
 * @param int $compression <p>
 * Compression must be one of Phar::GZ,
 * Phar::BZ2 to add compression, or Phar::NONE
 * to remove compression.
 * </p>
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 186,
        'endLine' => 192,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                  'startLine' => 200,
                  'endLine' => 200,
                  'startTokenPos' => 677,
                  'startFilePos' => 8423,
                  'endTokenPos' => 683,
                  'endFilePos' => 8439,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 200,
                  'endLine' => 200,
                  'startTokenPos' => 689,
                  'startFilePos' => 8451,
                  'endTokenPos' => 689,
                  'endFilePos' => 8456,
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
 * @link https://php.net/manual/en/phar.decompressfiles.php
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 200,
        'endLine' => 204,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 227,
                      'endLine' => 227,
                      'startTokenPos' => 744,
                      'startFilePos' => 9685,
                      'endTokenPos' => 750,
                      'endFilePos' => 9700,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 227,
                      'endLine' => 227,
                      'startTokenPos' => 756,
                      'startFilePos' => 9712,
                      'endTokenPos' => 756,
                      'endFilePos' => 9713,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 227,
            'endLine' => 228,
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
                'startLine' => 230,
                'endLine' => 230,
                'startTokenPos' => 792,
                'startFilePos' => 9890,
                'endTokenPos' => 792,
                'endFilePos' => 9893,
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
                      'startLine' => 229,
                      'endLine' => 229,
                      'startTokenPos' => 768,
                      'startFilePos' => 9813,
                      'endTokenPos' => 774,
                      'endFilePos' => 9836,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 229,
                      'endLine' => 229,
                      'startTokenPos' => 780,
                      'startFilePos' => 9848,
                      'endTokenPos' => 780,
                      'endFilePos' => 9849,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 229,
            'endLine' => 230,
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
                'code' => '[\'8.0\' => \'static|null\']',
                'attributes' => 
                array (
                  'startLine' => 225,
                  'endLine' => 225,
                  'startTokenPos' => 718,
                  'startFilePos' => 9545,
                  'endTokenPos' => 724,
                  'endFilePos' => 9568,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 225,
                  'endLine' => 225,
                  'startTokenPos' => 730,
                  'startFilePos' => 9580,
                  'endTokenPos' => 730,
                  'endFilePos' => 9581,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Compresses the entire Phar archive using Gzip or Bzip2 compression
 * @link https://php.net/manual/en/phar.compress.php
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
        'startLine' => 224,
        'endLine' => 233,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 252,
                'endLine' => 252,
                'startTokenPos' => 859,
                'startFilePos' => 10894,
                'endTokenPos' => 859,
                'endFilePos' => 10897,
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
                      'startLine' => 251,
                      'endLine' => 251,
                      'startTokenPos' => 835,
                      'startFilePos' => 10817,
                      'endTokenPos' => 841,
                      'endFilePos' => 10840,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 251,
                      'endLine' => 251,
                      'startTokenPos' => 847,
                      'startFilePos' => 10852,
                      'endTokenPos' => 847,
                      'endFilePos' => 10853,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 251,
            'endLine' => 252,
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
                'code' => '[\'8.0\' => \'static|null\']',
                'attributes' => 
                array (
                  'startLine' => 249,
                  'endLine' => 249,
                  'startTokenPos' => 809,
                  'startFilePos' => 10675,
                  'endTokenPos' => 815,
                  'endFilePos' => 10698,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 249,
                  'endLine' => 249,
                  'startTokenPos' => 821,
                  'startFilePos' => 10710,
                  'endTokenPos' => 821,
                  'endFilePos' => 10711,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 2.0.0)<br/>
 * Decompresses the entire Phar archive
 * @link https://php.net/manual/en/phar.decompress.php
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
        'startLine' => 248,
        'endLine' => 255,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 291,
                'endLine' => 291,
                'startTokenPos' => 907,
                'startFilePos' => 12766,
                'endTokenPos' => 907,
                'endFilePos' => 12769,
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
                      'startLine' => 290,
                      'endLine' => 290,
                      'startTokenPos' => 883,
                      'startFilePos' => 12698,
                      'endTokenPos' => 889,
                      'endFilePos' => 12718,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 290,
                      'endLine' => 290,
                      'startTokenPos' => 895,
                      'startFilePos' => 12730,
                      'endTokenPos' => 895,
                      'endFilePos' => 12731,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 290,
            'endLine' => 291,
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
                'startLine' => 293,
                'endLine' => 293,
                'startTokenPos' => 937,
                'startFilePos' => 12911,
                'endTokenPos' => 937,
                'endFilePos' => 12914,
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
                      'startLine' => 292,
                      'endLine' => 292,
                      'startTokenPos' => 913,
                      'startFilePos' => 12838,
                      'endTokenPos' => 919,
                      'endFilePos' => 12858,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 292,
                      'endLine' => 292,
                      'startTokenPos' => 925,
                      'startFilePos' => 12870,
                      'endTokenPos' => 925,
                      'endFilePos' => 12871,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 292,
            'endLine' => 293,
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
                'startLine' => 295,
                'endLine' => 295,
                'startTokenPos' => 967,
                'startFilePos' => 13060,
                'endTokenPos' => 967,
                'endFilePos' => 13063,
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
                      'startLine' => 294,
                      'endLine' => 294,
                      'startTokenPos' => 943,
                      'startFilePos' => 12983,
                      'endTokenPos' => 949,
                      'endFilePos' => 13006,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 294,
                      'endLine' => 294,
                      'startTokenPos' => 955,
                      'startFilePos' => 13018,
                      'endTokenPos' => 955,
                      'endFilePos' => 13019,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 294,
            'endLine' => 295,
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
 * @link https://php.net/manual/en/phar.converttoexecutable.php
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
        'startLine' => 288,
        'endLine' => 298,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 332,
                'endLine' => 332,
                'startTokenPos' => 1019,
                'startFilePos' => 14741,
                'endTokenPos' => 1019,
                'endFilePos' => 14744,
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
                      'startLine' => 331,
                      'endLine' => 331,
                      'startTokenPos' => 995,
                      'startFilePos' => 14673,
                      'endTokenPos' => 1001,
                      'endFilePos' => 14693,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 331,
                      'endLine' => 331,
                      'startTokenPos' => 1007,
                      'startFilePos' => 14705,
                      'endTokenPos' => 1007,
                      'endFilePos' => 14706,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 331,
            'endLine' => 332,
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
                'startLine' => 334,
                'endLine' => 334,
                'startTokenPos' => 1049,
                'startFilePos' => 14886,
                'endTokenPos' => 1049,
                'endFilePos' => 14889,
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
                      'startLine' => 333,
                      'endLine' => 333,
                      'startTokenPos' => 1025,
                      'startFilePos' => 14813,
                      'endTokenPos' => 1031,
                      'endFilePos' => 14833,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 333,
                      'endLine' => 333,
                      'startTokenPos' => 1037,
                      'startFilePos' => 14845,
                      'endTokenPos' => 1037,
                      'endFilePos' => 14846,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 333,
            'endLine' => 334,
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
                'startLine' => 336,
                'endLine' => 336,
                'startTokenPos' => 1079,
                'startFilePos' => 15035,
                'endTokenPos' => 1079,
                'endFilePos' => 15038,
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
                      'startLine' => 335,
                      'endLine' => 335,
                      'startTokenPos' => 1055,
                      'startFilePos' => 14958,
                      'endTokenPos' => 1061,
                      'endFilePos' => 14981,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 335,
                      'endLine' => 335,
                      'startTokenPos' => 1067,
                      'startFilePos' => 14993,
                      'endTokenPos' => 1067,
                      'endFilePos' => 14994,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 335,
            'endLine' => 336,
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
 * @link https://php.net/manual/en/phar.converttodata.php
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
        'startLine' => 329,
        'endLine' => 339,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 353,
                      'endLine' => 353,
                      'startTokenPos' => 1126,
                      'startFilePos' => 15837,
                      'endTokenPos' => 1132,
                      'endFilePos' => 15855,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 353,
                      'endLine' => 353,
                      'startTokenPos' => 1138,
                      'startFilePos' => 15867,
                      'endTokenPos' => 1138,
                      'endFilePos' => 15868,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 353,
            'endLine' => 354,
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
                      'startLine' => 355,
                      'endLine' => 355,
                      'startTokenPos' => 1150,
                      'startFilePos' => 15964,
                      'endTokenPos' => 1156,
                      'endFilePos' => 15982,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 355,
                      'endLine' => 355,
                      'startTokenPos' => 1162,
                      'startFilePos' => 15994,
                      'endTokenPos' => 1162,
                      'endFilePos' => 15995,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 355,
            'endLine' => 356,
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
                  'startLine' => 350,
                  'endLine' => 350,
                  'startTokenPos' => 1096,
                  'startFilePos' => 15650,
                  'endTokenPos' => 1102,
                  'endFilePos' => 15666,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 350,
                  'endLine' => 350,
                  'startTokenPos' => 1108,
                  'startFilePos' => 15678,
                  'endTokenPos' => 1108,
                  'endFilePos' => 15683,
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
 * @link https://php.net/manual/en/phar.copy.php
 * @param string $from
 * @param string $to
 * @return bool returns <b>TRUE</b> on success, but it is safer to encase method call in a
 * try/catch block and assume success if no exception is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 350,
        'endLine' => 359,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 372,
                'endLine' => 372,
                'startTokenPos' => 1205,
                'startFilePos' => 16664,
                'endTokenPos' => 1205,
                'endFilePos' => 16675,
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
                      'startLine' => 371,
                      'endLine' => 371,
                      'startTokenPos' => 1195,
                      'startFilePos' => 16632,
                      'endTokenPos' => 1195,
                      'endFilePos' => 16636,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 371,
            'endLine' => 372,
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
 * @link https://php.net/manual/en/phar.count.php
 * @param int $mode [optional]
 * @return int<0,max> The number of files contained within this phar, or 0 (the number zero)
 * if none.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 369,
        'endLine' => 375,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 390,
                      'endLine' => 390,
                      'startTokenPos' => 1251,
                      'startFilePos' => 17464,
                      'endTokenPos' => 1257,
                      'endFilePos' => 17482,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 390,
                      'endLine' => 390,
                      'startTokenPos' => 1263,
                      'startFilePos' => 17494,
                      'endTokenPos' => 1263,
                      'endFilePos' => 17495,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 390,
            'endLine' => 391,
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
                  'startLine' => 387,
                  'endLine' => 387,
                  'startTokenPos' => 1221,
                  'startFilePos' => 17275,
                  'endTokenPos' => 1227,
                  'endFilePos' => 17291,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 387,
                  'endLine' => 387,
                  'startTokenPos' => 1233,
                  'startFilePos' => 17303,
                  'endTokenPos' => 1233,
                  'endFilePos' => 17308,
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
 * @link https://php.net/manual/en/phar.delete.php
 * @param string $localName <p>
 * Path within an archive to the file to delete.
 * </p>
 * @return bool returns <b>TRUE</b> on success, but it is better to check for thrown exception,
 * and assume success if none is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 387,
        'endLine' => 394,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                  'startLine' => 403,
                  'endLine' => 403,
                  'startTokenPos' => 1282,
                  'startFilePos' => 18018,
                  'endTokenPos' => 1288,
                  'endFilePos' => 18034,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 403,
                  'endLine' => 403,
                  'startTokenPos' => 1294,
                  'startFilePos' => 18046,
                  'endTokenPos' => 1294,
                  'endFilePos' => 18051,
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
 * @link https://php.net/manual/en/phar.delmetadata.php
 * @return bool returns <b>TRUE</b> on success, but it is better to check for thrown exception,
 * and assume success if none is thrown.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 403,
        'endLine' => 407,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 427,
                      'endLine' => 427,
                      'startTokenPos' => 1330,
                      'startFilePos' => 19118,
                      'endTokenPos' => 1336,
                      'endFilePos' => 19136,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 427,
                      'endLine' => 427,
                      'startTokenPos' => 1342,
                      'startFilePos' => 19148,
                      'endTokenPos' => 1342,
                      'endFilePos' => 19149,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 427,
            'endLine' => 428,
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
                'startLine' => 430,
                'endLine' => 430,
                'startTokenPos' => 1380,
                'startFilePos' => 19335,
                'endTokenPos' => 1380,
                'endFilePos' => 19338,
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
                      'startLine' => 429,
                      'endLine' => 429,
                      'startTokenPos' => 1354,
                      'startFilePos' => 19250,
                      'endTokenPos' => 1360,
                      'endFilePos' => 19279,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 429,
                      'endLine' => 429,
                      'startTokenPos' => 1366,
                      'startFilePos' => 19291,
                      'endTokenPos' => 1366,
                      'endFilePos' => 19292,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 429,
            'endLine' => 430,
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
                'startLine' => 432,
                'endLine' => 432,
                'startTokenPos' => 1408,
                'startFilePos' => 19470,
                'endTokenPos' => 1408,
                'endFilePos' => 19474,
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
                      'startLine' => 431,
                      'endLine' => 431,
                      'startTokenPos' => 1386,
                      'startFilePos' => 19407,
                      'endTokenPos' => 1392,
                      'endFilePos' => 19423,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 431,
                      'endLine' => 431,
                      'startTokenPos' => 1398,
                      'startFilePos' => 19435,
                      'endTokenPos' => 1398,
                      'endFilePos' => 19436,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 431,
            'endLine' => 432,
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
 * @link https://php.net/manual/en/phar.extractto.php
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
        'startLine' => 425,
        'endLine' => 435,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 441,
        'endLine' => 444,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 458,
                'endLine' => 458,
                'startTokenPos' => 1470,
                'startFilePos' => 20576,
                'endTokenPos' => 1471,
                'endFilePos' => 20577,
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
                      'startLine' => 457,
                      'endLine' => 457,
                      'startTokenPos' => 1460,
                      'startFilePos' => 20528,
                      'endTokenPos' => 1460,
                      'endFilePos' => 20532,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 457,
            'endLine' => 458,
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
        'startLine' => 455,
        'endLine' => 461,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 469,
        'endLine' => 472,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                  'startLine' => 487,
                  'endLine' => 487,
                  'startTokenPos' => 1508,
                  'startFilePos' => 21849,
                  'endTokenPos' => 1521,
                  'endFilePos' => 21893,
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
        'startLine' => 487,
        'endLine' => 491,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 500,
        'endLine' => 503,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 515,
        'endLine' => 518,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 526,
        'endLine' => 529,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 537,
        'endLine' => 540,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 548,
        'endLine' => 551,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 565,
                      'endLine' => 565,
                      'startTokenPos' => 1669,
                      'startFilePos' => 25413,
                      'endTokenPos' => 1675,
                      'endFilePos' => 25428,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 565,
                      'endLine' => 565,
                      'startTokenPos' => 1681,
                      'startFilePos' => 25440,
                      'endTokenPos' => 1681,
                      'endFilePos' => 25441,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 565,
            'endLine' => 566,
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
        'startLine' => 563,
        'endLine' => 569,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 577,
        'endLine' => 580,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 592,
            'endLine' => 592,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * determines whether a file exists in the phar
 * @link https://php.net/manual/en/phar.offsetexists.php
 * @param string $localName <p>
 * The filename (relative path) to look for in a Phar.
 * </p>
 * @return bool <b>TRUE</b> if the file exists within the phar, or <b>FALSE</b> if not.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 591,
        'endLine' => 594,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 607,
            'endLine' => 607,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * Gets a <b>PharFileInfo</b> object for a specific file
 * @link https://php.net/manual/en/phar.offsetget.php
 * @param string $localName <p>
 * The filename (relative path) to look for in a Phar.
 * </p>
 * @return PharFileInfo A <b>PharFileInfo</b> object is returned that can be used to
 * iterate over a file\'s contents or to retrieve information about the current file.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 606,
        'endLine' => 609,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 624,
            'endLine' => 624,
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
            'startLine' => 624,
            'endLine' => 624,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * set the contents of an internal file to those of an external file
 * @link https://php.net/manual/en/phar.offsetset.php
 * @param string $localName <p>
 * The filename (relative path) to modify in a Phar.
 * </p>
 * @param string $value <p>
 * Content of the file.
 * </p>
 * @return void No return values.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 623,
        'endLine' => 626,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 638,
            'endLine' => 638,
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
 * (PHP &gt;= 5.3.0, PECL phar &gt;= 1.0.0)<br/>
 * remove a file from a phar
 * @link https://php.net/manual/en/phar.offsetunset.php
 * @param string $localName <p>
 * The filename (relative path) to modify in a Phar.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 637,
        'endLine' => 640,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 655,
                      'endLine' => 655,
                      'startTokenPos' => 1845,
                      'startFilePos' => 29022,
                      'endTokenPos' => 1851,
                      'endFilePos' => 29040,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 655,
                      'endLine' => 655,
                      'startTokenPos' => 1857,
                      'startFilePos' => 29052,
                      'endTokenPos' => 1857,
                      'endFilePos' => 29053,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 655,
            'endLine' => 656,
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
                  'startLine' => 653,
                  'endLine' => 653,
                  'startTokenPos' => 1819,
                  'startFilePos' => 28885,
                  'endTokenPos' => 1825,
                  'endFilePos' => 28901,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 653,
                  'endLine' => 653,
                  'startTokenPos' => 1831,
                  'startFilePos' => 28913,
                  'endTokenPos' => 1831,
                  'endFilePos' => 28918,
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
        'startLine' => 652,
        'endLine' => 659,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 677,
                'endLine' => 677,
                'startTokenPos' => 1930,
                'startFilePos' => 30088,
                'endTokenPos' => 1930,
                'endFilePos' => 30091,
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
                      'startLine' => 676,
                      'endLine' => 676,
                      'startTokenPos' => 1906,
                      'startFilePos' => 30015,
                      'endTokenPos' => 1912,
                      'endFilePos' => 30038,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 676,
                      'endLine' => 676,
                      'startTokenPos' => 1918,
                      'startFilePos' => 30050,
                      'endTokenPos' => 1918,
                      'endFilePos' => 30051,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 676,
            'endLine' => 677,
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
                'startLine' => 679,
                'endLine' => 679,
                'startTokenPos' => 1960,
                'startFilePos' => 30236,
                'endTokenPos' => 1960,
                'endFilePos' => 30239,
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
                      'startLine' => 678,
                      'endLine' => 678,
                      'startTokenPos' => 1936,
                      'startFilePos' => 30160,
                      'endTokenPos' => 1942,
                      'endFilePos' => 30183,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 678,
                      'endLine' => 678,
                      'startTokenPos' => 1948,
                      'startFilePos' => 30195,
                      'endTokenPos' => 1948,
                      'endFilePos' => 30196,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 678,
            'endLine' => 679,
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
                  'startLine' => 674,
                  'endLine' => 674,
                  'startTokenPos' => 1880,
                  'startFilePos' => 29872,
                  'endTokenPos' => 1886,
                  'endFilePos' => 29888,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 674,
                  'endLine' => 674,
                  'startTokenPos' => 1892,
                  'startFilePos' => 29900,
                  'endTokenPos' => 1892,
                  'endFilePos' => 29905,
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
        'startLine' => 673,
        'endLine' => 682,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 695,
                      'endLine' => 695,
                      'startTokenPos' => 1984,
                      'startFilePos' => 30852,
                      'endTokenPos' => 1990,
                      'endFilePos' => 30869,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 695,
                      'endLine' => 695,
                      'startTokenPos' => 1996,
                      'startFilePos' => 30881,
                      'endTokenPos' => 1996,
                      'endFilePos' => 30882,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 695,
            'endLine' => 696,
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
        'startLine' => 693,
        'endLine' => 699,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 726,
                      'endLine' => 726,
                      'startTokenPos' => 2029,
                      'startFilePos' => 32127,
                      'endTokenPos' => 2035,
                      'endFilePos' => 32142,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 726,
                      'endLine' => 726,
                      'startTokenPos' => 2041,
                      'startFilePos' => 32154,
                      'endTokenPos' => 2041,
                      'endFilePos' => 32155,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 726,
            'endLine' => 727,
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
                'startLine' => 729,
                'endLine' => 729,
                'startTokenPos' => 2077,
                'startFilePos' => 32326,
                'endTokenPos' => 2077,
                'endFilePos' => 32329,
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
                      'startLine' => 728,
                      'endLine' => 728,
                      'startTokenPos' => 2053,
                      'startFilePos' => 32248,
                      'endTokenPos' => 2059,
                      'endFilePos' => 32271,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 728,
                      'endLine' => 728,
                      'startTokenPos' => 2065,
                      'startFilePos' => 32283,
                      'endTokenPos' => 2065,
                      'endFilePos' => 32284,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 728,
            'endLine' => 729,
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
        'startLine' => 724,
        'endLine' => 732,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 749,
            'endLine' => 749,
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
                      'startLine' => 750,
                      'endLine' => 750,
                      'startTokenPos' => 2126,
                      'startFilePos' => 33197,
                      'endTokenPos' => 2132,
                      'endFilePos' => 33212,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 750,
                      'endLine' => 750,
                      'startTokenPos' => 2138,
                      'startFilePos' => 33224,
                      'endTokenPos' => 2138,
                      'endFilePos' => 33225,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 750,
            'endLine' => 751,
            'startColumn' => 13,
            'endColumn' => 23,
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
                  'startLine' => 746,
                  'endLine' => 746,
                  'startTokenPos' => 2093,
                  'startFilePos' => 32988,
                  'endTokenPos' => 2099,
                  'endFilePos' => 33004,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 746,
                  'endLine' => 746,
                  'startTokenPos' => 2105,
                  'startFilePos' => 33016,
                  'endTokenPos' => 2105,
                  'endFilePos' => 33021,
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
 * Used to set the PHP loader or bootstrap stub of a Phar archive
 * @link https://php.net/manual/en/phar.setstub.php
 * @param string $stub <p>
 * A string or an open stream handle to use as the executable stub for this
 * phar archive.
 * </p>
 * @param int $length [optional] <p>
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 746,
        'endLine' => 754,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 762,
        'endLine' => 765,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 773,
        'endLine' => 776,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 783,
        'endLine' => 785,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 797,
                'endLine' => 797,
                'startTokenPos' => 2233,
                'startFilePos' => 35157,
                'endTokenPos' => 2233,
                'endFilePos' => 35157,
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
            'startLine' => 797,
            'endLine' => 797,
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
        'startLine' => 797,
        'endLine' => 799,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 806,
        'endLine' => 808,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 819,
                'endLine' => 819,
                'startTokenPos' => 2283,
                'startFilePos' => 36128,
                'endTokenPos' => 2283,
                'endFilePos' => 36131,
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
            'startLine' => 819,
            'endLine' => 819,
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
                'startLine' => 819,
                'endLine' => 819,
                'startTokenPos' => 2293,
                'startFilePos' => 36154,
                'endTokenPos' => 2293,
                'endFilePos' => 36157,
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
            'startLine' => 819,
            'endLine' => 819,
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
        'startLine' => 819,
        'endLine' => 821,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 831,
        'endLine' => 833,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 841,
        'endLine' => 843,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 850,
        'endLine' => 852,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 866,
            'endLine' => 866,
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
                'startLine' => 866,
                'endLine' => 866,
                'startTokenPos' => 2389,
                'startFilePos' => 38199,
                'endTokenPos' => 2389,
                'endFilePos' => 38202,
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
            'startLine' => 866,
            'endLine' => 866,
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
        'startLine' => 866,
        'endLine' => 868,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 884,
            'endLine' => 884,
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
                'startLine' => 884,
                'endLine' => 884,
                'startTokenPos' => 2423,
                'startFilePos' => 39030,
                'endTokenPos' => 2423,
                'endFilePos' => 39033,
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
            'startLine' => 884,
            'endLine' => 884,
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
        'startLine' => 884,
        'endLine' => 886,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 900,
                'endLine' => 900,
                'startTokenPos' => 2452,
                'startFilePos' => 39744,
                'endTokenPos' => 2452,
                'endFilePos' => 39747,
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
            'startLine' => 900,
            'endLine' => 900,
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
                'startLine' => 900,
                'endLine' => 900,
                'startTokenPos' => 2461,
                'startFilePos' => 39764,
                'endTokenPos' => 2461,
                'endFilePos' => 39764,
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
            'startLine' => 900,
            'endLine' => 900,
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
        'startLine' => 900,
        'endLine' => 902,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 915,
                'endLine' => 915,
                'startTokenPos' => 2500,
                'startFilePos' => 40463,
                'endTokenPos' => 2500,
                'endFilePos' => 40466,
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
                      'startLine' => 914,
                      'endLine' => 914,
                      'startTokenPos' => 2490,
                      'startFilePos' => 40424,
                      'endTokenPos' => 2490,
                      'endFilePos' => 40428,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 914,
            'endLine' => 915,
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
        'startLine' => 913,
        'endLine' => 918,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 932,
            'endLine' => 932,
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
            'startLine' => 932,
            'endLine' => 932,
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
        'startLine' => 932,
        'endLine' => 934,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 948,
            'endLine' => 948,
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
        'startLine' => 948,
        'endLine' => 950,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
            'startLine' => 962,
            'endLine' => 962,
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
                  'startLine' => 961,
                  'endLine' => 961,
                  'startTokenPos' => 2569,
                  'startFilePos' => 42426,
                  'endTokenPos' => 2575,
                  'endFilePos' => 42442,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 961,
                  'endLine' => 961,
                  'startTokenPos' => 2581,
                  'startFilePos' => 42454,
                  'endTokenPos' => 2581,
                  'endFilePos' => 42459,
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
        'startLine' => 961,
        'endLine' => 964,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 1044,
                'endLine' => 1044,
                'startTokenPos' => 2627,
                'startFilePos' => 45908,
                'endTokenPos' => 2627,
                'endFilePos' => 45911,
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
            'startLine' => 1044,
            'endLine' => 1044,
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
                'startLine' => 1045,
                'endLine' => 1045,
                'startTokenPos' => 2637,
                'startFilePos' => 45943,
                'endTokenPos' => 2637,
                'endFilePos' => 45946,
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
            'startLine' => 1045,
            'endLine' => 1045,
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
                'startLine' => 1047,
                'endLine' => 1047,
                'startTokenPos' => 2667,
                'startFilePos' => 46107,
                'endTokenPos' => 2667,
                'endFilePos' => 46110,
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
                      'startLine' => 1046,
                      'endLine' => 1046,
                      'startTokenPos' => 2643,
                      'startFilePos' => 46015,
                      'endTokenPos' => 2649,
                      'endFilePos' => 46038,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'string\'',
                    'attributes' => 
                    array (
                      'startLine' => 1046,
                      'endLine' => 1046,
                      'startTokenPos' => 2655,
                      'startFilePos' => 46050,
                      'endTokenPos' => 2655,
                      'endFilePos' => 46057,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1046,
            'endLine' => 1047,
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
                'startLine' => 1048,
                'endLine' => 1048,
                'startTokenPos' => 2676,
                'startFilePos' => 46144,
                'endTokenPos' => 2677,
                'endFilePos' => 46145,
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
            'startLine' => 1048,
            'endLine' => 1048,
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
                'startLine' => 1049,
                'endLine' => 1049,
                'startTokenPos' => 2687,
                'startFilePos' => 46181,
                'endTokenPos' => 2687,
                'endFilePos' => 46184,
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
            'startLine' => 1049,
            'endLine' => 1049,
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
        'startLine' => 1043,
        'endLine' => 1052,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 49,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                'startLine' => 1064,
                'endLine' => 1064,
                'startTokenPos' => 2736,
                'startFilePos' => 46829,
                'endTokenPos' => 2736,
                'endFilePos' => 46833,
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
                      'startLine' => 1063,
                      'endLine' => 1063,
                      'startTokenPos' => 2714,
                      'startFilePos' => 46765,
                      'endTokenPos' => 2720,
                      'endFilePos' => 46781,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1063,
                      'endLine' => 1063,
                      'startTokenPos' => 2726,
                      'startFilePos' => 46793,
                      'endTokenPos' => 2726,
                      'endFilePos' => 46794,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1063,
            'endLine' => 1064,
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
        'startLine' => 1061,
        'endLine' => 1067,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 1074,
        'endLine' => 1077,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 1084,
        'endLine' => 1087,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 1094,
        'endLine' => 1097,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 1105,
        'endLine' => 1108,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 1116,
        'endLine' => 1119,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
        'startLine' => 1126,
        'endLine' => 1129,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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
                      'startLine' => 1141,
                      'endLine' => 1141,
                      'startTokenPos' => 2893,
                      'startFilePos' => 49825,
                      'endTokenPos' => 2899,
                      'endFilePos' => 49840,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1141,
                      'endLine' => 1141,
                      'startTokenPos' => 2905,
                      'startFilePos' => 49852,
                      'endTokenPos' => 2905,
                      'endFilePos' => 49853,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1141,
            'endLine' => 1142,
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
        'startLine' => 1139,
        'endLine' => 1145,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'Phar',
        'implementingClassName' => 'Phar',
        'currentClassName' => 'Phar',
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