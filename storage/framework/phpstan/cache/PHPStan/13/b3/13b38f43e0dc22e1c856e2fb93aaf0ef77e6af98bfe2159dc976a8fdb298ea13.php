<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Organizations\Commands\OrganizationCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Organizations\Commands\OrganizationCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-1dd53c3aacf2d1dd2aa2a08089dd1e7f3fdd8d7e24d0a5eed0bdad59261c890f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Organizations/Commands/OrganizationCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Organizations\\Commands',
    'name' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
    'shortName' => 'OrganizationCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Organization administration, dispatched by `op`:
 *  update{…attributes} · invite{email,role} · cancel_invitation{invitation_id} · change_role{user_id,role} · remove_member{user_id} · create_project{name,…} · transfer_ownership{user_id}
 *  update_project{project_id,…} · archive_project/restore_project{project_id} · add_project_member{project_id,user_id,role} · remove_project_member{project_id,user_id}
 *  assign_service_project{service_id,project_id|null} · rotate_calendar_feed{}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 38,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Commands\\OrganizationCommand',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'implementingClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'update\', \'invite\', \'cancel_invitation\', \'change_role\', \'remove_member\', \'create_project\', \'update_project\', \'archive_project\', \'restore_project\', \'add_project_member\', \'remove_project_member\', \'assign_service_project\', \'rotate_calendar_feed\', \'transfer_ownership\']',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 46,
            'startFilePos' => 762,
            'endTokenPos' => 87,
            'endFilePos' => 1027,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 290,
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
        'startLine' => 19,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Organizations\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'implementingClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'currentClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
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
        'startLine' => 24,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Organizations\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'implementingClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'currentClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
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
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Organizations\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'implementingClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
        'currentClassName' => 'Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand',
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