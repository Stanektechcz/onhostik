<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1-enums',
   'data' => 
  array (
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Audit\\AuditEvent.php' => 
    array (
      0 => 'c8e82dcc6acac72269b64b241e9fe254839fe86ca3c5da94091b4fb4a9f14ad6',
      1 => 
      array (
        0 => 'onhost\\platform\\audit\\auditevent',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\audit\\casts',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Audit\\AuditRecorder.php' => 
    array (
      0 => '62cf3ba175df889fb05d9536dcb743c01a9367cdedf0fbc36f80e4af01f877ab',
      1 => 
      array (
        0 => 'onhost\\platform\\audit\\auditrecorder',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\audit\\__construct',
        1 => 'onhost\\platform\\audit\\record',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Audit\\HashChain.php' => 
    array (
      0 => '6ac52c12a2cf795111b54115999ea9c6b95bbd2a53407bfe720dfd4daf778eaf',
      1 => 
      array (
        0 => 'onhost\\platform\\audit\\hashchain',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\audit\\next',
        1 => 'onhost\\platform\\audit\\verifyevent',
        2 => 'onhost\\platform\\audit\\hashpayload',
        3 => 'onhost\\platform\\audit\\canonical',
        4 => 'onhost\\platform\\audit\\timestamp',
        5 => 'onhost\\platform\\audit\\sortrecursively',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Clock\\Clock.php' => 
    array (
      0 => 'f769830481f2e0a5ff1083db9240b40c9ca6f6691e0d8fbca324614b28f3184a',
      1 => 
      array (
        0 => 'onhost\\platform\\clock\\clock',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\clock\\now',
        1 => 'onhost\\platform\\clock\\offsetseconds',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Clock\\SystemClock.php' => 
    array (
      0 => '99d666237fd2d9422ff6b75d37c85418c848ba9490337bf7e6e35eddef7c9593',
      1 => 
      array (
        0 => 'onhost\\platform\\clock\\systemclock',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\clock\\now',
        1 => 'onhost\\platform\\clock\\offsetseconds',
        2 => 'onhost\\platform\\clock\\recordoffset',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\AuthorizationDecision.php' => 
    array (
      0 => 'de4f1a0242d2fede6ce2efa186f204871ce7ef5d98ac2908f4d7e13e5594b8f9',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\authorizationdecision',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\allow',
        2 => 'onhost\\platform\\commands\\deny',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\Command.php' => 
    array (
      0 => '8b97ab768ac2fa4930b8aa42f49a5dcf4f08af90a1dd3f4289c5a2e843f7f955',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\command',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\permission',
        1 => 'onhost\\platform\\commands\\scope',
        2 => 'onhost\\platform\\commands\\idempotencykey',
        3 => 'onhost\\platform\\commands\\name',
        4 => 'onhost\\platform\\commands\\toaudit',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\CommandAuthorizer.php' => 
    array (
      0 => '6cb7b1afee502cfddb2d96bd2be1362701ea9147a84da6c911cf7421a30e8a6e',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\commandauthorizer',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\authorize',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\CommandBus.php' => 
    array (
      0 => 'ebc85f546c6a461a16d850b807e136b0d79811e600ed3a743bcca4fd71d2e102',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\commandbus',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\register',
        2 => 'onhost\\platform\\commands\\dispatch',
        3 => 'onhost\\platform\\commands\\resolvehandler',
        4 => 'onhost\\platform\\commands\\summarizeresult',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\CommandContext.php' => 
    array (
      0 => '5d6995d9b2a42a47654793ac6701e0431948e28dcf3bfd2ed84f63798c1d2b1a',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\commandcontext',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\system',
        2 => 'onhost\\platform\\commands\\ai',
        3 => 'onhost\\platform\\commands\\currentcorrelationid',
        4 => 'onhost\\platform\\commands\\withscope',
        5 => 'onhost\\platform\\commands\\withreason',
        6 => 'onhost\\platform\\commands\\toarray',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\CommandHandler.php' => 
    array (
      0 => '3cb29c01c4b74796559bb8d76fd2db9666cd7eae1a3bb6e6d6d9ab05a8157080',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\commandhandler',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\CommandScope.php' => 
    array (
      0 => 'f59323c624ea5a1ee8d17572cd7c478daa4c2fe48283a7580c2123ea2313413e',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\commandscope',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\global',
        2 => 'onhost\\platform\\commands\\organization',
        3 => 'onhost\\platform\\commands\\project',
        4 => 'onhost\\platform\\commands\\resource',
        5 => 'onhost\\platform\\commands\\isglobal',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\GlobalCommand.php' => 
    array (
      0 => '0f518b837645c3047fa3a40abe952d85757e9d1f6cbcdf3184b9d43a37e9d950',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\globalcommand',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\scope',
        2 => 'onhost\\platform\\commands\\idempotencykey',
        3 => 'onhost\\platform\\commands\\toaudit',
        4 => 'onhost\\platform\\commands\\get',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\IdempotencyStore.php' => 
    array (
      0 => '76bb31cf05872fc5affada79dd54b26450ffb0d6f2d5d2ae57417cc4ebd4454a',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\idempotencystore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\find',
        2 => 'onhost\\platform\\commands\\remember',
        3 => 'onhost\\platform\\commands\\rememberhttp',
        4 => 'onhost\\platform\\commands\\findhttp',
        5 => 'onhost\\platform\\commands\\scope',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Commands\\OrganizationCommand.php' => 
    array (
      0 => 'f71c2bbdc49ca99b7bd508576fee2bdc9dbe887150a7012fb7ab6598d45fcd6b',
      1 => 
      array (
        0 => 'onhost\\platform\\commands\\organizationcommand',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\commands\\__construct',
        1 => 'onhost\\platform\\commands\\scope',
        2 => 'onhost\\platform\\commands\\idempotencykey',
        3 => 'onhost\\platform\\commands\\toaudit',
        4 => 'onhost\\platform\\commands\\get',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Eloquent\\HasPrefixedUlid.php' => 
    array (
      0 => '6a88fd7ebf9b86aecbe5f9e56ef9fb52d7f245366393090a9170329283b76eac',
      1 => 
      array (
        0 => 'onhost\\platform\\eloquent\\hasprefixedulid',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\eloquent\\newuniqueid',
        1 => 'onhost\\platform\\eloquent\\idprefix',
        2 => 'onhost\\platform\\eloquent\\getincrementing',
        3 => 'onhost\\platform\\eloquent\\getkeytype',
        4 => 'onhost\\platform\\eloquent\\uniqueids',
        5 => 'onhost\\platform\\eloquent\\isvalidpublicid',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Eloquent\\Model.php' => 
    array (
      0 => 'ee8c35e5b77b4326171bb35690c49673726971030cc5782d95edefe968f5f328',
      1 => 
      array (
        0 => 'onhost\\platform\\eloquent\\model',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Errors\\DomainError.php' => 
    array (
      0 => '9030a3985d5fc3e77094faae7ce3730418fdfc73521668208a96926421e819f8',
      1 => 
      array (
        0 => 'onhost\\platform\\errors\\domainerror',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\errors\\__construct',
        1 => 'onhost\\platform\\errors\\notfound',
        2 => 'onhost\\platform\\errors\\forbidden',
        3 => 'onhost\\platform\\errors\\conflict',
        4 => 'onhost\\platform\\errors\\toproblem',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Errors\\ProviderErrorCode.php' => 
    array (
      0 => '201e5feb14e59c1c57dde5485d10dbb77fb9fc24a35ac2a1cfb23f630c48d1a9',
      1 => 
      array (
        0 => 'onhost\\platform\\errors\\providererrorcode',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\errors\\isretryable',
        1 => 'onhost\\platform\\errors\\opensincident',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Errors\\ProviderException.php' => 
    array (
      0 => '4dffaf694ca511375b43b970018a6c1037a4fe85f182452913167591e5d651bc',
      1 => 
      array (
        0 => 'onhost\\platform\\errors\\providerexception',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\errors\\__construct',
        1 => 'onhost\\platform\\errors\\isretryable',
        2 => 'onhost\\platform\\errors\\toarray',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Events\\DomainEvent.php' => 
    array (
      0 => '7a4cada0d982529165adc458eb4f8427f94df1dec2daf72bf14274ecb34c3980',
      1 => 
      array (
        0 => 'onhost\\platform\\events\\domainevent',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\events\\__construct',
        1 => 'onhost\\platform\\events\\name',
        2 => 'onhost\\platform\\events\\aggregatetype',
        3 => 'onhost\\platform\\events\\aggregateid',
        4 => 'onhost\\platform\\events\\payload',
        5 => 'onhost\\platform\\events\\organizationid',
        6 => 'onhost\\platform\\events\\correlationid',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Events\\GenericEvent.php' => 
    array (
      0 => 'caad3133ec373977f8fc1c6a705d15193f2d1303ad89fadf64322f532cb205f4',
      1 => 
      array (
        0 => 'onhost\\platform\\events\\genericevent',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\events\\of',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Files\\FileStore.php' => 
    array (
      0 => 'a4276920b5572cc9678ad09aee506adcbda5222e3432faec245c3d5094058f90',
      1 => 
      array (
        0 => 'onhost\\platform\\files\\filestore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\files\\disk',
        1 => 'onhost\\platform\\files\\diskname',
        2 => 'onhost\\platform\\files\\download',
        3 => 'onhost\\platform\\files\\prune',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Files\\UploadGuard.php' => 
    array (
      0 => 'a67509dcbe2cf96dcd14a74488eed12b793c7fcb20f1d9353019eb85ffeacb81',
      1 => 
      array (
        0 => 'onhost\\platform\\files\\uploadguard',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\files\\refused',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Files\\VirusScanner.php' => 
    array (
      0 => 'c3adfcd4464fe8201bc99cbe1c419f7377107b2cfb6057addb5216f0de3d7fbd',
      1 => 
      array (
        0 => 'onhost\\platform\\files\\virusscanner',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\files\\enabled',
        1 => 'onhost\\platform\\files\\enforced',
        2 => 'onhost\\platform\\files\\allows',
        3 => 'onhost\\platform\\files\\version',
        4 => 'onhost\\platform\\files\\command',
        5 => 'onhost\\platform\\files\\scanpath',
        6 => 'onhost\\platform\\files\\scanstream',
        7 => 'onhost\\platform\\files\\instream',
        8 => 'onhost\\platform\\files\\outcome',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Http\\Middleware\\CorrelationId.php' => 
    array (
      0 => 'e4692e27664e931ebb7583a2944b3c39c65a4e6901aaaa8b4bad0ea9cc122440',
      1 => 
      array (
        0 => 'onhost\\platform\\http\\middleware\\correlationid',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\http\\middleware\\handle',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Http\\Middleware\\IdempotencyKey.php' => 
    array (
      0 => 'd4f1f32b0f8b1437c8c9d34aff6557637341e6f2643557ee6a40260505652d7c',
      1 => 
      array (
        0 => 'onhost\\platform\\http\\middleware\\idempotencykey',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\http\\middleware\\__construct',
        1 => 'onhost\\platform\\http\\middleware\\handle',
        2 => 'onhost\\platform\\http\\middleware\\scope',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Ids\\PublicId.php' => 
    array (
      0 => '810fdc4abaa144428d30191d8a0b969dc11155ee90f314fb53b3e36f1fd75525',
      1 => 
      array (
        0 => 'onhost\\platform\\ids\\publicid',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\ids\\make',
        1 => 'onhost\\platform\\ids\\prefixof',
        2 => 'onhost\\platform\\ids\\documentnumber',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Money\\Currency.php' => 
    array (
      0 => '0959c60f56f270885f292b41bde43dfe09f980c3b0daacba01aba3d24b7c775f',
      1 => 
      array (
        0 => 'onhost\\platform\\money\\currency',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\money\\minorunits',
        1 => 'onhost\\platform\\money\\fromstring',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Money\\CurrencyMismatchException.php' => 
    array (
      0 => '0f9526d712fa27de08d7b22ddd1053acf09653efa304cb745b521d7c3e0d5fdd',
      1 => 
      array (
        0 => 'onhost\\platform\\money\\currencymismatchexception',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Money\\Money.php' => 
    array (
      0 => '2eafe0718e2f87eda0a99635a02f47987e10bbc403613f1008238260053d37c0',
      1 => 
      array (
        0 => 'onhost\\platform\\money\\money',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\money\\__construct',
        1 => 'onhost\\platform\\money\\minor',
        2 => 'onhost\\platform\\money\\zero',
        3 => 'onhost\\platform\\money\\decimal',
        4 => 'onhost\\platform\\money\\add',
        5 => 'onhost\\platform\\money\\subtract',
        6 => 'onhost\\platform\\money\\multiply',
        7 => 'onhost\\platform\\money\\percent',
        8 => 'onhost\\platform\\money\\share',
        9 => 'onhost\\platform\\money\\negate',
        10 => 'onhost\\platform\\money\\abs',
        11 => 'onhost\\platform\\money\\iszero',
        12 => 'onhost\\platform\\money\\isnegative',
        13 => 'onhost\\platform\\money\\ispositive',
        14 => 'onhost\\platform\\money\\greaterthanorequal',
        15 => 'onhost\\platform\\money\\greaterthan',
        16 => 'onhost\\platform\\money\\lessthan',
        17 => 'onhost\\platform\\money\\equals',
        18 => 'onhost\\platform\\money\\todecimal',
        19 => 'onhost\\platform\\money\\format',
        20 => 'onhost\\platform\\money\\jsonserialize',
        21 => 'onhost\\platform\\money\\assertsamecurrency',
        22 => 'onhost\\platform\\money\\roundhalfup',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Net\\DnsLookup.php' => 
    array (
      0 => '8215afaf1e3b75ea38d95aa633c065f43b5e19e4457248f84c707f88bc734e3e',
      1 => 
      array (
        0 => 'onhost\\platform\\net\\dnslookup',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\net\\cname',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Observability\\ErrorReporter.php' => 
    array (
      0 => 'c0c08bb11d53f33886924fb530bca7908731033852754ef9d0f1827485ed4469',
      1 => 
      array (
        0 => 'onhost\\platform\\observability\\errorreporter',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\observability\\__construct',
        1 => 'onhost\\platform\\observability\\enabled',
        2 => 'onhost\\platform\\observability\\expected',
        3 => 'onhost\\platform\\observability\\report',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Observability\\Tracer.php' => 
    array (
      0 => '5e24849bd2f5db867754cc6b6316050f8d6f92411582a0fb231a3fbb901b35e3',
      1 => 
      array (
        0 => 'onhost\\platform\\observability\\tracer',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\observability\\__construct',
        1 => 'onhost\\platform\\observability\\enabled',
        2 => 'onhost\\platform\\observability\\span',
        3 => 'onhost\\platform\\observability\\finished',
        4 => 'onhost\\platform\\observability\\flush',
        5 => 'onhost\\platform\\observability\\urlfor',
        6 => 'onhost\\platform\\observability\\traceid',
        7 => 'onhost\\platform\\observability\\record',
        8 => 'onhost\\platform\\observability\\attr',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Outbox\\OutboxEventDispatched.php' => 
    array (
      0 => '277e1fe8f42bac6021eef7468d88b0f65adbf63267198be882c8726ddc98b587',
      1 => 
      array (
        0 => 'onhost\\platform\\outbox\\outboxeventdispatched',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\outbox\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Outbox\\OutboxMessage.php' => 
    array (
      0 => 'e0ff98b502333e85ac2def3b5fc3ccda02c9bc61cf3cc7474703e499519ad5d4',
      1 => 
      array (
        0 => 'onhost\\platform\\outbox\\outboxmessage',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\outbox\\casts',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Outbox\\OutboxPublisher.php' => 
    array (
      0 => '6026753f06f18941b7dd2a9946bcb598f6a8e17dbbdcc40f0f163cc9a3a63355',
      1 => 
      array (
        0 => 'onhost\\platform\\outbox\\outboxpublisher',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\outbox\\__construct',
        1 => 'onhost\\platform\\outbox\\publish',
        2 => 'onhost\\platform\\outbox\\relaysoon',
        3 => 'onhost\\platform\\outbox\\relaypending',
        4 => 'onhost\\platform\\outbox\\relaylocked',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Outbox\\RelayOutboxJob.php' => 
    array (
      0 => '111dc7ed8b4eb7cb09c8cff63f9f7dc254eb1a35b640596f8889849219fcb8ae',
      1 => 
      array (
        0 => 'onhost\\platform\\outbox\\relayoutboxjob',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\outbox\\handle',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\ProviderHttp\\ProviderCallLogger.php' => 
    array (
      0 => '00c7bcdb3a4cf34b7474c1825bee46795820cde060373137d055b6cde6367db9',
      1 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\providercalllogger',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\__construct',
        1 => 'onhost\\platform\\providerhttp\\log',
        2 => 'onhost\\platform\\providerhttp\\pathof',
        3 => 'onhost\\platform\\providerhttp\\truncate',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\ProviderHttp\\ProviderHttpClient.php' => 
    array (
      0 => '908f07522aaf7911a11ad0acfc7163c142eef850416b086dddabe7d15c32a8c4',
      1 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\providerhttpclient',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\__construct',
        1 => 'onhost\\platform\\providerhttp\\configurebucket',
        2 => 'onhost\\platform\\providerhttp\\bucket',
        3 => 'onhost\\platform\\providerhttp\\breaker',
        4 => 'onhost\\platform\\providerhttp\\recordfailure',
        5 => 'onhost\\platform\\providerhttp\\recordsuccess',
        6 => 'onhost\\platform\\providerhttp\\send',
        7 => 'onhost\\platform\\providerhttp\\multipart',
        8 => 'onhost\\platform\\providerhttp\\prepare',
        9 => 'onhost\\platform\\providerhttp\\summarize',
        10 => 'onhost\\platform\\providerhttp\\summarizeresponse',
        11 => 'onhost\\platform\\providerhttp\\bodycode',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\ProviderHttp\\ProviderRequest.php' => 
    array (
      0 => 'ccbe81d42c31f1baac5383058d9fc8ed567873c8c7b740165fc74aa5880ebc19',
      1 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\providerrequest',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\ProviderHttp\\ProviderResponse.php' => 
    array (
      0 => '1c09a051ed6e8146954a8350866d412074c15f7a36647f26ae3a771375bb57a9',
      1 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\providerresponse',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\providerhttp\\__construct',
        1 => 'onhost\\platform\\providerhttp\\json',
        2 => 'onhost\\platform\\providerhttp\\isjson',
        3 => 'onhost\\platform\\providerhttp\\header',
        4 => 'onhost\\platform\\providerhttp\\retryafterseconds',
        5 => 'onhost\\platform\\providerhttp\\serverdate',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Redaction\\Redactor.php' => 
    array (
      0 => '42e564502582f96898df4b061eacd98ad19c8f3c1c5d09ff8687aef5a244e576',
      1 => 
      array (
        0 => 'onhost\\platform\\redaction\\redactor',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\redaction\\redact',
        1 => 'onhost\\platform\\redaction\\issecretkey',
        2 => 'onhost\\platform\\redaction\\redactstring',
        3 => 'onhost\\platform\\redaction\\fingerprint',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Resilience\\CircuitBreaker.php' => 
    array (
      0 => '1c9d468d980b7011b26313c5eab9001c37775cb99fe31f1053c8a9ffa227e8df',
      1 => 
      array (
        0 => 'onhost\\platform\\resilience\\circuitbreaker',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\resilience\\__construct',
        1 => 'onhost\\platform\\resilience\\state',
        2 => 'onhost\\platform\\resilience\\allowsrequest',
        3 => 'onhost\\platform\\resilience\\recordsuccess',
        4 => 'onhost\\platform\\resilience\\recordfailure',
        5 => 'onhost\\platform\\resilience\\trip',
        6 => 'onhost\\platform\\resilience\\reset',
        7 => 'onhost\\platform\\resilience\\failures',
        8 => 'onhost\\platform\\resilience\\k',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Resilience\\RetryPolicy.php' => 
    array (
      0 => 'bd1d9bdbf5942ce0536483be324009fc5749744752b992d6a6739d9e6266c8f7',
      1 => 
      array (
        0 => 'onhost\\platform\\resilience\\retrypolicy',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\resilience\\__construct',
        1 => 'onhost\\platform\\resilience\\provisioning',
        2 => 'onhost\\platform\\resilience\\sync',
        3 => 'onhost\\platform\\resilience\\delayforattempt',
        4 => 'onhost\\platform\\resilience\\canretry',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Resilience\\TokenBucket.php' => 
    array (
      0 => '4e05ab97c9b5642955c32f91b5c702a7fa342ea7e215228da9a7d790884ba891',
      1 => 
      array (
        0 => 'onhost\\platform\\resilience\\tokenbucket',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\resilience\\__construct',
        1 => 'onhost\\platform\\resilience\\tryconsume',
        2 => 'onhost\\platform\\resilience\\used',
        3 => 'onhost\\platform\\resilience\\remaining',
        4 => 'onhost\\platform\\resilience\\secondsuntilreset',
        5 => 'onhost\\platform\\resilience\\limit',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Secrets\\DbSecretStore.php' => 
    array (
      0 => '18e8f096cabd9a93cbc4f662b3c5ea31976870740eeaf3c3f538c8e4719c84e8',
      1 => 
      array (
        0 => 'onhost\\platform\\secrets\\dbsecretstore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\secrets\\__construct',
        1 => 'onhost\\platform\\secrets\\read',
        2 => 'onhost\\platform\\secrets\\write',
        3 => 'onhost\\platform\\secrets\\merge',
        4 => 'onhost\\platform\\secrets\\delete',
        5 => 'onhost\\platform\\secrets\\keys',
        6 => 'onhost\\platform\\secrets\\exists',
        7 => 'onhost\\platform\\secrets\\health',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Secrets\\EnvSecretStore.php' => 
    array (
      0 => '2bc362c4b19365550c5f8505d525dd23c76da0b61ac1690dd504f75b9e6fdbb4',
      1 => 
      array (
        0 => 'onhost\\platform\\secrets\\envsecretstore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\secrets\\read',
        1 => 'onhost\\platform\\secrets\\write',
        2 => 'onhost\\platform\\secrets\\exists',
        3 => 'onhost\\platform\\secrets\\health',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Secrets\\OpenBaoSecretStore.php' => 
    array (
      0 => '8332c993d968f27a8fc88ecce9da5c44986e336e701d692546d0628e059f0fb3',
      1 => 
      array (
        0 => 'onhost\\platform\\secrets\\openbaosecretstore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\secrets\\__construct',
        1 => 'onhost\\platform\\secrets\\read',
        2 => 'onhost\\platform\\secrets\\write',
        3 => 'onhost\\platform\\secrets\\exists',
        4 => 'onhost\\platform\\secrets\\health',
        5 => 'onhost\\platform\\secrets\\split',
        6 => 'onhost\\platform\\secrets\\client',
        7 => 'onhost\\platform\\secrets\\baserequest',
        8 => 'onhost\\platform\\secrets\\token',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Secrets\\SecretRef.php' => 
    array (
      0 => '8ea05088fa2ba2d123a6e5ff491d646b19936c392c9e4cab065fedb38943b41c',
      1 => 
      array (
        0 => 'onhost\\platform\\secrets\\secretref',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\secrets\\__construct',
        1 => 'onhost\\platform\\secrets\\parse',
        2 => 'onhost\\platform\\secrets\\bao',
        3 => 'onhost\\platform\\secrets\\env',
        4 => 'onhost\\platform\\secrets\\__tostring',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Secrets\\SecretStore.php' => 
    array (
      0 => '36f743ccc4aade37e2759778f9348c2b3ee6e902b7f20861cd71f437fea034d7',
      1 => 
      array (
        0 => 'onhost\\platform\\secrets\\secretstore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\secrets\\read',
        1 => 'onhost\\platform\\secrets\\write',
        2 => 'onhost\\platform\\secrets\\exists',
        3 => 'onhost\\platform\\secrets\\health',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Secrets\\SecretStoreHealth.php' => 
    array (
      0 => '44b5ef977043df8450d72706b9582d9c14828832f5fc9b9fa9c112ccde5d6bfc',
      1 => 
      array (
        0 => 'onhost\\platform\\secrets\\secretstorehealth',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\secrets\\__construct',
        1 => 'onhost\\platform\\secrets\\toarray',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Settings\\SettingsStore.php' => 
    array (
      0 => 'e7975fa834b672dd54a788fc38cea861a2f8d4d0f24412c50c8f6260c3b3eb2e',
      1 => 
      array (
        0 => 'onhost\\platform\\settings\\settingsstore',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\settings\\all',
        1 => 'onhost\\platform\\settings\\get',
        2 => 'onhost\\platform\\settings\\set',
        3 => 'onhost\\platform\\settings\\forget',
        4 => 'onhost\\platform\\settings\\flush',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\StateMachine\\InvalidTransitionException.php' => 
    array (
      0 => '224973b139205ac6f97358890606d56a4a52f99861c5f4fc4cc834e0b88badfc',
      1 => 
      array (
        0 => 'onhost\\platform\\statemachine\\invalidtransitionexception',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\StateMachine\\StateMachine.php' => 
    array (
      0 => '57b182a00b566dc8f1706bddf2b3b0f6269f0ac2b98ea1eefd0c8848e2e5277c',
      1 => 
      array (
        0 => 'onhost\\platform\\statemachine\\statemachine',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\statemachine\\__construct',
        1 => 'onhost\\platform\\statemachine\\cantransition',
        2 => 'onhost\\platform\\statemachine\\asserttransition',
        3 => 'onhost\\platform\\statemachine\\nextstates',
        4 => 'onhost\\platform\\statemachine\\label',
        5 => 'onhost\\platform\\statemachine\\has',
        6 => 'onhost\\platform\\statemachine\\isterminal',
        7 => 'onhost\\platform\\statemachine\\states',
        8 => 'onhost\\platform\\statemachine\\toarray',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Support\\Hostname.php' => 
    array (
      0 => '34b64bc6ef9a5b508af4f40bdf858e885b77cbc8c86bdb298c44658589e6e279',
      1 => 
      array (
        0 => 'onhost\\platform\\support\\hostname',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\support\\canonical',
        1 => 'onhost\\platform\\support\\unicode',
        2 => 'onhost\\platform\\support\\tld',
        3 => 'onhost\\platform\\support\\isregistrable',
        4 => 'onhost\\platform\\support\\isvalidlabel',
      ),
      3 => 
      array (
      ),
    ),
    'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\platform\\Ops\\PlatformBackup.php' => 
    array (
      0 => '4fb8c129190611f7f98ea18111c66d351bc39ea144be83e22e9ba49392f70380',
      1 => 
      array (
        0 => 'onhost\\platform\\ops\\platformbackup',
      ),
      2 => 
      array (
        0 => 'onhost\\platform\\ops\\disk',
        1 => 'onhost\\platform\\ops\\run',
        2 => 'onhost\\platform\\ops\\verify',
        3 => 'onhost\\platform\\ops\\status',
        4 => 'onhost\\platform\\ops\\latestset',
        5 => 'onhost\\platform\\ops\\dumpdatabase',
        6 => 'onhost\\platform\\ops\\archivefiles',
        7 => 'onhost\\platform\\ops\\tarheader',
        8 => 'onhost\\platform\\ops\\store',
        9 => 'onhost\\platform\\ops\\checkdump',
        10 => 'onhost\\platform\\ops\\prune',
        11 => 'onhost\\platform\\ops\\exec',
      ),
      3 => 
      array (
      ),
    ),
  ),
));