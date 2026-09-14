<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\AutomationLedger.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\AutomationLedger
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-24c574f673acf9cd0ce3ade6cb7f04c9ef5770bf210d6dd5bba1f6daf3c87034',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/AutomationLedger.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning',
    'name' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
    'shortName' => 'AutomationLedger',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * What the automations did last (audit §5f-2) and which of them staff switched off (§5g-7): every scheduled rule
 * records its last run and the counts it reported, so the staff console shows "usage watch · before 40 min ·
 * 212 checked, 3 warned" instead of a static list. A switched-off rule still runs on schedule but records a skip,
 * so the console shows it is off rather than silent. The two rules the platform cannot live without (dispatching
 * operations, probing the panels) have no switch — the provisioning freeze is the tool for an incident.
 * Runs are kept in the cache for six weeks; the switches live in the settings table.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 204,
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
      'TTL_SECONDS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'name' => 'TTL_SECONDS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '42 * 86400',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 77,
            'startFilePos' => 1142,
            'endTokenPos' => 81,
            'endFilePos' => 1151,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'SETTING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'name' => 'SETTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'automation.disabled\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 92,
            'startFilePos' => 1182,
            'endTokenPos' => 92,
            'endFilePos' => 1202,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 49,
      ),
      'SETTING_ON' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'name' => 'SETTING_ON',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'automation.enabled\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 105,
            'startFilePos' => 1312,
            'endTokenPos' => 105,
            'endFilePos' => 1331,
          ),
        ),
        'docComment' => '/** rules that are off unless staff switched them on (`default_off`) */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
      'STALE_MINUTES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'name' => 'STALE_MINUTES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 118,
            'startFilePos' => 1457,
            'endTokenPos' => 118,
            'endFilePos' => 1457,
          ),
        ),
        'docComment' => '/** Older than this and the scheduler / the queue worker count as down (minutes). */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'RULES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'name' => 'RULES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'key\' => \'provisioning.tick\', \'command\' => \'onhost:provisioning:tick\', \'name\' => \'Provisioning\', \'does\' => \'spouští splatné operace a oživuje zaseknuté\', \'runs\' => \'každou minutu\', \'switchable\' => false], [\'key\' => \'integrations.health\', \'command\' => \'onhost:integrations:health\', \'name\' => \'Zdraví integrací\', \'does\' => \'ping každé instance panelu, výpadek = interní oznámení; hlídá i frontu úloh\', \'runs\' => \'každou minutu\', \'switchable\' => false], [\'key\' => \'usage.watch\', \'command\' => \'onhost:services:usage-watch\', \'name\' => \'Hlídání využití tarifu\', \'does\' => \'při 85/95 % upozorní; s politikou služby objedná vyšší tarif z kreditu\', \'runs\' => \'každou hodinu\', \'switchable\' => true], [\'key\' => \'renewal.guard\', \'command\' => \'onhost:billing:renewal-guard\', \'name\' => \'Ochrana obnov\', \'does\' => \'týden před obnovou porovná kredit; chybí-li, dobije z uložené karty nebo upozorní\', \'runs\' => \'denně 07:35\', \'switchable\' => true], [\'key\' => \'operations.board\', \'command\' => \'onhost:provisioning:board\', \'name\' => \'Automatické odstavení uzlu\', \'does\' => \'uzel s přechodnými chybami odstaví; po zdravých sondách vrátí\', \'runs\' => \'každých 5 minut\', \'switchable\' => true], [\'key\' => \'nodes.check\', \'command\' => \'onhost:nodes:check\', \'name\' => \'Prerekvizity uzlů\', \'does\' => \'API, verze PHP, cron API, shellová sonda, herní panel; funkce se nabízejí jen kde fungují\', \'runs\' => \'denně 05:20\', \'switchable\' => true], [\'key\' => \'certificates.issue\', \'command\' => \'onhost:certificates:issue-pending\', \'name\' => \'Automatické certifikáty\', \'does\' => \'vystaví certifikát, jakmile DNS domény ukazuje na nás\', \'runs\' => \'každých 15 minut\', \'switchable\' => true], [\'key\' => \'backups.run\', \'command\' => \'onhost:backups:run\', \'name\' => \'Zálohy podle plánu\', \'does\' => \'spouští zálohy podle tarifu a uklízí staré generace\', \'runs\' => \'každých 15 minut\', \'switchable\' => true], [\'key\' => \'digest.weekly\', \'command\' => \'onhost:digest:weekly\', \'name\' => \'Týdenní přehled zákazníkům\', \'does\' => \'obnovy, kredit, zálohy, monitoring; frekvenci určuje zákazník\', \'runs\' => \'pondělí 07:00\', \'switchable\' => true], [\'key\' => \'digest.staff\', \'command\' => \'onhost:digest:staff-daily\', \'name\' => \'Denní provozní přehled\', \'does\' => \'zaseknuté operace, selhání, pohledávky, kapacita\', \'runs\' => \'denně 07:15\', \'switchable\' => true], [\'key\' => \'commerce.prune\', \'command\' => \'onhost:commerce:prune\', \'name\' => \'Úklid obchodu\', \'does\' => \'maže staré nabídky a opuštěné košíky\', \'runs\' => \'denně 04:20\', \'switchable\' => true], [\'key\' => \'order.risk\', \'command\' => null, \'name\' => \'Kontrola objednávek\', \'does\' => \'skóre rizika při objednávce; nad prahem čeká zaplacená objednávka na rozhodnutí\', \'runs\' => \'při každé objednávce\', \'switchable\' => true], [\'key\' => \'partners.auto_approve\', \'command\' => null, \'name\' => \'Automatické schválení smluvních změn\', \'does\' => \'zámek sazby do 6 měsíců a výplatní podmínky partnera s čistým rokem schválí bez financí; model a white-label vždy čekají\', \'runs\' => \'při každé žádosti\', \'switchable\' => true], [\'key\' => \'capacity.auto_order\', \'command\' => \'onhost:provisioning:capacity-forecast\', \'name\' => \'Automatický nákup uzlů\', \'does\' => \'schválí a objedná uzel u dodavatele, když fond dochází a instance umí objednávat (jinak jen návrh pro provoz)\', \'runs\' => \'denně 03:45\', \'switchable\' => true, \'default_off\' => true], [\'key\' => \'oncall.escalate\', \'command\' => \'onhost:oncall:escalate\', \'name\' => \'Eskalace on-call\', \'does\' => \'alert, který nikdo nepotvrdil do X minut, znovu zavolá pager s vyšší závažností (nejvýš N×)\', \'runs\' => \'každou minutu\', \'switchable\' => true], [\'key\' => \'platform.backup\', \'command\' => \'onhost:platform:backup\', \'name\' => \'Záloha platformy\', \'does\' => \'denně zálohuje databázi control plane a soukromé soubory (doklady, důkazy, exporty) na zálohovací disk a ověřuje obnovitelnost\', \'runs\' => \'denně 02:15, ověření 03:15\', \'switchable\' => true], [\'key\' => \'oncall.remind\', \'command\' => \'onhost:oncall:remind\', \'name\' => \'Připomenutí on-call směny\', \'does\' => \'hodinu před začátkem směny pošle jejímu držiteli upozornění a e-mail\', \'runs\' => \'každých 5 minut\', \'switchable\' => true], [\'key\' => \'game.templates.verify\', \'command\' => \'onhost:game:templates:verify\', \'name\' => \'Kontrola herních šablon\', \'does\' => \'porovná namapované šablony s eggy panelu; chybějící odebere z nabídky a ohlásí provozu, povinné proměnné načte znovu\', \'runs\' => \'denně 05:10\', \'switchable\' => true], [\'key\' => \'files.scan\', \'command\' => \'onhost:files:scan\', \'name\' => \'Antivirová kontrola souborů\', \'does\' => \'znovu prověří soubory, které ClamAV při nahrání nestihl; infikované smaže a nahlásí bezpečnosti\', \'runs\' => \'každých 10 minut\', \'switchable\' => false], [\'key\' => \'files.prune\', \'command\' => \'onhost:files:prune\', \'name\' => \'Retence souborů\', \'does\' => \'maže důkazy z marketplace po retenční lhůtě a exporty dat po expiraci\', \'runs\' => \'denně 04:25\', \'switchable\' => true], [\'key\' => \'game.migration\', \'command\' => null, \'name\' => \'Stěhování herních serverů\', \'does\' => \'záloha, nový server na jiném uzlu, přenos archivu, přepnutí adresy, úklid — bez zásahu do hry\', \'runs\' => \'na pokyn obsluhy\', \'switchable\' => false]]',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 59,
            'startTokenPos' => 131,
            'startFilePos' => 1713,
            'endTokenPos' => 1064,
            'endFilePos' => 7285,
          ),
        ),
        'docComment' => '/** The rules the console lists: key, command, human name, what it does, when it runs, whether staff may switch it off. @return list<array{key:string,command:?string,name:string,does:string,runs:string,switchable:bool}> */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'cache' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'name' => 'cache',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Contracts\\Cache\\Repository',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 33,
        'endColumn' => 71,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'settings' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
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
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 74,
        'endColumn' => 113,
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
          'cache' => 
          array (
            'name' => 'cache',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Cache\\Repository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 33,
            'endColumn' => 71,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 74,
            'endColumn' => 113,
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
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 117,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'record' => 
      array (
        'name' => 'record',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 28,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'stats' => 
          array (
            'name' => 'stats',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 64,
                'endLine' => 64,
                'startTokenPos' => 1113,
                'startFilePos' => 7523,
                'endTokenPos' => 1114,
                'endFilePos' => 7524,
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
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'error' => 
          array (
            'name' => 'error',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 64,
                'endLine' => 64,
                'startTokenPos' => 1124,
                'startFilePos' => 7544,
                'endTokenPos' => 1124,
                'endFilePos' => 7547,
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
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 60,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
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
        'docComment' => '/** @param array<string,int|string|bool|null> $stats */',
        'startLine' => 64,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'last' => 
      array (
        'name' => 'last',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 26,
            'endColumn' => 36,
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
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return array{at:?string, stats:array<string,mixed>, error:?string}|null */',
        'startLine' => 70,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'rule' => 
      array (
        'name' => 'rule',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 26,
            'endColumn' => 36,
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
        'docComment' => '/** @return array{key:string,command:?string,name:string,does:string,runs:string,switchable:bool} */',
        'startLine' => 78,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'enabled' => 
      array (
        'name' => 'enabled',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 29,
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
        'docComment' => '/** Whether the rule runs; a rule without a switch is always on. */',
        'startLine' => 89,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'switchedOn' => 
      array (
        'name' => 'switchedOn',
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
        'docComment' => '/** @return list<string> default-off rules staff switched on */',
        'startLine' => 101,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'disabled' => 
      array (
        'name' => 'disabled',
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
        'docComment' => '/** @return list<string> */',
        'startLine' => 107,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'setEnabled' => 
      array (
        'name' => 'setEnabled',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 117,
            'endLine' => 117,
            'startColumn' => 32,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'enabled' => 
          array (
            'name' => 'enabled',
            'default' => NULL,
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
            'startLine' => 117,
            'endLine' => 117,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 1,
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
                'startLine' => 117,
                'endLine' => 117,
                'startTokenPos' => 1605,
                'startFilePos' => 9608,
                'endTokenPos' => 1605,
                'endFilePos' => 9611,
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
            'startLine' => 117,
            'endLine' => 117,
            'startColumn' => 60,
            'endColumn' => 77,
            'parameterIndex' => 2,
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
 * Staff switch (§5g-7); the change lands in the settings table (audited through the command bus that calls this).
 *
 * @return array<string,mixed> the rule row as the console shows it
 */',
        'startLine' => 117,
        'endLine' => 137,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'off' => 
      array (
        'name' => 'off',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 143,
            'endLine' => 143,
            'startColumn' => 25,
            'endColumn' => 35,
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
        'docComment' => '/**
 * True when staff switched the rule off — the caller returns without doing anything; the skip is recorded so the
 * console shows "switched off" with a fresh timestamp rather than a rule that went quiet.
 */',
        'startLine' => 143,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'overview' => 
      array (
        'name' => 'overview',
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
        'docComment' => '/** Every rule with its last run and its switch. @return list<array<string,mixed>> */',
        'startLine' => 154,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'liveness' => 
      array (
        'name' => 'liveness',
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
 * Liveness of the two machines every rule depends on (§5g-6): the scheduler (last provisioning tick) and the
 * queue worker (its heartbeat job). Either older than STALE_MINUTES is reported as down.
 *
 * @return array{scheduler:array{at:?string,alive:bool}, worker:array{at:?string,alive:bool,driver:string}}
 */',
        'startLine' => 165,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'backlog' => 
      array (
        'name' => 'backlog',
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
 * Operations due for longer than the age limit, per queue (audit §5h-5): a backlog above the threshold means the
 * worker cannot keep up (or is gone) — the health rule raises `platform.queue.backlog`, the doctor warns, the
 * metrics endpoint exposes the gauge so a second worker can be started on it.
 *
 * @return array{stale:int, by_queue:array<string,int>, jobs:?int, threshold:int, age_minutes:int, alert:bool}
 */',
        'startLine' => 186,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'aliasName' => NULL,
      ),
      'present' => 
      array (
        'name' => 'present',
        'parameters' => 
        array (
          'rule' => 
          array (
            'name' => 'rule',
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
            'startLine' => 200,
            'endLine' => 200,
            'startColumn' => 30,
            'endColumn' => 40,
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
        'docComment' => '/** @param  array{key:string,command:?string,name:string,does:string,runs:string,switchable:bool}  $rule
 * @return array<string,mixed> */',
        'startLine' => 200,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
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