<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Support\Assistant\ServiceIntent.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Support\Assistant\ServiceIntent
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-12122fc67b9b80bb8dd1d0a7362e6022a4c8d4024beafb5a2bb12dabffcc51de',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Support/Assistant/ServiceIntent.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Support\\Assistant',
    'name' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
    'shortName' => 'ServiceIntent',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * What the customer wants done to a service, read from plain language ("restartuj vps", "zálohuj shop.cz", "nasaď
 * poslední verzi", "aktualizuj wordpress", "zapni redis cache", "obnov staging", "přepni na php 8.3"). The result is a
 * proposal — the panel chat, Discord and the LLM agent show it as a button that the customer confirms; nothing runs
 * without that click. Only actions the service\'s plan and executor offer are proposed.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 97,
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
      'RULES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
        'name' => 'RULES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'/\\b(restartuj|restartovat|restart|reboot|rebootni|rebootnout)\\b/\', \'power\', [\'power_action\' => \'reboot\'], [\'cloud\', \'game\'], [\'Restartovat %s\', \'Restart %s\'], \'power\'], [\'/\\b(vypni|vypnout|shutdown|zastav|zastavit|stopni)\\b(?!.*\\b(monitoring|cache|redis|https|ssl|cdn|hsts)\\b)/\', \'power\', [\'power_action\' => \'shutdown\'], [\'cloud\', \'game\'], [\'Vypnout %s\', \'Shut down %s\'], \'power\'], [\'/\\b(nastartuj|nastartovat|nahod|nahodit|spust server|spustit server|start server|zapni server|zapnout server)\\b/\', \'power\', [\'power_action\' => \'start\'], [\'cloud\', \'game\'], [\'Zapnout %s\', \'Start %s\'], \'power\'], [\'/\\b(zalohuj|zalohovat|zalohu|zaloha|zalohy|backup)\\b/\', \'backup\', [\'kind\' => \'manual\'], [\'web\', \'managed\', \'cloud\', \'game\', \'mail\'], [\'Zálohovat %s\', \'Back up %s\'], \'backups\'], [\'/\\b(deploy|deployni|deploynout|nasad|nasadit|nasadte|nasazeni|nasazen)\\b/\', \'deploy.run\', [], [\'web\', \'managed\'], [\'Nasadit poslední verzi na %s\', \'Deploy the latest version to %s\'], \'deploy\'], [\'/\\b(aktualizuj|aktualizovat|aktualizace|update|updatni|updatuj|upgraduj)\\b[^.]{0,40}\\b(wordpress|wp|plugin|pluginy|sablon|jadro|core)\\b|\\b(wordpress|wp)\\b[^.]{0,40}\\b(aktualizuj|aktualizovat|aktualizace|update)\\b/\', \'wp.update\', [\'what\' => \'all\', \'staged\' => true], [\'web\', \'managed\'], [\'Aktualizovat WordPress na %s\', \'Update WordPress on %s\'], \'wordpress\'], [\'/\\b(zapni|zapnout|aktivuj|aktivovat|enable)\\b[^.]{0,40}\\b(redis|object cache|objektov[a-z]* cache|cache)\\b/\', \'wp.cache\', [\'enabled\' => true], [\'web\', \'managed\'], [\'Zapnout Redis cache na %s\', \'Turn Redis cache on for %s\'], \'wordpress\'], [\'/\\b(vypni|vypnout|deaktivuj|disable)\\b[^.]{0,40}\\b(redis|object cache|cache)\\b/\', \'wp.cache\', [\'enabled\' => false], [\'web\', \'managed\'], [\'Vypnout Redis cache na %s\', \'Turn Redis cache off for %s\'], \'wordpress\'], [\'/\\b(obnov|obnovit|refresh|refreshni|aktualizuj)\\b[^.]{0,30}\\bstaging\\b|\\bstaging\\b[^.]{0,30}\\b(obnov|obnovit|refresh)\\b/\', \'staging.refresh\', [\'databases\' => true], [\'web\', \'managed\'], [\'Obnovit staging z produkce (%s)\', \'Refresh staging from production (%s)\'], \'staging\'], [\'/\\b(zaloz|zalozit|vytvor|vytvorit|create|udelej|udelat)\\b[^.]{0,30}\\bstaging\\b/\', \'staging.create\', [\'databases\' => true], [\'web\', \'managed\'], [\'Založit staging pro %s\', \'Create staging for %s\'], \'staging\'], [\'/\\b(prenes|prenest|push|nahraj|nahrat|preklop)\\b[^.]{0,30}\\bstaging\\b[^.]{0,30}\\b(produkc|ostr)|\\bstaging\\b[^.]{0,30}\\b(do produkce|na ostrou|to production)\\b/\', \'staging.push\', [\'databases\' => true, \'confirm\' => true], [\'web\', \'managed\'], [\'Přenést staging do produkce (%s)\', \'Push staging to production (%s)\'], \'staging\'], [\'/\\b(vymaz|vymazat|vyprazdni|vyprazdnit|promaz|purge|clear|flush)\\b[^.]{0,30}\\b(cdn|cache)\\b|\\bcdn\\b[^.]{0,20}\\b(vymaz|vyprazdni|purge)\\b/\', \'cdn.purge\', [\'settings\' => []], [\'web\', \'managed\'], [\'Vyprázdnit CDN cache pro %s\', \'Purge the CDN cache of %s\'], \'cdn\'], [\'/\\b(vystav|vystavit|obnov|obnovit|issue|renew|zaridit|zarid)\\b[^.]{0,30}\\b(certifikat|ssl|https|lets? ?encrypt)\\b|\\b(certifikat|ssl)\\b[^.]{0,30}\\b(vystav|vystavit|obnov|obnovit)\\b/\', \'ssl.issue\', [], [\'web\', \'managed\'], [\'Vystavit certifikát pro %s\', \'Issue a certificate for %s\'], \'ssl\'], [\'/\\b(vynut|vynutit|force|zapni|zapnout)\\b[^.]{0,20}\\bhttps\\b/\', \'https.force\', [\'enabled\' => true], [\'web\', \'managed\'], [\'Vynutit HTTPS na %s\', \'Force HTTPS on %s\'], \'https\'], [\'/\\b(zapni|zapnout|aktivuj|aktivovat|enable|povol|povolit|turn on)\\b[^.]{0,30}\\b(http ?\\/?3|quic)\\b|\\b(http ?\\/?3|quic)\\b[^.]{0,30}\\b(zapni|zapnout|aktivuj|aktivovat|povol|on)\\b/\', \'http3.set\', [\'enabled\' => true], [\'web\', \'managed\'], [\'Zapnout HTTP/3 na %s\', \'Turn HTTP/3 on for %s\'], \'http3\'], [\'/\\b(vypni|vypnout|deaktivuj|deaktivovat|disable|zakaz|zakazat|turn off)\\b[^.]{0,30}\\b(http ?\\/?3|quic)\\b|\\b(http ?\\/?3|quic)\\b[^.]{0,30}\\b(vypni|vypnout|deaktivuj|zakaz|off)\\b/\', \'http3.set\', [\'enabled\' => false], [\'web\', \'managed\'], [\'Vypnout HTTP/3 na %s\', \'Turn HTTP/3 off for %s\'], \'http3\'], [\'/\\bphp\\s*(5\\.6|7\\.[0-4]|8\\.[0-5])\\b/\', \'php.set\', [\'version\' => \'$1\'], [\'web\', \'managed\'], [\'Přepnout %s na PHP $1\', \'Switch %s to PHP $1\'], \'php\']]',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 40,
            'startTokenPos' => 60,
            'startFilePos' => 922,
            'endTokenPos' => 688,
            'endFilePos' => 5152,
          ),
        ),
        'docComment' => '/** regex on the normalized text, action, params, families, label cs/en (%s = service), feature key */',
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'detect' => 
      array (
        'name' => 'detect',
        'parameters' => 
        array (
          'text' => 
          array (
            'name' => 'text',
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'organization' => 
          array (
            'name' => 'organization',
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
                      'name' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
                      'isIdentifier' => false,
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 49,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 78,
            'endColumn' => 102,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'locale' => 
          array (
            'name' => 'locale',
            'default' => 
            array (
              'code' => '\'cs\'',
              'attributes' => 
              array (
                'startLine' => 45,
                'endLine' => 45,
                'startTokenPos' => 723,
                'startFilePos' => 5446,
                'endTokenPos' => 723,
                'endFilePos' => 5449,
              ),
            ),
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 105,
            'endColumn' => 125,
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
 * @return list<array{kind:string,label:string,service_id:string,action:string,params:array<string,mixed>,confirm:bool,class:string,service:string}>
 */',
        'startLine' => 45,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Support\\Assistant',
        'declaringClassName' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
        'currentClassName' => 'Onhost\\Domain\\Support\\Assistant\\ServiceIntent',
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