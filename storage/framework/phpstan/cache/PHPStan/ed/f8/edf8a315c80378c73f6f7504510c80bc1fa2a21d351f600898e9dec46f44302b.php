<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Invoicing\Commands\InvoiceCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Invoicing\Commands\InvoiceCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-bbcbe6a52b1477ad004a359df778da7a5dbe501b6fe04977766b2ffaf8617aa2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Invoicing/Commands/InvoiceCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
    'name' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
    'shortName' => 'InvoiceCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Invoice operations, dispatched by `op`:
 *  pay_from_wallet{invoice_id} (customer, postpaid invoices) · credit_note{invoice_id,reason,line_ids?,incident_ref?} (staff) ·
 *  mark_paid{invoice_id,method,reference} (staff, manual bank match)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 51,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Commands\\OrganizationCommand',
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Domain\\Identity\\Authorization\\RiskAwareCommand',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'pay_from_wallet\', \'pay_by_bank\', \'pay_by_card\', \'credit_note\', \'mark_paid\']',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 56,
            'startFilePos' => 608,
            'endTokenPos' => 70,
            'endFilePos' => 684,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 101,
      ),
      'CUSTOMER_OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'name' => 'CUSTOMER_OPS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'pay_from_wallet\', \'pay_by_bank\', \'pay_by_card\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 81,
            'startFilePos' => 721,
            'endTokenPos' => 89,
            'endFilePos' => 769,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 83,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'op' => 
      array (
        'name' => 'op',
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
        'startLine' => 22,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'aliasName' => NULL,
      ),
      'permission' => 
      array (
        'name' => 'permission',
        'parameters' => 
        array (
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
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'aliasName' => NULL,
      ),
      'name' => 
      array (
        'name' => 'name',
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
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'aliasName' => NULL,
      ),
      'riskLevel' => 
      array (
        'name' => 'riskLevel',
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
        'startLine' => 37,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'aliasName' => NULL,
      ),
      'requiresStepUp' => 
      array (
        'name' => 'requiresStepUp',
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
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'aliasName' => NULL,
      ),
      'requiresApproval' => 
      array (
        'name' => 'requiresApproval',
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
        'startLine' => 47,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand',
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