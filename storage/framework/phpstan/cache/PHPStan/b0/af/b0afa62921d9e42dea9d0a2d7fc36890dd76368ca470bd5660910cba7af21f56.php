<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Crypt/PublicKeyLoader.php-PHPStan\BetterReflection\Reflection\ReflectionClass-phpseclib3\Crypt\PublicKeyLoader
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-cebe354686e80f58253b0c8e5d1529b98bf0e5a5165cdd7c6cd84be01f19290e-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Crypt/PublicKeyLoader.php',
      ),
    ),
    'namespace' => 'phpseclib3\\Crypt',
    'name' => 'phpseclib3\\Crypt\\PublicKeyLoader',
    'shortName' => 'PublicKeyLoader',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => '/**
 * PublicKeyLoader
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 27,
    'endLine' => 112,
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
    ),
    'immediateMethods' => 
    array (
      'load' => 
      array (
        'name' => 'load',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 37,
                'endLine' => 37,
                'startTokenPos' => 61,
                'startFilePos' => 896,
                'endTokenPos' => 61,
                'endFilePos' => 900,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 39,
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
 * Loads a public or private key
 *
 * @return AsymmetricKey
 * @param string|array $key
 * @param string $password optional
 * @throws NoKeyLoadedException if key is not valid
 */',
        'startLine' => 37,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'implementingClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'currentClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'aliasName' => NULL,
      ),
      'loadPrivateKey' => 
      array (
        'name' => 'loadPrivateKey',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 43,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 74,
                'endLine' => 74,
                'startTokenPos' => 251,
                'startFilePos' => 1768,
                'endTokenPos' => 251,
                'endFilePos' => 1772,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 49,
            'endColumn' => 65,
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
 * Loads a private key
 *
 * @return PrivateKey
 * @param string|array $key
 * @param string $password optional
 */',
        'startLine' => 74,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'implementingClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'currentClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'aliasName' => NULL,
      ),
      'loadPublicKey' => 
      array (
        'name' => 'loadPublicKey',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 42,
            'endColumn' => 45,
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
 * Loads a public key
 *
 * @return PublicKey
 * @param string|array $key
 */',
        'startLine' => 89,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'implementingClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'currentClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'aliasName' => NULL,
      ),
      'loadParameters' => 
      array (
        'name' => 'loadParameters',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 43,
            'endColumn' => 46,
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
 * Loads parameters
 *
 * @return AsymmetricKey
 * @param string|array $key
 */',
        'startLine' => 104,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Crypt',
        'declaringClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'implementingClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
        'currentClassName' => 'phpseclib3\\Crypt\\PublicKeyLoader',
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