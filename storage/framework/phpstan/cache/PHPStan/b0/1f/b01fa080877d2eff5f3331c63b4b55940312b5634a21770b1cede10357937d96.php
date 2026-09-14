<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Console/Concerns/ConfiguresPrompts.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Concerns\ConfiguresPrompts
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-04614179908a9986969fea07249320214ba04b47d1413485b945439199c33214-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Console/Concerns/ConfiguresPrompts.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Concerns',
    'name' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
    'shortName' => 'ConfiguresPrompts',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 305,
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
      'configurePrompts' => 
      array (
        'name' => 'configurePrompts',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Console\\Input\\InputInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 41,
            'endColumn' => 61,
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
 * Configure the prompt fallbacks.
 *
 * @param  \\Symfony\\Component\\Console\\Input\\InputInterface  $input
 * @return void
 */',
        'startLine' => 29,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'promptUntilValid' => 
      array (
        'name' => 'promptUntilValid',
        'parameters' => 
        array (
          'prompt' => 
          array (
            'name' => 'prompt',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'required' => 
          array (
            'name' => 'required',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 50,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'validate' => 
          array (
            'name' => 'validate',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 61,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prompt the user until the given validation callback passes.
 *
 * @template PResult
 *
 * @param  \\Closure(): PResult  $prompt
 * @param  bool|string  $required
 * @param  (\\Closure(PResult): mixed)|null  $validate
 * @return PResult
 *
 * @throws \\Illuminate\\Console\\PromptValidationException
 */',
        'startLine' => 138,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'validatePrompt' => 
      array (
        'name' => 'validatePrompt',
        'parameters' => 
        array (
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
            'startLine' => 176,
            'endLine' => 176,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'rules' => 
          array (
            'name' => 'rules',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 176,
            'endLine' => 176,
            'startColumn' => 47,
            'endColumn' => 52,
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
 * Validate the given prompt value using the validator.
 *
 * @param  mixed  $value
 * @param  mixed  $rules
 * @return ?string
 */',
        'startLine' => 176,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'getPromptValidatorInstance' => 
      array (
        'name' => 'getPromptValidatorInstance',
        'parameters' => 
        array (
          'field' => 
          array (
            'name' => 'field',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 51,
            'endColumn' => 56,
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 59,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'rules' => 
          array (
            'name' => 'rules',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 67,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'messages' => 
          array (
            'name' => 'messages',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 209,
                'endLine' => 209,
                'startTokenPos' => 1439,
                'startFilePos' => 7164,
                'endTokenPos' => 1440,
                'endFilePos' => 7165,
              ),
            ),
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 75,
            'endColumn' => 94,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 209,
                'endLine' => 209,
                'startTokenPos' => 1449,
                'startFilePos' => 7188,
                'endTokenPos' => 1450,
                'endFilePos' => 7189,
              ),
            ),
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 97,
            'endColumn' => 118,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the validator instance that should be used to validate prompts.
 *
 * @param  mixed  $field
 * @param  mixed  $value
 * @param  mixed  $rules
 * @param  array  $messages
 * @param  array  $attributes
 * @return \\Illuminate\\Validation\\Validator
 */',
        'startLine' => 209,
        'endLine' => 217,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'validationMessages' => 
      array (
        'name' => 'validationMessages',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the validation messages that should be used during prompt validation.
 *
 * @return array<string, string>
 */',
        'startLine' => 224,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'validationAttributes' => 
      array (
        'name' => 'validationAttributes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the validation attributes that should be used during prompt validation.
 *
 * @return array<string, string>
 */',
        'startLine' => 234,
        'endLine' => 237,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'restorePrompts' => 
      array (
        'name' => 'restorePrompts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Restore the prompts output.
 *
 * @return void
 */',
        'startLine' => 244,
        'endLine' => 247,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'selectFallback' => 
      array (
        'name' => 'selectFallback',
        'parameters' => 
        array (
          'label' => 
          array (
            'name' => 'label',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 257,
            'endLine' => 257,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 257,
            'endLine' => 257,
            'startColumn' => 45,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 257,
                'endLine' => 257,
                'startTokenPos' => 1608,
                'startFilePos' => 8350,
                'endTokenPos' => 1608,
                'endFilePos' => 8353,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 257,
            'endLine' => 257,
            'startColumn' => 55,
            'endColumn' => 69,
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
 * Select fallback.
 *
 * @param  string  $label
 * @param  array<array-key, string>  $options
 * @param  string|int|null  $default
 * @return string|int
 */',
        'startLine' => 257,
        'endLine' => 266,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'aliasName' => NULL,
      ),
      'multiselectFallback' => 
      array (
        'name' => 'multiselectFallback',
        'parameters' => 
        array (
          'label' => 
          array (
            'name' => 'label',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 50,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 277,
                'endLine' => 277,
                'startTokenPos' => 1692,
                'startFilePos' => 8865,
                'endTokenPos' => 1693,
                'endFilePos' => 8866,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 60,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'required' => 
          array (
            'name' => 'required',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 277,
                'endLine' => 277,
                'startTokenPos' => 1700,
                'startFilePos' => 8881,
                'endTokenPos' => 1700,
                'endFilePos' => 8885,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 75,
            'endColumn' => 91,
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
 * Multi-select fallback.
 *
 * @param  string  $label
 * @param  array  $options
 * @param  array  $default
 * @param  bool|string  $required
 * @return array
 */',
        'startLine' => 277,
        'endLine' => 304,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\ConfiguresPrompts',
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