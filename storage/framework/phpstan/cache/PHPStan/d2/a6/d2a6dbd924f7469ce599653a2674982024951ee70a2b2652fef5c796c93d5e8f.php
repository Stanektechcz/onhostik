<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-openssl_x509_parse
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'openssl_x509_parse',
    'parameters' => 
    array (
      'certificate' => 
      array (
        'name' => 'certificate',
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
                  'name' => 'OpenSSLCertificate',
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
                'code' => '["8.0" => "OpenSSLCertificate|string"]',
                'attributes' => 
                array (
                  'startLine' => 19,
                  'endLine' => 19,
                  'startTokenPos' => 141,
                  'startFilePos' => 1285,
                  'endTokenPos' => 147,
                  'endFilePos' => 1322,
                ),
              ),
              'default' => 
              array (
                'code' => '"resource|string"',
                'attributes' => 
                array (
                  'startLine' => 19,
                  'endLine' => 19,
                  'startTokenPos' => 153,
                  'startFilePos' => 1334,
                  'endTokenPos' => 153,
                  'endFilePos' => 1350,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 19,
        'endLine' => 20,
        'startColumn' => 9,
        'endColumn' => 46,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'short_names' => 
      array (
        'name' => 'short_names',
        'default' => 
        array (
          'code' => '\\true',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 180,
            'startFilePos' => 1513,
            'endTokenPos' => 180,
            'endFilePos' => 1516,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'7.1\'',
                'attributes' => 
                array (
                  'startLine' => 21,
                  'endLine' => 21,
                  'startTokenPos' => 170,
                  'startFilePos' => 1477,
                  'endTokenPos' => 170,
                  'endFilePos' => 1481,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 21,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 32,
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
            'code' => '[\'name\' => \'string\', \'subject\' => \'string\', \'hash\' => \'string\', \'issuer\' => \'string\', \'version\' => \'int\', \'serialNumber\' => \'string\', \'serialNumberHex\' => \'string\', \'validFrom\' => \'string\', \'validTo\' => \'string\', \'validFrom_time_t\' => \'int\', \'validTo_time_t\' => \'int\', \'alias\' => \'string\', \'signatureTypeSN\' => \'string\', \'signatureTypeLN\' => \'string\', \'signatureTypeNID\' => \'int\', \'purposes\' => \'array\', \'extensions\' => \'array\']',
            'attributes' => 
            array (
              'startLine' => 17,
              'endLine' => 17,
              'startTokenPos' => 11,
              'startFilePos' => 759,
              'endTokenPos' => 129,
              'endFilePos' => 1186,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Parse an X509 certificate and return the information as an array
 * @link https://php.net/manual/en/function.openssl-x509-parse.php
 * @param OpenSSLCertificate|string|resource $certificate
 * @param bool $short_names [optional] <p>
 * <i>shortnames</i> controls how the data is indexed in the
 * array - if <i>shortnames</i> is true (the default) then
 * fields will be indexed with the short name form, otherwise, the long name
 * form will be used - e.g.: CN is the shortname form of commonName.
 * </p>
 * @return array|false The structure of the returned data is (deliberately) not
 * yet documented, as it is still subject to change.
 */',
    'startLine' => 17,
    'endLine' => 25,
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
        'name' => 'openssl_x509_parse',
        'filename' => 'phpstorm-stubs:openssl/openssl.stub',
        'extensionName' => 'openssl',
        'aliasName' => NULL,
      ),
    ),
  ),
));