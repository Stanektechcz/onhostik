<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\AaPanel\AaPanelShell.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\AaPanel\AaPanelShell
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-91adacb8187a20d02d732cb2b313844a0b9df146eff40b6b16aea1d7a4945e33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/AaPanel/AaPanelShell.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\AaPanel',
    'name' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
    'shortName' => 'AaPanelShell',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Commands on an aaPanel node through the panel API. `files?action=ExecShell` only acknowledges ("Command sent") and
 * runs the command in the background as root, so every call is wrapped: `timeout … bash -c … > /tmp/<id>.out 2>&1;
 * echo $? > /tmp/<id>.exit`, the exit file is polled through `GetFileBody`, the output read the same way and both
 * removed afterwards. Site-level work switches to the site user (`su -s /bin/bash www -c …`) — aaPanel runs every
 * site as `www`, so that is the same identity the customer\'s PHP already has.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 110,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Providers\\Contracts\\NodeShell',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OUTPUT_CAP' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'name' => 'OUTPUT_CAP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1048576',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 72,
            'startFilePos' => 966,
            'endTokenPos' => 72,
            'endFilePos' => 972,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'NOISE' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'name' => 'NOISE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'Your request has been recorded. Tips from BT security !!!\', \'Tips from BT security\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 85,
            'startFilePos' => 1107,
            'endTokenPos' => 90,
            'endFilePos' => 1192,
          ),
        ),
        'docComment' => '/** Lines the panel\'s `www` shell wrapper prints on every `su`; not part of the command\'s output. */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 113,
      ),
    ),
    'immediateProperties' => 
    array (
      'post' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'name' => 'post',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Closure',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 33,
        'endColumn' => 62,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'instanceKey' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'name' => 'instanceKey',
        'modifiers' => 132,
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
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 65,
        'endColumn' => 100,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'configured' => 
      array (
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'name' => 'configured',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 129,
            'startFilePos' => 1445,
            'endTokenPos' => 129,
            'endFilePos' => 1448,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 103,
        'endColumn' => 142,
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
          'post' => 
          array (
            'name' => 'post',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 33,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'instanceKey' => 
          array (
            'name' => 'instanceKey',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 65,
            'endColumn' => 100,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'configured' => 
          array (
            'name' => 'configured',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 30,
                'endLine' => 30,
                'startTokenPos' => 129,
                'startFilePos' => 1445,
                'endTokenPos' => 129,
                'endFilePos' => 1448,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 103,
            'endColumn' => 142,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param Closure(string, array<string,mixed>, string, bool): mixed $post the adapter\'s signed request */',
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 146,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\AaPanel',
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'currentClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 25,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 32,
                'endLine' => 32,
                'startTokenPos' => 152,
                'startFilePos' => 1513,
                'endTokenPos' => 153,
                'endFilePos' => 1514,
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
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 42,
            'endColumn' => 60,
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
            'name' => 'Onhost\\Providers\\Contracts\\ShellResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 32,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\AaPanel',
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'currentClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'aliasName' => NULL,
      ),
      'available' => 
      array (
        'name' => 'available',
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
        'startLine' => 85,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\AaPanel',
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'currentClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'aliasName' => NULL,
      ),
      'describe' => 
      array (
        'name' => 'describe',
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
        'docComment' => NULL,
        'startLine' => 90,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\AaPanel',
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'currentClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'aliasName' => NULL,
      ),
      'clean' => 
      array (
        'name' => 'clean',
        'parameters' => 
        array (
          'output' => 
          array (
            'name' => 'output',
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
            'startLine' => 95,
            'endLine' => 95,
            'startColumn' => 35,
            'endColumn' => 48,
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
        'docComment' => NULL,
        'startLine' => 95,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Onhost\\Providers\\AaPanel',
        'declaringClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'implementingClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
        'currentClassName' => 'Onhost\\Providers\\AaPanel\\AaPanelShell',
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