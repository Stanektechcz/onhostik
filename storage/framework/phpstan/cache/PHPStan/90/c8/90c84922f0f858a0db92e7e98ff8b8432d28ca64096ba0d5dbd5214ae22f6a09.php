<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-recursivecallbackfilteriterator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'RecursiveCallbackFilterIterator',
        'filename' => 'phpstorm-stubs:SPL/SPL.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'RecursiveCallbackFilterIterator',
    'shortName' => 'RecursiveCallbackFilterIterator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * (PHP 5 >= 5.4.0)<br>
 * RecursiveCallbackFilterIterator from a RecursiveIterator
 * @link https://secure.php.net/manual/en/class.recursivecallbackfilteriterator.php
 * @since 5.4
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 46,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => 'CallbackFilterIterator',
    'implementsClassNames' => 
    array (
      0 => 'RecursiveIterator',
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'iterator' => 
          array (
            'name' => 'iterator',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'RecursiveIterator',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 13,
            'endColumn' => 40,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'callable\']',
                    'attributes' => 
                    array (
                      'startLine' => 21,
                      'endLine' => 21,
                      'startTokenPos' => 39,
                      'startFilePos' => 948,
                      'endTokenPos' => 45,
                      'endFilePos' => 968,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 21,
                      'endLine' => 21,
                      'startTokenPos' => 51,
                      'startFilePos' => 980,
                      'endTokenPos' => 51,
                      'endFilePos' => 981,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 21,
            'endLine' => 22,
            'startColumn' => 13,
            'endColumn' => 30,
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
 * Create a RecursiveCallbackFilterIterator from a RecursiveIterator
 * @param RecursiveIterator $iterator The recursive iterator to be filtered.
 * @param callable $callback The callback, which should return TRUE to accept the current item or FALSE otherwise. See Examples.
 * May be any valid callable value.
 * @link https://www.php.net/manual/en/recursivecallbackfilteriterator.construct.php
 */',
        'startLine' => 19,
        'endLine' => 25,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveCallbackFilterIterator',
        'implementingClassName' => 'RecursiveCallbackFilterIterator',
        'currentClassName' => 'RecursiveCallbackFilterIterator',
        'aliasName' => NULL,
      ),
      'hasChildren' => 
      array (
        'name' => 'hasChildren',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Check whether the inner iterator\'s current element has children
 * @link https://php.net/manual/en/recursiveiterator.haschildren.php
 * @return bool Returns TRUE if the current element has children, FALSE otherwise.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveCallbackFilterIterator',
        'implementingClassName' => 'RecursiveCallbackFilterIterator',
        'currentClassName' => 'RecursiveCallbackFilterIterator',
        'aliasName' => NULL,
      ),
      'getChildren' => 
      array (
        'name' => 'getChildren',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'RecursiveCallbackFilterIterator',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Returns an iterator for the current entry.
 * @link https://secure.php.net/manual/en/recursivecallbackfilteriterator.haschildren.php
 * @return RecursiveCallbackFilterIterator containing the children.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'RecursiveCallbackFilterIterator',
        'implementingClassName' => 'RecursiveCallbackFilterIterator',
        'currentClassName' => 'RecursiveCallbackFilterIterator',
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