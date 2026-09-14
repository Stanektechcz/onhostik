<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-openssl_csr_export
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'openssl_csr_export',
    'parameters' => 
    array (
      'csr' => 
      array (
        'name' => 'csr',
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
                  'name' => 'OpenSSLCertificateSigningRequest',
                  'isIdentifier' => false,
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
                'code' => '["8.0" => "OpenSSLCertificateSigningRequest|string"]',
                'attributes' => 
                array (
                  'startLine' => 13,
                  'endLine' => 13,
                  'startTokenPos' => 16,
                  'startFilePos' => 428,
                  'endTokenPos' => 22,
                  'endFilePos' => 479,
                ),
              ),
              'default' => 
              array (
                'code' => '"resource|string"',
                'attributes' => 
                array (
                  'startLine' => 13,
                  'endLine' => 13,
                  'startTokenPos' => 28,
                  'startFilePos' => 491,
                  'endTokenPos' => 28,
                  'endFilePos' => 507,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 13,
        'endLine' => 14,
        'startColumn' => 9,
        'endColumn' => 52,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'output' => 
      array (
        'name' => 'output',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => true,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 9,
        'endColumn' => 16,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'no_text' => 
      array (
        'name' => 'no_text',
        'default' => 
        array (
          'code' => '\\true',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 49,
            'startFilePos' => 607,
            'endTokenPos' => 49,
            'endFilePos' => 610,
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
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 9,
        'endColumn' => 28,
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
    ),
    'docComment' => '/**
 * Exports a CSR as a string
 * @link https://php.net/manual/en/function.openssl-csr-export.php
 * @param OpenSSLCertificateSigningRequest|string|resource $csr
 * @param string &$output
 * @param bool $no_text [optional]
 * @return bool true on success or false on failure.
 */',
    'startLine' => 12,
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
        'name' => 'openssl_csr_export',
        'filename' => 'phpstorm-stubs:openssl/openssl.stub',
        'extensionName' => 'openssl',
        'aliasName' => NULL,
      ),
    ),
  ),
));