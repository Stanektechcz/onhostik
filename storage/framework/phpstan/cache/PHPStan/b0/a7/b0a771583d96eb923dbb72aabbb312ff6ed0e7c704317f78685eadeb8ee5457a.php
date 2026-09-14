<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Payments\BankStatementImporter.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '062101d835d713bc2ba3dd5313977755' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5c9d3e34dcfcaef15d1fd08cfa92db10' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'fbab8ab023af4a8662b54f0e95a607d0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'record',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'dcdabe816bde154cbecbb7ffeb5fc3be' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'syncFio',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '452ea36dda90438436c1b27100b31361' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'overview',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '9b4f44a6d854a625cbad197abfc03ae8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'presentLine',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'dd50a79afff10cf7268303505f172806' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'pendingBySymbol',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '6d8fab5325486ad3d6dbe032839d41c2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'minor',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '7a0d89d7be7eccb56120d0226bed46b2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'digits',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'ca5573d2f89344a04713ae611d486b75' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Payments',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'money' => 'Onhost\\Platform\\Money\\Money',
        ),
         'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
         'functionName' => 'column',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Payments',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'bankstatementline' => 'Onhost\\Domain\\Payments\\Models\\BankStatementLine',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            's' => 'Onhost\\Domain\\Payments\\Models\\PaymentStateMachineStates',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'money' => 'Onhost\\Platform\\Money\\Money',
          ),
           'className' => 'Onhost\\Domain\\Payments\\BankStatementImporter',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Payments\\BankStatementImporter.php' => '12e4c2d352c191b5ee4ce9f26c7c3e90ef3b6ad19d971ee47cb1285f57d214d7',
    ),
  ),
));