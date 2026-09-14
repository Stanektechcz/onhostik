<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Orders\CheckoutService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '3b9ec36317eeb557c0eafe69c24c9dfa' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '65d0befa8bb2595a54876935a29d8365' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '034751462267dbc1b886dc4cf7330b9f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'placeOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '1f6e858907439456fa3cffe1bf9b4d62' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'markPaid',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      'd992f0fea9d49b1cc8ee632feb653a6b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'review',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '4562a5b1d48adccff4f49ac76c0846cc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'transition',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '6c14fb32efb5ad8fda3554591a036189' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'fingerprint',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '8d20b288bf8249c6ffe083393a81a168' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'requiredDocuments',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      'aef34ed95e18676892dc2b5d9f0dc1f1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'recordConsents',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      'e75d3e676191abb98331d4b1da736c73' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'hasDomain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      '4c7fb336279fd2c19828622fc36fb1be' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Orders',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
          'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
          'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
          'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
          'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
         'functionName' => 'allocateNumber',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Orders',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'consent' => 'Onhost\\Domain\\Orders\\Models\\Consent',
            'consentdocument' => 'Onhost\\Domain\\Orders\\Models\\ConsentDocument',
            'order' => 'Onhost\\Domain\\Orders\\Models\\Order',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'quote' => 'Onhost\\Domain\\Orders\\Models\\Quote',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'paymentintent' => 'Onhost\\Domain\\Payments\\Models\\PaymentIntent',
            'paymentservice' => 'Onhost\\Domain\\Payments\\PaymentService',
            'wallethold' => 'Onhost\\Domain\\WalletLedger\\Models\\WalletHold',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'publicid' => 'Onhost\\Platform\\Ids\\PublicId',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Orders\\CheckoutService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Orders\\CheckoutService.php' => '9c3d85b6599487f7bfce65fcdc29e4eb861a1b108fa71f787f0344ae0e6b4404',
    ),
  ),
));