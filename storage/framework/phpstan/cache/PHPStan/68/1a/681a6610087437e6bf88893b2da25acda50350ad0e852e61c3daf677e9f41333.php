<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-domattr
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'DOMAttr',
        'filename' => 'phpstorm-stubs:dom/dom_c.stub',
        'extensionName' => 'dom',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'DOMAttr',
    'shortName' => 'DOMAttr',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The DOMAttr interface represents an attribute in an DOMElement object.
 * @link https://php.net/manual/en/class.domattr.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 75,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => 'DOMNode',
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
      'name' => 
      array (
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'name' => 'name',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * @var string
 * (PHP5)<br/>
 * The name of the attribute
 * @link https://php.net/manual/en/class.domattr.php#domattr.props.name
 */',
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
                'code' => '[\'8.1\' => \'string\']',
                'attributes' => 
                array (
                  'startLine' => 16,
                  'endLine' => 16,
                  'startTokenPos' => 23,
                  'startFilePos' => 460,
                  'endTokenPos' => 29,
                  'endFilePos' => 478,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 16,
                  'endLine' => 16,
                  'startTokenPos' => 35,
                  'startFilePos' => 490,
                  'endTokenPos' => 35,
                  'endFilePos' => 491,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 16,
        'endLine' => 17,
        'startColumn' => 9,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'ownerElement' => 
      array (
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'name' => 'ownerElement',
        'modifiers' => 1,
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
                  'name' => 'DOMElement',
                  'isIdentifier' => false,
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
        'default' => NULL,
        'docComment' => '/**
 * @var DOMElement
 * (PHP5)<br/>
 * The element which contains the attribute
 * @link https://php.net/manual/en/class.domattr.php#domattr.props.ownerelement
 */',
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
                'code' => '[\'8.1\' => \'DOMElement|null\']',
                'attributes' => 
                array (
                  'startLine' => 24,
                  'endLine' => 24,
                  'startTokenPos' => 51,
                  'startFilePos' => 800,
                  'endTokenPos' => 57,
                  'endFilePos' => 827,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 24,
                  'endLine' => 24,
                  'startTokenPos' => 63,
                  'startFilePos' => 839,
                  'endTokenPos' => 63,
                  'endFilePos' => 840,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 24,
        'endLine' => 25,
        'startColumn' => 9,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'schemaTypeInfo' => 
      array (
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'name' => 'schemaTypeInfo',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * @var bool
 * (PHP5)<br/>
 * Not implemented yet, always is NULL
 * @link https://php.net/manual/en/class.domattr.php#domattr.props.schematypeinfo
 */',
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
                'code' => '[\'8.1\' => \'mixed\']',
                'attributes' => 
                array (
                  'startLine' => 32,
                  'endLine' => 32,
                  'startTokenPos' => 81,
                  'startFilePos' => 1157,
                  'endTokenPos' => 87,
                  'endFilePos' => 1174,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 32,
                  'endLine' => 32,
                  'startTokenPos' => 93,
                  'startFilePos' => 1186,
                  'endTokenPos' => 93,
                  'endFilePos' => 1187,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 32,
        'endLine' => 33,
        'startColumn' => 9,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'specified' => 
      array (
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'name' => 'specified',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * @var bool
 * (PHP5)<br/>
 * Not implemented yet, always is NULL
 * @link https://php.net/manual/en/class.domattr.php#domattr.props.specified
 */',
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
                'code' => '[\'8.1\' => \'bool\']',
                'attributes' => 
                array (
                  'startLine' => 40,
                  'endLine' => 40,
                  'startTokenPos' => 109,
                  'startFilePos' => 1491,
                  'endTokenPos' => 115,
                  'endFilePos' => 1507,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 40,
                  'endLine' => 40,
                  'startTokenPos' => 121,
                  'startFilePos' => 1519,
                  'endTokenPos' => 121,
                  'endFilePos' => 1520,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 40,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'value' => 
      array (
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'name' => 'value',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * @var string
 * (PHP5)<br/>
 * The value of the attribute
 * @link https://php.net/manual/en/class.domattr.php#domattr.props.value
 */',
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
                'code' => '[\'8.1\' => \'string\']',
                'attributes' => 
                array (
                  'startLine' => 48,
                  'endLine' => 48,
                  'startTokenPos' => 137,
                  'startFilePos' => 1807,
                  'endTokenPos' => 143,
                  'endFilePos' => 1825,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 48,
                  'endLine' => 48,
                  'startTokenPos' => 149,
                  'startFilePos' => 1837,
                  'endTokenPos' => 149,
                  'endFilePos' => 1838,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 48,
        'endLine' => 49,
        'startColumn' => 9,
        'endColumn' => 29,
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
      'isId' => 
      array (
        'name' => 'isId',
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
 * Checks if attribute is a defined ID
 * @link https://php.net/manual/en/domattr.isid.php
 * @return bool true on success or false on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'currentClassName' => 'DOMAttr',
        'aliasName' => NULL,
      ),
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 68,
                      'endLine' => 68,
                      'startTokenPos' => 193,
                      'startFilePos' => 2658,
                      'endTokenPos' => 199,
                      'endFilePos' => 2676,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 68,
                      'endLine' => 68,
                      'startTokenPos' => 205,
                      'startFilePos' => 2688,
                      'endTokenPos' => 205,
                      'endFilePos' => 2689,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 68,
            'endLine' => 69,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 71,
                'endLine' => 71,
                'startTokenPos' => 239,
                'startFilePos' => 2848,
                'endTokenPos' => 239,
                'endFilePos' => 2849,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 70,
                      'endLine' => 70,
                      'startTokenPos' => 217,
                      'startFilePos' => 2785,
                      'endTokenPos' => 223,
                      'endFilePos' => 2803,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 70,
                      'endLine' => 70,
                      'startTokenPos' => 229,
                      'startFilePos' => 2815,
                      'endTokenPos' => 229,
                      'endFilePos' => 2816,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 70,
            'endLine' => 71,
            'startColumn' => 13,
            'endColumn' => 30,
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
 * Creates a new {@see DOMAttr} object
 * @link https://php.net/manual/en/domattr.construct.php
 * @param string $name <p>The tag name of the attribute.</p>
 * @param string $value [optional] <p>The value of the attribute.</p>
 * @throws DOMException If invalid $name
 */',
        'startLine' => 67,
        'endLine' => 74,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DOMAttr',
        'implementingClassName' => 'DOMAttr',
        'currentClassName' => 'DOMAttr',
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