<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Crypt/EC.php-PHPStan\BetterReflection\Reflection\ReflectionClass-phpseclib3\Crypt\EC
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-62be80e6e6e0e4ea82496fcf33d0c33674be6aa4f954f2645289ab7a94e0ba45-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'phpseclib3\\Crypt\\EC',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Crypt/EC.php',
      ),
    ),
    'namespace' => 'phpseclib3\\Crypt',
    'name' => 'phpseclib3\\Crypt\\EC',
    'shortName' => 'EC',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => '/**
 * Pure-PHP implementation of EC.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 57,
    'endLine' => 628,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'phpseclib3\\Crypt\\Common\\AsymmetricKey',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'ALGORITHM' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'ALGORITHM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'EC\'',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 134,
            'startFilePos' => 1814,
            'endTokenPos' => 134,
            'endFilePos' => 1817,
          ),
        ),
        'docComment' => '/**
 * Algorithm Name
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
    ),
    'immediateProperties' => 
    array (
      'QA' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'QA',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Public Key QA
 *
 * @var object[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'curve' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'curve',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Curve
 *
 * @var EC\\BaseCurves\\Base
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shortFormat' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'shortFormat',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Signature Format (Short)
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'curveName' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'curveName',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Curve Name
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'q' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'q',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Curve Order
 *
 * Used for deterministic ECDSA
 *
 * @var BigInteger
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 101,
        'endLine' => 101,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'x' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'x',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Alias for the private key
 *
 * Used for deterministic ECDSA. AsymmetricKey expects $x. I don\'t like x because
 * with x you have x * the base point yielding an (x, y)-coordinate that is the
 * public key. But the x is different depending on which side of the equal sign
 * you\'re on. It\'s less ambiguous if you do dA * base point = (x, y)-coordinate.
 *
 * @var BigInteger
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 113,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'context' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'context',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Context
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 120,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'sigFormat' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'sigFormat',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Signature Format
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 127,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'forcedEngine' => 
      array (
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'name' => 'forcedEngine',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 135,
            'endLine' => 135,
            'startTokenPos' => 203,
            'startFilePos' => 3065,
            'endTokenPos' => 203,
            'endFilePos' => 3068,
          ),
        ),
        'docComment' => '/**
 * Forced Engine
 *
 * @var ?string
 * @see parent::forceEngine()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 135,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 42,
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
      'createKey' => 
      array (
        'name' => 'createKey',
        'parameters' => 
        array (
          'curve' => 
          array (
            'name' => 'curve',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 143,
            'endLine' => 143,
            'startColumn' => 38,
            'endColumn' => 43,
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
 * Create public / private key pair.
 *
 * @param string $curve
 * @return PrivateKey
 */',
        'startLine' => 143,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getCurveCase' => 
      array (
        'name' => 'getCurveCase',
        'parameters' => 
        array (
          'curveName' => 
          array (
            'name' => 'curveName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 245,
            'endLine' => 245,
            'startColumn' => 42,
            'endColumn' => 51,
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
 * Returns the actual case of the curve
 *
 * Useful for initializing the curve class
 *
 * @param string $curveName
 * @return string
 */',
        'startLine' => 245,
        'endLine' => 255,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getOpenSSLCurveName' => 
      array (
        'name' => 'getOpenSSLCurveName',
        'parameters' => 
        array (
          'curve' => 
          array (
            'name' => 'curve',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 263,
            'endLine' => 263,
            'startColumn' => 49,
            'endColumn' => 54,
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
 * Return the OpenSSL name for a curve
 *
 * @param string $curve
 * @return string
 */',
        'startLine' => 263,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'generateWithEngine' => 
      array (
        'name' => 'generateWithEngine',
        'parameters' => 
        array (
          'engine' => 
          array (
            'name' => 'engine',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 281,
            'endLine' => 281,
            'startColumn' => 48,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'curve' => 
          array (
            'name' => 'curve',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 281,
            'endLine' => 281,
            'startColumn' => 57,
            'endColumn' => 62,
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
 * Generate the key for a given curve / engine combo
 *
 * @param string $engine
 * @param string $curve
 * @return ?PrivateKey
 */',
        'startLine' => 281,
        'endLine' => 359,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'onLoad' => 
      array (
        'name' => 'onLoad',
        'parameters' => 
        array (
          'components' => 
          array (
            'name' => 'components',
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
            'startLine' => 366,
            'endLine' => 366,
            'startColumn' => 38,
            'endColumn' => 54,
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
 * OnLoad Handler
 *
 * @return bool
 */',
        'startLine' => 366,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Constructor
 *
 * PublicKey and PrivateKey objects can only be created from abstract RSA class
 */',
        'startLine' => 397,
        'endLine' => 403,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getCurve' => 
      array (
        'name' => 'getCurve',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the curve
 *
 * Returns a string if it\'s a named curve, an array if not
 *
 * @return string|array
 */',
        'startLine' => 412,
        'endLine' => 442,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getLength' => 
      array (
        'name' => 'getLength',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the key size
 *
 * Quoting https://tools.ietf.org/html/rfc5656#section-2,
 *
 * "The size of a set of elliptic curve domain parameters on a prime
 *  curve is defined as the number of bits in the binary representation
 *  of the field order, commonly denoted by p.  Size on a
 *  characteristic-2 curve is defined as the number of bits in the binary
 *  representation of the field, commonly denoted by m.  A set of
 *  elliptic curve domain parameters defines a group of order n generated
 *  by a base point P"
 *
 * @return int
 */',
        'startLine' => 459,
        'endLine' => 462,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getEncodedCoordinates' => 
      array (
        'name' => 'getEncodedCoordinates',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the public key coordinates as a string
 *
 * Used by ECDH
 *
 * @return string
 */',
        'startLine' => 471,
        'endLine' => 480,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'convertPointToPublicKey' => 
      array (
        'name' => 'convertPointToPublicKey',
        'parameters' => 
        array (
          'curveName' => 
          array (
            'name' => 'curveName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 52,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'secret' => 
          array (
            'name' => 'secret',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 64,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'toPublicKey' => 
          array (
            'name' => 'toPublicKey',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 496,
                'endLine' => 496,
                'startTokenPos' => 2242,
                'startFilePos' => 15377,
                'endTokenPos' => 2242,
                'endFilePos' => 15380,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 73,
            'endColumn' => 91,
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
 * Convert point to public key
 *
 * For Weierstrass curves, if only the x coordinate is present (as is the case after doing a round of ECDH)
 * then we\'ll guess at the y coordinate. There are only two possible y values and, atleast in-so-far as
 * multiplication is concerned, neither value affects the resultant x value
 *
 * If $toPublicKey is set to false then a string will be returned - a kind of public key precursor
 *
 * @param string $curveName
 * @param string $secret
 * @param bool $toPublicKey optional
 * @return PublicKey|string
 */',
        'startLine' => 496,
        'endLine' => 522,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getParameters' => 
      array (
        'name' => 'getParameters',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'PKCS1\'',
              'attributes' => 
              array (
                'startLine' => 531,
                'endLine' => 531,
                'startTokenPos' => 2482,
                'startFilePos' => 16489,
                'endTokenPos' => 2482,
                'endFilePos' => 16495,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 531,
            'endLine' => 531,
            'startColumn' => 35,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the parameters
 *
 * @see self::getPublicKey()
 * @param string $type optional
 * @return mixed
 */',
        'startLine' => 531,
        'endLine' => 540,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'withSignatureFormat' => 
      array (
        'name' => 'withSignatureFormat',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 549,
            'endLine' => 549,
            'startColumn' => 41,
            'endColumn' => 47,
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
 * Determines the signature padding mode
 *
 * Valid values are: ASN1, IEEE, SSH2, Raw
 *
 * @param string $format
 */',
        'startLine' => 549,
        'endLine' => 559,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getSignatureFormat' => 
      array (
        'name' => 'getSignatureFormat',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the signature format currently being used
 *
 */',
        'startLine' => 565,
        'endLine' => 568,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'withContext' => 
      array (
        'name' => 'withContext',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 579,
                'endLine' => 579,
                'startTokenPos' => 2668,
                'startFilePos' => 17711,
                'endTokenPos' => 2668,
                'endFilePos' => 17714,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 579,
            'endLine' => 579,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the context
 *
 * Used by Ed25519 / Ed448.
 *
 * @see self::sign()
 * @see self::verify()
 * @param string $context optional
 */',
        'startLine' => 579,
        'endLine' => 598,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'getContext' => 
      array (
        'name' => 'getContext',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the signature format currently being used
 *
 */',
        'startLine' => 604,
        'endLine' => 607,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
        'aliasName' => NULL,
      ),
      'withHash' => 
      array (
        'name' => 'withHash',
        'parameters' => 
        array (
          'hash' => 
          array (
            'name' => 'hash',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 614,
            'endLine' => 614,
            'startColumn' => 30,
            'endColumn' => 34,
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
 * Determines which hashing function should be used
 *
 * @param string $hash
 */',
        'startLine' => 614,
        'endLine' => 627,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\EC',
        'implementingClassName' => 'phpseclib3\\Crypt\\EC',
        'currentClassName' => 'phpseclib3\\Crypt\\EC',
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