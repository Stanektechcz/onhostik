<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\Workflows\Steps\EnsureRegistrarContactsStep.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\Workflows\Steps\EnsureRegistrarContactsStep
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-b131a19ca2fcb8715b28f2ed569c63d0e8fd20c539bdb2b17e5579e9f3695f03',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/Workflows/Steps/EnsureRegistrarContactsStep.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains\\Workflows\\Steps',
    'name' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
    'shortName' => 'EnsureRegistrarContactsStep',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Creates registrant/admin contacts at the registrar when they are not synced yet (idempotent by remote handle). */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 78,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Domain\\Domains\\Workflows\\DomainStep',
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
      'label' => 
      array (
        'name' => 'label',
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
        'startLine' => 19,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Workflows\\Steps',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Workflow\\StepContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 25,
            'endColumn' => 44,
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
            'name' => 'Onhost\\Domain\\Provisioning\\Workflow\\StepResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 24,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Workflows\\Steps',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'aliasName' => NULL,
      ),
      'handleFor' => 
      array (
        'name' => 'handleFor',
        'parameters' => 
        array (
          'contact' => 
          array (
            'name' => 'contact',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 32,
            'endColumn' => 56,
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
        'startLine' => 74,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Domains\\Workflows\\Steps',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Workflows\\Steps\\EnsureRegistrarContactsStep',
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