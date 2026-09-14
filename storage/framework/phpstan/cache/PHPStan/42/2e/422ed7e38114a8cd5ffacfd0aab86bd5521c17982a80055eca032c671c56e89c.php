<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-openssl_pkey_new
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'openssl_pkey_new',
    'parameters' => 
    array (
      'options' => 
      array (
        'name' => 'options',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 38,
            'startFilePos' => 701,
            'endTokenPos' => 38,
            'endFilePos' => 704,
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
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 31,
        'endColumn' => 52,
        'parameterIndex' => 0,
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
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 11,
              'startFilePos' => 584,
              'endTokenPos' => 17,
              'endFilePos' => 622,
            ),
          ),
          'default' => 
          array (
            'code' => '"resource|false"',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 23,
              'startFilePos' => 634,
              'endTokenPos' => 23,
              'endFilePos' => 649,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Generates a new private key
 * @link https://php.net/manual/en/function.openssl-pkey-new.php
 * @param array|null $options [optional] <p>
 * You can finetune the key generation (such as specifying the number of
 * bits) using <i>configargs</i>. See
 * <b>openssl_csr_new</b> for more information about
 * <i>configargs</i>.
 * </p>
 * @return OpenSSLAsymmetricKey|resource|false a resource identifier for the pkey on success, or false on
 * error.
 */',
    'startLine' => 16,
    'endLine' => 19,
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
        'name' => 'openssl_pkey_new',
        'filename' => 'phpstorm-stubs:openssl/openssl.stub',
        'extensionName' => 'openssl',
        'aliasName' => NULL,
      ),
    ),
  ),
));