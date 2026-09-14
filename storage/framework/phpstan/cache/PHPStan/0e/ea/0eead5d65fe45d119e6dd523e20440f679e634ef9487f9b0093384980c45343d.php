<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Invoicing\UblExporter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Invoicing\UblExporter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-b38e5481c4bd9b74306e58a7b1678e787babb37dcc8b37c048079fd30e5547c4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Invoicing/UblExporter.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Invoicing',
    'name' => 'Onhost\\Domain\\Invoicing\\UblExporter',
    'shortName' => 'UblExporter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * EN 16931 / Peppol BIS Billing 3.0 mapping (UBL 2.1). The structured array is
 * frozen on the invoice at issue time; `export()` renders the XML for the SK
 * eFaktúra / Peppol delivery adapter (§64.5).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 157,
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
      'CUSTOMIZATION_ID' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'name' => 'CUSTOMIZATION_ID',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 43,
            'startFilePos' => 424,
            'endTokenPos' => 43,
            'endFilePos' => 499,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 113,
      ),
      'PROFILE_ID' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'name' => 'PROFILE_ID',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 54,
            'startFilePos' => 533,
            'endTokenPos' => 54,
            'endFilePos' => 577,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 76,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'structure' => 
      array (
        'name' => 'structure',
        'parameters' => 
        array (
          'invoice' => 
          array (
            'name' => 'invoice',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 31,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return array<string,mixed> */',
        'startLine' => 22,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'aliasName' => NULL,
      ),
      'export' => 
      array (
        'name' => 'export',
        'parameters' => 
        array (
          'invoice' => 
          array (
            'name' => 'invoice',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 88,
            'endLine' => 88,
            'startColumn' => 28,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 88,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'aliasName' => NULL,
      ),
      'exemptionReason' => 
      array (
        'name' => 'exemptionReason',
        'parameters' => 
        array (
          'category' => 
          array (
            'name' => 'category',
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
            'startLine' => 147,
            'endLine' => 147,
            'startColumn' => 38,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 147,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Invoicing',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\UblExporter',
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