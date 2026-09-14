<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Files\VirusScanner.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Files\VirusScanner
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-c3adfcd4464fe8201bc99cbe1c419f7377107b2cfb6057addb5216f0de3d7fbd',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Files\\VirusScanner',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Files/VirusScanner.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Files',
    'name' => 'Onhost\\Platform\\Files\\VirusScanner',
    'shortName' => 'VirusScanner',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Virus scan of uploaded files (audit §5r-4): clamd\'s INSTREAM protocol over TCP (`ONHOST_CLAMAV_HOST`,
 * `ONHOST_CLAMAV_PORT`) — the file goes in 64 kB chunks, clamd answers `stream: OK` or `stream: <signature> FOUND`.
 * An infected file never reaches the customer or a server; while clamd is unreachable the file is kept as
 * `unavailable` and, with `ONHOST_CLAMAV_ENFORCE`, cannot be downloaded until the retry pass (`onhost:files:scan`)
 * finds it clean. Without a host the scanner is off and every file counts as unscanned-but-allowed.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 169,
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
      'CLEAN' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'name' => 'CLEAN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'clean\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 36,
            'startFilePos' => 686,
            'endTokenPos' => 36,
            'endFilePos' => 692,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'INFECTED' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'name' => 'INFECTED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'infected\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 47,
            'startFilePos' => 724,
            'endTokenPos' => 47,
            'endFilePos' => 733,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'UNAVAILABLE' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'name' => 'UNAVAILABLE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'unavailable\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 58,
            'startFilePos' => 768,
            'endTokenPos' => 58,
            'endFilePos' => 780,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 45,
      ),
      'OFF' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'name' => 'OFF',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'off\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 69,
            'startFilePos' => 807,
            'endTokenPos' => 69,
            'endFilePos' => 811,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'CHUNK' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'name' => 'CHUNK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '65536',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 80,
            'startFilePos' => 840,
            'endTokenPos' => 80,
            'endFilePos' => 844,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'enabled' => 
      array (
        'name' => 'enabled',
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
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'enforced' => 
      array (
        'name' => 'enforced',
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
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'allows' => 
      array (
        'name' => 'allows',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
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
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 28,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Whether a file with this scan result may be handed out. */',
        'startLine' => 39,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'version' => 
      array (
        'name' => 'version',
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
        'docComment' => '/**
 * clamd\'s `VERSION` (audit §5t-6): engine, signature database number and its date; null when unreachable.
 *
 * @return array{engine:string, database:?int, signatures_at:?string}|null
 */',
        'startLine' => 53,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'command' => 
      array (
        'name' => 'command',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 32,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** One short clamd command and its reply line. */',
        'startLine' => 70,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'scanPath' => 
      array (
        'name' => 'scanPath',
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
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 30,
            'endColumn' => 41,
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
 * Scans a file on the store\'s disk.
 *
 * @return array{result:string, signature:?string, at:string}
 */',
        'startLine' => 92,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'scanStream' => 
      array (
        'name' => 'scanStream',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 116,
            'endLine' => 116,
            'startColumn' => 32,
            'endColumn' => 38,
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
 * @param  resource  $stream
 * @return array{result:string, signature:?string, at:string}
 */',
        'startLine' => 116,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'instream' => 
      array (
        'name' => 'instream',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 33,
            'endColumn' => 39,
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
 * The clamd conversation: `zINSTREAM\\0`, length-prefixed chunks, a zero-length terminator, one reply line.
 *
 * @param  resource  $stream
 */',
        'startLine' => 138,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'aliasName' => NULL,
      ),
      'outcome' => 
      array (
        'name' => 'outcome',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
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
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 37,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'signature' => 
          array (
            'name' => 'signature',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 1130,
                'startFilePos' => 5434,
                'endTokenPos' => 1130,
                'endFilePos' => 5437,
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
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 53,
            'endColumn' => 77,
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
        'docComment' => '/** @return array{result:string, signature:?string, at:string} */',
        'startLine' => 165,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'implementingClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
        'currentClassName' => 'Onhost\\Platform\\Files\\VirusScanner',
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