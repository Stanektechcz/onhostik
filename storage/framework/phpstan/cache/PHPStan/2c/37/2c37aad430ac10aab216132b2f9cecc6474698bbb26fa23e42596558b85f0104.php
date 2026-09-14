<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-openssl_pkey_get_details
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'openssl_pkey_get_details',
    'parameters' => 
    array (
      'key' => 
      array (
        'name' => 'key',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'OpenSSLAsymmetricKey',
            'isIdentifier' => false,
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
                'code' => '["8.0" => "OpenSSLAsymmetricKey"]',
                'attributes' => 
                array (
                  'startLine' => 25,
                  'endLine' => 25,
                  'startTokenPos' => 71,
                  'startFilePos' => 1086,
                  'endTokenPos' => 77,
                  'endFilePos' => 1118,
                ),
              ),
              'default' => 
              array (
                'code' => '"resource"',
                'attributes' => 
                array (
                  'startLine' => 25,
                  'endLine' => 25,
                  'startTokenPos' => 83,
                  'startFilePos' => 1130,
                  'endTokenPos' => 83,
                  'endFilePos' => 1139,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 25,
        'endLine' => 26,
        'startColumn' => 9,
        'endColumn' => 33,
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
            'code' => '["bits" => "int", "key" => "string", "rsa" => "array", "dsa" => "array", "dh" => "array", "ec" => "array", "type" => "int"]',
            'attributes' => 
            array (
              'startLine' => 23,
              'endLine' => 23,
              'startTokenPos' => 11,
              'startFilePos' => 859,
              'endTokenPos' => 59,
              'endFilePos' => 981,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Returns an array with the key details
 * @link https://php.net/manual/en/function.openssl-pkey-get-details.php
 * @param OpenSSLAsymmetricKey|resource $key <p>
 * Resource holding the key.
 * </p>
 * @return array|false an array with the key details in success or false in failure.
 * Returned array has indexes bits (number of bits),
 * key (string representation of the public key) and
 * type (type of the key which is one of
 * <b>OPENSSL_KEYTYPE_RSA</b>,
 * <b>OPENSSL_KEYTYPE_DSA</b>,
 * <b>OPENSSL_KEYTYPE_DH</b>,
 * <b>OPENSSL_KEYTYPE_EC</b> or -1 meaning unknown).
 * </p>
 * <p>
 * Depending on the key type used, additional details may be returned. Note that
 * some elements may not always be available.
 */',
    'startLine' => 23,
    'endLine' => 29,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => false,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'openssl_pkey_get_details',
        'filename' => 'phpstorm-stubs:openssl/openssl.stub',
        'extensionName' => 'openssl',
        'aliasName' => NULL,
      ),
    ),
  ),
));