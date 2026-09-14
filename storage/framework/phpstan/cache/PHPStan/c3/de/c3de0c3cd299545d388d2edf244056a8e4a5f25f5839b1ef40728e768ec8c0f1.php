<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Providers\PlatformServiceProvider.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'e08b9ea1edf97e10f9f5567fb0bc25f1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Providers',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'encrypter' => 'Illuminate\\Contracts\\Encryption\\Encrypter',
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'identitycommandauthorizer' => 'Onhost\\Domain\\Identity\\Authorization\\IdentityCommandAuthorizer',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'aiproviderregistry' => 'Onhost\\Domain\\Support\\Assistant\\AiProviderRegistry',
          'clock' => 'Onhost\\Platform\\Clock\\Clock',
          'systemclock' => 'Onhost\\Platform\\Clock\\SystemClock',
          'commandauthorizer' => 'Onhost\\Platform\\Commands\\CommandAuthorizer',
          'commandbus' => 'Onhost\\Platform\\Commands\\CommandBus',
          'idempotencystore' => 'Onhost\\Platform\\Commands\\IdempotencyStore',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'providercalllogger' => 'Onhost\\Platform\\ProviderHttp\\ProviderCallLogger',
          'providerhttpclient' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'envsecretstore' => 'Onhost\\Platform\\Secrets\\EnvSecretStore',
          'openbaosecretstore' => 'Onhost\\Platform\\Secrets\\OpenBaoSecretStore',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'aapanelwebprovider' => 'Onhost\\Providers\\AaPanel\\AaPanelWebProvider',
          'ipgeoprovider' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
          'httpipgeoprovider' => 'Onhost\\Providers\\IpGeo\\HttpIpGeoProvider',
          'nullipgeoprovider' => 'Onhost\\Providers\\IpGeo\\NullIpGeoProvider',
          'ispconfigwebprovider' => 'Onhost\\Providers\\IspConfig\\IspConfigWebProvider',
          'kubernetesappsprovider' => 'Onhost\\Providers\\Kubernetes\\KubernetesAppsProvider',
          'pbsbackupprovider' => 'Onhost\\Providers\\Pbs\\PbsBackupProvider',
          'powerdnsprovider' => 'Onhost\\Providers\\PowerDns\\PowerDnsProvider',
          'proxmoxcomputeprovider' => 'Onhost\\Providers\\Proxmox\\ProxmoxComputeProvider',
          'pterodactylgameprovider' => 'Onhost\\Providers\\Pterodactyl\\PterodactylGameProvider',
          'subregregistrarprovider' => 'Onhost\\Providers\\Subreg\\SubregRegistrarProvider',
          'wedosregistrarprovider' => 'Onhost\\Providers\\Wedos\\WedosRegistrarProvider',
          'wedoszonednsprovider' => 'Onhost\\Providers\\Wedos\\WedosZoneDnsProvider',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Providers\\PlatformServiceProvider',
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
      '593cdd0c7305974be8c09bd942f896e1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Providers',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'encrypter' => 'Illuminate\\Contracts\\Encryption\\Encrypter',
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'identitycommandauthorizer' => 'Onhost\\Domain\\Identity\\Authorization\\IdentityCommandAuthorizer',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'aiproviderregistry' => 'Onhost\\Domain\\Support\\Assistant\\AiProviderRegistry',
          'clock' => 'Onhost\\Platform\\Clock\\Clock',
          'systemclock' => 'Onhost\\Platform\\Clock\\SystemClock',
          'commandauthorizer' => 'Onhost\\Platform\\Commands\\CommandAuthorizer',
          'commandbus' => 'Onhost\\Platform\\Commands\\CommandBus',
          'idempotencystore' => 'Onhost\\Platform\\Commands\\IdempotencyStore',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'providercalllogger' => 'Onhost\\Platform\\ProviderHttp\\ProviderCallLogger',
          'providerhttpclient' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'envsecretstore' => 'Onhost\\Platform\\Secrets\\EnvSecretStore',
          'openbaosecretstore' => 'Onhost\\Platform\\Secrets\\OpenBaoSecretStore',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'aapanelwebprovider' => 'Onhost\\Providers\\AaPanel\\AaPanelWebProvider',
          'ipgeoprovider' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
          'httpipgeoprovider' => 'Onhost\\Providers\\IpGeo\\HttpIpGeoProvider',
          'nullipgeoprovider' => 'Onhost\\Providers\\IpGeo\\NullIpGeoProvider',
          'ispconfigwebprovider' => 'Onhost\\Providers\\IspConfig\\IspConfigWebProvider',
          'kubernetesappsprovider' => 'Onhost\\Providers\\Kubernetes\\KubernetesAppsProvider',
          'pbsbackupprovider' => 'Onhost\\Providers\\Pbs\\PbsBackupProvider',
          'powerdnsprovider' => 'Onhost\\Providers\\PowerDns\\PowerDnsProvider',
          'proxmoxcomputeprovider' => 'Onhost\\Providers\\Proxmox\\ProxmoxComputeProvider',
          'pterodactylgameprovider' => 'Onhost\\Providers\\Pterodactyl\\PterodactylGameProvider',
          'subregregistrarprovider' => 'Onhost\\Providers\\Subreg\\SubregRegistrarProvider',
          'wedosregistrarprovider' => 'Onhost\\Providers\\Wedos\\WedosRegistrarProvider',
          'wedoszonednsprovider' => 'Onhost\\Providers\\Wedos\\WedosZoneDnsProvider',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Providers\\PlatformServiceProvider',
         'functionName' => 'register',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Providers',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'encrypter' => 'Illuminate\\Contracts\\Encryption\\Encrypter',
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'identitycommandauthorizer' => 'Onhost\\Domain\\Identity\\Authorization\\IdentityCommandAuthorizer',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'aiproviderregistry' => 'Onhost\\Domain\\Support\\Assistant\\AiProviderRegistry',
            'clock' => 'Onhost\\Platform\\Clock\\Clock',
            'systemclock' => 'Onhost\\Platform\\Clock\\SystemClock',
            'commandauthorizer' => 'Onhost\\Platform\\Commands\\CommandAuthorizer',
            'commandbus' => 'Onhost\\Platform\\Commands\\CommandBus',
            'idempotencystore' => 'Onhost\\Platform\\Commands\\IdempotencyStore',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'providercalllogger' => 'Onhost\\Platform\\ProviderHttp\\ProviderCallLogger',
            'providerhttpclient' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'envsecretstore' => 'Onhost\\Platform\\Secrets\\EnvSecretStore',
            'openbaosecretstore' => 'Onhost\\Platform\\Secrets\\OpenBaoSecretStore',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'aapanelwebprovider' => 'Onhost\\Providers\\AaPanel\\AaPanelWebProvider',
            'ipgeoprovider' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
            'httpipgeoprovider' => 'Onhost\\Providers\\IpGeo\\HttpIpGeoProvider',
            'nullipgeoprovider' => 'Onhost\\Providers\\IpGeo\\NullIpGeoProvider',
            'ispconfigwebprovider' => 'Onhost\\Providers\\IspConfig\\IspConfigWebProvider',
            'kubernetesappsprovider' => 'Onhost\\Providers\\Kubernetes\\KubernetesAppsProvider',
            'pbsbackupprovider' => 'Onhost\\Providers\\Pbs\\PbsBackupProvider',
            'powerdnsprovider' => 'Onhost\\Providers\\PowerDns\\PowerDnsProvider',
            'proxmoxcomputeprovider' => 'Onhost\\Providers\\Proxmox\\ProxmoxComputeProvider',
            'pterodactylgameprovider' => 'Onhost\\Providers\\Pterodactyl\\PterodactylGameProvider',
            'subregregistrarprovider' => 'Onhost\\Providers\\Subreg\\SubregRegistrarProvider',
            'wedosregistrarprovider' => 'Onhost\\Providers\\Wedos\\WedosRegistrarProvider',
            'wedoszonednsprovider' => 'Onhost\\Providers\\Wedos\\WedosZoneDnsProvider',
            'runtimeexception' => 'RuntimeException',
          ),
           'className' => 'App\\Providers\\PlatformServiceProvider',
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
      'dba119d4505c18c2f87e365350158af1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Providers',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'encrypter' => 'Illuminate\\Contracts\\Encryption\\Encrypter',
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'sanctum' => 'Laravel\\Sanctum\\Sanctum',
          'identitycommandauthorizer' => 'Onhost\\Domain\\Identity\\Authorization\\IdentityCommandAuthorizer',
          'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'aiproviderregistry' => 'Onhost\\Domain\\Support\\Assistant\\AiProviderRegistry',
          'clock' => 'Onhost\\Platform\\Clock\\Clock',
          'systemclock' => 'Onhost\\Platform\\Clock\\SystemClock',
          'commandauthorizer' => 'Onhost\\Platform\\Commands\\CommandAuthorizer',
          'commandbus' => 'Onhost\\Platform\\Commands\\CommandBus',
          'idempotencystore' => 'Onhost\\Platform\\Commands\\IdempotencyStore',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'providercalllogger' => 'Onhost\\Platform\\ProviderHttp\\ProviderCallLogger',
          'providerhttpclient' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'envsecretstore' => 'Onhost\\Platform\\Secrets\\EnvSecretStore',
          'openbaosecretstore' => 'Onhost\\Platform\\Secrets\\OpenBaoSecretStore',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'aapanelwebprovider' => 'Onhost\\Providers\\AaPanel\\AaPanelWebProvider',
          'ipgeoprovider' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
          'httpipgeoprovider' => 'Onhost\\Providers\\IpGeo\\HttpIpGeoProvider',
          'nullipgeoprovider' => 'Onhost\\Providers\\IpGeo\\NullIpGeoProvider',
          'ispconfigwebprovider' => 'Onhost\\Providers\\IspConfig\\IspConfigWebProvider',
          'kubernetesappsprovider' => 'Onhost\\Providers\\Kubernetes\\KubernetesAppsProvider',
          'pbsbackupprovider' => 'Onhost\\Providers\\Pbs\\PbsBackupProvider',
          'powerdnsprovider' => 'Onhost\\Providers\\PowerDns\\PowerDnsProvider',
          'proxmoxcomputeprovider' => 'Onhost\\Providers\\Proxmox\\ProxmoxComputeProvider',
          'pterodactylgameprovider' => 'Onhost\\Providers\\Pterodactyl\\PterodactylGameProvider',
          'subregregistrarprovider' => 'Onhost\\Providers\\Subreg\\SubregRegistrarProvider',
          'wedosregistrarprovider' => 'Onhost\\Providers\\Wedos\\WedosRegistrarProvider',
          'wedoszonednsprovider' => 'Onhost\\Providers\\Wedos\\WedosZoneDnsProvider',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Providers\\PlatformServiceProvider',
         'functionName' => 'boot',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Providers',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'encrypter' => 'Illuminate\\Contracts\\Encryption\\Encrypter',
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            'sanctum' => 'Laravel\\Sanctum\\Sanctum',
            'identitycommandauthorizer' => 'Onhost\\Domain\\Identity\\Authorization\\IdentityCommandAuthorizer',
            'personalaccesstoken' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'aiproviderregistry' => 'Onhost\\Domain\\Support\\Assistant\\AiProviderRegistry',
            'clock' => 'Onhost\\Platform\\Clock\\Clock',
            'systemclock' => 'Onhost\\Platform\\Clock\\SystemClock',
            'commandauthorizer' => 'Onhost\\Platform\\Commands\\CommandAuthorizer',
            'commandbus' => 'Onhost\\Platform\\Commands\\CommandBus',
            'idempotencystore' => 'Onhost\\Platform\\Commands\\IdempotencyStore',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'providercalllogger' => 'Onhost\\Platform\\ProviderHttp\\ProviderCallLogger',
            'providerhttpclient' => 'Onhost\\Platform\\ProviderHttp\\ProviderHttpClient',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'envsecretstore' => 'Onhost\\Platform\\Secrets\\EnvSecretStore',
            'openbaosecretstore' => 'Onhost\\Platform\\Secrets\\OpenBaoSecretStore',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'aapanelwebprovider' => 'Onhost\\Providers\\AaPanel\\AaPanelWebProvider',
            'ipgeoprovider' => 'Onhost\\Providers\\Contracts\\IpGeoProvider',
            'httpipgeoprovider' => 'Onhost\\Providers\\IpGeo\\HttpIpGeoProvider',
            'nullipgeoprovider' => 'Onhost\\Providers\\IpGeo\\NullIpGeoProvider',
            'ispconfigwebprovider' => 'Onhost\\Providers\\IspConfig\\IspConfigWebProvider',
            'kubernetesappsprovider' => 'Onhost\\Providers\\Kubernetes\\KubernetesAppsProvider',
            'pbsbackupprovider' => 'Onhost\\Providers\\Pbs\\PbsBackupProvider',
            'powerdnsprovider' => 'Onhost\\Providers\\PowerDns\\PowerDnsProvider',
            'proxmoxcomputeprovider' => 'Onhost\\Providers\\Proxmox\\ProxmoxComputeProvider',
            'pterodactylgameprovider' => 'Onhost\\Providers\\Pterodactyl\\PterodactylGameProvider',
            'subregregistrarprovider' => 'Onhost\\Providers\\Subreg\\SubregRegistrarProvider',
            'wedosregistrarprovider' => 'Onhost\\Providers\\Wedos\\WedosRegistrarProvider',
            'wedoszonednsprovider' => 'Onhost\\Providers\\Wedos\\WedosZoneDnsProvider',
            'runtimeexception' => 'RuntimeException',
          ),
           'className' => 'App\\Providers\\PlatformServiceProvider',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\app\\Providers\\PlatformServiceProvider.php' => '1780f10a5eb4687620c7c003910b15b26f88d771fbe566f078d1bf37b0eb25c5',
    ),
  ),
));