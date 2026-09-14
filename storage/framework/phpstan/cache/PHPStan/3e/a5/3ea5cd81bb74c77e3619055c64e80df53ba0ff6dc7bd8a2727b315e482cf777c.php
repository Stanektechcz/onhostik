<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Risk\Turnstile.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Risk\Turnstile
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-944853640ac5c4af7c516d93aa8ac048819e8130ec53068219362825849e3009',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Risk\\Turnstile',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Risk/Turnstile.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Risk',
    'name' => 'Onhost\\Domain\\Risk\\Turnstile',
    'shortName' => 'Turnstile',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Cloudflare Turnstile on registration and checkout (audit §5q-6). The surfaces render the widget when the boot
 * object carries the site key and send its token as `turnstile` (or the `CF-Turnstile-Response` header). The
 * platform verifies it once per request at `siteverify` and keeps the outcome in the request context: registration
 * refuses a missing or failed check (`enforce_register`), the order check scores it as one more risk signal
 * (`turnstile_failed`) — a bot without a token is not blocked, its order waits for a person. Off without keys.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 106,
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
      'VERIFY_URL' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'VERIFY_URL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://challenges.cloudflare.com/turnstile/v0/siteverify\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 62,
            'startFilePos' => 865,
            'endTokenPos' => 62,
            'endFilePos' => 923,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 90,
      ),
      'CONTEXT_KEY' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'CONTEXT_KEY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'turnstile\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 73,
            'startFilePos' => 958,
            'endTokenPos' => 73,
            'endFilePos' => 968,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'PASS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'PASS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'pass\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 84,
            'startFilePos' => 996,
            'endTokenPos' => 84,
            'endFilePos' => 1001,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'FAIL' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'FAIL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'fail\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 95,
            'startFilePos' => 1029,
            'endTokenPos' => 95,
            'endFilePos' => 1034,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'MISSING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'MISSING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'missing\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 106,
            'startFilePos' => 1065,
            'endTokenPos' => 106,
            'endFilePos' => 1073,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'OFF' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'OFF',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'off\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 117,
            'startFilePos' => 1100,
            'endTokenPos' => 117,
            'endFilePos' => 1104,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
    ),
    'immediateProperties' => 
    array (
      'http' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'name' => 'http',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Http\\Client\\Factory',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 33,
        'endColumn' => 66,
        'isPromoted' => true,
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
          'http' => 
          array (
            'name' => 'http',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Client\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 33,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 70,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'aliasName' => NULL,
      ),
      'enabled' => 
      array (
        'name' => 'enabled',
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
        ),
        'docComment' => NULL,
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'aliasName' => NULL,
      ),
      'check' => 
      array (
        'name' => 'check',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 27,
            'endColumn' => 42,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Verifies the request\'s token once and remembers the outcome for the risk check. @return self::PASS|self::FAIL|self::MISSING|self::OFF */',
        'startLine' => 42,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'aliasName' => NULL,
      ),
      'verify' => 
      array (
        'name' => 'verify',
        'parameters' => 
        array (
          'token' => 
          array (
            'name' => 'token',
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
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 28,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ip' => 
          array (
            'name' => 'ip',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 43,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return self::PASS|self::FAIL|self::MISSING */',
        'startLine' => 57,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'aliasName' => NULL,
      ),
      'result' => 
      array (
        'name' => 'result',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The outcome of this request\'s check (`off` when nothing ran). */',
        'startLine' => 73,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'aliasName' => NULL,
      ),
      'requireForForm' => 
      array (
        'name' => 'requireForForm',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 36,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'form' => 
          array (
            'name' => 'form',
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
            ),
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 54,
            'endColumn' => 65,
            'parameterIndex' => 1,
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
 * The public forms — contact and support requests, tender requests, the partner application (audit §5r-6) — refuse
 * a missing or failed check while `enforce_forms` is on. A signed-in user is trusted (their session already passed
 * registration), so the portal\'s own forms never depend on the widget.
 */',
        'startLine' => 85,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'aliasName' => NULL,
      ),
      'requireForRegistration' => 
      array (
        'name' => 'requireForRegistration',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 98,
            'endLine' => 98,
            'startColumn' => 44,
            'endColumn' => 59,
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
        'docComment' => '/** Registration refuses a missing or failed check while `enforce_register` is on. */',
        'startLine' => 98,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
        'currentClassName' => 'Onhost\\Domain\\Risk\\Turnstile',
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