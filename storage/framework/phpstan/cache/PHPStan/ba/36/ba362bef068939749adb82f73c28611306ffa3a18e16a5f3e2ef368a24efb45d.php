<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Queue/InteractsWithUniqueJobs.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Foundation\Queue\InteractsWithUniqueJobs
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-824fd53524ded3039544b446e73513670d947f2cc40f58ab7807848a8b60e985-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Queue/InteractsWithUniqueJobs.php',
      ),
    ),
    'namespace' => 'Illuminate\\Foundation\\Queue',
    'name' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
    'shortName' => 'InteractsWithUniqueJobs',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 60,
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
      'addUniqueJobInformationToContext' => 
      array (
        'name' => 'addUniqueJobInformationToContext',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 54,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Store unique job information in the context in case we can\'t resolve the job on the queue side.
 *
 * @param  mixed  $job
 * @return void
 */',
        'startLine' => 18,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Queue',
        'declaringClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'implementingClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'currentClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'aliasName' => NULL,
      ),
      'removeUniqueJobInformationFromContext' => 
      array (
        'name' => 'removeUniqueJobInformationFromContext',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
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
            'startColumn' => 59,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove the unique job information from the context.
 *
 * @param  mixed  $job
 * @return void
 */',
        'startLine' => 37,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Queue',
        'declaringClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'implementingClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'currentClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'aliasName' => NULL,
      ),
      'getUniqueJobCacheStore' => 
      array (
        'name' => 'getUniqueJobCacheStore',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 47,
            'endColumn' => 50,
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine the cache store used by the unique job to acquire locks.
 *
 * @param  mixed  $job
 * @return string|null
 */',
        'startLine' => 54,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Queue',
        'declaringClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'implementingClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
        'currentClassName' => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
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