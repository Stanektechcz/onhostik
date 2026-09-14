<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Crypt/EC/PrivateKey.php-PHPStan\BetterReflection\Reflection\ReflectionClass-phpseclib3\Crypt\EC\PrivateKey
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-94ddefa798fec9b0a66e425d8c30723e9c747bcae9cb4ef70363ba1b0f418dfe-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Crypt/EC/PrivateKey.php',
      ),
    ),
    'namespace' => 'phpseclib3\\Crypt\\EC',
    'name' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
    'shortName' => 'PrivateKey',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * EC Private Key
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 33,
    'endLine' => 362,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'phpseclib3\\Crypt\\EC',
    'implementsClassNames' => 
    array (
      0 => 'phpseclib3\\Crypt\\Common\\PrivateKey',
    ),
    'traitClassNames' => 
    array (
      0 => 'phpseclib3\\Crypt\\Common\\Traits\\PasswordProtected',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'dA' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'name' => 'dA',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Private Key dA
 *
 * sign() converts this to a BigInteger so one might wonder why this is a FiniteFieldInteger instead of
 * a BigInteger. That\'s because a FiniteFieldInteger, when converted to a byte string, is null padded by
 * a certain amount whereas a BigInteger isn\'t.
 *
 * @var object
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'secret' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'name' => 'secret',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 22,
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
      'multiply' => 
      array (
        'name' => 'multiply',
        'parameters' => 
        array (
          'coordinates' => 
          array (
            'name' => 'coordinates',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 30,
            'endColumn' => 41,
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
 * Multiplies an encoded point by the private key
 *
 * Used by ECDH
 *
 * @param string $coordinates
 * @return string
 */',
        'startLine' => 61,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt\\EC',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'currentClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'aliasName' => NULL,
      ),
      'sign' => 
      array (
        'name' => 'sign',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 26,
            'endColumn' => 33,
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
 * Create a signature
 *
 * @see self::verify()
 * @param string $message
 * @return mixed
 */',
        'startLine' => 115,
        'endLine' => 294,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt\\EC',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'currentClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'aliasName' => NULL,
      ),
      'toString' => 
      array (
        'name' => 'toString',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 303,
            'endLine' => 303,
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 303,
                'endLine' => 303,
                'startTokenPos' => 2199,
                'startFilePos' => 12962,
                'endTokenPos' => 2200,
                'endFilePos' => 12963,
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
            'startLine' => 303,
            'endLine' => 303,
            'startColumn' => 37,
            'endColumn' => 55,
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
 * Returns the private key
 *
 * @param string $type
 * @param array $options optional
 * @return string
 */',
        'startLine' => 303,
        'endLine' => 308,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt\\EC',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'currentClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'aliasName' => NULL,
      ),
      'getPublicKey' => 
      array (
        'name' => 'getPublicKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the public key
 *
 * @see self::getPrivateKey()
 * @return mixed
 */',
        'startLine' => 316,
        'endLine' => 337,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt\\EC',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'currentClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'aliasName' => NULL,
      ),
      'formatSignature' => 
      array (
        'name' => 'formatSignature',
        'parameters' => 
        array (
          'r' => 
          array (
            'name' => 'r',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'phpseclib3\\Math\\BigInteger',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 344,
            'endLine' => 344,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          's' => 
          array (
            'name' => 's',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'phpseclib3\\Math\\BigInteger',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 344,
            'endLine' => 344,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a signature in the appropriate format
 *
 * @return string
 */',
        'startLine' => 344,
        'endLine' => 361,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Crypt\\EC',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
        'currentClassName' => 'phpseclib3\\Crypt\\EC\\PrivateKey',
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