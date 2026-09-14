<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-sleep
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'name' => 'sleep',
    'parameters' => 
    array (
      'seconds' => 
      array (
        'name' => 'seconds',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 20,
        'endColumn' => 31,
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
        'name' => 'int',
        'isIdentifier' => true,
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
            'code' => '["8.0" => "int"]',
            'attributes' => 
            array (
              'startLine' => 24,
              'endLine' => 24,
              'startTokenPos' => 11,
              'startFilePos' => 935,
              'endTokenPos' => 17,
              'endFilePos' => 950,
            ),
          ),
          'default' => 
          array (
            'code' => '"int|false"',
            'attributes' => 
            array (
              'startLine' => 24,
              'endLine' => 24,
              'startTokenPos' => 23,
              'startFilePos' => 962,
              'endTokenPos' => 23,
              'endFilePos' => 972,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Delays the program execution for the given number of seconds
 * @link https://php.net/manual/en/function.sleep.php
 * @param int<0,max> $seconds <p>
 * Halt time in seconds (must be greater than or equal to 0).
 * </p>
 * @return int Returns zero on success.
 * <p>
 * If the call was interrupted by a signal, sleep() returns a
 * non-zero value. On Windows, this value will always be 192
 * (the value of the WAIT_IO_COMPLETION constant within the Windows API).
 * On other platforms, the return value will be the
 * number of seconds left to sleep.
 * </p>
 * <p>
 * As of PHP 8.0, if the specified number of seconds is negative,
 * this function will throw a ValueError.
 * Before PHP 8.0, an E_WARNING was raised instead, and the function returned false.
 * </p>
 */',
    'startLine' => 24,
    'endLine' => 27,
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
        'name' => 'sleep',
        'filename' => 'phpstorm-stubs:standard/standard_0.stub',
        'extensionName' => 'standard',
        'aliasName' => NULL,
      ),
    ),
  ),
));