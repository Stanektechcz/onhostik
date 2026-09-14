<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\OperationsBoard.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\OperationsBoard
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-4ef6000c337ed893749a23b298a9232748f63fae775f832c90e9da0b116bb634',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/OperationsBoard.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning',
    'name' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
    'shortName' => 'OperationsBoard',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Staff operations board (audit §5e-3): what is stuck across all tenants right now — operations waiting on a node
 * error, failed in the last day, running suspiciously long — and the nodes behind them with their recent success and
 * failure counts. The same numbers drive the automatic drain: a node whose operations keep failing on transient
 * errors stops receiving new placements (state `draining`, the scheduler only picks `active` nodes) and is put back
 * once its operations succeed again; staff can drain or resume a node by hand at any time.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 180,
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
      'LONG_RUNNING_MINUTES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'name' => 'LONG_RUNNING_MINUTES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '10',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 88,
            'startFilePos' => 1187,
            'endTokenPos' => 88,
            'endFilePos' => 1188,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'DRAIN_FAILURES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'name' => 'DRAIN_FAILURES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 99,
            'startFilePos' => 1226,
            'endTokenPos' => 99,
            'endFilePos' => 1226,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'DRAIN_WINDOW_MINUTES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'name' => 'DRAIN_WINDOW_MINUTES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '15',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 110,
            'startFilePos' => 1270,
            'endTokenPos' => 110,
            'endFilePos' => 1271,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'RESUME_PROBES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'name' => 'RESUME_PROBES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 127,
            'endLine' => 127,
            'startTokenPos' => 1634,
            'startFilePos' => 7857,
            'endTokenPos' => 1634,
            'endFilePos' => 7857,
          ),
        ),
        'docComment' => '/** Healthy probes in a row before an automatically drained node comes back without any operation succeeding. */',
        'attributes' => 
        array (
        ),
        'startLine' => 127,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
    ),
    'immediateProperties' => 
    array (
      'outbox' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'name' => 'outbox',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
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
        'endColumn' => 72,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'audit' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'name' => 'audit',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Audit\\AuditRecorder',
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
        'startColumn' => 75,
        'endColumn' => 111,
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
          'outbox' => 
          array (
            'name' => 'outbox',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
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
            'endColumn' => 72,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'audit' => 
          array (
            'name' => 'audit',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Audit\\AuditRecorder',
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
            'startColumn' => 75,
            'endColumn' => 111,
            'parameterIndex' => 1,
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
        'endColumn' => 115,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'board' => 
      array (
        'name' => 'board',
        'parameters' => 
        array (
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
        'docComment' => '/** @return array{stalled:list<array<string,mixed>>, failed:list<array<string,mixed>>, long_running:list<array<string,mixed>>, nodes:list<array<string,mixed>>, counts:array<string,int>} */',
        'startLine' => 37,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'nodes' => 
      array (
        'name' => 'nodes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Support\\Collection',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Per node: state, the instance\'s health, and the operations of the last window (succeeded, transient failures),
 * plus whether the automatic drain would act.
 *
 * @return Collection<int, array<string,mixed>>
 */',
        'startLine' => 60,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'autoDrain' => 
      array (
        'name' => 'autoDrain',
        'parameters' => 
        array (
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
 * Scheduled every few minutes: drain nodes that only fail, resume the ones the automatic drain took out once they
 * succeed again. Staff-set states are never touched (only nodes tagged `auto_drain` are resumed automatically).
 *
 * @return array{checked:int, drained:int, resumed:int}
 */',
        'startLine' => 91,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'setState' => 
      array (
        'name' => 'setState',
        'parameters' => 
        array (
          'node' => 
          array (
            'name' => 'node',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'state' => 
          array (
            'name' => 'state',
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
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'reason' => 
          array (
            'name' => 'reason',
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
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 57,
            'endColumn' => 71,
            'parameterIndex' => 2,
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
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 74,
            'endColumn' => 96,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'automatic' => 
          array (
            'name' => 'automatic',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 130,
                'endLine' => 130,
                'startTokenPos' => 1672,
                'startFilePos' => 8142,
                'endTokenPos' => 1672,
                'endFilePos' => 8146,
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
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 99,
            'endColumn' => 121,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'failed' => 
          array (
            'name' => 'failed',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 130,
                'endLine' => 130,
                'startTokenPos' => 1681,
                'startFilePos' => 8165,
                'endTokenPos' => 1682,
                'endFilePos' => 8166,
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
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 124,
            'endColumn' => 141,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'keep' => 
          array (
            'name' => 'keep',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 130,
                'endLine' => 130,
                'startTokenPos' => 1691,
                'startFilePos' => 8182,
                'endTokenPos' => 1691,
                'endFilePos' => 8186,
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
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 144,
            'endColumn' => 161,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Drain or resume a node; staff and the automatic drain share this path (audit + `node.drained` / `node.resumed`). @param list<array<string,mixed>> $failed */',
        'startLine' => 130,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'failedOperations' => 
      array (
        'name' => 'failedOperations',
        'parameters' => 
        array (
          'node' => 
          array (
            'name' => 'node',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 39,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The transient failures behind an automatic drain (id, step, message) — what staff see in the notification. @return list<array<string,mixed>> */',
        'startLine' => 152,
        'endLine' => 159,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'probeOk' => 
      array (
        'name' => 'probeOk',
        'parameters' => 
        array (
          'node' => 
          array (
            'name' => 'node',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 161,
            'endLine' => 161,
            'startColumn' => 30,
            'endColumn' => 39,
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
        'startLine' => 161,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'aliasName' => NULL,
      ),
      'instanceName' => 
      array (
        'name' => 'instanceName',
        'parameters' => 
        array (
          'instanceId' => 
          array (
            'name' => 'instanceId',
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
            'startLine' => 174,
            'endLine' => 174,
            'startColumn' => 35,
            'endColumn' => 52,
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
        'startLine' => 174,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\OperationsBoard',
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