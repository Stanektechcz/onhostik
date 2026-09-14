<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Mail/Mailable.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Mail\Mailable
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-b68da981804a719e79811c0242f2e62590f8487e7bb6de1c1410ea8ab97d1fb5-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Mail\\Mailable',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Mail/Mailable.php',
      ),
    ),
    'namespace' => 'Illuminate\\Mail',
    'name' => 'Illuminate\\Mail\\Mailable',
    'shortName' => 'Mailable',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 36,
    'endLine' => 1939,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Mail\\Mailable',
      1 => 'Illuminate\\Contracts\\Support\\Renderable',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Conditionable',
      1 => 'Illuminate\\Support\\Traits\\ForwardsCalls',
      2 => 'Illuminate\\Support\\Traits\\Localizable',
      3 => 'Illuminate\\Support\\Traits\\ReadsClassAttributes',
      4 => 'Illuminate\\Support\\Traits\\Tappable',
      5 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'locale' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'locale',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The locale of the message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'from' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'from',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 54,
            'endLine' => 54,
            'startTokenPos' => 243,
            'startFilePos' => 1741,
            'endTokenPos' => 244,
            'endFilePos' => 1742,
          ),
        ),
        'docComment' => '/**
 * The person the message is from.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'to' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'to',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 61,
            'endLine' => 61,
            'startTokenPos' => 255,
            'startFilePos' => 1847,
            'endTokenPos' => 256,
            'endFilePos' => 1848,
          ),
        ),
        'docComment' => '/**
 * The "to" recipients of the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cc' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'cc',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 68,
            'endLine' => 68,
            'startTokenPos' => 267,
            'startFilePos' => 1953,
            'endTokenPos' => 268,
            'endFilePos' => 1954,
          ),
        ),
        'docComment' => '/**
 * The "cc" recipients of the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 68,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bcc' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'bcc',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 75,
            'endLine' => 75,
            'startTokenPos' => 279,
            'startFilePos' => 2061,
            'endTokenPos' => 280,
            'endFilePos' => 2062,
          ),
        ),
        'docComment' => '/**
 * The "bcc" recipients of the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 75,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'replyTo' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'replyTo',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 82,
            'endLine' => 82,
            'startTokenPos' => 291,
            'startFilePos' => 2178,
            'endTokenPos' => 292,
            'endFilePos' => 2179,
          ),
        ),
        'docComment' => '/**
 * The "reply to" recipients of the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'subject' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'subject',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The subject of the message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 89,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'markdown' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'markdown',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Markdown template for the message (if applicable).
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 96,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'html' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'html',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The HTML to use for the message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'view' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'view',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The view to use for the message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 110,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'textView' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'textView',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The plain text view to use for the message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 117,
        'endLine' => 117,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'viewData' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'viewData',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 124,
            'endLine' => 124,
            'startTokenPos' => 338,
            'startFilePos' => 2832,
            'endTokenPos' => 339,
            'endFilePos' => 2833,
          ),
        ),
        'docComment' => '/**
 * The view data for the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 124,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'attachments' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'attachments',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 131,
            'endLine' => 131,
            'startTokenPos' => 350,
            'startFilePos' => 2944,
            'endTokenPos' => 351,
            'endFilePos' => 2945,
          ),
        ),
        'docComment' => '/**
 * The attachments for the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 131,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'rawAttachments' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'rawAttachments',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 138,
            'endLine' => 138,
            'startTokenPos' => 362,
            'startFilePos' => 3063,
            'endTokenPos' => 363,
            'endFilePos' => 3064,
          ),
        ),
        'docComment' => '/**
 * The raw attachments for the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 138,
        'endLine' => 138,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'diskAttachments' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'diskAttachments',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 145,
            'endLine' => 145,
            'startTokenPos' => 374,
            'startFilePos' => 3183,
            'endTokenPos' => 375,
            'endFilePos' => 3184,
          ),
        ),
        'docComment' => '/**
 * The attachments from a storage disk.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 145,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tags' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'tags',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 152,
            'endLine' => 152,
            'startTokenPos' => 386,
            'startFilePos' => 3284,
            'endTokenPos' => 387,
            'endFilePos' => 3285,
          ),
        ),
        'docComment' => '/**
 * The tags for the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 152,
        'endLine' => 152,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'metadata' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'metadata',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 159,
            'endLine' => 159,
            'startTokenPos' => 398,
            'startFilePos' => 3393,
            'endTokenPos' => 399,
            'endFilePos' => 3394,
          ),
        ),
        'docComment' => '/**
 * The metadata for the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 159,
        'endLine' => 159,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'callbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'callbacks',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 166,
            'endLine' => 166,
            'startTokenPos' => 410,
            'startFilePos' => 3501,
            'endTokenPos' => 411,
            'endFilePos' => 3502,
          ),
        ),
        'docComment' => '/**
 * The callbacks for the message.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 166,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'theme' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'theme',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name of the theme that should be used when formatting the message.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 173,
        'endLine' => 173,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mailer' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'mailer',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name of the mailer that should send the message.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 180,
        'endLine' => 180,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'assertionableRenderStrings' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'assertionableRenderStrings',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The rendered mailable views for testing / assertions.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 187,
        'endLine' => 187,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'viewDataCallback' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'name' => 'viewDataCallback',
        'modifiers' => 17,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The callback that should be invoked while building the view data.
 *
 * @var callable
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 194,
        'endLine' => 194,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'send' => 
      array (
        'name' => 'send',
        'parameters' => 
        array (
          'mailer' => 
          array (
            'name' => 'mailer',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 202,
            'endLine' => 202,
            'startColumn' => 26,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send the message using the given mailer.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Factory|\\Illuminate\\Contracts\\Mail\\Mailer  $mailer
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 202,
        'endLine' => 221,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'queue' => 
      array (
        'name' => 'queue',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Queue\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 229,
            'endLine' => 229,
            'startColumn' => 27,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue the message for sending.
 *
 * @param  \\Illuminate\\Contracts\\Queue\\Factory  $queue
 * @return mixed
 */',
        'startLine' => 229,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'later' => 
      array (
        'name' => 'later',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Queue\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Deliver the queued message after (n) seconds.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @param  \\Illuminate\\Contracts\\Queue\\Factory  $queue
 * @return mixed
 */',
        'startLine' => 261,
        'endLine' => 282,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'newQueuedJob' => 
      array (
        'name' => 'newQueuedJob',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Make the queued mailable job instance.
 *
 * @return mixed
 */',
        'startLine' => 289,
        'endLine' => 302,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'render' => 
      array (
        'name' => 'render',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Render the mailable into a view.
 *
 * @return string
 *
 * @throws \\ReflectionException
 */',
        'startLine' => 311,
        'endLine' => 320,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildView' => 
      array (
        'name' => 'buildView',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the view for the message.
 *
 * @return array|string
 *
 * @throws \\ReflectionException
 */',
        'startLine' => 329,
        'endLine' => 349,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildMarkdownView' => 
      array (
        'name' => 'buildMarkdownView',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the Markdown view for the message.
 *
 * @return array
 *
 * @throws \\ReflectionException
 */',
        'startLine' => 358,
        'endLine' => 366,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildViewData' => 
      array (
        'name' => 'buildViewData',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the view data for the message.
 *
 * @return array
 *
 * @throws \\ReflectionException
 */',
        'startLine' => 375,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'additionalMessageData' => 
      array (
        'name' => 'additionalMessageData',
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
 * Get additional meta-data to pass along with the view data.
 *
 * @return array<string, mixed>
 */',
        'startLine' => 397,
        'endLine' => 402,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildMarkdownHtml' => 
      array (
        'name' => 'buildMarkdownHtml',
        'parameters' => 
        array (
          'viewData' => 
          array (
            'name' => 'viewData',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 410,
            'endLine' => 410,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the HTML view for a Markdown message.
 *
 * @param  array  $viewData
 * @return \\Closure
 */',
        'startLine' => 410,
        'endLine' => 416,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildMarkdownText' => 
      array (
        'name' => 'buildMarkdownText',
        'parameters' => 
        array (
          'viewData' => 
          array (
            'name' => 'viewData',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 424,
            'endLine' => 424,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the text view for a Markdown message.
 *
 * @param  array  $viewData
 * @return \\Closure
 */',
        'startLine' => 424,
        'endLine' => 438,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'markdownRenderer' => 
      array (
        'name' => 'markdownRenderer',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resolves a Markdown instance with the mail\'s theme.
 *
 * @return \\Illuminate\\Mail\\Markdown
 */',
        'startLine' => 445,
        'endLine' => 452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildFrom' => 
      array (
        'name' => 'buildFrom',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 460,
            'endLine' => 460,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add the sender to the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 460,
        'endLine' => 467,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildRecipients' => 
      array (
        'name' => 'buildRecipients',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 475,
            'endLine' => 475,
            'startColumn' => 40,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add all of the recipients to the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 475,
        'endLine' => 484,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildSubject' => 
      array (
        'name' => 'buildSubject',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 492,
            'endLine' => 492,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the subject for the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 492,
        'endLine' => 501,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildAttachments' => 
      array (
        'name' => 'buildAttachments',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 509,
            'endLine' => 509,
            'startColumn' => 41,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add all of the attachments to the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 509,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildDiskAttachments' => 
      array (
        'name' => 'buildDiskAttachments',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 532,
            'endLine' => 532,
            'startColumn' => 45,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add all of the disk attachments to the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return void
 */',
        'startLine' => 532,
        'endLine' => 545,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildTags' => 
      array (
        'name' => 'buildTags',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 553,
            'endLine' => 553,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add all defined tags to the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 553,
        'endLine' => 562,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildMetadata' => 
      array (
        'name' => 'buildMetadata',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 570,
            'endLine' => 570,
            'startColumn' => 38,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add all defined metadata to the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 570,
        'endLine' => 579,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'runCallbacks' => 
      array (
        'name' => 'runCallbacks',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 587,
            'endLine' => 587,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the callbacks for the message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return $this
 */',
        'startLine' => 587,
        'endLine' => 594,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'locale' => 
      array (
        'name' => 'locale',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 602,
            'endLine' => 602,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the locale of the message.
 *
 * @param  string  $locale
 * @return $this
 */',
        'startLine' => 602,
        'endLine' => 607,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'priority' => 
      array (
        'name' => 'priority',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => 
            array (
              'code' => '3',
              'attributes' => 
              array (
                'startLine' => 617,
                'endLine' => 617,
                'startTokenPos' => 2515,
                'startFilePos' => 15790,
                'endTokenPos' => 2515,
                'endFilePos' => 15790,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 617,
            'endLine' => 617,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the priority of this message.
 *
 * The value is an integer where 1 is the highest priority and 5 is the lowest.
 *
 * @param  int  $level
 * @return $this
 */',
        'startLine' => 617,
        'endLine' => 624,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'from' => 
      array (
        'name' => 'from',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 633,
            'endLine' => 633,
            'startColumn' => 26,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 633,
                'endLine' => 633,
                'startTokenPos' => 2575,
                'startFilePos' => 16148,
                'endTokenPos' => 2575,
                'endFilePos' => 16151,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 633,
            'endLine' => 633,
            'startColumn' => 36,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the sender of the message.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 633,
        'endLine' => 636,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasFrom' => 
      array (
        'name' => 'hasFrom',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 645,
            'endLine' => 645,
            'startColumn' => 29,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 645,
                'endLine' => 645,
                'startTokenPos' => 2613,
                'startFilePos' => 16458,
                'endTokenPos' => 2613,
                'endFilePos' => 16461,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 645,
            'endLine' => 645,
            'startColumn' => 39,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given recipient is set on the mailable.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return bool
 */',
        'startLine' => 645,
        'endLine' => 648,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'to' => 
      array (
        'name' => 'to',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 24,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 657,
                'endLine' => 657,
                'startTokenPos' => 2651,
                'startFilePos' => 16744,
                'endTokenPos' => 2651,
                'endFilePos' => 16747,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 34,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the recipients of the message.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 657,
        'endLine' => 664,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasTo' => 
      array (
        'name' => 'hasTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 673,
            'endLine' => 673,
            'startColumn' => 27,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 673,
                'endLine' => 673,
                'startTokenPos' => 2723,
                'startFilePos' => 17191,
                'endTokenPos' => 2723,
                'endFilePos' => 17194,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 673,
            'endLine' => 673,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given recipient is set on the mailable.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return bool
 */',
        'startLine' => 673,
        'endLine' => 676,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'cc' => 
      array (
        'name' => 'cc',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 685,
            'endLine' => 685,
            'startColumn' => 24,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 685,
                'endLine' => 685,
                'startTokenPos' => 2761,
                'startFilePos' => 17475,
                'endTokenPos' => 2761,
                'endFilePos' => 17478,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 685,
            'endLine' => 685,
            'startColumn' => 34,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the recipients of the message.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 685,
        'endLine' => 688,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasCc' => 
      array (
        'name' => 'hasCc',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 697,
            'endLine' => 697,
            'startColumn' => 27,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 697,
                'endLine' => 697,
                'startTokenPos' => 2799,
                'startFilePos' => 17781,
                'endTokenPos' => 2799,
                'endFilePos' => 17784,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 697,
            'endLine' => 697,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given recipient is set on the mailable.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return bool
 */',
        'startLine' => 697,
        'endLine' => 700,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'bcc' => 
      array (
        'name' => 'bcc',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 709,
            'endLine' => 709,
            'startColumn' => 25,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 709,
                'endLine' => 709,
                'startTokenPos' => 2837,
                'startFilePos' => 18066,
                'endTokenPos' => 2837,
                'endFilePos' => 18069,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 709,
            'endLine' => 709,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the recipients of the message.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 709,
        'endLine' => 712,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasBcc' => 
      array (
        'name' => 'hasBcc',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 28,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 721,
                'endLine' => 721,
                'startTokenPos' => 2875,
                'startFilePos' => 18374,
                'endTokenPos' => 2875,
                'endFilePos' => 18377,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 38,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given recipient is set on the mailable.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return bool
 */',
        'startLine' => 721,
        'endLine' => 724,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'replyTo' => 
      array (
        'name' => 'replyTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 733,
            'endLine' => 733,
            'startColumn' => 29,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 733,
                'endLine' => 733,
                'startTokenPos' => 2913,
                'startFilePos' => 18672,
                'endTokenPos' => 2913,
                'endFilePos' => 18675,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 733,
            'endLine' => 733,
            'startColumn' => 39,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the "reply to" address of the message.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 733,
        'endLine' => 736,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasReplyTo' => 
      array (
        'name' => 'hasReplyTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 745,
            'endLine' => 745,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 745,
                'endLine' => 745,
                'startTokenPos' => 2951,
                'startFilePos' => 18986,
                'endTokenPos' => 2951,
                'endFilePos' => 18989,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 745,
            'endLine' => 745,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given replyTo is set on the mailable.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return bool
 */',
        'startLine' => 745,
        'endLine' => 748,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'setAddress' => 
      array (
        'name' => 'setAddress',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 760,
            'endLine' => 760,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 760,
                'endLine' => 760,
                'startTokenPos' => 2989,
                'startFilePos' => 19405,
                'endTokenPos' => 2989,
                'endFilePos' => 19408,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 760,
            'endLine' => 760,
            'startColumn' => 45,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'property' => 
          array (
            'name' => 'property',
            'default' => 
            array (
              'code' => '\'to\'',
              'attributes' => 
              array (
                'startLine' => 760,
                'endLine' => 760,
                'startTokenPos' => 2996,
                'startFilePos' => 19423,
                'endTokenPos' => 2996,
                'endFilePos' => 19426,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 760,
            'endLine' => 760,
            'startColumn' => 59,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the recipients of the message.
 *
 * All recipients are stored internally as [[\'name\' => ?, \'address\' => ?]]
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @param  string  $property
 * @return $this
 */',
        'startLine' => 760,
        'endLine' => 783,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'addressesToArray' => 
      array (
        'name' => 'addressesToArray',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 41,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 51,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Convert the given recipient arguments to an array.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return array
 */',
        'startLine' => 792,
        'endLine' => 799,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'normalizeRecipient' => 
      array (
        'name' => 'normalizeRecipient',
        'parameters' => 
        array (
          'recipient' => 
          array (
            'name' => 'recipient',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 807,
            'endLine' => 807,
            'startColumn' => 43,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Convert the given recipient into an object.
 *
 * @param  mixed  $recipient
 * @return object
 */',
        'startLine' => 807,
        'endLine' => 826,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasRecipient' => 
      array (
        'name' => 'hasRecipient',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 836,
                'endLine' => 836,
                'startTokenPos' => 3438,
                'startFilePos' => 21679,
                'endTokenPos' => 3438,
                'endFilePos' => 21682,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 47,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'property' => 
          array (
            'name' => 'property',
            'default' => 
            array (
              'code' => '\'to\'',
              'attributes' => 
              array (
                'startLine' => 836,
                'endLine' => 836,
                'startTokenPos' => 3445,
                'startFilePos' => 21697,
                'endTokenPos' => 3445,
                'endFilePos' => 21700,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 61,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given recipient is set on the mailable.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @param  string  $property
 * @return bool
 */',
        'startLine' => 836,
        'endLine' => 862,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasEnvelopeRecipient' => 
      array (
        'name' => 'hasEnvelopeRecipient',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 872,
            'endLine' => 872,
            'startColumn' => 43,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 872,
            'endLine' => 872,
            'startColumn' => 53,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'property' => 
          array (
            'name' => 'property',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 872,
            'endLine' => 872,
            'startColumn' => 60,
            'endColumn' => 68,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable "envelope" method defines a recipient.
 *
 * @param  string  $address
 * @param  string|null  $name
 * @param  string  $property
 * @return bool
 */',
        'startLine' => 872,
        'endLine' => 881,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'subject' => 
      array (
        'name' => 'subject',
        'parameters' => 
        array (
          'subject' => 
          array (
            'name' => 'subject',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 889,
            'endLine' => 889,
            'startColumn' => 29,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the subject of the message.
 *
 * @param  string  $subject
 * @return $this
 */',
        'startLine' => 889,
        'endLine' => 894,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasSubject' => 
      array (
        'name' => 'hasSubject',
        'parameters' => 
        array (
          'subject' => 
          array (
            'name' => 'subject',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 902,
            'endLine' => 902,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given subject.
 *
 * @param  string  $subject
 * @return bool
 */',
        'startLine' => 902,
        'endLine' => 906,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'markdown' => 
      array (
        'name' => 'markdown',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 915,
            'endLine' => 915,
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 915,
                'endLine' => 915,
                'startTokenPos' => 3871,
                'startFilePos' => 23893,
                'endTokenPos' => 3872,
                'endFilePos' => 23894,
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
            'startLine' => 915,
            'endLine' => 915,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the Markdown template for the message.
 *
 * @param  string  $view
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 915,
        'endLine' => 921,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'view' => 
      array (
        'name' => 'view',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 930,
            'endLine' => 930,
            'startColumn' => 26,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 930,
                'endLine' => 930,
                'startTokenPos' => 3927,
                'startFilePos' => 24227,
                'endTokenPos' => 3928,
                'endFilePos' => 24228,
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
            'startLine' => 930,
            'endLine' => 930,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the view and view data for the message.
 *
 * @param  string  $view
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 930,
        'endLine' => 936,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'html' => 
      array (
        'name' => 'html',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 944,
            'endLine' => 944,
            'startColumn' => 26,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the rendered HTML content for the message.
 *
 * @param  string  $html
 * @return $this
 */',
        'startLine' => 944,
        'endLine' => 949,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'text' => 
      array (
        'name' => 'text',
        'parameters' => 
        array (
          'textView' => 
          array (
            'name' => 'textView',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 958,
            'endLine' => 958,
            'startColumn' => 26,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 958,
                'endLine' => 958,
                'startTokenPos' => 4012,
                'startFilePos' => 24786,
                'endTokenPos' => 4013,
                'endFilePos' => 24787,
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
            'startLine' => 958,
            'endLine' => 958,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the plain text view for the message.
 *
 * @param  string  $textView
 * @param  array  $data
 * @return $this
 */',
        'startLine' => 958,
        'endLine' => 964,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'with' => 
      array (
        'name' => 'with',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 973,
            'endLine' => 973,
            'startColumn' => 26,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 973,
                'endLine' => 973,
                'startTokenPos' => 4066,
                'startFilePos' => 25115,
                'endTokenPos' => 4066,
                'endFilePos' => 25118,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 973,
            'endLine' => 973,
            'startColumn' => 32,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the view data for the message.
 *
 * @param  string|array  $key
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 973,
        'endLine' => 982,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'attach' => 
      array (
        'name' => 'attach',
        'parameters' => 
        array (
          'file' => 
          array (
            'name' => 'file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 991,
            'endLine' => 991,
            'startColumn' => 28,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 991,
                'endLine' => 991,
                'startTokenPos' => 4143,
                'startFilePos' => 25582,
                'endTokenPos' => 4144,
                'endFilePos' => 25583,
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
            'startLine' => 991,
            'endLine' => 991,
            'startColumn' => 35,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attach a file to the message.
 *
 * @param  string|\\Illuminate\\Contracts\\Mail\\Attachable|\\Illuminate\\Mail\\Attachment  $file
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 991,
        'endLine' => 1007,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'attachMany' => 
      array (
        'name' => 'attachMany',
        'parameters' => 
        array (
          'files' => 
          array (
            'name' => 'files',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1015,
            'endLine' => 1015,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attach multiple files to the message.
 *
 * @param  array  $files
 * @return $this
 */',
        'startLine' => 1015,
        'endLine' => 1026,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasAttachment' => 
      array (
        'name' => 'hasAttachment',
        'parameters' => 
        array (
          'file' => 
          array (
            'name' => 'file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1035,
            'endLine' => 1035,
            'startColumn' => 35,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1035,
                'endLine' => 1035,
                'startTokenPos' => 4349,
                'startFilePos' => 26701,
                'endTokenPos' => 4350,
                'endFilePos' => 26702,
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
            'startLine' => 1035,
            'endLine' => 1035,
            'startColumn' => 42,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given attachment.
 *
 * @param  string|\\Illuminate\\Contracts\\Mail\\Attachable|\\Illuminate\\Mail\\Attachment  $file
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1035,
        'endLine' => 1066,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasEnvelopeAttachment' => 
      array (
        'name' => 'hasEnvelopeAttachment',
        'parameters' => 
        array (
          'attachment' => 
          array (
            'name' => 'attachment',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1075,
            'endLine' => 1075,
            'startColumn' => 44,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1075,
                'endLine' => 1075,
                'startTokenPos' => 4653,
                'startFilePos' => 28024,
                'endTokenPos' => 4654,
                'endFilePos' => 28025,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1075,
            'endLine' => 1075,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given envelope attachment.
 *
 * @param  \\Illuminate\\Mail\\Attachment  $attachment
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1075,
        'endLine' => 1086,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'attachFromStorage' => 
      array (
        'name' => 'attachFromStorage',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1096,
            'endLine' => 1096,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1096,
                'endLine' => 1096,
                'startTokenPos' => 4787,
                'startFilePos' => 28707,
                'endTokenPos' => 4787,
                'endFilePos' => 28710,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1096,
            'endLine' => 1096,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1096,
                'endLine' => 1096,
                'startTokenPos' => 4796,
                'startFilePos' => 28730,
                'endTokenPos' => 4797,
                'endFilePos' => 28731,
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
            'startLine' => 1096,
            'endLine' => 1096,
            'startColumn' => 60,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attach a file to the message from storage.
 *
 * @param  string  $path
 * @param  string|null  $name
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 1096,
        'endLine' => 1099,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'attachFromStorageDisk' => 
      array (
        'name' => 'attachFromStorageDisk',
        'parameters' => 
        array (
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1110,
            'endLine' => 1110,
            'startColumn' => 43,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1110,
            'endLine' => 1110,
            'startColumn' => 50,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1110,
                'endLine' => 1110,
                'startTokenPos' => 4841,
                'startFilePos' => 29103,
                'endTokenPos' => 4841,
                'endFilePos' => 29106,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1110,
            'endLine' => 1110,
            'startColumn' => 57,
            'endColumn' => 68,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1110,
                'endLine' => 1110,
                'startTokenPos' => 4850,
                'startFilePos' => 29126,
                'endTokenPos' => 4851,
                'endFilePos' => 29127,
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
            'startLine' => 1110,
            'endLine' => 1110,
            'startColumn' => 71,
            'endColumn' => 89,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attach a file to the message from storage.
 *
 * @param  string  $disk
 * @param  string  $path
 * @param  string|null  $name
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 1110,
        'endLine' => 1122,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasAttachmentFromStorage' => 
      array (
        'name' => 'hasAttachmentFromStorage',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1132,
            'endLine' => 1132,
            'startColumn' => 46,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1132,
                'endLine' => 1132,
                'startTokenPos' => 4970,
                'startFilePos' => 29768,
                'endTokenPos' => 4970,
                'endFilePos' => 29771,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1132,
            'endLine' => 1132,
            'startColumn' => 53,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1132,
                'endLine' => 1132,
                'startTokenPos' => 4979,
                'startFilePos' => 29791,
                'endTokenPos' => 4980,
                'endFilePos' => 29792,
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
            'startLine' => 1132,
            'endLine' => 1132,
            'startColumn' => 67,
            'endColumn' => 85,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given attachment from storage.
 *
 * @param  string  $path
 * @param  string|null  $name
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1132,
        'endLine' => 1135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasAttachmentFromStorageDisk' => 
      array (
        'name' => 'hasAttachmentFromStorageDisk',
        'parameters' => 
        array (
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1146,
            'endLine' => 1146,
            'startColumn' => 50,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1146,
            'endLine' => 1146,
            'startColumn' => 57,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1146,
                'endLine' => 1146,
                'startTokenPos' => 5024,
                'startFilePos' => 30215,
                'endTokenPos' => 5024,
                'endFilePos' => 30218,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1146,
            'endLine' => 1146,
            'startColumn' => 64,
            'endColumn' => 75,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1146,
                'endLine' => 1146,
                'startTokenPos' => 5033,
                'startFilePos' => 30238,
                'endTokenPos' => 5034,
                'endFilePos' => 30239,
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
            'startLine' => 1146,
            'endLine' => 1146,
            'startColumn' => 78,
            'endColumn' => 96,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given attachment from a specific storage disk.
 *
 * @param  string  $disk
 * @param  string  $path
 * @param  string|null  $name
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1146,
        'endLine' => 1154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'attachData' => 
      array (
        'name' => 'attachData',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1164,
            'endLine' => 1164,
            'startColumn' => 32,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1164,
            'endLine' => 1164,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1164,
                'endLine' => 1164,
                'startTokenPos' => 5139,
                'startFilePos' => 30811,
                'endTokenPos' => 5140,
                'endFilePos' => 30812,
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
            'startLine' => 1164,
            'endLine' => 1164,
            'startColumn' => 46,
            'endColumn' => 64,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attach in-memory data as an attachment.
 *
 * @param  string  $data
 * @param  string  $name
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 1164,
        'endLine' => 1172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasAttachedData' => 
      array (
        'name' => 'hasAttachedData',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1182,
            'endLine' => 1182,
            'startColumn' => 37,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1182,
            'endLine' => 1182,
            'startColumn' => 44,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1182,
                'endLine' => 1182,
                'startTokenPos' => 5243,
                'startFilePos' => 31355,
                'endTokenPos' => 5244,
                'endFilePos' => 31356,
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
            'startLine' => 1182,
            'endLine' => 1182,
            'startColumn' => 51,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given data as an attachment.
 *
 * @param  string  $data
 * @param  string  $name
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1182,
        'endLine' => 1189,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'tag' => 
      array (
        'name' => 'tag',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1197,
            'endLine' => 1197,
            'startColumn' => 25,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a tag header to the message when supported by the underlying transport.
 *
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 1197,
        'endLine' => 1202,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasTag' => 
      array (
        'name' => 'hasTag',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1210,
            'endLine' => 1210,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given tag.
 *
 * @param  string  $value
 * @return bool
 */',
        'startLine' => 1210,
        'endLine' => 1214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'metadata' => 
      array (
        'name' => 'metadata',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1223,
            'endLine' => 1223,
            'startColumn' => 30,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1223,
                'endLine' => 1223,
                'startTokenPos' => 5417,
                'startFilePos' => 32462,
                'endTokenPos' => 5417,
                'endFilePos' => 32465,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1223,
            'endLine' => 1223,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a metadata header to the message when supported by the underlying transport.
 *
 * @param  array|string  $key
 * @param  string|null  $value
 * @return $this
 */',
        'startLine' => 1223,
        'endLine' => 1232,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'hasMetadata' => 
      array (
        'name' => 'hasMetadata',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1241,
            'endLine' => 1241,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1241,
            'endLine' => 1241,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable has the given metadata.
 *
 * @param  string  $key
 * @param  string  $value
 * @return bool
 */',
        'startLine' => 1241,
        'endLine' => 1245,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertFrom' => 
      array (
        'name' => 'assertFrom',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1254,
            'endLine' => 1254,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1254,
                'endLine' => 1254,
                'startTokenPos' => 5566,
                'startFilePos' => 33303,
                'endTokenPos' => 5566,
                'endFilePos' => 33306,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1254,
            'endLine' => 1254,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable is from the given address.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 1254,
        'endLine' => 1267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertTo' => 
      array (
        'name' => 'assertTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1276,
            'endLine' => 1276,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1276,
                'endLine' => 1276,
                'startTokenPos' => 5660,
                'startFilePos' => 33926,
                'endTokenPos' => 5660,
                'endFilePos' => 33929,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1276,
            'endLine' => 1276,
            'startColumn' => 40,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given recipient.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 1276,
        'endLine' => 1289,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasTo' => 
      array (
        'name' => 'assertHasTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1298,
            'endLine' => 1298,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1298,
                'endLine' => 1298,
                'startTokenPos' => 5754,
                'startFilePos' => 34568,
                'endTokenPos' => 5754,
                'endFilePos' => 34571,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1298,
            'endLine' => 1298,
            'startColumn' => 43,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given recipient.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 1298,
        'endLine' => 1301,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasCc' => 
      array (
        'name' => 'assertHasCc',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1310,
            'endLine' => 1310,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1310,
                'endLine' => 1310,
                'startTokenPos' => 5789,
                'startFilePos' => 34866,
                'endTokenPos' => 5789,
                'endFilePos' => 34869,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1310,
            'endLine' => 1310,
            'startColumn' => 43,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given recipient.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 1310,
        'endLine' => 1323,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasBcc' => 
      array (
        'name' => 'assertHasBcc',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1332,
            'endLine' => 1332,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1332,
                'endLine' => 1332,
                'startTokenPos' => 5883,
                'startFilePos' => 35509,
                'endTokenPos' => 5883,
                'endFilePos' => 35512,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1332,
            'endLine' => 1332,
            'startColumn' => 44,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given recipient.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 1332,
        'endLine' => 1345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasReplyTo' => 
      array (
        'name' => 'assertHasReplyTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1354,
            'endLine' => 1354,
            'startColumn' => 38,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1354,
                'endLine' => 1354,
                'startTokenPos' => 5977,
                'startFilePos' => 36168,
                'endTokenPos' => 5977,
                'endFilePos' => 36171,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1354,
            'endLine' => 1354,
            'startColumn' => 48,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given "reply to" address.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return $this
 */',
        'startLine' => 1354,
        'endLine' => 1367,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'formatAssertionRecipient' => 
      array (
        'name' => 'formatAssertionRecipient',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1376,
            'endLine' => 1376,
            'startColumn' => 47,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1376,
                'endLine' => 1376,
                'startTokenPos' => 6071,
                'startFilePos' => 36855,
                'endTokenPos' => 6071,
                'endFilePos' => 36858,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1376,
            'endLine' => 1376,
            'startColumn' => 57,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Format the mailable recipient for display in an assertion message.
 *
 * @param  object|array|string  $address
 * @param  string|null  $name
 * @return string
 */',
        'startLine' => 1376,
        'endLine' => 1387,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'formatActualRecipients' => 
      array (
        'name' => 'formatActualRecipients',
        'parameters' => 
        array (
          'recipients' => 
          array (
            'name' => 'recipients',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1395,
            'endLine' => 1395,
            'startColumn' => 45,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Format actual recipients for display in assertion messages.
 *
 * @param  array  $recipients
 * @return string
 */',
        'startLine' => 1395,
        'endLine' => 1409,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasSubject' => 
      array (
        'name' => 'assertHasSubject',
        'parameters' => 
        array (
          'subject' => 
          array (
            'name' => 'subject',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1417,
            'endLine' => 1417,
            'startColumn' => 38,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given subject.
 *
 * @param  string  $subject
 * @return $this
 */',
        'startLine' => 1417,
        'endLine' => 1429,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertSeeInHtml' => 
      array (
        'name' => 'assertSeeInHtml',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1438,
            'endLine' => 1438,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1438,
                'endLine' => 1438,
                'startTokenPos' => 6369,
                'startFilePos' => 38505,
                'endTokenPos' => 6369,
                'endFilePos' => 38508,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1438,
            'endLine' => 1438,
            'startColumn' => 46,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given text is present in the HTML email body.
 *
 * @param  string  $string
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 1438,
        'endLine' => 1451,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertDontSeeInHtml' => 
      array (
        'name' => 'assertDontSeeInHtml',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1460,
            'endLine' => 1460,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1460,
                'endLine' => 1460,
                'startTokenPos' => 6457,
                'startFilePos' => 39092,
                'endTokenPos' => 6457,
                'endFilePos' => 39095,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1460,
            'endLine' => 1460,
            'startColumn' => 50,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given text is not present in the HTML email body.
 *
 * @param  string  $string
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 1460,
        'endLine' => 1473,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertSeeInOrderInHtml' => 
      array (
        'name' => 'assertSeeInOrderInHtml',
        'parameters' => 
        array (
          'strings' => 
          array (
            'name' => 'strings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1482,
            'endLine' => 1482,
            'startColumn' => 44,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1482,
                'endLine' => 1482,
                'startTokenPos' => 6545,
                'startFilePos' => 39694,
                'endTokenPos' => 6545,
                'endFilePos' => 39697,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1482,
            'endLine' => 1482,
            'startColumn' => 54,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given text strings are present in order in the HTML email body.
 *
 * @param  array  $strings
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 1482,
        'endLine' => 1493,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertSeeInText' => 
      array (
        'name' => 'assertSeeInText',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1501,
            'endLine' => 1501,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given text is present in the plain-text email body.
 *
 * @param  string  $string
 * @return $this
 */',
        'startLine' => 1501,
        'endLine' => 1512,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertDontSeeInText' => 
      array (
        'name' => 'assertDontSeeInText',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1520,
            'endLine' => 1520,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given text is not present in the plain-text email body.
 *
 * @param  string  $string
 * @return $this
 */',
        'startLine' => 1520,
        'endLine' => 1531,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertSeeInOrderInText' => 
      array (
        'name' => 'assertSeeInOrderInText',
        'parameters' => 
        array (
          'strings' => 
          array (
            'name' => 'strings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1539,
            'endLine' => 1539,
            'startColumn' => 44,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the given text strings are present in order in the plain-text email body.
 *
 * @param  array  $strings
 * @return $this
 */',
        'startLine' => 1539,
        'endLine' => 1546,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasNoAttachments' => 
      array (
        'name' => 'assertHasNoAttachments',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the mailable has the given attachment.
 *
 * @return $this
 */',
        'startLine' => 1553,
        'endLine' => 1573,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasAttachment' => 
      array (
        'name' => 'assertHasAttachment',
        'parameters' => 
        array (
          'file' => 
          array (
            'name' => 'file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1575,
            'endLine' => 1575,
            'startColumn' => 41,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1575,
                'endLine' => 1575,
                'startTokenPos' => 6909,
                'startFilePos' => 42123,
                'endTokenPos' => 6910,
                'endFilePos' => 42124,
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
            'startLine' => 1575,
            'endLine' => 1575,
            'startColumn' => 48,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1575,
        'endLine' => 1585,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasAttachedData' => 
      array (
        'name' => 'assertHasAttachedData',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1595,
            'endLine' => 1595,
            'startColumn' => 43,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1595,
            'endLine' => 1595,
            'startColumn' => 50,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1595,
                'endLine' => 1595,
                'startTokenPos' => 6970,
                'startFilePos' => 42615,
                'endTokenPos' => 6971,
                'endFilePos' => 42616,
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
            'startLine' => 1595,
            'endLine' => 1595,
            'startColumn' => 57,
            'endColumn' => 75,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the mailable has the given data as an attachment.
 *
 * @param  string  $data
 * @param  string  $name
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 1595,
        'endLine' => 1605,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasAttachmentFromStorage' => 
      array (
        'name' => 'assertHasAttachmentFromStorage',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1615,
            'endLine' => 1615,
            'startColumn' => 52,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1615,
                'endLine' => 1615,
                'startTokenPos' => 7029,
                'startFilePos' => 43116,
                'endTokenPos' => 7029,
                'endFilePos' => 43119,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1615,
            'endLine' => 1615,
            'startColumn' => 59,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1615,
                'endLine' => 1615,
                'startTokenPos' => 7038,
                'startFilePos' => 43139,
                'endTokenPos' => 7039,
                'endFilePos' => 43140,
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
            'startLine' => 1615,
            'endLine' => 1615,
            'startColumn' => 73,
            'endColumn' => 91,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the mailable has the given attachment from storage.
 *
 * @param  string  $path
 * @param  string|null  $name
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 1615,
        'endLine' => 1625,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasAttachmentFromStorageDisk' => 
      array (
        'name' => 'assertHasAttachmentFromStorageDisk',
        'parameters' => 
        array (
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1636,
            'endLine' => 1636,
            'startColumn' => 56,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1636,
            'endLine' => 1636,
            'startColumn' => 63,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1636,
                'endLine' => 1636,
                'startTokenPos' => 7100,
                'startFilePos' => 43705,
                'endTokenPos' => 7100,
                'endFilePos' => 43708,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1636,
            'endLine' => 1636,
            'startColumn' => 70,
            'endColumn' => 81,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1636,
                'endLine' => 1636,
                'startTokenPos' => 7109,
                'startFilePos' => 43728,
                'endTokenPos' => 7110,
                'endFilePos' => 43729,
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
            'startLine' => 1636,
            'endLine' => 1636,
            'startColumn' => 84,
            'endColumn' => 102,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert the mailable has the given attachment from a specific storage disk.
 *
 * @param  string  $disk
 * @param  string  $path
 * @param  string|null  $name
 * @param  array  $options
 * @return $this
 */',
        'startLine' => 1636,
        'endLine' => 1646,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasTag' => 
      array (
        'name' => 'assertHasTag',
        'parameters' => 
        array (
          'tag' => 
          array (
            'name' => 'tag',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1654,
            'endLine' => 1654,
            'startColumn' => 34,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given tag.
 *
 * @param  string  $tag
 * @return $this
 */',
        'startLine' => 1654,
        'endLine' => 1667,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'assertHasMetadata' => 
      array (
        'name' => 'assertHasMetadata',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1676,
            'endLine' => 1676,
            'startColumn' => 39,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1676,
            'endLine' => 1676,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the mailable has the given metadata.
 *
 * @param  string  $key
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 1676,
        'endLine' => 1690,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'renderForAssertions' => 
      array (
        'name' => 'renderForAssertions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Render the HTML and plain-text version of the mailable into views for assertions.
 *
 * @return array
 *
 * @throws \\ReflectionException
 */',
        'startLine' => 1699,
        'endLine' => 1726,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'prepareMailableForDelivery' => 
      array (
        'name' => 'prepareMailableForDelivery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepare the mailable instance for delivery.
 *
 * @return void
 */',
        'startLine' => 1733,
        'endLine' => 1743,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'ensureHeadersAreHydrated' => 
      array (
        'name' => 'ensureHeadersAreHydrated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the mailable\'s headers are hydrated from the "headers" method.
 *
 * @return void
 */',
        'startLine' => 1750,
        'endLine' => 1771,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'ensureEnvelopeIsHydrated' => 
      array (
        'name' => 'ensureEnvelopeIsHydrated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the mailable\'s "envelope" data is hydrated from the "envelope" method.
 *
 * @return void
 */',
        'startLine' => 1778,
        'endLine' => 1811,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'ensureContentIsHydrated' => 
      array (
        'name' => 'ensureContentIsHydrated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the mailable\'s content is hydrated from the "content" method.
 *
 * @return void
 */',
        'startLine' => 1818,
        'endLine' => 1849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'ensureAttachmentsAreHydrated' => 
      array (
        'name' => 'ensureAttachmentsAreHydrated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the mailable\'s attachments are hydrated from the "attachments" method.
 *
 * @return void
 */',
        'startLine' => 1856,
        'endLine' => 1868,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'usesMailer' => 
      array (
        'name' => 'usesMailer',
        'parameters' => 
        array (
          'mailer' => 
          array (
            'name' => 'mailer',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1876,
            'endLine' => 1876,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the mailable will be sent by the given mailer.
 *
 * @param  string  $mailer
 * @return bool
 */',
        'startLine' => 1876,
        'endLine' => 1879,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'mailer' => 
      array (
        'name' => 'mailer',
        'parameters' => 
        array (
          'mailer' => 
          array (
            'name' => 'mailer',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1887,
            'endLine' => 1887,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the name of the mailer that should send the message.
 *
 * @param  string  $mailer
 * @return $this
 */',
        'startLine' => 1887,
        'endLine' => 1892,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'withSymfonyMessage' => 
      array (
        'name' => 'withSymfonyMessage',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1900,
            'endLine' => 1900,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a callback to be called with the Symfony message instance.
 *
 * @param  callable  $callback
 * @return $this
 */',
        'startLine' => 1900,
        'endLine' => 1905,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      'buildViewDataUsing' => 
      array (
        'name' => 'buildViewDataUsing',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1913,
            'endLine' => 1913,
            'startColumn' => 47,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a callback to be called while building the view data.
 *
 * @param  callable  $callback
 * @return void
 */',
        'startLine' => 1913,
        'endLine' => 1916,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1927,
            'endLine' => 1927,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1927,
            'endLine' => 1927,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dynamically bind parameters to the message.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return $this
 *
 * @throws \\BadMethodCallException
 */',
        'startLine' => 1927,
        'endLine' => 1938,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailable',
        'implementingClassName' => 'Illuminate\\Mail\\Mailable',
        'currentClassName' => 'Illuminate\\Mail\\Mailable',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Support\\Traits\\Conditionable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\conditionable::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\ForwardsCalls' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\forwardscalls::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Localizable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\localizable::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\ReadsClassAttributes' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\readsclassattributes::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Tappable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\tappable::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\support\\traits\\conditionable::__call' => 'Illuminate\\Support\\Traits\\Conditionable::__call',
        'illuminate\\support\\traits\\forwardscalls::__call' => 'Illuminate\\Support\\Traits\\ForwardsCalls::__call',
        'illuminate\\support\\traits\\localizable::__call' => 'Illuminate\\Support\\Traits\\Localizable::__call',
        'illuminate\\support\\traits\\readsclassattributes::__call' => 'Illuminate\\Support\\Traits\\ReadsClassAttributes::__call',
        'illuminate\\support\\traits\\tappable::__call' => 'Illuminate\\Support\\Traits\\Tappable::__call',
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));