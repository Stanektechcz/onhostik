<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Services\Web\CommandRunner.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Services\Web\CommandRunner
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-f661d518335b5c44ca4c4000bf1a86545190ef2cf38d3110504d90dd653c4b5e',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Services/Web/CommandRunner.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Services\\Web',
    'name' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
    'shortName' => 'CommandRunner',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The customer terminal: non-interactive commands in the site\'s document root as the site user. The operating
 * system enforces what the user may touch; this guard keeps the panel from becoming a launcher for daemons and
 * privilege changes (no sudo/su, no background jobs, no scheduler edits, a fixed set of tools).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 54,
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
      'ALLOWED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'name' => 'ALLOWED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'wp\', \'php\', \'composer\', \'git\', \'npm\', \'npx\', \'node\', \'yarn\', \'pnpm\', \'ls\', \'cat\', \'head\', \'tail\', \'grep\', \'find\', \'du\', \'df\', \'pwd\', \'echo\', \'printf\', \'env\', \'mkdir\', \'rm\', \'rmdir\', \'cp\', \'mv\', \'tar\', \'zip\', \'unzip\', \'gzip\', \'gunzip\', \'mysql\', \'mysqldump\', \'curl\', \'wget\', \'sed\', \'awk\', \'sort\', \'uniq\', \'wc\', \'chmod\', \'touch\', \'ln\', \'date\', \'which\', \'whoami\', \'id\', \'uname\', \'stat\', \'tree\', \'diff\', \'md5sum\', \'sha256sum\', \'base64\', \'xargs\', \'true\', \'false\', \'test\', \'cd\', \'export\', \'rsync\', \'artisan\']',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 19,
            'startTokenPos' => 38,
            'startFilePos' => 496,
            'endTokenPos' => 226,
            'endFilePos' => 1021,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'FORBIDDEN' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'name' => 'FORBIDDEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'sudo\', \'su\', \'chattr\', \'crontab\', \'nohup\', \'screen\', \'tmux\', \'setsid\', \'systemctl\', \'service\', \'nc\', \'ncat\', \'socat\', \'telnet\', \'ssh\', \'scp\', \'sftp\', \'mount\', \'umount\', \'kill\', \'pkill\', \'killall\', \'reboot\', \'shutdown\', \'passwd\', \'useradd\', \'usermod\', \'chown\', \'iptables\', \'nft\', \'docker\']',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 237,
            'startFilePos' => 1054,
            'endTokenPos' => 329,
            'endFilePos' => 1343,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 320,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'guard' => 
      array (
        'name' => 'guard',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 34,
            'endColumn' => 48,
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
        'docComment' => '/** Returns the command unchanged when every segment starts with an allowed tool; throws otherwise. */',
        'startLine' => 24,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Services\\Web',
        'declaringClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
        'currentClassName' => 'Onhost\\Domain\\Services\\Web\\CommandRunner',
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