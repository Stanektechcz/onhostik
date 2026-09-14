<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Support\Triage.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Support\Triage
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-97813af83e0e24c19c16e387c7f74b5e308270354448fb0fb8ee98dec3c8c14b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Support\\Triage',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Support/Triage.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Support',
    'name' => 'Onhost\\Domain\\Support\\Triage',
    'shortName' => 'Triage',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Deterministic triage (blueprint §68.4, §68.6): topic from keywords (the prototype\'s
 * topic list, diacritics-insensitive), required skills and queue per topic, priority
 * policy — customers cannot flood P1; staff override anytime.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 96,
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
      'TOPICS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'name' => 'TOPICS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    \'dostupnost\' => [\'label\' => \'Výpadky a dostupnost\', \'kw\' => [\'vypadek\', \'nedostupn\', \'502\', \'503\', \'timeout\', \'pada\', \'spadl\', \'restart\', \'nejede\', \'nefunguje\', \'down\', \'outage\'], \'skills\' => [\'GENERAL\', \'PROXMOX\', \'ISPCONFIG\'], \'queue\' => \'l2\'],
    \'latence\' => [\'label\' => \'Latence a síť\', \'kw\' => [\'latence\', \'ping\', \'traceroute\', \'retransmis\', \'uplink\', \'paket\', \'route\', \'ztrat\', \'latency\', \'packet loss\'], \'skills\' => [\'NETWORK\'], \'queue\' => \'l3\'],
    \'vykon\' => [\'label\' => \'Výkon a kapacita\', \'kw\' => [\'cpu\', \'ram\', \'pomal\', \'vytiz\', \'iops\', \'disk\', \'tick\', \'kapacit\', \'load\', \'zpomal\', \'slow\'], \'skills\' => [\'PROXMOX\', \'ISPCONFIG\'], \'queue\' => \'l2\'],
    \'fakturace\' => [\'label\' => \'Fakturace a doklady\', \'kw\' => [\'faktur\', \'ico\', \'doklad\', \'dph\', \'platb\', \'upominka\', \'storno\', \'cena\', \'uhrad\', \'zaplat\', \'neuhraz\', \'nedoplat\', \'dluz\', \'invoice\', \'vat\', \'payment\', \'kredit\'], \'skills\' => [\'BILLING\'], \'queue\' => \'billing\'],
    \'zalohy\' => [\'label\' => \'Zálohy a obnova\', \'kw\' => [\'zaloh\', \'obnov\', \'snapshot\', \'restore\', \'smazal\', \'ztratil\', \'backup\'], \'skills\' => [\'BACKUP\'], \'queue\' => \'l2\'],
    \'pristup\' => [\'label\' => \'Přístupy a bezpečnost\', \'kw\' => [\'heslo\', \'pristup\', \'ssh\', \'2fa\', \'klic\', \'firewall\', \'port\', \'certifik\', \'ssl\', \'tls\', \'prihlas\', \'password\', \'login\', \'totp\'], \'skills\' => [\'GENERAL\', \'SECURITY\'], \'queue\' => \'l1\'],
    \'dns\' => [\'label\' => \'DNS a domény\', \'kw\' => [\'dns\', \'domen\', \'zaznam\', \'mx\', \'nameserver\', \'ttl\', \'zona\', \'presmerov\', \'domain\', \'dnssec\', \'registr\'], \'skills\' => [\'DNS\', \'DOMAIN_WAPI\'], \'queue\' => \'domains\'],
    \'mail\' => [\'label\' => \'E-mail a doručitelnost\', \'kw\' => [\'mail\', \'spf\', \'dkim\', \'dmarc\', \'spam\', \'schrank\', \'dorucit\', \'posta\', \'smtp\', \'imap\'], \'skills\' => [\'MAIL_DELIVERABILITY\'], \'queue\' => \'l2\'],
    \'migrace\' => [\'label\' => \'Migrace a přenos\', \'kw\' => [\'migrac\', \'prenos\', \'presun\', \'postgres\', \'databaz\', \'import\', \'okno\', \'stehov\', \'migration\'], \'skills\' => [\'ISPCONFIG\', \'PROXMOX\'], \'queue\' => \'l2\'],
    \'objednavka\' => [\'label\' => \'Objednávky a služby\', \'kw\' => [\'objedn\', \'provisioning\', \'aktivac\', \'navys\', \'upgrade\', \'zrus\', \'tarif\', \'sluzb\', \'sluzeb\', \'stav mych\', \'moje sluzb\', \'my services\', \'order\'], \'skills\' => [\'ORDERS\'], \'queue\' => \'l1\'],
    \'hry\' => [\'label\' => \'Herní servery\', \'kw\' => [\'minecraft\', \'cs2\', \'rust\', \'ark\', \'valheim\', \'palworld\', \'herni\', \'server hry\', \'mod\', \'plugin\', \'wings\', \'pterodactyl\'], \'skills\' => [\'GAME_MINECRAFT\', \'PTERODACTYL\'], \'queue\' => \'games\'],
    \'sprava\' => [\'label\' => \'Správa služeb\', \'kw\' => [\'restartuj\', \'zalohuj\', \'nasad\', \'deploy\', \'staging\', \'aktualizuj\', \'wordpress\', \'redis\', \'object cache\', \'vyprazdni\', \'purge\', \'vynut https\', \'prepni\', \'terminal\', \'cron\', \'wp-cli\', \'git\'], \'skills\' => [\'ISPCONFIG\', \'AAPANEL\'], \'queue\' => \'l1\'],
    \'bezpecnost\' => [\'label\' => \'Bezpečnostní incident\', \'kw\' => [\'hack\', \'napaden\', \'unik\', \'breach\', \'phishing\', \'malware\', \'ransom\', \'ddos\', \'zneuzit\', \'abuse\'], \'skills\' => [\'SECURITY\', \'ABUSE\'], \'queue\' => \'security\'],
    // the haystack is padded with spaces, so \' api \' matches the word and not "napište"
    \'api\' => [\'label\' => \'API a integrace\', \'kw\' => [\' api \', \' api,\', \' api.\', \'api kli\', \'api key\', \'token\', \'webhook\', \'openapi\', \'bearer\', \'integrac\', \'endpoint\', \'swagger\'], \'skills\' => [\'GENERAL\'], \'queue\' => \'l1\'],
    \'cenik\' => [\'label\' => \'Ceník a tarify\', \'kw\' => [\'cenik\', \'kolik stoji\', \'kolik to stoji\', \'pricing\', \'price\', \'nabidk\', \'nabizite\', \'levn\', \'draz\', \'zdarma\', \'za mesic\', \'rocne\', \'jak drah\'], \'skills\' => [\'GENERAL\'], \'queue\' => \'l1\'],
]',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 32,
            'startTokenPos' => 35,
            'startFilePos' => 465,
            'endTokenPos' => 1125,
            'endFilePos' => 4111,
          ),
        ),
        'docComment' => '/** @var array<string, array{label:string, kw:list<string>, skills:list<string>, queue:string}> */',
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'SECURITY_TOPICS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'name' => 'SECURITY_TOPICS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'bezpecnost\']',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 1136,
            'startFilePos' => 4150,
            'endTokenPos' => 1138,
            'endFilePos' => 4163,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'normalize' => 
      array (
        'name' => 'normalize',
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
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 38,
            'endColumn' => 49,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 36,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Support',
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'currentClassName' => 'Onhost\\Domain\\Support\\Triage',
        'aliasName' => NULL,
      ),
      'classify' => 
      array (
        'name' => 'classify',
        'parameters' => 
        array (
          'texts' => 
          array (
            'name' => 'texts',
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
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 37,
            'endColumn' => 52,
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
        'docComment' => '/** @return array{topic:string, label:string, confident:bool, hits:int} */',
        'startLine' => 47,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Support',
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'currentClassName' => 'Onhost\\Domain\\Support\\Triage',
        'aliasName' => NULL,
      ),
      'skillsFor' => 
      array (
        'name' => 'skillsFor',
        'parameters' => 
        array (
          'topic' => 
          array (
            'name' => 'topic',
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 38,
            'endColumn' => 50,
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
        'docComment' => '/** @return list<string> */',
        'startLine' => 65,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Support',
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'currentClassName' => 'Onhost\\Domain\\Support\\Triage',
        'aliasName' => NULL,
      ),
      'queueFor' => 
      array (
        'name' => 'queueFor',
        'parameters' => 
        array (
          'topic' => 
          array (
            'name' => 'topic',
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
            'startColumn' => 37,
            'endColumn' => 49,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 70,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Support',
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'currentClassName' => 'Onhost\\Domain\\Support\\Triage',
        'aliasName' => NULL,
      ),
      'priority' => 
      array (
        'name' => 'priority',
        'parameters' => 
        array (
          'requested' => 
          array (
            'name' => 'requested',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 37,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'topic' => 
          array (
            'name' => 'topic',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'staff' => 
          array (
            'name' => 'staff',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 72,
            'endColumn' => 82,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'contractualSla' => 
          array (
            'name' => 'contractualSla',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 85,
            'endColumn' => 104,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'subjectAndBody' => 
          array (
            'name' => 'subjectAndBody',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 79,
                'endLine' => 79,
                'startTokenPos' => 1558,
                'startFilePos' => 6016,
                'endTokenPos' => 1558,
                'endFilePos' => 6019,
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 107,
            'endColumn' => 136,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Priority policy: customers may ask for `vysoka` (→ P2); P1 is reserved for staff and for
 * availability tickets of services sold with a contractual SLA. Security topics are at least P2.
 */',
        'startLine' => 79,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Support',
        'declaringClassName' => 'Onhost\\Domain\\Support\\Triage',
        'implementingClassName' => 'Onhost\\Domain\\Support\\Triage',
        'currentClassName' => 'Onhost\\Domain\\Support\\Triage',
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