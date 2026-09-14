<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Redaction\Redactor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Redaction\Redactor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-42e564502582f96898df4b061eacd98ad19c8f3c1c5d09ff8687aef5a244e576',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Redaction\\Redactor',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Redaction/Redactor.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Redaction',
    'name' => 'Onhost\\Platform\\Redaction\\Redactor',
    'shortName' => 'Redactor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Removes credentials and secret-like fields from anything that is logged or
 * persisted (blueprint §5.4, §45.2, §6.1). Vendor payloads from aaPanel/WAPI may
 * echo secrets back, so redaction is applied to responses as well as requests.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 91,
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
      'MASK' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'name' => 'MASK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'[redacted]\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 33,
            'startFilePos' => 369,
            'endTokenPos' => 33,
            'endFilePos' => 380,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'SECRET_KEYS' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'name' => 'SECRET_KEYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'password\', \'passwd\', \'secret\', \'token\', \'auth\', \'authorization\', \'api_key\', \'apikey\', \'request_token\', \'session_id\', \'auth_info\', \'authinfo\', \'private_key\', \'privatekey\', \'cookie\', \'set-cookie\', \'client_secret\', \'access_token\', \'refresh_token\', \'id_token\', \'ticket\', \'csrfpreventiontoken\', \'vncticket\', \'dkim_private\', \'signature\', \'cvv\', \'ssh_private\', \'root_password\', \'db_password\', \'mysql_password\', \'totp\', \'recovery_codes\']',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 23,
            'startTokenPos' => 46,
            'startFilePos' => 494,
            'endTokenPos' => 144,
            'endFilePos' => 971,
          ),
        ),
        'docComment' => '/** @var list<string> lower-cased key fragments that are always masked */',
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'ALLOW_KEYS' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'name' => 'ALLOW_KEYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'keyset\', \'nsset\', \'key_name\', \'keyid\', \'token_id\', \'tokenid\', \'ticket_id\', \'ticket_ref\', \'ticket_number\', \'public_key\', \'card_last4\', \'card_brand\', \'signature_ok\', \'auth_method\', \'authenticated\', \'tokens_total\']',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 29,
            'startTokenPos' => 157,
            'startFilePos' => 1082,
            'endTokenPos' => 207,
            'endFilePos' => 1317,
          ),
        ),
        'docComment' => '/** @var list<string> keys that look secret-ish but are safe to keep */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'redact' => 
      array (
        'name' => 'redact',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 28,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'depth' => 
          array (
            'name' => 'depth',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 31,
                'endLine' => 31,
                'startTokenPos' => 227,
                'startFilePos' => 1375,
                'endTokenPos' => 227,
                'endFilePos' => 1375,
              ),
            ),
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 42,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 31,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Redaction',
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'currentClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'aliasName' => NULL,
      ),
      'isSecretKey' => 
      array (
        'name' => 'isSecretKey',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 33,
            'endColumn' => 43,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 54,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Redaction',
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'currentClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'aliasName' => NULL,
      ),
      'redactString' => 
      array (
        'name' => 'redactString',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 34,
            'endColumn' => 46,
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
        'docComment' => '/** Masks bearer tokens, PVE/PBS tokens, SHA1 auth strings and URL credentials inside free text. */',
        'startLine' => 70,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Redaction',
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'currentClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'aliasName' => NULL,
      ),
      'fingerprint' => 
      array (
        'name' => 'fingerprint',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 40,
            'endColumn' => 51,
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
        'docComment' => '/** Short, safe fingerprint so two calls can be correlated without storing the payload. */',
        'startLine' => 87,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Platform\\Redaction',
        'declaringClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'implementingClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
        'currentClassName' => 'Onhost\\Platform\\Redaction\\Redactor',
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