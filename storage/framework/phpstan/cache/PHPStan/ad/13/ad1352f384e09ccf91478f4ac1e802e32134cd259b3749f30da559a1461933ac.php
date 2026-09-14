<?php declare(strict_types = 1);

// odsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Collections/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-when
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-0ab3c32b59702c826377fa2af8246cc11c803f7f69f86c41bfe84ab8fb86a8df',
   'data' => 
  array (
    'name' => 'when',
    'parameters' => 
    array (
      'condition' => 
      array (
        'name' => 'condition',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 283,
        'endLine' => 283,
        'startColumn' => 19,
        'endColumn' => 28,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'value' => 
      array (
        'name' => 'value',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 283,
        'endLine' => 283,
        'startColumn' => 31,
        'endColumn' => 36,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'default' => 
      array (
        'name' => 'default',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 283,
            'endLine' => 283,
            'startTokenPos' => 1619,
            'startFilePos' => 8004,
            'endTokenPos' => 1619,
            'endFilePos' => 8007,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 283,
        'endLine' => 283,
        'startColumn' => 39,
        'endColumn' => 53,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => NULL,
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Return a value if the given condition is true.
 *
 * @template TValue
 * @template TArgs
 * @template TDefault
 *
 * @param  mixed  $condition
 * @param  TValue|\\Closure(TArgs): TValue  $value
 * @param  TDefault|\\Closure(): TDefault  $default
 * @return ($condition is true|positive-int|non-falsy-string|non-empty-array ? TValue : ($condition is callable ? TValue|TDefault : TDefault))
 */',
    'startLine' => 283,
    'endLine' => 292,
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
        'name' => 'when',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Collections/helpers.php',
      ),
    ),
  ),
));