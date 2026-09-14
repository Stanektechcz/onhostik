<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Risk\RiskWeights.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Risk\RiskWeights
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-c398be3bfc991ffc383ef1722baa2d65d5e748cda0e6eaf6e52b18f9b47f8951',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Risk/RiskWeights.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Risk',
    'name' => 'Onhost\\Domain\\Risk\\RiskWeights',
    'shortName' => 'RiskWeights',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * One risk model (audit §5n-4): the order check and the referral check keep their own thresholds but read and teach one
 * weight table. A signal that appears in both loops (a disposable mailbox, the cross signals) learns from every staff
 * decision in either loop; a reject makes the signals that fired heavier, a release makes them lighter, one step per
 * decision, within bounds. The table lives in system settings under `risk.weights`; the legacy per-loop tables are read
 * once and merged so a tuned installation keeps what it learned.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 156,
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
      'SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'risk.weights\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 43,
            'startFilePos' => 756,
            'endTokenPos' => 43,
            'endFilePos' => 769,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'FEEDBACK_SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'FEEDBACK_SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'risk.feedback\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 54,
            'startFilePos' => 809,
            'endTokenPos' => 54,
            'endFilePos' => 823,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'LEGACY' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'LEGACY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'orders.risk.weights\', \'loyalty.referral.weights\']',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 67,
            'startFilePos' => 940,
            'endTokenPos' => 72,
            'endFilePos' => 990,
          ),
        ),
        'docComment' => '/** the per-loop tables before §5n-4; merged into the shared one on first read */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 78,
      ),
      'MIN_WEIGHT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'MIN_WEIGHT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 83,
            'startFilePos' => 1024,
            'endTokenPos' => 83,
            'endFilePos' => 1024,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'MAX_WEIGHT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'MAX_WEIGHT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '100',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 94,
            'startFilePos' => 1058,
            'endTokenPos' => 94,
            'endFilePos' => 1060,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'DEFAULTS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'DEFAULTS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    // order check (§5g-4)
    \'referral_flagged\' => 40,
    \'new_account\' => 25,
    \'disposable_email\' => 50,
    \'free_mail_company\' => 10,
    \'rapid_orders\' => 30,
    \'large_first_order\' => 25,
    \'failed_payments\' => 30,
    \'vat_country_mismatch\' => 15,
    \'ip_country_mismatch\' => 20,
    \'turnstile_failed\' => 35,
    // referral check (§5l-4)
    \'same_email_domain\' => 100,
    \'risk_hold\' => 100,
    \'chargeback\' => 100,
    \'same_address\' => 60,
    \'referrer_risk\' => 40,
    \'refused_history\' => 30,
    \'rapid_signup\' => 25,
    \'many_pending\' => 20,
]',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 36,
            'startTokenPos' => 107,
            'startFilePos' => 1155,
            'endTokenPos' => 239,
            'endFilePos' => 1686,
          ),
        ),
        'docComment' => '/** Every signal of every loop with its default weight. */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'settings' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'name' => 'settings',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Settings\\SettingsStore',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 33,
        'endColumn' => 72,
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
          'settings' => 
          array (
            'name' => 'settings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Settings\\SettingsStore',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 33,
            'endColumn' => 72,
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
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 76,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'weights' => 
      array (
        'name' => 'weights',
        'parameters' => 
        array (
          'subset' => 
          array (
            'name' => 'subset',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 46,
                'endLine' => 46,
                'startTokenPos' => 275,
                'startFilePos' => 2047,
                'endTokenPos' => 275,
                'endFilePos' => 2050,
              ),
            ),
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
                      'name' => 'array',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 29,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * The weights in force for the given signals (all of them when null), defaults moved by what staff taught.
 *
 * @param  array<string,int>|null  $subset  signal => default
 * @return array<string,int>
 */',
        'startLine' => 46,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'set' => 
      array (
        'name' => 'set',
        'parameters' => 
        array (
          'weights' => 
          array (
            'name' => 'weights',
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
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 25,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'by' => 
          array (
            'name' => 'by',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 58,
                'endLine' => 58,
                'startTokenPos' => 388,
                'startFilePos' => 2514,
                'endTokenPos' => 388,
                'endFilePos' => 2517,
              ),
            ),
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
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 41,
            'endColumn' => 58,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Staff set weights by hand (the console\'s tuning); unknown signals are refused. @param array<string,int> $weights */',
        'startLine' => 58,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'reset' => 
      array (
        'name' => 'reset',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 74,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'learn' => 
      array (
        'name' => 'learn',
        'parameters' => 
        array (
          'signals' => 
          array (
            'name' => 'signals',
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 27,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'decision' => 
          array (
            'name' => 'decision',
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'step' => 
          array (
            'name' => 'step',
            'default' => 
            array (
              'code' => '5',
              'attributes' => 
              array (
                'startLine' => 90,
                'endLine' => 90,
                'startTokenPos' => 643,
                'startFilePos' => 3764,
                'endTokenPos' => 643,
                'endFilePos' => 3764,
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 61,
            'endColumn' => 73,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'by' => 
          array (
            'name' => 'by',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 90,
                'endLine' => 90,
                'startTokenPos' => 653,
                'startFilePos' => 3781,
                'endTokenPos' => 653,
                'endFilePos' => 3784,
              ),
            ),
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 76,
            'endColumn' => 93,
            'parameterIndex' => 3,
            'isOptional' => true,
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
 * A staff decision teaches the signals that fired: release −step, reject +step, within bounds; the feedback counts
 * per signal (released / rejected) tell the model review how precise each signal is.
 *
 * @param  list<string>  $signals
 * @return array<string,int> the whole table now in force
 */',
        'startLine' => 90,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'feedback' => 
      array (
        'name' => 'feedback',
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
        'docComment' => '/** What staff taught so far, per signal. @return array<string,array{released:int,rejected:int}> */',
        'startLine' => 116,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'clamp' => 
      array (
        'name' => 'clamp',
        'parameters' => 
        array (
          'weight' => 
          array (
            'name' => 'weight',
            'default' => NULL,
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
            'startLine' => 127,
            'endLine' => 127,
            'startColumn' => 34,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 127,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'aliasName' => NULL,
      ),
      'stored' => 
      array (
        'name' => 'stored',
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
        'docComment' => '/** @return array<string,int> the stored table; legacy per-loop tables merged in once */',
        'startLine' => 133,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Risk',
        'declaringClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'implementingClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
        'currentClassName' => 'Onhost\\Domain\\Risk\\RiskWeights',
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