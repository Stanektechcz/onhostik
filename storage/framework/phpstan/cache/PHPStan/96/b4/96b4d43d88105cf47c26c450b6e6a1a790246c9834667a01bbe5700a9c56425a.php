<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Filesystem/FilesystemManager.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Filesystem\FilesystemManager
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-a70aa4a658f5729bc7bf9c70144fcab2ff22d7f64f54787e0051eec7845ecf07-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Filesystem\\FilesystemManager',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Filesystem/FilesystemManager.php',
      ),
    ),
    'namespace' => 'Illuminate\\Filesystem',
    'name' => 'Illuminate\\Filesystem\\FilesystemManager',
    'shortName' => 'FilesystemManager',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin \\Illuminate\\Contracts\\Filesystem\\Filesystem
 * @mixin \\Illuminate\\Filesystem\\FilesystemAdapter
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 34,
    'endLine' => 526,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Filesystem\\Factory',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\RebindsCallbacksToSelf',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'app' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'name' => 'app',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The application instance.
 *
 * @var \\Illuminate\\Contracts\\Foundation\\Application
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'disks' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'name' => 'disks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 180,
            'startFilePos' => 1534,
            'endTokenPos' => 181,
            'endFilePos' => 1535,
          ),
        ),
        'docComment' => '/**
 * The array of resolved filesystem drivers.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'customCreators' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'name' => 'customCreators',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 192,
            'startFilePos' => 1658,
            'endTokenPos' => 193,
            'endFilePos' => 1659,
          ),
        ),
        'docComment' => '/**
 * The registered custom driver creators.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 35,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 33,
            'endColumn' => 36,
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
 * Create a new filesystem manager instance.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 */',
        'startLine' => 64,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'drive' => 
      array (
        'name' => 'drive',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 75,
                'endLine' => 75,
                'startTokenPos' => 232,
                'startFilePos' => 2073,
                'endTokenPos' => 232,
                'endFilePos' => 2076,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 27,
            'endColumn' => 38,
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
 * Get a filesystem instance.
 *
 * @param  \\UnitEnum|string|null  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 75,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'disk' => 
      array (
        'name' => 'disk',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 86,
                'endLine' => 86,
                'startTokenPos' => 261,
                'startFilePos' => 2320,
                'endTokenPos' => 261,
                'endFilePos' => 2323,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 86,
            'endLine' => 86,
            'startColumn' => 26,
            'endColumn' => 37,
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
 * Get a filesystem instance.
 *
 * @param  \\UnitEnum|string|null  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 86,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'cloud' => 
      array (
        'name' => 'cloud',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a default cloud filesystem instance.
 *
 * @return \\Illuminate\\Contracts\\Filesystem\\Cloud
 */',
        'startLine' => 98,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'build' => 
      array (
        'name' => 'build',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 27,
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
 * Build an on-demand disk.
 *
 * @param  string|array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 111,
        'endLine' => 117,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 125,
            'endLine' => 125,
            'startColumn' => 28,
            'endColumn' => 32,
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
 * Attempt to get the disk from the local cache.
 *
 * @param  string  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 125,
        'endLine' => 128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'resolve' => 
      array (
        'name' => 'resolve',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 139,
            'endLine' => 139,
            'startColumn' => 32,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 139,
                'endLine' => 139,
                'startTokenPos' => 454,
                'startFilePos' => 3625,
                'endTokenPos' => 454,
                'endFilePos' => 3628,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 139,
            'endLine' => 139,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Resolve the given disk.
 *
 * @param  string  $name
 * @param  array|null  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 139,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'callCustomCreator' => 
      array (
        'name' => 'callCustomCreator',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 42,
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
 * Call a custom driver creator.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 168,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createLocalDriver' => 
      array (
        'name' => 'createLocalDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 180,
            'endLine' => 180,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => '\'local\'',
              'attributes' => 
              array (
                'startLine' => 180,
                'endLine' => 180,
                'startTokenPos' => 665,
                'startFilePos' => 4822,
                'endTokenPos' => 665,
                'endFilePos' => 4828,
              ),
            ),
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
            'startLine' => 180,
            'endLine' => 180,
            'startColumn' => 54,
            'endColumn' => 75,
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
 * Create an instance of the local driver.
 *
 * @param  array  $config
 * @param  string  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 180,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createFtpDriver' => 
      array (
        'name' => 'createFtpDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 211,
            'endLine' => 211,
            'startColumn' => 37,
            'endColumn' => 49,
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
 * Create an instance of the ftp driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 211,
        'endLine' => 220,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createSftpDriver' => 
      array (
        'name' => 'createSftpDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 38,
            'endColumn' => 50,
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
 * Create an instance of the sftp driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 228,
        'endLine' => 241,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createS3Driver' => 
      array (
        'name' => 'createS3Driver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 249,
            'endLine' => 249,
            'startColumn' => 36,
            'endColumn' => 48,
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
 * Create an instance of the Amazon S3 driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Cloud
 */',
        'startLine' => 249,
        'endLine' => 268,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createReadThroughDriver' => 
      array (
        'name' => 'createReadThroughDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => '\'read-through\'',
              'attributes' => 
              array (
                'startLine' => 277,
                'endLine' => 277,
                'startTokenPos' => 1221,
                'startFilePos' => 7776,
                'endTokenPos' => 1221,
                'endFilePos' => 7789,
              ),
            ),
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
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 60,
            'endColumn' => 88,
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
 * Create a read-through filesystem driver.
 *
 * @param  array  $config
 * @param  string  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 277,
        'endLine' => 311,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'formatS3Config' => 
      array (
        'name' => 'formatS3Config',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 319,
            'endLine' => 319,
            'startColumn' => 39,
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
 * Format the given S3 configuration with the default options.
 *
 * @param  array  $config
 * @return array
 */',
        'startLine' => 319,
        'endLine' => 332,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createScopedDriver' => 
      array (
        'name' => 'createScopedDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 342,
            'endLine' => 342,
            'startColumn' => 40,
            'endColumn' => 52,
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
 * Create a scoped driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 342,
        'endLine' => 373,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createFlysystem' => 
      array (
        'name' => 'createFlysystem',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'League\\Flysystem\\FilesystemAdapter',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 382,
            'endLine' => 382,
            'startColumn' => 40,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 382,
            'endLine' => 382,
            'startColumn' => 67,
            'endColumn' => 79,
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
 * Create a Flysystem instance with the given adapter.
 *
 * @param  \\League\\Flysystem\\FilesystemAdapter  $adapter
 * @param  array  $config
 * @return \\League\\Flysystem\\FilesystemOperator
 */',
        'startLine' => 382,
        'endLine' => 404,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'set' => 
      array (
        'name' => 'set',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 413,
            'endLine' => 413,
            'startColumn' => 25,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 413,
            'endLine' => 413,
            'startColumn' => 32,
            'endColumn' => 36,
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
 * Set the given disk instance.
 *
 * @param  string  $name
 * @param  mixed  $disk
 * @return $this
 */',
        'startLine' => 413,
        'endLine' => 418,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'getConfig' => 
      array (
        'name' => 'getConfig',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 426,
            'endLine' => 426,
            'startColumn' => 34,
            'endColumn' => 38,
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
 * Get the filesystem connection configuration.
 *
 * @param  string  $name
 * @return array
 */',
        'startLine' => 426,
        'endLine' => 429,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'getDefaultDriver' => 
      array (
        'name' => 'getDefaultDriver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default driver name.
 *
 * @return string
 */',
        'startLine' => 436,
        'endLine' => 439,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'getDefaultCloudDriver' => 
      array (
        'name' => 'getDefaultCloudDriver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default cloud driver name.
 *
 * @return string
 */',
        'startLine' => 446,
        'endLine' => 449,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'forgetDisk' => 
      array (
        'name' => 'forgetDisk',
        'parameters' => 
        array (
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 457,
            'endLine' => 457,
            'startColumn' => 32,
            'endColumn' => 36,
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
 * Unset the given disk instances.
 *
 * @param  array|string  $disk
 * @return $this
 */',
        'startLine' => 457,
        'endLine' => 464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'purge' => 
      array (
        'name' => 'purge',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 472,
                'endLine' => 472,
                'startTokenPos' => 2291,
                'startFilePos' => 13679,
                'endTokenPos' => 2291,
                'endFilePos' => 13682,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 472,
            'endLine' => 472,
            'startColumn' => 27,
            'endColumn' => 38,
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
 * Disconnect the given disk and remove from local cache.
 *
 * @param  string|null  $name
 * @return void
 */',
        'startLine' => 472,
        'endLine' => 477,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'extend' => 
      array (
        'name' => 'extend',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 489,
            'endLine' => 489,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 489,
            'endLine' => 489,
            'startColumn' => 37,
            'endColumn' => 53,
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
 * Register a custom driver creator Closure.
 *
 * @param  string  $driver
 * @param  \\Closure  $callback
 *
 * @param-closure-this  $this  $callback
 *
 * @return $this
 */',
        'startLine' => 489,
        'endLine' => 500,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'setApplication' => 
      array (
        'name' => 'setApplication',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 36,
            'endColumn' => 39,
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
 * Set the application instance used by the manager.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 * @return $this
 */',
        'startLine' => 508,
        'endLine' => 513,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 522,
            'endLine' => 522,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 522,
            'endLine' => 522,
            'startColumn' => 37,
            'endColumn' => 47,
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
 * Dynamically call the default driver instance.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 522,
        'endLine' => 525,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
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