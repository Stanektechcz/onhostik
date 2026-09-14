<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Errors\ProviderErrorCode.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Errors\ProviderErrorCode
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-201e5feb14e59c1c57dde5485d10dbb77fb9fc24a35ac2a1cfb23f630c48d1a9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Errors/ProviderErrorCode.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Errors',
    'name' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
    'shortName' => 'ProviderErrorCode',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => true,
    'isBackedEnum' => true,
    'modifiers' => 0,
    'docComment' => '/**
 * Normalised provider error taxonomy (blueprint §6.1). Vendor codes never leak to
 * customers; adapters map every failure into exactly one of these classes and
 * the operation runner decides retry/compensation from the class, not the vendor.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 38,
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
    ),
    'immediateProperties' => 
    array (
      'name' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'name' => 'name',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'value' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'name' => 'value',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
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
      'isRetryable' => 
      array (
        'name' => 'isRetryable',
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
        'startLine' => 25,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Errors',
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'currentClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'aliasName' => NULL,
      ),
      'opensIncident' => 
      array (
        'name' => 'opensIncident',
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
        'docComment' => '/** Authentication failures are never retried: they open an incident instead (§docs-provider-apis §0.7). */',
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Errors',
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'currentClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'aliasName' => NULL,
      ),
      'cases' => 
      array (
        'name' => 'cases',
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
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Platform\\Errors',
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'currentClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'aliasName' => NULL,
      ),
      'from' => 
      array (
        'name' => 'from',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
                      'name' => 'int',
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
            'startLine' => NULL,
            'endLine' => NULL,
            'startColumn' => -1,
            'endColumn' => -1,
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
            'name' => 'static',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Platform\\Errors',
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'currentClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'aliasName' => NULL,
      ),
      'tryFrom' => 
      array (
        'name' => 'tryFrom',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
                      'name' => 'int',
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
            'startLine' => NULL,
            'endLine' => NULL,
            'startColumn' => -1,
            'endColumn' => -1,
            'parameterIndex' => 0,
            'isOptional' => false,
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
                  'name' => 'static',
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
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Platform\\Errors',
        'declaringClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'implementingClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
        'currentClassName' => 'Onhost\\Platform\\Errors\\ProviderErrorCode',
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
    'backingType' => 
    array (
      'name' => 'string',
      'isIdentifier' => true,
    ),
    'cases' => 
    array (
      'AUTH' => 
      array (
        'name' => 'AUTH',
        'value' => 
        array (
          'code' => '\'AUTH\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 32,
            'startFilePos' => 371,
            'endTokenPos' => 32,
            'endFilePos' => 376,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 23,
      ),
      'CAPACITY' => 
      array (
        'name' => 'CAPACITY',
        'value' => 
        array (
          'code' => '\'CAPACITY\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 41,
            'startFilePos' => 399,
            'endTokenPos' => 41,
            'endFilePos' => 408,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'VALIDATION' => 
      array (
        'name' => 'VALIDATION',
        'value' => 
        array (
          'code' => '\'VALIDATION\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 50,
            'startFilePos' => 433,
            'endTokenPos' => 50,
            'endFilePos' => 444,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'RATE_LIMIT' => 
      array (
        'name' => 'RATE_LIMIT',
        'value' => 
        array (
          'code' => '\'RATE_LIMIT\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 59,
            'startFilePos' => 469,
            'endTokenPos' => 59,
            'endFilePos' => 480,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'TRANSIENT' => 
      array (
        'name' => 'TRANSIENT',
        'value' => 
        array (
          'code' => '\'TRANSIENT\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 68,
            'startFilePos' => 504,
            'endTokenPos' => 68,
            'endFilePos' => 514,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'PROVIDER_BUG' => 
      array (
        'name' => 'PROVIDER_BUG',
        'value' => 
        array (
          'code' => '\'PROVIDER_BUG\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 77,
            'startFilePos' => 541,
            'endTokenPos' => 77,
            'endFilePos' => 554,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'NOT_FOUND' => 
      array (
        'name' => 'NOT_FOUND',
        'value' => 
        array (
          'code' => '\'NOT_FOUND\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 86,
            'startFilePos' => 578,
            'endTokenPos' => 86,
            'endFilePos' => 588,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'CONFLICT' => 
      array (
        'name' => 'CONFLICT',
        'value' => 
        array (
          'code' => '\'CONFLICT\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 95,
            'startFilePos' => 611,
            'endTokenPos' => 95,
            'endFilePos' => 620,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'CIRCUIT_OPEN' => 
      array (
        'name' => 'CIRCUIT_OPEN',
        'value' => 
        array (
          'code' => '\'CIRCUIT_OPEN\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 104,
            'startFilePos' => 647,
            'endTokenPos' => 104,
            'endFilePos' => 660,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'UNKNOWN' => 
      array (
        'name' => 'UNKNOWN',
        'value' => 
        array (
          'code' => '\'UNKNOWN\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 113,
            'startFilePos' => 682,
            'endTokenPos' => 113,
            'endFilePos' => 690,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
    ),
  ),
));