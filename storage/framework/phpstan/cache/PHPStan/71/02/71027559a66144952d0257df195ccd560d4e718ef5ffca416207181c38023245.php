<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-openssl_get_cert_locations
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'openssl_get_cert_locations',
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
      0 => 
      array (
        'name' => 'JetBrains\\PhpStorm\\ArrayShape',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '[\'default_cert_file\' => \'string\', \'default_cert_file_env\' => \'string\', \'default_cert_dir\' => \'string\', \'default_cert_dir_env\' => \'string\', \'default_private_dir\' => \'string\', \'default_default_cert_area\' => \'string\', \'ini_cafile\' => \'string\', \'ini_capath\' => \'string\']',
            'attributes' => 
            array (
              'startLine' => 10,
              'endLine' => 10,
              'startTokenPos' => 11,
              'startFilePos' => 292,
              'endTokenPos' => 66,
              'endFilePos' => 557,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Retrieve the available certificate locations
 * @link https://php.net/manual/en/function.openssl-get-cert-locations.php
 * @return array an array with the available certificate locations
 * @since 5.6
 */',
    'startLine' => 10,
    'endLine' => 13,
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
        'name' => 'openssl_get_cert_locations',
        'filename' => 'phpstorm-stubs:openssl/openssl.stub',
        'extensionName' => 'openssl',
        'aliasName' => NULL,
      ),
    ),
  ),
));