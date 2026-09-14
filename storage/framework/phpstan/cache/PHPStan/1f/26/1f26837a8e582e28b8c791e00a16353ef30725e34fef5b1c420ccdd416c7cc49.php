<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-openssl_pkey_get_private
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'openssl_pkey_get_private',
    'parameters' => 
    array (
      'private_key' => 
      array (
        'name' => 'private_key',
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
                  'name' => 'OpenSSLAsymmetricKey',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'OpenSSLCertificate',
                  'isIdentifier' => false,
                ),
              ),
              2 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'array',
                  'isIdentifier' => true,
                ),
              ),
              3 => 
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
                'code' => '[\'8.0\' => \'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string\']',
                'attributes' => 
                array (
                  'startLine' => 25,
                  'endLine' => 25,
                  'startTokenPos' => 35,
                  'startFilePos' => 1102,
                  'endTokenPos' => 41,
                  'endFilePos' => 1166,
                ),
              ),
              'default' => 
              array (
                'code' => '\'resource|array|string\'',
                'attributes' => 
                array (
                  'startLine' => 25,
                  'endLine' => 25,
                  'startTokenPos' => 47,
                  'startFilePos' => 1178,
                  'endTokenPos' => 47,
                  'endFilePos' => 1200,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 25,
        'endLine' => 26,
        'startColumn' => 9,
        'endColumn' => 73,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'passphrase' => 
      array (
        'name' => 'passphrase',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 69,
            'startFilePos' => 1309,
            'endTokenPos' => 69,
            'endFilePos' => 1312,
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
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 9,
        'endColumn' => 34,
        'parameterIndex' => 1,
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
              'name' => 'OpenSSLAsymmetricKey',
              'isIdentifier' => false,
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
        'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '["8.0" => "OpenSSLAsymmetricKey|false"]',
            'attributes' => 
            array (
              'startLine' => 23,
              'endLine' => 23,
              'startTokenPos' => 11,
              'startFilePos' => 932,
              'endTokenPos' => 17,
              'endFilePos' => 970,
            ),
          ),
          'default' => 
          array (
            'code' => '"resource|false"',
            'attributes' => 
            array (
              'startLine' => 23,
              'endLine' => 23,
              'startTokenPos' => 23,
              'startFilePos' => 982,
              'endTokenPos' => 23,
              'endFilePos' => 997,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Get a private key
 * @link https://php.net/manual/en/function.openssl-pkey-get-private.php
 * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string $private_key
 * <p>
 * <b><em>key</em></b> can be one of the following:
 * <ol>
 * <li>a string having the format
 * <var>file://path/to/file.pem</var>. The named file must
 * contain a PEM encoded certificate/private key (it may contain both).
 * </li>
 * <li>A PEM formatted private key.</li>
 * </ol></p>
 * @param string|null $passphrase <p>
 * The optional parameter <b><em>passphrase</em></b> must be used
 * if the specified key is encrypted (protected by a passphrase).
 * </p>
 * @return OpenSSLAsymmetricKey|resource|false Returns a positive key resource identifier on success, or <b>FALSE</b> on error.
 */',
    'startLine' => 23,
    'endLine' => 30,
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
        'name' => 'openssl_pkey_get_private',
        'filename' => 'phpstorm-stubs:openssl/openssl.stub',
        'extensionName' => 'openssl',
        'aliasName' => NULL,
      ),
    ),
  ),
));