<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Http\Controllers\Api\V1\AuthController.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'cb33e22bb22479e66292ae80cf6d27a3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      'fdfe33d6b7b0b0e3ca0bc02d507ba634' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'register',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      '588cbb4639f9464e042d155aaa254262' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'login',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      'df5592f64e2a1abbf2ec97575c6771a0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'logout',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      '56933917de369a585d66c6813257565c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'me',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      'd1ae0fe402da86b35ad819775cbfca5b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'stepUp',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      '8fc766a10b84d11eb64b5ec5b657346b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'requestPasswordReset',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      '5d01518366ff92bcd28c06d1d0c143e5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'confirmPasswordReset',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      '2c684347efe1d998a98b344b7f59de38' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'verifyEmail',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      'd40fa29d2b6c149548f72bbff06128a1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'hash' => 'Illuminate\\Support\\Facades\\Hash',
          'str' => 'Illuminate\\Support\\Str',
          'password' => 'Illuminate\\Validation\\Rules\\Password',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
          'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
          'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
          'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
         'functionName' => 'startSession',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'rememberreferral' => 'App\\Http\\Middleware\\RememberReferral',
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'hash' => 'Illuminate\\Support\\Facades\\Hash',
            'str' => 'Illuminate\\Support\\Str',
            'password' => 'Illuminate\\Validation\\Rules\\Password',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'passwordresetnotification' => 'Onhost\\Domain\\Identity\\Notifications\\PasswordResetNotification',
            'verifyemailnotification' => 'Onhost\\Domain\\Identity\\Notifications\\VerifyEmailNotification',
            'stepupservice' => 'Onhost\\Domain\\Identity\\StepUp\\StepUpService',
            'referralservice' => 'Onhost\\Domain\\Loyalty\\ReferralService',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\AuthController',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\app\\Http\\Controllers\\Api\\V1\\AuthController.php' => '362843609d303666417ba68a9a7a59b32b0f65f303155c12257fc58b2f49d017',
    ),
  ),
));