<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Compliance\ComplianceService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '26793df3f691a08c402882648f21f2e6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '211f35fe345c24e67483c852cfa1c0e8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '568476fcc83e005e847bfe0454610282' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'openCyberIncident',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '4381416e9ff2fd4c1fa9142cc6562263' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'nis2InScope',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'b8229f663ebc773a5e75632a2a978547' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'transitionCyberIncident',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'bd80a1f71320aae38fee5e9ff49c008b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'attachEvidence',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '5d8321830ac73933ab9d0848dd2c432c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'startTimer',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '5eb19ff2cf9e77fae37ec149ebaa14ce' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'submitTimer',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '3137d6517e7466030620ea3309683638' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'waiveTimer',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '0dac338156c09fc6d83c85a652b0a90b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'tickTimers',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '8cb9b96c04d328bba7b8a0c1ddd9868f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'reportAbuse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'c5255d06e1253471eff9ad347e3dbe29' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'triageAbuse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '62dc6aa2ffd9e5fb807d4de065e62bbd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'notifyCustomer',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '788525f198992f237cde0f03b1130df7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'actionAbuse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'b2d181c2793a4a5b8332a5d88b71e5dd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'appealAbuse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'a753b38ed44a5113dc1d052e8ce5d1e2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'closeAbuse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '926e9c3de6dbe961ff351968b3997c27' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'actionLabel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '2dc11b70863c13ef821067a58b260c25' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'serviceForUrl',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '5d61d661ad88ce2c708ac446b6e61256' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'requestData',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '91630ce127dd4a38a0a9a348e1f60e1d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'downloadLink',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '7ba59420cd4b70fa26723583de4b9a85' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'redeemLink',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '4c9f66fae967932be63b24edac488996' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'assertDeletable',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'cca40ea45cd17fbd1e35de6bfe7ee342' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'setLegalHold',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '44249b3772f7faa8bd5e7facaaf5812e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'processDataRequests',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '685a2f69e80c7363dd1728cda6180e72' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'buildExport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'b6ec3e394bed3e11f61f11e627b98d40' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'executeDeletion',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      '0668c7184675abeaebb2765efff55d81' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Compliance',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'url' => 'Illuminate\\Support\\Facades\\URL',
          'str' => 'Illuminate\\Support\\Str',
          'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
          'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
          'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
          'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
          'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
          'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
         'functionName' => 'withNumber',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Compliance',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'url' => 'Illuminate\\Support\\Facades\\URL',
            'str' => 'Illuminate\\Support\\Str',
            'abusecase' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
            'compliancetimer' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
            'cyberincident' => 'Onhost\\Domain\\Compliance\\Models\\CyberIncident',
            'datarequest' => 'Onhost\\Domain\\Compliance\\Models\\DataRequest',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'incidentservice' => 'Onhost\\Domain\\Incidents\\IncidentService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'organizationmembership' => 'Onhost\\Domain\\Organizations\\Models\\OrganizationMembership',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'ticket' => 'Onhost\\Domain\\Support\\Models\\Ticket',
            'ticketservice' => 'Onhost\\Domain\\Support\\TicketService',
            'auditevent' => 'Onhost\\Platform\\Audit\\AuditEvent',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Compliance\\ComplianceService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Compliance\\ComplianceService.php' => '0f4fa446c5f5ec9367f9e30a4902bd75a57fa546128e6bec763855e5c4665f5d',
    ),
  ),
));