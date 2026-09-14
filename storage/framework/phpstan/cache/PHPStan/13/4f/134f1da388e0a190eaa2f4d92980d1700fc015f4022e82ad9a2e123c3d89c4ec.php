<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Services\ServiceSpecService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Services\ServiceSpecService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-7c8554a7a96692caf1ad9efc1af82ca7bbae04e4b6db815f07c9eb2aebe19294',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Services/ServiceSpecService.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Services',
    'name' => 'Onhost\\Domain\\Services\\ServiceSpecService',
    'shortName' => 'ServiceSpecService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Declarative service spec (audit §5e-6, §5f-4): the whole configurable state of a service as one document — for a
 * web service PHP version, reverse proxies, default documents, the redirect, the security rules, cron jobs and
 * monitoring; for a game server its name, container image, startup variables and schedules; for a VPS its firewall
 * — read with GET and applied with PUT. Applying compares the document with what the node reports and dispatches
 * only the actions that change something, each through the ordinary workflow (audited, retried, reported), so
 * automation (Terraform-style, CI) can converge a service idempotently instead of scripting clicks.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 455,
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
      'SECTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'name' => 'SECTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'web\' => [\'php\' => \'php\', \'proxies\' => \'proxy\', \'index\' => \'default_docs\', \'redirect\' => \'redirects\', \'security\' => \'security\', \'cron\' => \'cron\', \'monitoring\' => \'monitoring\'], \'managed\' => [\'php\' => \'php\', \'proxies\' => \'proxy\', \'index\' => \'default_docs\', \'redirect\' => \'redirects\', \'security\' => \'security\', \'cron\' => \'cron\', \'monitoring\' => \'monitoring\'], \'game\' => [\'name\' => \'game_settings\', \'image\' => \'startup\', \'variables\' => \'startup\', \'schedules\' => \'schedule_tools\'], \'cloud\' => [\'firewall\' => \'firewall\'], \'mail\' => [\'forwards\' => \'forwards\', \'catchall\' => \'catchall\', \'aliases\' => \'aliases\', \'mailboxes\' => \'mailboxes\', \'autoresponders\' => \'autoresponder\', \'spam\' => \'spam\']]',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 30,
            'startTokenPos' => 60,
            'startFilePos' => 1084,
            'endTokenPos' => 267,
            'endFilePos' => 1818,
          ),
        ),
        'docComment' => '/** Sections per service family and the feature that must be on for each. */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'MAIL_DETAIL_LIMIT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'name' => 'MAIL_DETAIL_LIMIT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '50',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 280,
            'startFilePos' => 1988,
            'endTokenPos' => 280,
            'endFilePos' => 1989,
          ),
        ),
        'docComment' => '/** Per-mailbox details (autoresponders, spam policies) are read for this many mailboxes at most — one panel call each. */',
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
    ),
    'immediateProperties' => 
    array (
      'features' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'name' => 'features',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 33,
        'endColumn' => 74,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'services' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'name' => 'services',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Services\\ServiceService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 77,
        'endColumn' => 117,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'monitor' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'name' => 'monitor',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 120,
        'endColumn' => 158,
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
          'features' => 
          array (
            'name' => 'features',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\ServiceFeatures',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 33,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'services' => 
          array (
            'name' => 'services',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\ServiceService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 77,
            'endColumn' => 117,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'monitor' => 
          array (
            'name' => 'monitor',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 120,
            'endColumn' => 158,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 162,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'aliasName' => NULL,
      ),
      'sectionsFor' => 
      array (
        'name' => 'sectionsFor',
        'parameters' => 
        array (
          'family' => 
          array (
            'name' => 'family',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 40,
            'endColumn' => 53,
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
        'docComment' => '/** @return list<string> the section names a family knows */',
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'aliasName' => NULL,
      ),
      'current' => 
      array (
        'name' => 'current',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 29,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The current document: every section the service\'s features offer; sections the node cannot report carry null. @return array<string,mixed> */',
        'startLine' => 44,
        'endLine' => 147,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'aliasName' => NULL,
      ),
      'apply' => 
      array (
        'name' => 'apply',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 27,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'spec' => 
          array (
            'name' => 'spec',
            'default' => NULL,
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 45,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Commands\\CommandContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 58,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'idempotencyKey' => 
          array (
            'name' => 'idempotencyKey',
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
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 83,
            'endColumn' => 104,
            'parameterIndex' => 3,
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
        'docComment' => '/**
 * Applies the document: only sections present in `$spec` are considered; each that differs from the node\'s
 * state becomes one action (or, for cron and schedules, one action per job to add or remove).
 *
 * @param  array<string,mixed>  $spec
 * @return array{operations:list<array{section:string,action:string,operation_id:string}>, unchanged:list<string>, skipped:list<array{section:string,reason:string}>}
 */',
        'startLine' => 156,
        'endLine' => 446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'aliasName' => NULL,
      ),
      'sameSet' => 
      array (
        'name' => 'sameSet',
        'parameters' => 
        array (
          'a' => 
          array (
            'name' => 'a',
            'default' => NULL,
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
            'startLine' => 449,
            'endLine' => 449,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'b' => 
          array (
            'name' => 'b',
            'default' => NULL,
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
            'startLine' => 449,
            'endLine' => 449,
            'startColumn' => 47,
            'endColumn' => 54,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param  list<array<string,mixed>>  $a @param  list<array<string,mixed>>  $b */',
        'startLine' => 449,
        'endLine' => 454,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceSpecService',
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