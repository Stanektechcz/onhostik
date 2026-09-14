<?php declare(strict_types = 1);

// odsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Support/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-retry
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-e5ae2237a852be4732850a07ea452918e4d17eb162593301f9e8b50351abbc8a',
   'data' => 
  array (
    'name' => 'retry',
    'parameters' => 
    array (
      'times' => 
      array (
        'name' => 'times',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 311,
        'endLine' => 311,
        'startColumn' => 20,
        'endColumn' => 25,
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
            'name' => 'callable',
            'isIdentifier' => true,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 311,
        'endLine' => 311,
        'startColumn' => 28,
        'endColumn' => 45,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'sleepMilliseconds' => 
      array (
        'name' => 'sleepMilliseconds',
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 311,
            'endLine' => 311,
            'startTokenPos' => 1361,
            'startFilePos' => 7906,
            'endTokenPos' => 1361,
            'endFilePos' => 7906,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 311,
        'endLine' => 311,
        'startColumn' => 48,
        'endColumn' => 69,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'when' => 
      array (
        'name' => 'when',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 311,
            'endLine' => 311,
            'startTokenPos' => 1368,
            'startFilePos' => 7917,
            'endTokenPos' => 1368,
            'endFilePos' => 7920,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 311,
        'endLine' => 311,
        'startColumn' => 72,
        'endColumn' => 83,
        'parameterIndex' => 3,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => NULL,
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Retry an operation a given number of times.
 *
 * @template TValue
 *
 * @param  int|array<int, int>  $times
 * @param  callable(int): TValue  $callback
 * @param  CarbonInterval|int|\\Closure(int, \\Throwable): CarbonInterval|int  $sleepMilliseconds
 * @param  (callable(\\Throwable): bool)|null  $when
 * @return TValue
 *
 * @throws \\Throwable
 */',
    'startLine' => 311,
    'endLine' => 346,
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
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'retry',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Support/helpers.php',
      ),
    ),
  ),
));