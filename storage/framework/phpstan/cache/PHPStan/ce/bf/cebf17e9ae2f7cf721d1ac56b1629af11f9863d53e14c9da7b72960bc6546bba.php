<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../symfony/uid/AbstractUid.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Symfony\Component\Uid\AbstractUid
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-aa10b5ea5f48246c88d513e5f1c384037df5037520a7b3872aab32d259bb8a00-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Symfony\\Component\\Uid\\AbstractUid',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../symfony/uid/AbstractUid.php',
      ),
    ),
    'namespace' => 'Symfony\\Component\\Uid',
    'name' => 'Symfony\\Component\\Uid\\AbstractUid',
    'shortName' => 'AbstractUid',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => '/**
 * @author Nicolas Grekas <p@tchwork.com>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 212,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'JsonSerializable',
      1 => 'Stringable',
      2 => 'Symfony\\Component\\Uid\\HashableInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'uid' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'name' => 'uid',
        'modifiers' => 2,
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
        'docComment' => '/**
 * The identifier in its canonic representation.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 26,
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
      'isValid' => 
      array (
        'name' => 'isValid',
        'parameters' => 
        array (
          'uid' => 
          array (
            'name' => 'uid',
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
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 45,
            'endColumn' => 55,
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
        'docComment' => '/**
 * Whether the passed value is valid for the constructor of the current class.
 */',
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 63,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 81,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'fromString' => 
      array (
        'name' => 'fromString',
        'parameters' => 
        array (
          'uid' => 
          array (
            'name' => 'uid',
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
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 48,
            'endColumn' => 58,
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
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates an AbstractUid from an identifier represented in any of the supported formats.
 *
 * @throws InvalidArgumentException When the passed value is not valid
 */',
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 68,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 81,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'fromBinary' => 
      array (
        'name' => 'fromBinary',
        'parameters' => 
        array (
          'uid' => 
          array (
            'name' => 'uid',
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
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 39,
            'endColumn' => 49,
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
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws InvalidArgumentException When the passed value is not valid
 */',
        'startLine' => 41,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'fromBase58' => 
      array (
        'name' => 'fromBase58',
        'parameters' => 
        array (
          'uid' => 
          array (
            'name' => 'uid',
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
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 39,
            'endColumn' => 49,
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
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws InvalidArgumentException When the passed value is not valid
 */',
        'startLine' => 53,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'fromBase32' => 
      array (
        'name' => 'fromBase32',
        'parameters' => 
        array (
          'uid' => 
          array (
            'name' => 'uid',
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 39,
            'endColumn' => 49,
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
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws InvalidArgumentException When the passed value is not valid
 */',
        'startLine' => 65,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'fromRfc4122' => 
      array (
        'name' => 'fromRfc4122',
        'parameters' => 
        array (
          'uid' => 
          array (
            'name' => 'uid',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 40,
            'endColumn' => 50,
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
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $uid A valid RFC 9562/4122 uid
 *
 * @throws InvalidArgumentException When the passed value is not valid
 */',
        'startLine' => 79,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'toBinary' => 
      array (
        'name' => 'toBinary',
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
 * Returns the identifier as a raw binary string.
 *
 * @return non-empty-string
 */',
        'startLine' => 93,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 65,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'toBase58' => 
      array (
        'name' => 'toBase58',
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
 * Returns the identifier as a base58 case-sensitive string.
 *
 * @example 2AifFTC3zXgZzK5fPrrprL (len=22)
 *
 * @return non-empty-string
 */',
        'startLine' => 102,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'toBase32' => 
      array (
        'name' => 'toBase32',
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
 * Returns the identifier as a base32 case-insensitive string.
 *
 * @see https://tools.ietf.org/html/rfc4648#section-6
 *
 * @example 09EJ0S614A9FXVG9C5537Q9ZE1 (len=26)
 *
 * @return non-empty-string
 */',
        'startLine' => 116,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'toRfc4122' => 
      array (
        'name' => 'toRfc4122',
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
 * Returns the identifier as a RFC 9562/4122 case-insensitive string.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9562/#section-4
 *
 * @example 09748193-048a-4bfb-b825-8528cf74fdc1 (len=36)
 *
 * @return non-empty-string
 */',
        'startLine' => 141,
        'endLine' => 150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'toHex' => 
      array (
        'name' => 'toHex',
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
 * Returns the identifier as a prefixed hexadecimal case insensitive string.
 *
 * @example 0x09748193048a4bfbb8258528cf74fdc1 (len=34)
 *
 * @return non-empty-string
 */',
        'startLine' => 159,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'equals' => 
      array (
        'name' => 'equals',
        'parameters' => 
        array (
          'other' => 
          array (
            'name' => 'other',
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
            ),
            'startLine' => 167,
            'endLine' => 167,
            'startColumn' => 28,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns whether the argument is an AbstractUid and contains the same value as the current instance.
 */',
        'startLine' => 167,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'hash' => 
      array (
        'name' => 'hash',
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
 * @return non-empty-string
 */',
        'startLine' => 179,
        'endLine' => 182,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'compare' => 
      array (
        'name' => 'compare',
        'parameters' => 
        array (
          'other' => 
          array (
            'name' => 'other',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'self',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 184,
            'endLine' => 184,
            'startColumn' => 29,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 184,
        'endLine' => 187,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'toString' => 
      array (
        'name' => 'toString',
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
 * @return non-empty-string
 */',
        'startLine' => 192,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 33,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      '__toString' => 
      array (
        'name' => '__toString',
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
 * @return non-empty-string
 */',
        'startLine' => 200,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'aliasName' => NULL,
      ),
      'jsonSerialize' => 
      array (
        'name' => 'jsonSerialize',
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
 * @return non-empty-string
 */',
        'startLine' => 208,
        'endLine' => 211,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Uid',
        'declaringClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'implementingClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
        'currentClassName' => 'Symfony\\Component\\Uid\\AbstractUid',
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