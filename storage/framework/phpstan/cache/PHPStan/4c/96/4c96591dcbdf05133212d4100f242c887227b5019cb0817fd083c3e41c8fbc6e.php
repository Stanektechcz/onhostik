<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Http\Controllers\Api\V1\CheckoutController.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '020e079dddfdcc2131e4ef6d64732ddc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'guestaccountnotification' => 'Onhost\\Domain\\Identity\\Notifications\\GuestAccountNotification',
          'placeordercommand' => 'Onhost\\Domain\\Orders\\Commands\\PlaceOrderCommand',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'quoteservice' => 'Onhost\\Domain\\Orders\\QuoteService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\CheckoutController',
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
      '249ece4b2df79f788302468c1a6f5aca' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'guestaccountnotification' => 'Onhost\\Domain\\Identity\\Notifications\\GuestAccountNotification',
          'placeordercommand' => 'Onhost\\Domain\\Orders\\Commands\\PlaceOrderCommand',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'quoteservice' => 'Onhost\\Domain\\Orders\\QuoteService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\CheckoutController',
         'functionName' => 'guest',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'guestaccountnotification' => 'Onhost\\Domain\\Identity\\Notifications\\GuestAccountNotification',
            'placeordercommand' => 'Onhost\\Domain\\Orders\\Commands\\PlaceOrderCommand',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'quoteservice' => 'Onhost\\Domain\\Orders\\QuoteService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\CheckoutController',
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
      '748ecdbe4c86437645e9237795864603' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Http\\Controllers\\Api\\V1',
         'uses' => 
        array (
          'presenters' => 'App\\Http\\Presenters\\Presenters',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'auth' => 'Illuminate\\Support\\Facades\\Auth',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'guestaccountnotification' => 'Onhost\\Domain\\Identity\\Notifications\\GuestAccountNotification',
          'placeordercommand' => 'Onhost\\Domain\\Orders\\Commands\\PlaceOrderCommand',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'quoteservice' => 'Onhost\\Domain\\Orders\\QuoteService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
          'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
          'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'App\\Http\\Controllers\\Api\\V1\\CheckoutController',
         'functionName' => 'startSession',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Http\\Controllers\\Api\\V1',
           'uses' => 
          array (
            'presenters' => 'App\\Http\\Presenters\\Presenters',
            'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
            'request' => 'Illuminate\\Http\\Request',
            'auth' => 'Illuminate\\Support\\Facades\\Auth',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'emailverificationtoken' => 'Onhost\\Domain\\Identity\\Models\\EmailVerificationToken',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'guestaccountnotification' => 'Onhost\\Domain\\Identity\\Notifications\\GuestAccountNotification',
            'placeordercommand' => 'Onhost\\Domain\\Orders\\Commands\\PlaceOrderCommand',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'quoteservice' => 'Onhost\\Domain\\Orders\\QuoteService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationservice' => 'Onhost\\Domain\\Organizations\\OrganizationService',
            'partnerservice' => 'Onhost\\Domain\\Partners\\PartnerService',
            'turnstile' => 'Onhost\\Domain\\Risk\\Turnstile',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'App\\Http\\Controllers\\Api\\V1\\CheckoutController',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\app\\Http\\Controllers\\Api\\V1\\CheckoutController.php' => '67682ac4c712624a22149719898fd4801fcb39248e8c207e5103ce7e6b0c4c5e',
    ),
  ),
));