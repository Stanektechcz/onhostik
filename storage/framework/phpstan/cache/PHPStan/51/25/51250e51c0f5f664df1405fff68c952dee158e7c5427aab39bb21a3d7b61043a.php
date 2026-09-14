<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Net/SSH2.php-PHPStan\BetterReflection\Reflection\ReflectionClass-phpseclib3\Net\SSH2
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-48f36d11d38ed0929665b2e7c0910edcd5b5707490394a508e28f54762ad0229-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'phpseclib3\\Net\\SSH2',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Net/SSH2.php',
      ),
    ),
    'namespace' => 'phpseclib3\\Net',
    'name' => 'phpseclib3\\Net\\SSH2',
    'shortName' => 'SSH2',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Pure-PHP implementation of SSHv2.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 81,
    'endLine' => 5643,
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
      'NET_SSH2_COMPRESSION_NONE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'NET_SSH2_COMPRESSION_NONE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 90,
            'endLine' => 90,
            'startTokenPos' => 164,
            'startFilePos' => 2434,
            'endTokenPos' => 164,
            'endFilePos' => 2434,
          ),
        ),
        'docComment' => '/**
 * No compression
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 90,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'NET_SSH2_COMPRESSION_ZLIB' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'NET_SSH2_COMPRESSION_ZLIB',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 94,
            'endLine' => 94,
            'startTokenPos' => 175,
            'startFilePos' => 2515,
            'endTokenPos' => 175,
            'endFilePos' => 2515,
          ),
        ),
        'docComment' => '/**
 * zlib compression
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 94,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'NET_SSH2_COMPRESSION_ZLIB_AT_OPENSSH' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'NET_SSH2_COMPRESSION_ZLIB_AT_OPENSSH',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 98,
            'endLine' => 98,
            'startTokenPos' => 186,
            'startFilePos' => 2607,
            'endTokenPos' => 186,
            'endFilePos' => 2607,
          ),
        ),
        'docComment' => '/**
 * zlib@openssh.com
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 98,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
      'MASK_CONSTRUCTOR' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'MASK_CONSTRUCTOR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x1',
          'attributes' => 
          array (
            'startLine' => 102,
            'endLine' => 102,
            'startTokenPos' => 199,
            'startFilePos' => 2685,
            'endTokenPos' => 199,
            'endFilePos' => 2694,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 102,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'MASK_CONNECTED' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'MASK_CONNECTED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x2',
          'attributes' => 
          array (
            'startLine' => 103,
            'endLine' => 103,
            'startTokenPos' => 208,
            'startFilePos' => 2728,
            'endTokenPos' => 208,
            'endFilePos' => 2737,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'MASK_LOGIN_REQ' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'MASK_LOGIN_REQ',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x4',
          'attributes' => 
          array (
            'startLine' => 104,
            'endLine' => 104,
            'startTokenPos' => 217,
            'startFilePos' => 2771,
            'endTokenPos' => 217,
            'endFilePos' => 2780,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 104,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'MASK_LOGIN' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'MASK_LOGIN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x8',
          'attributes' => 
          array (
            'startLine' => 105,
            'endLine' => 105,
            'startTokenPos' => 226,
            'startFilePos' => 2814,
            'endTokenPos' => 226,
            'endFilePos' => 2823,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 105,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'MASK_SHELL' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'MASK_SHELL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x10',
          'attributes' => 
          array (
            'startLine' => 106,
            'endLine' => 106,
            'startTokenPos' => 235,
            'startFilePos' => 2857,
            'endTokenPos' => 235,
            'endFilePos' => 2866,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 106,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'MASK_DISCONNECT' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'MASK_DISCONNECT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x20',
          'attributes' => 
          array (
            'startLine' => 107,
            'endLine' => 107,
            'startTokenPos' => 244,
            'startFilePos' => 2900,
            'endTokenPos' => 244,
            'endFilePos' => 2909,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 107,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'CHANNEL_EXEC' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'CHANNEL_EXEC',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 124,
            'endLine' => 124,
            'startTokenPos' => 255,
            'startFilePos' => 3839,
            'endTokenPos' => 255,
            'endFilePos' => 3839,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 124,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'CHANNEL_SHELL' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'CHANNEL_SHELL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 125,
            'endLine' => 125,
            'startTokenPos' => 266,
            'startFilePos' => 3896,
            'endTokenPos' => 266,
            'endFilePos' => 3896,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 125,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'CHANNEL_SUBSYSTEM' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'CHANNEL_SUBSYSTEM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 126,
            'endLine' => 126,
            'startTokenPos' => 275,
            'startFilePos' => 3933,
            'endTokenPos' => 275,
            'endFilePos' => 3933,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 126,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'CHANNEL_AGENT_FORWARD' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'CHANNEL_AGENT_FORWARD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 127,
            'endLine' => 127,
            'startTokenPos' => 284,
            'startFilePos' => 3970,
            'endTokenPos' => 284,
            'endFilePos' => 3970,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 127,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'CHANNEL_KEEP_ALIVE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'CHANNEL_KEEP_ALIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 128,
            'endLine' => 128,
            'startTokenPos' => 293,
            'startFilePos' => 4007,
            'endTokenPos' => 293,
            'endFilePos' => 4007,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 128,
        'endLine' => 128,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'LOG_SIMPLE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_SIMPLE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 135,
            'endLine' => 135,
            'startTokenPos' => 304,
            'startFilePos' => 4119,
            'endTokenPos' => 304,
            'endFilePos' => 4119,
          ),
        ),
        'docComment' => '/**
 * Returns the message numbers
 *
 * @see SSH2::getLog()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 135,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'LOG_COMPLEX' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_COMPLEX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 141,
            'endLine' => 141,
            'startTokenPos' => 315,
            'startFilePos' => 4231,
            'endTokenPos' => 315,
            'endFilePos' => 4231,
          ),
        ),
        'docComment' => '/**
 * Returns the message content
 *
 * @see SSH2::getLog()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 141,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 26,
      ),
      'LOG_REALTIME' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_REALTIME',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 145,
            'endLine' => 145,
            'startTokenPos' => 326,
            'startFilePos' => 4312,
            'endTokenPos' => 326,
            'endFilePos' => 4312,
          ),
        ),
        'docComment' => '/**
 * Outputs the content real-time
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 145,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
      'LOG_REALTIME_FILE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_REALTIME_FILE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 149,
            'endLine' => 149,
            'startTokenPos' => 337,
            'startFilePos' => 4406,
            'endTokenPos' => 337,
            'endFilePos' => 4406,
          ),
        ),
        'docComment' => '/**
 * Dumps the content real-time to a file
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 149,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'LOG_SIMPLE_REALTIME' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_SIMPLE_REALTIME',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 153,
            'endLine' => 153,
            'startTokenPos' => 348,
            'startFilePos' => 4502,
            'endTokenPos' => 348,
            'endFilePos' => 4502,
          ),
        ),
        'docComment' => '/**
 * Outputs the message numbers real-time
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 153,
        'endLine' => 153,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'LOG_REALTIME_SIMPLE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_REALTIME_SIMPLE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 157,
            'endLine' => 157,
            'startTokenPos' => 359,
            'startFilePos' => 4595,
            'endTokenPos' => 359,
            'endFilePos' => 4595,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 157,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'LOG_MAX_SIZE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'LOG_MAX_SIZE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1048576',
          'attributes' => 
          array (
            'startLine' => 163,
            'endLine' => 163,
            'startTokenPos' => 370,
            'startFilePos' => 4731,
            'endTokenPos' => 370,
            'endFilePos' => 4737,
          ),
        ),
        'docComment' => '/**
 * Make sure that the log never gets larger than this
 *
 * @see SSH2::getLog()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 163,
        'endLine' => 163,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'READ_SIMPLE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'READ_SIMPLE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 170,
            'endLine' => 170,
            'startTokenPos' => 383,
            'startFilePos' => 4891,
            'endTokenPos' => 383,
            'endFilePos' => 4891,
          ),
        ),
        'docComment' => '/**
 * Returns when a string matching $expect exactly is found
 *
 * @see SSH2::read()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 170,
        'endLine' => 170,
        'startColumn' => 5,
        'endColumn' => 26,
      ),
      'READ_REGEX' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'READ_REGEX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 176,
            'endLine' => 176,
            'startTokenPos' => 394,
            'startFilePos' => 5043,
            'endTokenPos' => 394,
            'endFilePos' => 5043,
          ),
        ),
        'docComment' => '/**
 * Returns when a string matching the regular expression $expect is found
 *
 * @see SSH2::read()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 176,
        'endLine' => 176,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'READ_NEXT' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'READ_NEXT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 185,
            'endLine' => 185,
            'startTokenPos' => 405,
            'startFilePos' => 5318,
            'endTokenPos' => 405,
            'endFilePos' => 5318,
          ),
        ),
        'docComment' => '/**
 * Returns whenever a data packet is received.
 *
 * Some data packets may only contain a single character so it may be necessary
 * to call read() multiple times when using this option
 *
 * @see SSH2::read()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 185,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 24,
      ),
    ),
    'immediateProperties' => 
    array (
      'identifier' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'identifier',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The SSH identifier
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 192,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fsock' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'fsock',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Socket Object
 *
 * @var resource|closed-resource|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 199,
        'endLine' => 199,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bitmap' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'bitmap',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 209,
            'endLine' => 209,
            'startTokenPos' => 430,
            'startFilePos' => 5826,
            'endTokenPos' => 430,
            'endFilePos' => 5826,
          ),
        ),
        'docComment' => '/**
 * Execution Bitmap
 *
 * The bits that are set represent functions that have been called already.  This is used to determine
 * if a requisite function has been successfully executed.  If not, an error should be thrown.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 209,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'errors' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'errors',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 218,
            'endLine' => 218,
            'startTokenPos' => 441,
            'startFilePos' => 5981,
            'endTokenPos' => 442,
            'endFilePos' => 5982,
          ),
        ),
        'docComment' => '/**
 * Error information
 *
 * @see self::getErrors()
 * @see self::getLastError()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 218,
        'endLine' => 218,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'server_identifier' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'server_identifier',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 226,
            'endLine' => 226,
            'startTokenPos' => 453,
            'startFilePos' => 6138,
            'endTokenPos' => 453,
            'endFilePos' => 6142,
          ),
        ),
        'docComment' => '/**
 * Server Identifier
 *
 * @see self::getServerIdentification()
 * @var string|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 226,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'kex_algorithms' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'kex_algorithms',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 234,
            'endLine' => 234,
            'startTokenPos' => 464,
            'startFilePos' => 6292,
            'endTokenPos' => 464,
            'endFilePos' => 6296,
          ),
        ),
        'docComment' => '/**
 * Key Exchange Algorithms
 *
 * @see self::getKexAlgorithims()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 234,
        'endLine' => 234,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'kex_algorithm' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'kex_algorithm',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 242,
            'endLine' => 242,
            'startTokenPos' => 475,
            'startFilePos' => 6448,
            'endTokenPos' => 475,
            'endFilePos' => 6452,
          ),
        ),
        'docComment' => '/**
 * Key Exchange Algorithm
 *
 * @see self::getMethodsNegotiated()
 * @var string|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 242,
        'endLine' => 242,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'kex_dh_group_size_min' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'kex_dh_group_size_min',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '1536',
          'attributes' => 
          array (
            'startLine' => 250,
            'endLine' => 250,
            'startTokenPos' => 486,
            'startFilePos' => 6644,
            'endTokenPos' => 486,
            'endFilePos' => 6647,
          ),
        ),
        'docComment' => '/**
 * Minimum Diffie-Hellman Group Bit Size in RFC 4419 Key Exchange Methods
 *
 * @see self::_key_exchange()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 250,
        'endLine' => 250,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'kex_dh_group_size_preferred' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'kex_dh_group_size_preferred',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '2048',
          'attributes' => 
          array (
            'startLine' => 258,
            'endLine' => 258,
            'startTokenPos' => 497,
            'startFilePos' => 6847,
            'endTokenPos' => 497,
            'endFilePos' => 6850,
          ),
        ),
        'docComment' => '/**
 * Preferred Diffie-Hellman Group Bit Size in RFC 4419 Key Exchange Methods
 *
 * @see self::_key_exchange()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 258,
        'endLine' => 258,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'kex_dh_group_size_max' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'kex_dh_group_size_max',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '4096',
          'attributes' => 
          array (
            'startLine' => 266,
            'endLine' => 266,
            'startTokenPos' => 508,
            'startFilePos' => 7042,
            'endTokenPos' => 508,
            'endFilePos' => 7045,
          ),
        ),
        'docComment' => '/**
 * Maximum Diffie-Hellman Group Bit Size in RFC 4419 Key Exchange Methods
 *
 * @see self::_key_exchange()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 266,
        'endLine' => 266,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'server_host_key_algorithms' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'server_host_key_algorithms',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 274,
            'endLine' => 274,
            'startTokenPos' => 519,
            'startFilePos' => 7219,
            'endTokenPos' => 519,
            'endFilePos' => 7223,
          ),
        ),
        'docComment' => '/**
 * Server Host Key Algorithms
 *
 * @see self::getServerHostKeyAlgorithms()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 274,
        'endLine' => 274,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'supported_private_key_algorithms' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'supported_private_key_algorithms',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 286,
            'endLine' => 286,
            'startTokenPos' => 530,
            'startFilePos' => 7627,
            'endTokenPos' => 530,
            'endFilePos' => 7631,
          ),
        ),
        'docComment' => '/**
 * Supported Private Key Algorithms
 *
 * In theory this should be the same as the Server Host Key Algorithms but, in practice,
 * some servers (eg. Azure) will support rsa-sha2-512 as a server host key algorithm but
 * not a private key algorithm
 *
 * @see self::privatekey_login()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 286,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 54,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encryption_algorithms_client_to_server' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encryption_algorithms_client_to_server',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 294,
            'endLine' => 294,
            'startTokenPos' => 541,
            'startFilePos' => 7840,
            'endTokenPos' => 541,
            'endFilePos' => 7844,
          ),
        ),
        'docComment' => '/**
 * Encryption Algorithms: Client to Server
 *
 * @see self::getEncryptionAlgorithmsClient2Server()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 294,
        'endLine' => 294,
        'startColumn' => 5,
        'endColumn' => 60,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encryption_algorithms_server_to_client' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encryption_algorithms_server_to_client',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 302,
            'endLine' => 302,
            'startTokenPos' => 552,
            'startFilePos' => 8053,
            'endTokenPos' => 552,
            'endFilePos' => 8057,
          ),
        ),
        'docComment' => '/**
 * Encryption Algorithms: Server to Client
 *
 * @see self::getEncryptionAlgorithmsServer2Client()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 302,
        'endLine' => 302,
        'startColumn' => 5,
        'endColumn' => 60,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mac_algorithms_client_to_server' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'mac_algorithms_client_to_server',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 310,
            'endLine' => 310,
            'startTokenPos' => 563,
            'startFilePos' => 8245,
            'endTokenPos' => 563,
            'endFilePos' => 8249,
          ),
        ),
        'docComment' => '/**
 * MAC Algorithms: Client to Server
 *
 * @see self::getMACAlgorithmsClient2Server()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 310,
        'endLine' => 310,
        'startColumn' => 5,
        'endColumn' => 53,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mac_algorithms_server_to_client' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'mac_algorithms_server_to_client',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 318,
            'endLine' => 318,
            'startTokenPos' => 574,
            'startFilePos' => 8437,
            'endTokenPos' => 574,
            'endFilePos' => 8441,
          ),
        ),
        'docComment' => '/**
 * MAC Algorithms: Server to Client
 *
 * @see self::getMACAlgorithmsServer2Client()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 318,
        'endLine' => 318,
        'startColumn' => 5,
        'endColumn' => 53,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'compression_algorithms_client_to_server' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'compression_algorithms_client_to_server',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 326,
            'endLine' => 326,
            'startTokenPos' => 585,
            'startFilePos' => 8653,
            'endTokenPos' => 585,
            'endFilePos' => 8657,
          ),
        ),
        'docComment' => '/**
 * Compression Algorithms: Client to Server
 *
 * @see self::getCompressionAlgorithmsClient2Server()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 326,
        'endLine' => 326,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'compression_algorithms_server_to_client' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'compression_algorithms_server_to_client',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 334,
            'endLine' => 334,
            'startTokenPos' => 596,
            'startFilePos' => 8869,
            'endTokenPos' => 596,
            'endFilePos' => 8873,
          ),
        ),
        'docComment' => '/**
 * Compression Algorithms: Server to Client
 *
 * @see self::getCompressionAlgorithmsServer2Client()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 334,
        'endLine' => 334,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'languages_server_to_client' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'languages_server_to_client',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 342,
            'endLine' => 342,
            'startTokenPos' => 607,
            'startFilePos' => 9047,
            'endTokenPos' => 607,
            'endFilePos' => 9051,
          ),
        ),
        'docComment' => '/**
 * Languages: Server to Client
 *
 * @see self::getLanguagesServer2Client()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 342,
        'endLine' => 342,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'languages_client_to_server' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'languages_client_to_server',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 350,
            'endLine' => 350,
            'startTokenPos' => 618,
            'startFilePos' => 9225,
            'endTokenPos' => 618,
            'endFilePos' => 9229,
          ),
        ),
        'docComment' => '/**
 * Languages: Client to Server
 *
 * @see self::getLanguagesClient2Server()
 * @var array|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 350,
        'endLine' => 350,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preferred' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'preferred',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 358,
            'endLine' => 358,
            'startTokenPos' => 629,
            'startFilePos' => 9370,
            'endTokenPos' => 630,
            'endFilePos' => 9371,
          ),
        ),
        'docComment' => '/**
 * Preferred Algorithms
 *
 * @see self::setPreferredAlgorithms()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 358,
        'endLine' => 358,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encrypt_block_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encrypt_block_size',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 374,
            'endLine' => 374,
            'startTokenPos' => 641,
            'startFilePos' => 9923,
            'endTokenPos' => 641,
            'endFilePos' => 9923,
          ),
        ),
        'docComment' => '/**
 * Block Size for Server to Client Encryption
 *
 * "Note that the length of the concatenation of \'packet_length\',
 *  \'padding_length\', \'payload\', and \'random padding\' MUST be a multiple
 *  of the cipher block size or 8, whichever is larger.  This constraint
 *  MUST be enforced, even when using stream ciphers."
 *
 *  -- http://tools.ietf.org/html/rfc4253#section-6
 *
 * @see self::__construct()
 * @see self::_send_binary_packet()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 374,
        'endLine' => 374,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decrypt_block_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decrypt_block_size',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 383,
            'endLine' => 383,
            'startTokenPos' => 652,
            'startFilePos' => 10121,
            'endTokenPos' => 652,
            'endFilePos' => 10121,
          ),
        ),
        'docComment' => '/**
 * Block Size for Client to Server Encryption
 *
 * @see self::__construct()
 * @see self::_get_binary_packet()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 383,
        'endLine' => 383,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decrypt' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decrypt',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 391,
            'endLine' => 391,
            'startTokenPos' => 663,
            'startFilePos' => 10283,
            'endTokenPos' => 663,
            'endFilePos' => 10287,
          ),
        ),
        'docComment' => '/**
 * Server to Client Encryption Object
 *
 * @see self::_get_binary_packet()
 * @var SymmetricKey|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 391,
        'endLine' => 391,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decryptName' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decryptName',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Decryption Algorithm Name
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 398,
        'endLine' => 398,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decryptInvocationCounter' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decryptInvocationCounter',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Decryption Invocation Counter
 *
 * Used by GCM
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 407,
        'endLine' => 407,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decryptFixedPart' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decryptFixedPart',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Fixed Part of Nonce
 *
 * Used by GCM
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 416,
        'endLine' => 416,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lengthDecrypt' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'lengthDecrypt',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 424,
            'endLine' => 424,
            'startTokenPos' => 695,
            'startFilePos' => 10839,
            'endTokenPos' => 695,
            'endFilePos' => 10843,
          ),
        ),
        'docComment' => '/**
 * Server to Client Length Encryption Object
 *
 * @see self::_get_binary_packet()
 * @var object
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 424,
        'endLine' => 424,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encrypt' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encrypt',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 432,
            'endLine' => 432,
            'startTokenPos' => 706,
            'startFilePos' => 11006,
            'endTokenPos' => 706,
            'endFilePos' => 11010,
          ),
        ),
        'docComment' => '/**
 * Client to Server Encryption Object
 *
 * @see self::_send_binary_packet()
 * @var SymmetricKey|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 432,
        'endLine' => 432,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encryptName' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encryptName',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Encryption Algorithm Name
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 439,
        'endLine' => 439,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encryptInvocationCounter' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encryptInvocationCounter',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Encryption Invocation Counter
 *
 * Used by GCM
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 448,
        'endLine' => 448,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encryptFixedPart' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'encryptFixedPart',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Fixed Part of Nonce
 *
 * Used by GCM
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 457,
        'endLine' => 457,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lengthEncrypt' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'lengthEncrypt',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 465,
            'endLine' => 465,
            'startTokenPos' => 738,
            'startFilePos' => 11563,
            'endTokenPos' => 738,
            'endFilePos' => 11567,
          ),
        ),
        'docComment' => '/**
 * Client to Server Length Encryption Object
 *
 * @see self::_send_binary_packet()
 * @var object
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 465,
        'endLine' => 465,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_create' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_create',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 473,
            'endLine' => 473,
            'startTokenPos' => 749,
            'startFilePos' => 11716,
            'endTokenPos' => 749,
            'endFilePos' => 11720,
          ),
        ),
        'docComment' => '/**
 * Client to Server HMAC Object
 *
 * @see self::_send_binary_packet()
 * @var object
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 473,
        'endLine' => 473,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_create_name' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_create_name',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Client to Server HMAC Name
 *
 * @var string|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 480,
        'endLine' => 480,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_create_etm' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_create_etm',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Client to Server ETM
 *
 * @var int|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 487,
        'endLine' => 487,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_check' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_check',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 495,
            'endLine' => 495,
            'startTokenPos' => 774,
            'startFilePos' => 12085,
            'endTokenPos' => 774,
            'endFilePos' => 12089,
          ),
        ),
        'docComment' => '/**
 * Server to Client HMAC Object
 *
 * @see self::_get_binary_packet()
 * @var object
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 495,
        'endLine' => 495,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_check_name' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_check_name',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Server to Client HMAC Name
 *
 * @var string|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 502,
        'endLine' => 502,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_check_etm' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_check_etm',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Server to Client ETM
 *
 * @var int|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 509,
        'endLine' => 509,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hmac_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'hmac_size',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 521,
            'endLine' => 521,
            'startTokenPos' => 799,
            'startFilePos' => 12719,
            'endTokenPos' => 799,
            'endFilePos' => 12723,
          ),
        ),
        'docComment' => '/**
 * Size of server to client HMAC
 *
 * We need to know how big the HMAC will be for the server to client direction so that we know how many bytes to read.
 * For the client to server side, the HMAC object will make the HMAC as long as it needs to be.  All we need to do is
 * append it.
 *
 * @see self::_get_binary_packet()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 521,
        'endLine' => 521,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'server_public_host_key' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'server_public_host_key',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Server Public Host Key
 *
 * @see self::getServerPublicHostKey()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 529,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'session_id' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'session_id',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 543,
            'endLine' => 543,
            'startTokenPos' => 817,
            'startFilePos' => 13251,
            'endTokenPos' => 817,
            'endFilePos' => 13255,
          ),
        ),
        'docComment' => '/**
 * Session identifier
 *
 * "The exchange hash H from the first key exchange is additionally
 *  used as the session identifier, which is a unique identifier for
 *  this connection."
 *
 *  -- http://tools.ietf.org/html/rfc4253#section-7.2
 *
 * @see self::_key_exchange()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 543,
        'endLine' => 543,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exchange_hash' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'exchange_hash',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 553,
            'endLine' => 553,
            'startTokenPos' => 828,
            'startFilePos' => 13425,
            'endTokenPos' => 828,
            'endFilePos' => 13429,
          ),
        ),
        'docComment' => '/**
 * Exchange hash
 *
 * The current exchange hash
 *
 * @see self::_key_exchange()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 553,
        'endLine' => 553,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'message_numbers' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'message_numbers',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 562,
            'endLine' => 562,
            'startTokenPos' => 841,
            'startFilePos' => 13590,
            'endTokenPos' => 842,
            'endFilePos' => 13591,
          ),
        ),
        'docComment' => '/**
 * Message Numbers
 *
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 562,
        'endLine' => 562,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'disconnect_reasons' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'disconnect_reasons',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 571,
            'endLine' => 571,
            'startTokenPos' => 855,
            'startFilePos' => 13795,
            'endTokenPos' => 856,
            'endFilePos' => 13796,
          ),
        ),
        'docComment' => '/**
 * Disconnection Message \'reason codes\' defined in RFC4253
 *
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 571,
        'endLine' => 571,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_open_failure_reasons' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channel_open_failure_reasons',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 580,
            'endLine' => 580,
            'startTokenPos' => 869,
            'startFilePos' => 14018,
            'endTokenPos' => 870,
            'endFilePos' => 14019,
          ),
        ),
        'docComment' => '/**
 * SSH_MSG_CHANNEL_OPEN_FAILURE \'reason codes\', defined in RFC4254
 *
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 580,
        'endLine' => 580,
        'startColumn' => 5,
        'endColumn' => 54,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'terminal_modes' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'terminal_modes',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 590,
            'endLine' => 590,
            'startTokenPos' => 883,
            'startFilePos' => 14236,
            'endTokenPos' => 884,
            'endFilePos' => 14237,
          ),
        ),
        'docComment' => '/**
 * Terminal Modes
 *
 * @link http://tools.ietf.org/html/rfc4254#section-8
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 590,
        'endLine' => 590,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_extended_data_type_codes' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channel_extended_data_type_codes',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 600,
            'endLine' => 600,
            'startTokenPos' => 897,
            'startFilePos' => 14507,
            'endTokenPos' => 898,
            'endFilePos' => 14508,
          ),
        ),
        'docComment' => '/**
 * SSH_MSG_CHANNEL_EXTENDED_DATA\'s data_type_codes
 *
 * @link http://tools.ietf.org/html/rfc4254#section-5.2
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 600,
        'endLine' => 600,
        'startColumn' => 5,
        'endColumn' => 58,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'send_seq_no' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'send_seq_no',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 610,
            'endLine' => 610,
            'startTokenPos' => 909,
            'startFilePos' => 14721,
            'endTokenPos' => 909,
            'endFilePos' => 14721,
          ),
        ),
        'docComment' => '/**
 * Send Sequence Number
 *
 * See \'Section 6.4.  Data Integrity\' of rfc4253 for more info.
 *
 * @see self::_send_binary_packet()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 610,
        'endLine' => 610,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'get_seq_no' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'get_seq_no',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 620,
            'endLine' => 620,
            'startTokenPos' => 920,
            'startFilePos' => 14931,
            'endTokenPos' => 920,
            'endFilePos' => 14931,
          ),
        ),
        'docComment' => '/**
 * Get Sequence Number
 *
 * See \'Section 6.4.  Data Integrity\' of rfc4253 for more info.
 *
 * @see self::_get_binary_packet()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 620,
        'endLine' => 620,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'server_channels' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'server_channels',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 631,
            'endLine' => 631,
            'startTokenPos' => 931,
            'startFilePos' => 15150,
            'endTokenPos' => 932,
            'endFilePos' => 15151,
          ),
        ),
        'docComment' => '/**
 * Server Channels
 *
 * Maps client channels to server channels
 *
 * @see self::get_channel_packet()
 * @see self::exec()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 631,
        'endLine' => 631,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_buffers' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channel_buffers',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 643,
            'endLine' => 643,
            'startTokenPos' => 943,
            'startFilePos' => 15468,
            'endTokenPos' => 944,
            'endFilePos' => 15469,
          ),
        ),
        'docComment' => '/**
 * Channel Read Buffers
 *
 * If a client requests a packet from one channel but receives two packets from another those packets should
 * be placed in a buffer
 *
 * @see self::get_channel_packet()
 * @see self::exec()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 643,
        'endLine' => 643,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_buffers_write' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channel_buffers_write',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 654,
            'endLine' => 654,
            'startTokenPos' => 955,
            'startFilePos' => 15786,
            'endTokenPos' => 956,
            'endFilePos' => 15787,
          ),
        ),
        'docComment' => '/**
 * Channel Write Buffers
 *
 * If a client sends a packet and receives a timeout error mid-transmission, buffer the data written so it
 * can be de-duplicated upon resuming write
 *
 * @see self::send_channel_packet()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 654,
        'endLine' => 654,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_status' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channel_status',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 664,
            'endLine' => 664,
            'startTokenPos' => 967,
            'startFilePos' => 15982,
            'endTokenPos' => 968,
            'endFilePos' => 15983,
          ),
        ),
        'docComment' => '/**
 * Channel Status
 *
 * Contains the type of the last sent message
 *
 * @see self::get_channel_packet()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 664,
        'endLine' => 664,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_id_last_interactive' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channel_id_last_interactive',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 672,
            'endLine' => 672,
            'startTokenPos' => 979,
            'startFilePos' => 16193,
            'endTokenPos' => 979,
            'endFilePos' => 16193,
          ),
        ),
        'docComment' => '/**
 * The identifier of the interactive channel which was opened most recently
 *
 * @see self::getInteractiveChannelId()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 672,
        'endLine' => 672,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'packet_size_client_to_server' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'packet_size_client_to_server',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 682,
            'endLine' => 682,
            'startTokenPos' => 990,
            'startFilePos' => 16396,
            'endTokenPos' => 991,
            'endFilePos' => 16397,
          ),
        ),
        'docComment' => '/**
 * Packet Size
 *
 * Maximum packet size indexed by channel
 *
 * @see self::send_channel_packet()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 682,
        'endLine' => 682,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'message_number_log' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'message_number_log',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 690,
            'endLine' => 690,
            'startTokenPos' => 1002,
            'startFilePos' => 16529,
            'endTokenPos' => 1003,
            'endFilePos' => 16530,
          ),
        ),
        'docComment' => '/**
 * Message Number Log
 *
 * @see self::getLog()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 690,
        'endLine' => 690,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'message_log' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'message_log',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 698,
            'endLine' => 698,
            'startTokenPos' => 1014,
            'startFilePos' => 16648,
            'endTokenPos' => 1015,
            'endFilePos' => 16649,
          ),
        ),
        'docComment' => '/**
 * Message Log
 *
 * @see self::getLog()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 698,
        'endLine' => 698,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'window_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'window_size',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0x7fffffff',
          'attributes' => 
          array (
            'startLine' => 709,
            'endLine' => 709,
            'startTokenPos' => 1026,
            'startFilePos' => 16923,
            'endTokenPos' => 1026,
            'endFilePos' => 16932,
          ),
        ),
        'docComment' => '/**
 * The Window Size
 *
 * Bytes the other party can send before it must wait for the window to be adjusted (0x7FFFFFFF = 2GB)
 *
 * @var int
 * @see self::send_channel_packet()
 * @see self::exec()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 709,
        'endLine' => 709,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'window_resize' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'window_resize',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0x40000000',
          'attributes' => 
          array (
            'startLine' => 722,
            'endLine' => 722,
            'startTokenPos' => 1037,
            'startFilePos' => 17368,
            'endTokenPos' => 1037,
            'endFilePos' => 17377,
          ),
        ),
        'docComment' => '/**
 * What we resize the window to
 *
 * When PuTTY resizes the window it doesn\'t add an additional 0x7FFFFFFF bytes - it adds 0x40000000 bytes.
 * Some SFTP clients (GoAnywhere) don\'t support adding 0x7FFFFFFF to the window size after the fact so
 * we\'ll just do what PuTTY does
 *
 * @var int
 * @see self::_send_channel_packet()
 * @see self::exec()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 722,
        'endLine' => 722,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'window_size_server_to_client' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'window_size_server_to_client',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 732,
            'endLine' => 732,
            'startTokenPos' => 1048,
            'startFilePos' => 17590,
            'endTokenPos' => 1049,
            'endFilePos' => 17591,
          ),
        ),
        'docComment' => '/**
 * Window size, server to client
 *
 * Window size indexed by channel
 *
 * @see self::send_channel_packet()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 732,
        'endLine' => 732,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'window_size_client_to_server' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'window_size_client_to_server',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 742,
            'endLine' => 742,
            'startTokenPos' => 1060,
            'startFilePos' => 17801,
            'endTokenPos' => 1061,
            'endFilePos' => 17802,
          ),
        ),
        'docComment' => '/**
 * Window size, client to server
 *
 * Window size indexed by channel
 *
 * @see self::get_channel_packet()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 742,
        'endLine' => 742,
        'startColumn' => 5,
        'endColumn' => 47,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'signature' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'signature',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 752,
            'endLine' => 752,
            'startTokenPos' => 1072,
            'startFilePos' => 17989,
            'endTokenPos' => 1072,
            'endFilePos' => 17990,
          ),
        ),
        'docComment' => '/**
 * Server signature
 *
 * Verified against $this->session_id
 *
 * @see self::getServerPublicHostKey()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 752,
        'endLine' => 752,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'signature_format' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'signature_format',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 762,
            'endLine' => 762,
            'startTokenPos' => 1083,
            'startFilePos' => 18176,
            'endTokenPos' => 1083,
            'endFilePos' => 18177,
          ),
        ),
        'docComment' => '/**
 * Server signature format
 *
 * ssh-rsa or ssh-dss.
 *
 * @see self::getServerPublicHostKey()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 762,
        'endLine' => 762,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'interactiveBuffer' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'interactiveBuffer',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 770,
            'endLine' => 770,
            'startTokenPos' => 1094,
            'startFilePos' => 18307,
            'endTokenPos' => 1094,
            'endFilePos' => 18308,
          ),
        ),
        'docComment' => '/**
 * Interactive Buffer
 *
 * @see self::read()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 770,
        'endLine' => 770,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'log_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'log_size',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Current log size
 *
 * Should never exceed self::LOG_MAX_SIZE
 *
 * @see self::_send_binary_packet()
 * @see self::_get_binary_packet()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 781,
        'endLine' => 781,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timeout' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'timeout',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Timeout
 *
 * @see self::setTimeout()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 788,
        'endLine' => 788,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'curTimeout' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'curTimeout',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Current Timeout
 *
 * @see self::get_channel_packet()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 795,
        'endLine' => 795,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'keepAlive' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'keepAlive',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Keep Alive Interval
 *
 * @see self::setKeepAlive()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 802,
        'endLine' => 802,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'realtime_log_file' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'realtime_log_file',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Real-time log file pointer
 *
 * @see self::_append_log()
 * @var resource|closed-resource
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 810,
        'endLine' => 810,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'realtime_log_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'realtime_log_size',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Real-time log file size
 *
 * @see self::_append_log()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 818,
        'endLine' => 818,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'signature_validated' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'signature_validated',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 826,
            'endLine' => 826,
            'startTokenPos' => 1147,
            'startFilePos' => 19299,
            'endTokenPos' => 1147,
            'endFilePos' => 19303,
          ),
        ),
        'docComment' => '/**
 * Has the signature been validated?
 *
 * @see self::getServerPublicHostKey()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 826,
        'endLine' => 826,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'realtime_log_wrap' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'realtime_log_wrap',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Real-time log file wrap boolean
 *
 * @see self::_append_log()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 834,
        'endLine' => 834,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'quiet_mode' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'quiet_mode',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 841,
            'endLine' => 841,
            'startTokenPos' => 1165,
            'startFilePos' => 19579,
            'endTokenPos' => 1165,
            'endFilePos' => 19583,
          ),
        ),
        'docComment' => '/**
 * Flag to suppress stderr from output
 *
 * @see self::enableQuietMode()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 841,
        'endLine' => 841,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'last_packet' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'last_packet',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 848,
            'endLine' => 848,
            'startTokenPos' => 1176,
            'startFilePos' => 19703,
            'endTokenPos' => 1176,
            'endFilePos' => 19706,
          ),
        ),
        'docComment' => '/**
 * Time of last read/write network activity
 *
 * @var float
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 848,
        'endLine' => 848,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exit_status' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'exit_status',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Exit status returned from ssh if any
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 855,
        'endLine' => 855,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'request_pty' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'request_pty',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 863,
            'endLine' => 863,
            'startTokenPos' => 1194,
            'startFilePos' => 19964,
            'endTokenPos' => 1194,
            'endFilePos' => 19968,
          ),
        ),
        'docComment' => '/**
 * Flag to request a PTY when using exec()
 *
 * @var bool
 * @see self::enablePTY()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 863,
        'endLine' => 863,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stdErrorLog' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'stdErrorLog',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Contents of stdError
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 870,
        'endLine' => 870,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'last_interactive_response' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'last_interactive_response',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 878,
            'endLine' => 878,
            'startTokenPos' => 1212,
            'startFilePos' => 20239,
            'endTokenPos' => 1212,
            'endFilePos' => 20240,
          ),
        ),
        'docComment' => '/**
 * The Last Interactive Response
 *
 * @see self::_keyboard_interactive_process()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 878,
        'endLine' => 878,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'keyboard_requests_responses' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'keyboard_requests_responses',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 886,
            'endLine' => 886,
            'startTokenPos' => 1223,
            'startFilePos' => 20426,
            'endTokenPos' => 1224,
            'endFilePos' => 20427,
          ),
        ),
        'docComment' => '/**
 * Keyboard Interactive Request / Responses
 *
 * @see self::_keyboard_interactive_process()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 886,
        'endLine' => 886,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'banner_message' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'banner_message',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 898,
            'endLine' => 898,
            'startTokenPos' => 1235,
            'startFilePos' => 20752,
            'endTokenPos' => 1235,
            'endFilePos' => 20753,
          ),
        ),
        'docComment' => '/**
 * Banner Message
 *
 * Quoting from the RFC, "in some jurisdictions, sending a warning message before
 * authentication may be relevant for getting legal protection."
 *
 * @see self::_filter()
 * @see self::getBannerMessage()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 898,
        'endLine' => 898,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'is_timeout' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'is_timeout',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 906,
            'endLine' => 906,
            'startTokenPos' => 1246,
            'startFilePos' => 20901,
            'endTokenPos' => 1246,
            'endFilePos' => 20905,
          ),
        ),
        'docComment' => '/**
 * Did read() timeout or return normally?
 *
 * @see self::isTimeout()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 906,
        'endLine' => 906,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'log_boundary' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'log_boundary',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\':\'',
          'attributes' => 
          array (
            'startLine' => 914,
            'endLine' => 914,
            'startTokenPos' => 1257,
            'startFilePos' => 21031,
            'endTokenPos' => 1257,
            'endFilePos' => 21033,
          ),
        ),
        'docComment' => '/**
 * Log Boundary
 *
 * @see self::_format_log()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 914,
        'endLine' => 914,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'log_long_width' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'log_long_width',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '65',
          'attributes' => 
          array (
            'startLine' => 922,
            'endLine' => 922,
            'startTokenPos' => 1268,
            'startFilePos' => 21160,
            'endTokenPos' => 1268,
            'endFilePos' => 21161,
          ),
        ),
        'docComment' => '/**
 * Log Long Width
 *
 * @see self::_format_log()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 922,
        'endLine' => 922,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'log_short_width' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'log_short_width',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 930,
            'endLine' => 930,
            'startTokenPos' => 1279,
            'startFilePos' => 21290,
            'endTokenPos' => 1279,
            'endFilePos' => 21291,
          ),
        ),
        'docComment' => '/**
 * Log Short Width
 *
 * @see self::_format_log()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 930,
        'endLine' => 930,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'host' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'host',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Hostname
 *
 * @see self::__construct()
 * @see self::_connect()
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 939,
        'endLine' => 939,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'port' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'port',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Port Number
 *
 * @see self::__construct()
 * @see self::_connect()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 948,
        'endLine' => 948,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'windowColumns' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'windowColumns',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '80',
          'attributes' => 
          array (
            'startLine' => 958,
            'endLine' => 958,
            'startTokenPos' => 1304,
            'startFilePos' => 21799,
            'endTokenPos' => 1304,
            'endFilePos' => 21800,
          ),
        ),
        'docComment' => '/**
 * Number of columns for terminal window size
 *
 * @see self::getWindowColumns()
 * @see self::setWindowColumns()
 * @see self::setWindowSize()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 958,
        'endLine' => 958,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'windowRows' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'windowRows',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '24',
          'attributes' => 
          array (
            'startLine' => 968,
            'endLine' => 968,
            'startTokenPos' => 1315,
            'startFilePos' => 22021,
            'endTokenPos' => 1315,
            'endFilePos' => 22022,
          ),
        ),
        'docComment' => '/**
 * Number of columns for terminal window size
 *
 * @see self::getWindowRows()
 * @see self::setWindowRows()
 * @see self::setWindowSize()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 968,
        'endLine' => 968,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'crypto_engine' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'crypto_engine',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 977,
            'endLine' => 977,
            'startTokenPos' => 1328,
            'startFilePos' => 22192,
            'endTokenPos' => 1328,
            'endFilePos' => 22196,
          ),
        ),
        'docComment' => '/**
 * Crypto Engine
 *
 * @see self::setCryptoEngine()
 * @see self::_key_exchange()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 977,
        'endLine' => 977,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'agent' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'agent',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * A System_SSH_Agent for use in the SSH2 Agent Forwarding scenario
 *
 * @var Agent
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 984,
        'endLine' => 984,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'connections' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'connections',
        'modifiers' => 20,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Connection storage to replicates ssh2 extension functionality:
 * {@link http://php.net/manual/en/wrappers.ssh2.php#refsect1-wrappers.ssh2-examples}
 *
 * @var array<string, SSH2|\\WeakReference<SSH2>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 992,
        'endLine' => 992,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'send_id_string_first' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'send_id_string_first',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 999,
            'endLine' => 999,
            'startTokenPos' => 1355,
            'startFilePos' => 22725,
            'endTokenPos' => 1355,
            'endFilePos' => 22728,
          ),
        ),
        'docComment' => '/**
 * Send the identification string first?
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 999,
        'endLine' => 999,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'send_kex_first' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'send_kex_first',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 1006,
            'endLine' => 1006,
            'startTokenPos' => 1366,
            'startFilePos' => 22856,
            'endTokenPos' => 1366,
            'endFilePos' => 22859,
          ),
        ),
        'docComment' => '/**
 * Send the key exchange initiation packet first?
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1006,
        'endLine' => 1006,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bad_key_size_fix' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'bad_key_size_fix',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1013,
            'endLine' => 1013,
            'startTokenPos' => 1377,
            'startFilePos' => 23002,
            'endTokenPos' => 1377,
            'endFilePos' => 23006,
          ),
        ),
        'docComment' => '/**
 * Some versions of OpenSSH incorrectly calculate the key size
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1013,
        'endLine' => 1013,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'login_credentials_finalized' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'login_credentials_finalized',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1020,
            'endLine' => 1020,
            'startTokenPos' => 1388,
            'startFilePos' => 23150,
            'endTokenPos' => 1388,
            'endFilePos' => 23154,
          ),
        ),
        'docComment' => '/**
 * Should we try to re-connect to re-establish keys?
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1020,
        'endLine' => 1020,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'binary_packet_buffer' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'binary_packet_buffer',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 1027,
            'endLine' => 1027,
            'startTokenPos' => 1399,
            'startFilePos' => 23269,
            'endTokenPos' => 1399,
            'endFilePos' => 23272,
          ),
        ),
        'docComment' => '/**
 * Binary Packet Buffer
 *
 * @var object|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1027,
        'endLine' => 1027,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preferred_signature_format' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'preferred_signature_format',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1034,
            'endLine' => 1034,
            'startTokenPos' => 1410,
            'startFilePos' => 23402,
            'endTokenPos' => 1410,
            'endFilePos' => 23406,
          ),
        ),
        'docComment' => '/**
 * Preferred Signature Format
 *
 * @var string|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1034,
        'endLine' => 1034,
        'startColumn' => 5,
        'endColumn' => 50,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'auth' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'auth',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 1041,
            'endLine' => 1041,
            'startTokenPos' => 1421,
            'startFilePos' => 23507,
            'endTokenPos' => 1422,
            'endFilePos' => 23508,
          ),
        ),
        'docComment' => '/**
 * Authentication Credentials
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1041,
        'endLine' => 1041,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'term' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'term',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'vt100\'',
          'attributes' => 
          array (
            'startLine' => 1048,
            'endLine' => 1048,
            'startTokenPos' => 1433,
            'startFilePos' => 23590,
            'endTokenPos' => 1433,
            'endFilePos' => 23596,
          ),
        ),
        'docComment' => '/**
 * Terminal
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1048,
        'endLine' => 1048,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'auth_methods_to_continue' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'auth_methods_to_continue',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 1056,
            'endLine' => 1056,
            'startTokenPos' => 1444,
            'startFilePos' => 23827,
            'endTokenPos' => 1444,
            'endFilePos' => 23830,
          ),
        ),
        'docComment' => '/**
 * The authentication methods that may productively continue authentication.
 *
 * @see https://tools.ietf.org/html/rfc4252#section-5.1
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1056,
        'endLine' => 1056,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'compress' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'compress',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'self::NET_SSH2_COMPRESSION_NONE',
          'attributes' => 
          array (
            'startLine' => 1063,
            'endLine' => 1063,
            'startTokenPos' => 1455,
            'startFilePos' => 23923,
            'endTokenPos' => 1457,
            'endFilePos' => 23953,
          ),
        ),
        'docComment' => '/**
 * Compression method
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1063,
        'endLine' => 1063,
        'startColumn' => 5,
        'endColumn' => 56,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decompress' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decompress',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'self::NET_SSH2_COMPRESSION_NONE',
          'attributes' => 
          array (
            'startLine' => 1070,
            'endLine' => 1070,
            'startTokenPos' => 1468,
            'startFilePos' => 24050,
            'endTokenPos' => 1470,
            'endFilePos' => 24080,
          ),
        ),
        'docComment' => '/**
 * Decompression method
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1070,
        'endLine' => 1070,
        'startColumn' => 5,
        'endColumn' => 58,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'compress_context' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'compress_context',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Compression context
 *
 * @var resource|false|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1077,
        'endLine' => 1077,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'decompress_context' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'decompress_context',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Decompression context
 *
 * @var resource|object
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1084,
        'endLine' => 1084,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'regenerate_compression_context' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'regenerate_compression_context',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1091,
            'endLine' => 1091,
            'startTokenPos' => 1495,
            'startFilePos' => 24436,
            'endTokenPos' => 1495,
            'endFilePos' => 24440,
          ),
        ),
        'docComment' => '/**
 * Regenerate Compression Context
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1091,
        'endLine' => 1091,
        'startColumn' => 5,
        'endColumn' => 52,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'regenerate_decompression_context' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'regenerate_decompression_context',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1098,
            'endLine' => 1098,
            'startTokenPos' => 1506,
            'startFilePos' => 24572,
            'endTokenPos' => 1506,
            'endFilePos' => 24576,
          ),
        ),
        'docComment' => '/**
 * Regenerate Decompression Context
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1098,
        'endLine' => 1098,
        'startColumn' => 5,
        'endColumn' => 54,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'smartMFA' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'smartMFA',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 1105,
            'endLine' => 1105,
            'startTokenPos' => 1517,
            'startFilePos' => 24690,
            'endTokenPos' => 1517,
            'endFilePos' => 24693,
          ),
        ),
        'docComment' => '/**
 * Smart multi-factor authentication flag
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1105,
        'endLine' => 1105,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channelCount' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'channelCount',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 1112,
            'endLine' => 1112,
            'startTokenPos' => 1528,
            'startFilePos' => 24810,
            'endTokenPos' => 1528,
            'endFilePos' => 24810,
          ),
        ),
        'docComment' => '/**
 * How many channels are currently opened
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1112,
        'endLine' => 1112,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'errorOnMultipleChannels' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'errorOnMultipleChannels',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Does the server support multiple channels? If not then error out
 * when multiple channels are attempted to be opened
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1120,
        'endLine' => 1120,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bytesTransferredSinceLastKEX' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'bytesTransferredSinceLastKEX',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 1129,
            'endLine' => 1129,
            'startTokenPos' => 1546,
            'startFilePos' => 25205,
            'endTokenPos' => 1546,
            'endFilePos' => 25205,
          ),
        ),
        'docComment' => '/**
 * Bytes Transferred Since Last Key Exchange
 *
 * Includes outbound and inbound totals
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1129,
        'endLine' => 1129,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'doKeyReexchangeAfterXBytes' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'doKeyReexchangeAfterXBytes',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '1024 * 1024 * 1024',
          'attributes' => 
          array (
            'startLine' => 1136,
            'endLine' => 1136,
            'startTokenPos' => 1557,
            'startFilePos' => 25374,
            'endTokenPos' => 1565,
            'endFilePos' => 25391,
          ),
        ),
        'docComment' => '/**
 * After how many transferred byte should phpseclib initiate a key re-exchange?
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1136,
        'endLine' => 1136,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'keyExchangeInProgress' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'keyExchangeInProgress',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1144,
            'endLine' => 1144,
            'startTokenPos' => 1576,
            'startFilePos' => 25542,
            'endTokenPos' => 1576,
            'endFilePos' => 25546,
          ),
        ),
        'docComment' => '/**
 * Has a key re-exchange been initialized?
 *
 * @var bool
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1144,
        'endLine' => 1144,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'kex_buffer' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'kex_buffer',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 1158,
            'endLine' => 1158,
            'startTokenPos' => 1587,
            'startFilePos' => 25897,
            'endTokenPos' => 1588,
            'endFilePos' => 25898,
          ),
        ),
        'docComment' => '/**
 * KEX Buffer
 *
 * If we\'re in the middle of a key exchange we want to buffer any additional packets we get until
 * the key exchange is over
 *
 * @see self::_get_binary_packet()
 * @see self::_key_exchange()
 * @see self::exec()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1158,
        'endLine' => 1158,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'strict_kex_flag' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'name' => 'strict_kex_flag',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 1171,
            'endLine' => 1171,
            'startTokenPos' => 1599,
            'startFilePos' => 26211,
            'endTokenPos' => 1599,
            'endFilePos' => 26215,
          ),
        ),
        'docComment' => '/**
 * Strict KEX Flag
 *
 * If kex-strict-s-v00@openssh.com is present in the first KEX packet it need not
 * be present in subsequent packet
 *
 * @see self::_key_exchange()
 * @see self::exec()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 1171,
        'endLine' => 1171,
        'startColumn' => 5,
        'endColumn' => 37,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1185,
            'endLine' => 1185,
            'startColumn' => 33,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'port' => 
          array (
            'name' => 'port',
            'default' => 
            array (
              'code' => '22',
              'attributes' => 
              array (
                'startLine' => 1185,
                'endLine' => 1185,
                'startTokenPos' => 1617,
                'startFilePos' => 26619,
                'endTokenPos' => 1617,
                'endFilePos' => 26620,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1185,
            'endLine' => 1185,
            'startColumn' => 40,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'timeout' => 
          array (
            'name' => 'timeout',
            'default' => 
            array (
              'code' => '10',
              'attributes' => 
              array (
                'startLine' => 1185,
                'endLine' => 1185,
                'startTokenPos' => 1624,
                'startFilePos' => 26634,
                'endTokenPos' => 1624,
                'endFilePos' => 26635,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1185,
            'endLine' => 1185,
            'startColumn' => 52,
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
 * Default Constructor.
 *
 * $host can either be a string, representing the host, or a stream resource.
 * If $host is a stream resource then $port doesn\'t do anything, altho $timeout
 * still will be used
 *
 * @param mixed $host
 * @param int $port
 * @param int $timeout
 * @see self::login()
 */',
        'startLine' => 1185,
        'endLine' => 1288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setCryptoEngine' => 
      array (
        'name' => 'setCryptoEngine',
        'parameters' => 
        array (
          'engine' => 
          array (
            'name' => 'engine',
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
            'startColumn' => 44,
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
 * Set Crypto Engine Mode
 *
 * Possible $engine values:
 * OpenSSL, mcrypt, Eval, PHP
 *
 * @param int $engine
 */',
        'startLine' => 1298,
        'endLine' => 1301,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'sendIdentificationStringFirst' => 
      array (
        'name' => 'sendIdentificationStringFirst',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send Identification String First
 *
 * https://tools.ietf.org/html/rfc4253#section-4.2 says "when the connection has been established,
 * both sides MUST send an identification string". It does not say which side sends it first. In
 * theory it shouldn\'t matter but it is a fact of life that some SSH servers are simply buggy
 *
 */',
        'startLine' => 1311,
        'endLine' => 1314,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'sendIdentificationStringLast' => 
      array (
        'name' => 'sendIdentificationStringLast',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send Identification String Last
 *
 * https://tools.ietf.org/html/rfc4253#section-4.2 says "when the connection has been established,
 * both sides MUST send an identification string". It does not say which side sends it first. In
 * theory it shouldn\'t matter but it is a fact of life that some SSH servers are simply buggy
 *
 */',
        'startLine' => 1324,
        'endLine' => 1327,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'sendKEXINITFirst' => 
      array (
        'name' => 'sendKEXINITFirst',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send SSH_MSG_KEXINIT First
 *
 * https://tools.ietf.org/html/rfc4253#section-7.1 says "key exchange begins by each sending
 * sending the [SSH_MSG_KEXINIT] packet". It does not say which side sends it first. In theory
 * it shouldn\'t matter but it is a fact of life that some SSH servers are simply buggy
 *
 */',
        'startLine' => 1337,
        'endLine' => 1340,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'sendKEXINITLast' => 
      array (
        'name' => 'sendKEXINITLast',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send SSH_MSG_KEXINIT Last
 *
 * https://tools.ietf.org/html/rfc4253#section-7.1 says "key exchange begins by each sending
 * sending the [SSH_MSG_KEXINIT] packet". It does not say which side sends it first. In theory
 * it shouldn\'t matter but it is a fact of life that some SSH servers are simply buggy
 *
 */',
        'startLine' => 1350,
        'endLine' => 1353,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'stream_select' => 
      array (
        'name' => 'stream_select',
        'parameters' => 
        array (
          'read' => 
          array (
            'name' => 'read',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1363,
            'endLine' => 1363,
            'startColumn' => 43,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'write' => 
          array (
            'name' => 'write',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1363,
            'endLine' => 1363,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'except' => 
          array (
            'name' => 'except',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1363,
            'endLine' => 1363,
            'startColumn' => 60,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'seconds' => 
          array (
            'name' => 'seconds',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1363,
            'endLine' => 1363,
            'startColumn' => 70,
            'endColumn' => 77,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'microseconds' => 
          array (
            'name' => 'microseconds',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1363,
                'endLine' => 1363,
                'startTokenPos' => 2398,
                'startFilePos' => 33781,
                'endTokenPos' => 2398,
                'endFilePos' => 33784,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1363,
            'endLine' => 1363,
            'startColumn' => 80,
            'endColumn' => 99,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * stream_select wrapper
 *
 * Quoting https://stackoverflow.com/a/14262151/569976,
 * "The general approach to `EINTR` is to simply handle the error and retry the operation again"
 *
 * This wrapper does that loop
 */',
        'startLine' => 1363,
        'endLine' => 1379,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'connect' => 
      array (
        'name' => 'connect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Connect to an SSHv2 server
 *
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @throws \\RuntimeException on other errors
 */',
        'startLine' => 1387,
        'endLine' => 1541,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'generate_identifier' => 
      array (
        'name' => 'generate_identifier',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Generates the SSH identifier
 *
 * You should overwrite this method in your own class if you want to use another identifier
 *
 * @return string
 */',
        'startLine' => 1550,
        'endLine' => 1576,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'key_exchange' => 
      array (
        'name' => 'key_exchange',
        'parameters' => 
        array (
          'kexinit_payload_server' => 
          array (
            'name' => 'kexinit_payload_server',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1587,
                'endLine' => 1587,
                'startTokenPos' => 3931,
                'startFilePos' => 42383,
                'endTokenPos' => 3931,
                'endFilePos' => 42387,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1587,
            'endLine' => 1587,
            'startColumn' => 35,
            'endColumn' => 65,
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
 * Key Exchange
 *
 * @return bool
 * @param string|bool $kexinit_payload_server optional
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @throws \\RuntimeException on other errors
 * @throws NoSupportedAlgorithmsException when none of the algorithms phpseclib has loaded are compatible
 */',
        'startLine' => 1587,
        'endLine' => 2110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'encryption_algorithm_to_key_size' => 
      array (
        'name' => 'encryption_algorithm_to_key_size',
        'parameters' => 
        array (
          'algorithm' => 
          array (
            'name' => 'algorithm',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2118,
            'endLine' => 2118,
            'startColumn' => 55,
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
 * Maps an encryption algorithm name to the number of key bytes.
 *
 * @param string $algorithm Name of the encryption algorithm
 * @return int|null Number of bytes as an integer or null for unknown
 */',
        'startLine' => 2118,
        'endLine' => 2156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'encryption_algorithm_to_crypt_instance' => 
      array (
        'name' => 'encryption_algorithm_to_crypt_instance',
        'parameters' => 
        array (
          'algorithm' => 
          array (
            'name' => 'algorithm',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2165,
            'endLine' => 2165,
            'startColumn' => 68,
            'endColumn' => 77,
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
 * Maps an encryption algorithm name to an instance of a subclass of
 * \\phpseclib3\\Crypt\\Common\\SymmetricKey.
 *
 * @param string $algorithm Name of the encryption algorithm
 * @return SymmetricKey|null
 */',
        'startLine' => 2165,
        'endLine' => 2204,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'mac_algorithm_to_hash_instance' => 
      array (
        'name' => 'mac_algorithm_to_hash_instance',
        'parameters' => 
        array (
          'algorithm' => 
          array (
            'name' => 'algorithm',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2213,
            'endLine' => 2213,
            'startColumn' => 60,
            'endColumn' => 69,
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
 * Maps an encryption algorithm name to an instance of a subclass of
 * \\phpseclib3\\Crypt\\Hash.
 *
 * @param string $algorithm Name of the encryption algorithm
 * @return array{Hash, int}|null
 */',
        'startLine' => 2213,
        'endLine' => 2238,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'bad_algorithm_candidate' => 
      array (
        'name' => 'bad_algorithm_candidate',
        'parameters' => 
        array (
          'algorithm' => 
          array (
            'name' => 'algorithm',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2248,
            'endLine' => 2248,
            'startColumn' => 53,
            'endColumn' => 62,
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
 * Tests whether or not proposed algorithm has a potential for issues
 *
 * @link https://www.chiark.greenend.org.uk/~sgtatham/putty/wishlist/ssh2-aesctr-openssh.html
 * @link https://bugzilla.mindrot.org/show_bug.cgi?id=1291
 * @param string $algorithm Name of the encryption algorithm
 * @return bool
 */',
        'startLine' => 2248,
        'endLine' => 2258,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'login' => 
      array (
        'name' => 'login',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2270,
            'endLine' => 2270,
            'startColumn' => 27,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2270,
            'endLine' => 2270,
            'startColumn' => 38,
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
 * Login
 *
 * The $password parameter can be a plaintext password, a \\phpseclib3\\Crypt\\RSA|EC|DSA object, a \\phpseclib3\\System\\SSH\\Agent object or an array
 *
 * @param string $username
 * @param string|PrivateKey|array[]|Agent|null ...$args
 * @return bool
 * @see self::_login()
 */',
        'startLine' => 2270,
        'endLine' => 2287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'sublogin' => 
      array (
        'name' => 'sublogin',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2297,
            'endLine' => 2297,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2297,
            'endLine' => 2297,
            'startColumn' => 44,
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
 * Login Helper
 *
 * @param string $username
 * @param string|PrivateKey|array[]|Agent|null ...$args
 * @return bool
 * @see self::_login_helper()
 */',
        'startLine' => 2297,
        'endLine' => 2381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'login_helper' => 
      array (
        'name' => 'login_helper',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2395,
            'endLine' => 2395,
            'startColumn' => 35,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2395,
                'endLine' => 2395,
                'startTokenPos' => 9554,
                'startFilePos' => 77128,
                'endTokenPos' => 9554,
                'endFilePos' => 77131,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2395,
            'endLine' => 2395,
            'startColumn' => 46,
            'endColumn' => 61,
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
 * Login Helper
 *
 * {@internal It might be worthwhile, at some point, to protect against {@link http://tools.ietf.org/html/rfc4251#section-9.3.9 traffic analysis}
 *           by sending dummy SSH_MSG_IGNORE messages.}
 *
 * @param string $username
 * @param string|AsymmetricKey|array[]|Agent|null ...$args
 * @return bool
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @throws \\RuntimeException on other errors
 */',
        'startLine' => 2395,
        'endLine' => 2532,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'keyboard_interactive_login' => 
      array (
        'name' => 'keyboard_interactive_login',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2543,
            'endLine' => 2543,
            'startColumn' => 49,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2543,
            'endLine' => 2543,
            'startColumn' => 60,
            'endColumn' => 68,
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
 * Login via keyboard-interactive authentication
 *
 * See {@link http://tools.ietf.org/html/rfc4256 RFC4256} for details.  This is not a full-featured keyboard-interactive authenticator.
 *
 * @param string $username
 * @param string|array $password
 * @return bool
 */',
        'startLine' => 2543,
        'endLine' => 2557,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'keyboard_interactive_process' => 
      array (
        'name' => 'keyboard_interactive_process',
        'parameters' => 
        array (
          'responses' => 
          array (
            'name' => 'responses',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2566,
            'endLine' => 2566,
            'startColumn' => 51,
            'endColumn' => 63,
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
 * Handle the keyboard-interactive requests / responses.
 *
 * @param string|array ...$responses
 * @return bool
 * @throws \\RuntimeException on connection error
 */',
        'startLine' => 2566,
        'endLine' => 2653,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'ssh_agent_login' => 
      array (
        'name' => 'ssh_agent_login',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2662,
            'endLine' => 2662,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'agent' => 
          array (
            'name' => 'agent',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'phpseclib3\\System\\SSH\\Agent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2662,
            'endLine' => 2662,
            'startColumn' => 49,
            'endColumn' => 60,
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
 * Login with an ssh-agent provided key
 *
 * @param string $username
 * @param Agent $agent
 * @return bool
 */',
        'startLine' => 2662,
        'endLine' => 2675,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'privatekey_login' => 
      array (
        'name' => 'privatekey_login',
        'parameters' => 
        array (
          'username' => 
          array (
            'name' => 'username',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2688,
            'endLine' => 2688,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'privatekey' => 
          array (
            'name' => 'privatekey',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'phpseclib3\\Crypt\\Common\\PrivateKey',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2688,
            'endLine' => 2688,
            'startColumn' => 50,
            'endColumn' => 71,
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
 * Login with an RSA private key
 *
 * {@internal It might be worthwhile, at some point, to protect against {@link http://tools.ietf.org/html/rfc4251#section-9.3.9 traffic analysis}
 *           by sending dummy SSH_MSG_IGNORE messages.}
 *
 * @param string $username
 * @param PrivateKey $privatekey
 * @return bool
 * @throws \\RuntimeException on connection error
 */',
        'startLine' => 2688,
        'endLine' => 2814,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getTimeout' => 
      array (
        'name' => 'getTimeout',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the currently configured timeout
 *
 * @return int
 */',
        'startLine' => 2821,
        'endLine' => 2824,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setTimeout' => 
      array (
        'name' => 'setTimeout',
        'parameters' => 
        array (
          'timeout' => 
          array (
            'name' => 'timeout',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2834,
            'endLine' => 2834,
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
 * Set Timeout
 *
 * $ssh->exec(\'ping 127.0.0.1\'); on a Linux host will never return and will run indefinitely.  setTimeout() makes it so it\'ll timeout.
 * Setting $timeout to false or 0 will revert to the default socket timeout.
 *
 * @param mixed $timeout
 */',
        'startLine' => 2834,
        'endLine' => 2837,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setKeepAlive' => 
      array (
        'name' => 'setKeepAlive',
        'parameters' => 
        array (
          'interval' => 
          array (
            'name' => 'interval',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2846,
            'endLine' => 2846,
            'startColumn' => 34,
            'endColumn' => 42,
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
 * Set Keep Alive
 *
 * Sends an SSH2_MSG_IGNORE message every x seconds, if x is a positive non-zero number.
 *
 * @param int $interval
 */',
        'startLine' => 2846,
        'endLine' => 2849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getStdError' => 
      array (
        'name' => 'getStdError',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the output from stdError
 *
 */',
        'startLine' => 2855,
        'endLine' => 2858,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'exec' => 
      array (
        'name' => 'exec',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2872,
            'endLine' => 2872,
            'startColumn' => 26,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2872,
                'endLine' => 2872,
                'startTokenPos' => 12281,
                'startFilePos' => 95322,
                'endTokenPos' => 12281,
                'endFilePos' => 95325,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2872,
            'endLine' => 2872,
            'startColumn' => 36,
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
 * Execute Command
 *
 * If $callback is set to false then \\phpseclib3\\Net\\SSH2::get_channel_packet(self::CHANNEL_EXEC) will need to be called manually.
 * In all likelihood, this is not a feature you want to be taking advantage of.
 *
 * @param string $command
 * @param callable $callback
 * @return string|bool
 * @psalm-return ($callback is callable ? bool : string|bool)
 * @throws \\RuntimeException on connection error
 */',
        'startLine' => 2872,
        'endLine' => 2967,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getOpenChannelCount' => 
      array (
        'name' => 'getOpenChannelCount',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * How many channels are currently open?
 *
 * @return int
 */',
        'startLine' => 2974,
        'endLine' => 2977,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'open_channel' => 
      array (
        'name' => 'open_channel',
        'parameters' => 
        array (
          'channel' => 
          array (
            'name' => 'channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2986,
            'endLine' => 2986,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'skip_extended' => 
          array (
            'name' => 'skip_extended',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2986,
                'endLine' => 2986,
                'startTokenPos' => 12836,
                'startFilePos' => 99405,
                'endTokenPos' => 12836,
                'endFilePos' => 99409,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2986,
            'endLine' => 2986,
            'startColumn' => 47,
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
 * Opens a channel
 *
 * @param string $channel
 * @param bool $skip_extended
 * @return bool
 */',
        'startLine' => 2986,
        'endLine' => 3021,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'openShell' => 
      array (
        'name' => 'openShell',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates an interactive shell
 *
 * Returns bool(true) if the shell was opened.
 * Returns bool(false) if the shell was already open.
 *
 * @see self::isShellOpen()
 * @see self::read()
 * @see self::write()
 * @return bool
 * @throws InsufficientSetupException if not authenticated
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @throws \\RuntimeException on other errors
 */',
        'startLine' => 3037,
        'endLine' => 3089,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_interactive_channel' => 
      array (
        'name' => 'get_interactive_channel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the channel to be used with read(), write(), and reset(), if none were specified
 * @deprecated for lack of transparency in intended channel target, to be potentially replaced
 *             with method which guarantees open-ness of all yielded channels and throws
 *             error for multiple open channels
 * @see self::read()
 * @see self::write()
 * @return int
 */',
        'startLine' => 3100,
        'endLine' => 3110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'is_channel_status_data' => 
      array (
        'name' => 'is_channel_status_data',
        'parameters' => 
        array (
          'channel' => 
          array (
            'name' => 'channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3118,
            'endLine' => 3118,
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
 * Indicates the DATA status on the given channel
 *
 * @param int $channel The channel number to evaluate
 * @return bool
 */',
        'startLine' => 3118,
        'endLine' => 3121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_open_channel' => 
      array (
        'name' => 'get_open_channel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return an available open channel
 *
 * @return int
 */',
        'startLine' => 3128,
        'endLine' => 3138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'requestAgentForwarding' => 
      array (
        'name' => 'requestAgentForwarding',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Request agent forwarding of remote server
 *
 * @return bool
 */',
        'startLine' => 3145,
        'endLine' => 3171,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'read' => 
      array (
        'name' => 'read',
        'parameters' => 
        array (
          'expect' => 
          array (
            'name' => 'expect',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 3194,
                'endLine' => 3194,
                'startTokenPos' => 13652,
                'startFilePos' => 106630,
                'endTokenPos' => 13652,
                'endFilePos' => 106631,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3194,
            'endLine' => 3194,
            'startColumn' => 26,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => 
            array (
              'code' => 'self::READ_SIMPLE',
              'attributes' => 
              array (
                'startLine' => 3194,
                'endLine' => 3194,
                'startTokenPos' => 13659,
                'startFilePos' => 106642,
                'endTokenPos' => 13661,
                'endFilePos' => 106658,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3194,
            'endLine' => 3194,
            'startColumn' => 40,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'channel' => 
          array (
            'name' => 'channel',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3194,
                'endLine' => 3194,
                'startTokenPos' => 13668,
                'startFilePos' => 106672,
                'endTokenPos' => 13668,
                'endFilePos' => 106675,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3194,
            'endLine' => 3194,
            'startColumn' => 67,
            'endColumn' => 81,
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
 * Returns the output of an interactive shell
 *
 * Returns when there\'s a match for $expect, which can take the form of a string literal or,
 * if $mode == self::READ_REGEX, a regular expression.
 *
 * If not specifying a channel, an open interactive channel will be selected, or, if there are
 * no open channels, an interactive shell will be created. If there are multiple open
 * interactive channels, a legacy behavior will apply in which channel selection prioritizes
 * an active subsystem, the exec pty, and, lastly, the shell. If using multiple interactive
 * channels, callers are discouraged from relying on this legacy behavior and should specify
 * the intended channel.
 *
 * @see self::write()
 * @param string $expect
 * @param int $mode One of the self::READ_* constants
 * @param int|null $channel Channel id returned by self::getInteractiveChannelId()
 * @return string|bool|null
 * @throws \\RuntimeException on connection error
 * @throws InsufficientSetupException on unexpected channel status, possibly due to closure
 */',
        'startLine' => 3194,
        'endLine' => 3236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'write' => 
      array (
        'name' => 'write',
        'parameters' => 
        array (
          'cmd' => 
          array (
            'name' => 'cmd',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3256,
            'endLine' => 3256,
            'startColumn' => 27,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'channel' => 
          array (
            'name' => 'channel',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3256,
                'endLine' => 3256,
                'startTokenPos' => 14060,
                'startFilePos' => 109331,
                'endTokenPos' => 14060,
                'endFilePos' => 109334,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3256,
            'endLine' => 3256,
            'startColumn' => 33,
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
 * Inputs a command into an interactive shell.
 *
 * If not specifying a channel, an open interactive channel will be selected, or, if there are
 * no open channels, an interactive shell will be created. If there are multiple open
 * interactive channels, a legacy behavior will apply in which channel selection prioritizes
 * an active subsystem, the exec pty, and, lastly, the shell. If using multiple interactive
 * channels, callers are discouraged from relying on this legacy behavior and should specify
 * the intended channel.
 *
 * @see SSH2::read()
 * @param string $cmd
 * @param int|null $channel Channel id returned by self::getInteractiveChannelId()
 * @return void
 * @throws \\RuntimeException on connection error
 * @throws InsufficientSetupException on unexpected channel status, possibly due to closure
 * @throws TimeoutException if the write could not be completed within the requested self::setTimeout()
 */',
        'startLine' => 3256,
        'endLine' => 3277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'startSubsystem' => 
      array (
        'name' => 'startSubsystem',
        'parameters' => 
        array (
          'subsystem' => 
          array (
            'name' => 'subsystem',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3292,
            'endLine' => 3292,
            'startColumn' => 36,
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
 * Start a subsystem.
 *
 * Right now only one subsystem at a time is supported. To support multiple subsystem\'s stopSubsystem() could accept
 * a string that contained the name of the subsystem, but at that point, only one subsystem of each type could be opened.
 * To support multiple subsystem\'s of the same name maybe it\'d be best if startSubsystem() generated a new channel id and
 * returns that and then that that was passed into stopSubsystem() but that\'ll be saved for a future date and implemented
 * if there\'s sufficient demand for such a feature.
 *
 * @see self::stopSubsystem()
 * @param string $subsystem
 * @return bool
 */',
        'startLine' => 3292,
        'endLine' => 3317,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'stopSubsystem' => 
      array (
        'name' => 'stopSubsystem',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Stops a subsystem.
 *
 * @see self::startSubsystem()
 * @return bool
 */',
        'startLine' => 3325,
        'endLine' => 3331,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'reset' => 
      array (
        'name' => 'reset',
        'parameters' => 
        array (
          'channel' => 
          array (
            'name' => 'channel',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3347,
                'endLine' => 3347,
                'startTokenPos' => 14409,
                'startFilePos' => 112614,
                'endTokenPos' => 14409,
                'endFilePos' => 112617,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3347,
            'endLine' => 3347,
            'startColumn' => 27,
            'endColumn' => 41,
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
 * Closes a channel
 *
 * If read() timed out you might want to just close the channel and have it auto-restart on the next read() call
 *
 * If not specifying a channel, an open interactive channel will be selected. If there are
 * multiple open interactive channels, a legacy behavior will apply in which channel selection
 * prioritizes an active subsystem, the exec pty, and, lastly, the shell. If using multiple
 * interactive channels, callers are discouraged from relying on this legacy behavior and
 * should specify the intended channel.
 *
 * @param int|null $channel Channel id returned by self::getInteractiveChannelId()
 * @return void
 */',
        'startLine' => 3347,
        'endLine' => 3355,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'sendEOF' => 
      array (
        'name' => 'sendEOF',
        'parameters' => 
        array (
          'channel' => 
          array (
            'name' => 'channel',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3366,
                'endLine' => 3366,
                'startTokenPos' => 14476,
                'startFilePos' => 113177,
                'endTokenPos' => 14476,
                'endFilePos' => 113180,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3366,
            'endLine' => 3366,
            'startColumn' => 29,
            'endColumn' => 43,
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
 * Send EOF on a channel
 *
 * Sends an EOF to the stream; this is typically used to close standard
 * input, while keeping output and error alive.
 *
 * @param int|null $channel Channel id returned by self::getInteractiveChannelId()
 * @return void
 */',
        'startLine' => 3366,
        'endLine' => 3376,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isTimeout' => 
      array (
        'name' => 'isTimeout',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Is timeout?
 *
 * Did exec() or read() return because they timed out or because they encountered the end?
 *
 */',
        'startLine' => 3384,
        'endLine' => 3387,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'disconnect' => 
      array (
        'name' => 'disconnect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disconnect
 *
 */',
        'startLine' => 3393,
        'endLine' => 3400,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      '__destruct' => 
      array (
        'name' => '__destruct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Destructor.
 *
 * Will be called, automatically, if you\'re supporting just PHP5.  If you\'re supporting PHP4, you\'ll need to call
 * disconnect().
 *
 */',
        'startLine' => 3409,
        'endLine' => 3412,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isConnected' => 
      array (
        'name' => 'isConnected',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 3429,
                'endLine' => 3429,
                'startTokenPos' => 14699,
                'startFilePos' => 115272,
                'endTokenPos' => 14699,
                'endFilePos' => 115272,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3429,
            'endLine' => 3429,
            'startColumn' => 33,
            'endColumn' => 42,
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
 * Is the connection still active?
 *
 * $level has 3x possible values:
 * 0 (default): phpseclib takes a passive approach to see if the connection is still active by calling feof()
 *    on the socket
 * 1: phpseclib takes an active approach to see if the connection is still active by sending an SSH_MSG_IGNORE
 *    packet that doesn\'t require a response
 * 2: phpseclib takes an active approach to see if the connection is still active by sending an SSH_MSG_CHANNEL_OPEN
 *    packet and imediately trying to close that channel. some routers, in particular, however, will only let you
 *    open one channel, so this approach could yield false positives
 *
 * @param int $level
 * @return bool
 */',
        'startLine' => 3429,
        'endLine' => 3449,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isAuthenticated' => 
      array (
        'name' => 'isAuthenticated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Have you successfully been logged in?
 *
 * @return bool
 */',
        'startLine' => 3456,
        'endLine' => 3459,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isShellOpen' => 
      array (
        'name' => 'isShellOpen',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Is the interactive shell active?
 *
 * @return bool
 */',
        'startLine' => 3466,
        'endLine' => 3469,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isPTYOpen' => 
      array (
        'name' => 'isPTYOpen',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Is the exec pty active?
 *
 * @return bool
 */',
        'startLine' => 3476,
        'endLine' => 3479,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isInteractiveChannelOpen' => 
      array (
        'name' => 'isInteractiveChannelOpen',
        'parameters' => 
        array (
          'channel' => 
          array (
            'name' => 'channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3487,
            'endLine' => 3487,
            'startColumn' => 46,
            'endColumn' => 53,
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
 * Is the given interactive channel active?
 *
 * @param int $channel Channel id returned by self::getInteractiveChannelId()
 * @return bool
 */',
        'startLine' => 3487,
        'endLine' => 3490,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getInteractiveChannelId' => 
      array (
        'name' => 'getInteractiveChannelId',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a channel identifier, presently of the last interactive channel opened, regardless of current status.
 * Returns 0 if no interactive channel has been opened.
 *
 * @see self::isInteractiveChannelOpen()
 * @return int
 */',
        'startLine' => 3499,
        'endLine' => 3502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'ping' => 
      array (
        'name' => 'ping',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Pings a server connection, or tries to reconnect if the connection has gone down
 *
 * Inspired by http://php.net/manual/en/mysqli.ping.php
 *
 * @return bool
 */',
        'startLine' => 3511,
        'endLine' => 3528,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'reconnect' => 
      array (
        'name' => 'reconnect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * In situ reconnect method
 *
 * @return boolean
 */',
        'startLine' => 3535,
        'endLine' => 3543,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'reset_connection' => 
      array (
        'name' => 'reset_connection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resets a connection for re-use
 */',
        'startLine' => 3548,
        'endLine' => 3567,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_stream_timeout' => 
      array (
        'name' => 'get_stream_timeout',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return int[] second and microsecond stream timeout options based on user-requested timeout and keep-alive, or the default socket timeout by default, which mirrors PHP socket streams.
 */',
        'startLine' => 3572,
        'endLine' => 3589,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_binary_packet_or_close' => 
      array (
        'name' => 'get_binary_packet_or_close',
        'parameters' => 
        array (
          'message_types' => 
          array (
            'name' => 'message_types',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3598,
            'endLine' => 3598,
            'startColumn' => 49,
            'endColumn' => 65,
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
 * Retrieves the next packet with added timeout and type handling
 *
 * @param string $message_types Message types to enforce in response, closing if not met
 * @return string
 * @throws ConnectionClosedException If an error has occurred preventing read of the next packet
 */',
        'startLine' => 3598,
        'endLine' => 3612,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_binary_packet' => 
      array (
        'name' => 'get_binary_packet',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets Binary Packets
 *
 * See \'6. Binary Packet Protocol\' of rfc4253 for more info.
 *
 * @see self::_send_binary_packet()
 * @return string
 * @throws TimeoutException If user requested timeout was reached while waiting for next packet
 * @throws ConnectionClosedException If an error has occurred preventing read of the next packet
 */',
        'startLine' => 3624,
        'endLine' => 3821,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_binary_packet_size' => 
      array (
        'name' => 'get_binary_packet_size',
        'parameters' => 
        array (
          'packet' => 
          array (
            'name' => 'packet',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3828,
            'endLine' => 3828,
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
 * @param object $packet The packet object being constructed, passed by reference
 *        The size, packet_length, and plain properties of this object may be modified in processing
 * @throws InvalidPacketLengthException if the packet length header is invalid
 */',
        'startLine' => 3828,
        'endLine' => 3883,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'handleDisconnect' => 
      array (
        'name' => 'handleDisconnect',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3895,
            'endLine' => 3895,
            'startColumn' => 39,
            'endColumn' => 46,
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
 * Handle Disconnect
 *
 * Because some binary packets need to be ignored...
 *
 * @see self::filter()
 * @see self::key_exchange()
 * @return boolean
 * @access private
 */',
        'startLine' => 3895,
        'endLine' => 3902,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'filter' => 
      array (
        'name' => 'filter',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3913,
            'endLine' => 3913,
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
 * Filter Binary Packets
 *
 * Because some binary packets need to be ignored...
 *
 * @see self::_get_binary_packet()
 * @param string $payload
 * @return string
 */',
        'startLine' => 3913,
        'endLine' => 4062,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'enableQuietMode' => 
      array (
        'name' => 'enableQuietMode',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable Quiet Mode
 *
 * Suppress stderr from output
 *
 */',
        'startLine' => 4070,
        'endLine' => 4073,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'disableQuietMode' => 
      array (
        'name' => 'disableQuietMode',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable Quiet Mode
 *
 * Show stderr in output
 *
 */',
        'startLine' => 4081,
        'endLine' => 4084,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isQuietModeEnabled' => 
      array (
        'name' => 'isQuietModeEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns whether Quiet Mode is enabled or not
 *
 * @see self::enableQuietMode()
 * @see self::disableQuietMode()
 * @return bool
 */',
        'startLine' => 4093,
        'endLine' => 4096,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'enablePTY' => 
      array (
        'name' => 'enablePTY',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable request-pty when using exec()
 *
 */',
        'startLine' => 4102,
        'endLine' => 4105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'disablePTY' => 
      array (
        'name' => 'disablePTY',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable request-pty when using exec()
 *
 */',
        'startLine' => 4111,
        'endLine' => 4117,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'isPTYEnabled' => 
      array (
        'name' => 'isPTYEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns whether request-pty is enabled or not
 *
 * @see self::enablePTY()
 * @see self::disablePTY()
 * @return bool
 */',
        'startLine' => 4126,
        'endLine' => 4129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'get_channel_packet' => 
      array (
        'name' => 'get_channel_packet',
        'parameters' => 
        array (
          'client_channel' => 
          array (
            'name' => 'client_channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4155,
            'endLine' => 4155,
            'startColumn' => 43,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'skip_extended' => 
          array (
            'name' => 'skip_extended',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 4155,
                'endLine' => 4155,
                'startTokenPos' => 19468,
                'startFilePos' => 145445,
                'endTokenPos' => 19468,
                'endFilePos' => 145449,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4155,
            'endLine' => 4155,
            'startColumn' => 60,
            'endColumn' => 81,
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
 * Gets channel data
 *
 * Returns the data as a string. bool(true) is returned if:
 *
 * - the server closes the channel
 * - if the connection times out
 * - if a window adjust packet is received on the given negated client channel
 * - if the channel status is CHANNEL_OPEN and the response was CHANNEL_OPEN_CONFIRMATION
 * - if the channel status is CHANNEL_REQUEST and the response was CHANNEL_SUCCESS
 * - if the channel status is CHANNEL_CLOSE and the response was CHANNEL_CLOSE
 *
 * bool(false) is returned if:
 *
 * - if the channel status is CHANNEL_REQUEST and the response was CHANNEL_FAILURE
 *
 * @param int $client_channel Specifies the channel to return data for, and data received
 *        on other channels is buffered. The respective negative value of a channel is
 *        also supported for the case that the caller is awaiting adjustment of the data
 *        window, and where data received on that respective channel is also buffered.
 * @param bool $skip_extended
 * @return mixed
 * @throws \\RuntimeException on connection error
 */',
        'startLine' => 4155,
        'endLine' => 4374,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'send_binary_packet' => 
      array (
        'name' => 'send_binary_packet',
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
            'startLine' => 4386,
            'endLine' => 4386,
            'startColumn' => 43,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'logged' => 
          array (
            'name' => 'logged',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4386,
                'endLine' => 4386,
                'startTokenPos' => 21067,
                'startFilePos' => 157691,
                'endTokenPos' => 21067,
                'endFilePos' => 157694,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4386,
            'endLine' => 4386,
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
 * Sends Binary Packets
 *
 * See \'6. Binary Packet Protocol\' of rfc4253 for more info.
 *
 * @param string $data
 * @param string $logged
 * @see self::_get_binary_packet()
 * @return void
 */',
        'startLine' => 4386,
        'endLine' => 4531,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'send_keep_alive' => 
      array (
        'name' => 'send_keep_alive',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sends a keep-alive message, if keep-alive is enabled and interval is met
 */',
        'startLine' => 4536,
        'endLine' => 4544,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'append_log' => 
      array (
        'name' => 'append_log',
        'parameters' => 
        array (
          'message_number' => 
          array (
            'name' => 'message_number',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4554,
            'endLine' => 4554,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 4554,
            'endLine' => 4554,
            'startColumn' => 50,
            'endColumn' => 57,
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
 * Logs data packets
 *
 * Makes sure that only the last 1MB worth of packets will be logged
 *
 * @param string $message_number
 * @param string $message
 */',
        'startLine' => 4554,
        'endLine' => 4567,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'append_log_helper' => 
      array (
        'name' => 'append_log_helper',
        'parameters' => 
        array (
          'constant' => 
          array (
            'name' => 'constant',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'message_number' => 
          array (
            'name' => 'message_number',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 53,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 70,
            'endColumn' => 77,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'message_number_log' => 
          array (
            'name' => 'message_number_log',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 80,
            'endColumn' => 105,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'message_log' => 
          array (
            'name' => 'message_log',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 108,
            'endColumn' => 126,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
          'log_size' => 
          array (
            'name' => 'log_size',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 129,
            'endColumn' => 138,
            'parameterIndex' => 5,
            'isOptional' => false,
          ),
          'realtime_log_file' => 
          array (
            'name' => 'realtime_log_file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 141,
            'endColumn' => 159,
            'parameterIndex' => 6,
            'isOptional' => false,
          ),
          'realtime_log_wrap' => 
          array (
            'name' => 'realtime_log_wrap',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 162,
            'endColumn' => 180,
            'parameterIndex' => 7,
            'isOptional' => false,
          ),
          'realtime_log_size' => 
          array (
            'name' => 'realtime_log_size',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4582,
            'endLine' => 4582,
            'startColumn' => 183,
            'endColumn' => 201,
            'parameterIndex' => 8,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Logs data packet helper
 *
 * @param int $constant
 * @param string $message_number
 * @param string $message
 * @param array &$message_number_log
 * @param array &$message_log
 * @param int &$log_size
 * @param resource &$realtime_log_file
 * @param bool &$realtime_log_wrap
 * @param int &$realtime_log_size
 */',
        'startLine' => 4582,
        'endLine' => 4658,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'send_channel_packet' => 
      array (
        'name' => 'send_channel_packet',
        'parameters' => 
        array (
          'client_channel' => 
          array (
            'name' => 'client_channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4669,
            'endLine' => 4669,
            'startColumn' => 44,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 4669,
            'endLine' => 4669,
            'startColumn' => 61,
            'endColumn' => 65,
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
 * Sends channel data
 *
 * Spans multiple SSH_MSG_CHANNEL_DATAs if appropriate
 *
 * @param int $client_channel
 * @param string $data
 * @return void
 */',
        'startLine' => 4669,
        'endLine' => 4712,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'close_channel' => 
      array (
        'name' => 'close_channel',
        'parameters' => 
        array (
          'client_channel' => 
          array (
            'name' => 'client_channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4725,
            'endLine' => 4725,
            'startColumn' => 38,
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
 * Closes and flushes a channel
 *
 * \\phpseclib3\\Net\\SSH2 doesn\'t properly close most channels.  For exec() channels are normally closed by the server
 * and for SFTP channels are presumably closed when the client disconnects.  This functions is intended
 * for SCP more than anything.
 *
 * @param int $client_channel
 * @param bool $want_reply
 * @return void
 */',
        'startLine' => 4725,
        'endLine' => 4745,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'close_channel_bitmap' => 
      array (
        'name' => 'close_channel_bitmap',
        'parameters' => 
        array (
          'client_channel' => 
          array (
            'name' => 'client_channel',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4753,
            'endLine' => 4753,
            'startColumn' => 43,
            'endColumn' => 57,
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
 * Maintains execution state bitmap in response to channel closure
 *
 * @param int $client_channel The channel number to maintain closure status of
 * @return void
 */',
        'startLine' => 4753,
        'endLine' => 4764,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'disconnect_helper' => 
      array (
        'name' => 'disconnect_helper',
        'parameters' => 
        array (
          'reason' => 
          array (
            'name' => 'reason',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4772,
            'endLine' => 4772,
            'startColumn' => 42,
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
 * Disconnect
 *
 * @param int $reason
 * @return false
 */',
        'startLine' => 4772,
        'endLine' => 4790,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'define_array' => 
      array (
        'name' => 'define_array',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4802,
            'endLine' => 4802,
            'startColumn' => 44,
            'endColumn' => 51,
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
 * Define Array
 *
 * Takes any number of arrays whose indices are integers and whose values are strings and defines a bunch of
 * named constants from it, using the value as the name of the constant and the index as the value of the constant.
 * If any of the constants that would be defined already exists, none of the constants will be defined.
 *
 * @param mixed[] ...$args
 * @access protected
 */',
        'startLine' => 4802,
        'endLine' => 4813,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 18,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getLog' => 
      array (
        'name' => 'getLog',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a log of the packets that have been sent and received.
 *
 * Returns a string if NET_SSH2_LOGGING == self::LOG_COMPLEX, an array if NET_SSH2_LOGGING == self::LOG_SIMPLE and false if !defined(\'NET_SSH2_LOGGING\')
 *
 * @return array|false|string
 */',
        'startLine' => 4822,
        'endLine' => 4837,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'format_log' => 
      array (
        'name' => 'format_log',
        'parameters' => 
        array (
          'message_log' => 
          array (
            'name' => 'message_log',
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
            'startLine' => 4846,
            'endLine' => 4846,
            'startColumn' => 35,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'message_number_log' => 
          array (
            'name' => 'message_number_log',
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
            'startLine' => 4846,
            'endLine' => 4846,
            'startColumn' => 55,
            'endColumn' => 79,
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
 * Formats a log for printing
 *
 * @param array $message_log
 * @param array $message_number_log
 * @return string
 */',
        'startLine' => 4846,
        'endLine' => 4875,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'on_channel_open' => 
      array (
        'name' => 'on_channel_open',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Helper function for agent->on_channel_open()
 *
 * Used when channels are created to inform agent
 * of said channel opening. Must be called after
 * channel open confirmation received
 *
 */',
        'startLine' => 4885,
        'endLine' => 4890,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'array_intersect_first' => 
      array (
        'name' => 'array_intersect_first',
        'parameters' => 
        array (
          'array1' => 
          array (
            'name' => 'array1',
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
            'startLine' => 4900,
            'endLine' => 4900,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'array2' => 
          array (
            'name' => 'array2',
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
            'startLine' => 4900,
            'endLine' => 4900,
            'startColumn' => 66,
            'endColumn' => 78,
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
 * Returns the first value of the intersection of two arrays or false if
 * the intersection is empty. The order is defined by the first parameter.
 *
 * @param array $array1
 * @param array $array2
 * @return mixed False if intersection is empty, else intersected value.
 */',
        'startLine' => 4900,
        'endLine' => 4908,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getErrors' => 
      array (
        'name' => 'getErrors',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns all errors / debug messages on the SSH layer
 *
 * If you are looking for messages from the SFTP layer, please see SFTP::getSFTPErrors()
 *
 * @return string[]
 * @removed in phpseclib 4.0.0
 */',
        'startLine' => 4918,
        'endLine' => 4921,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getLastError' => 
      array (
        'name' => 'getLastError',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the last error received on the SSH layer
 *
 * If you are looking for messages from the SFTP layer, please see SFTP::getLastSFTPError()
 *
 * @return string
 * @removed in phpseclib 4.0.0
 */',
        'startLine' => 4931,
        'endLine' => 4938,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getServerIdentification' => 
      array (
        'name' => 'getServerIdentification',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the server identification.
 *
 * @return string|false
 */',
        'startLine' => 4945,
        'endLine' => 4950,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getServerAlgorithms' => 
      array (
        'name' => 'getServerAlgorithms',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a list of algorithms the server supports
 *
 * @return array
 */',
        'startLine' => 4957,
        'endLine' => 4977,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getSupportedKEXAlgorithms' => 
      array (
        'name' => 'getSupportedKEXAlgorithms',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a list of KEX algorithms that phpseclib supports
 *
 * @return array
 */',
        'startLine' => 4984,
        'endLine' => 5013,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getSupportedHostKeyAlgorithms' => 
      array (
        'name' => 'getSupportedHostKeyAlgorithms',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a list of host key algorithms that phpseclib supports
 *
 * @return array
 */',
        'startLine' => 5020,
        'endLine' => 5032,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getSupportedEncryptionAlgorithms' => 
      array (
        'name' => 'getSupportedEncryptionAlgorithms',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a list of symmetric key algorithms that phpseclib supports
 *
 * @return array
 */',
        'startLine' => 5039,
        'endLine' => 5156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getSupportedMACAlgorithms' => 
      array (
        'name' => 'getSupportedMACAlgorithms',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a list of MAC algorithms that phpseclib supports
 *
 * @return array
 */',
        'startLine' => 5163,
        'endLine' => 5188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getSupportedCompressionAlgorithms' => 
      array (
        'name' => 'getSupportedCompressionAlgorithms',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a list of compression algorithms that phpseclib supports
 *
 * @return array
 */',
        'startLine' => 5195,
        'endLine' => 5203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getAlgorithmsNegotiated' => 
      array (
        'name' => 'getAlgorithmsNegotiated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return list of negotiated algorithms
 *
 * Uses the same format as https://www.php.net/ssh2-methods-negotiated
 *
 * @return array
 */',
        'startLine' => 5212,
        'endLine' => 5236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'forceMultipleChannels' => 
      array (
        'name' => 'forceMultipleChannels',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Force multiple channels (even if phpseclib has decided to disable them)
 */',
        'startLine' => 5241,
        'endLine' => 5244,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setTerminal' => 
      array (
        'name' => 'setTerminal',
        'parameters' => 
        array (
          'term' => 
          array (
            'name' => 'term',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5251,
            'endLine' => 5251,
            'startColumn' => 33,
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
 * Allows you to set the terminal
 *
 * @param string $term
 */',
        'startLine' => 5251,
        'endLine' => 5254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setPreferredAlgorithms' => 
      array (
        'name' => 'setPreferredAlgorithms',
        'parameters' => 
        array (
          'methods' => 
          array (
            'name' => 'methods',
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
            'startLine' => 5262,
            'endLine' => 5262,
            'startColumn' => 44,
            'endColumn' => 57,
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
 * Accepts an associative array with up to four parameters as described at
 * <https://www.php.net/manual/en/function.ssh2-connect.php>
 *
 * @param array $methods
 */',
        'startLine' => 5262,
        'endLine' => 5362,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getBannerMessage' => 
      array (
        'name' => 'getBannerMessage',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the banner message.
 *
 * Quoting from the RFC, "in some jurisdictions, sending a warning message before
 * authentication may be relevant for getting legal protection."
 *
 * @return string
 */',
        'startLine' => 5372,
        'endLine' => 5375,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getServerPublicHostKey' => 
      array (
        'name' => 'getServerPublicHostKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the server public host key.
 *
 * Caching this the first time you connect to a server and checking the result on subsequent connections
 * is recommended.  Returns false if the server signature is not signed correctly with the public host key.
 *
 * @return string|false
 * @throws \\RuntimeException on badly formatted keys
 * @throws NoSupportedAlgorithmsException when the key isn\'t in a supported format
 */',
        'startLine' => 5387,
        'endLine' => 5464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getExitStatus' => 
      array (
        'name' => 'getExitStatus',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the exit status of an SSH command or false.
 *
 * @return false|int
 */',
        'startLine' => 5471,
        'endLine' => 5477,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getWindowColumns' => 
      array (
        'name' => 'getWindowColumns',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the number of columns for the terminal window size.
 *
 * @return int
 */',
        'startLine' => 5484,
        'endLine' => 5487,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getWindowRows' => 
      array (
        'name' => 'getWindowRows',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the number of rows for the terminal window size.
 *
 * @return int
 */',
        'startLine' => 5494,
        'endLine' => 5497,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setWindowColumns' => 
      array (
        'name' => 'setWindowColumns',
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
            'startLine' => 5504,
            'endLine' => 5504,
            'startColumn' => 38,
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
 * Sets the number of columns for the terminal window size.
 *
 * @param int $value
 */',
        'startLine' => 5504,
        'endLine' => 5507,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setWindowRows' => 
      array (
        'name' => 'setWindowRows',
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
            'startLine' => 5514,
            'endLine' => 5514,
            'startColumn' => 35,
            'endColumn' => 40,
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
 * Sets the number of rows for the terminal window size.
 *
 * @param int $value
 */',
        'startLine' => 5514,
        'endLine' => 5517,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'setWindowSize' => 
      array (
        'name' => 'setWindowSize',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '80',
              'attributes' => 
              array (
                'startLine' => 5525,
                'endLine' => 5525,
                'startTokenPos' => 27019,
                'startFilePos' => 200366,
                'endTokenPos' => 27019,
                'endFilePos' => 200367,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5525,
            'endLine' => 5525,
            'startColumn' => 35,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'rows' => 
          array (
            'name' => 'rows',
            'default' => 
            array (
              'code' => '24',
              'attributes' => 
              array (
                'startLine' => 5525,
                'endLine' => 5525,
                'startTokenPos' => 27026,
                'startFilePos' => 200378,
                'endTokenPos' => 27026,
                'endFilePos' => 200379,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5525,
            'endLine' => 5525,
            'startColumn' => 50,
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
 * Sets the number of columns and rows for the terminal window size.
 *
 * @param int $columns
 * @param int $rows
 */',
        'startLine' => 5525,
        'endLine' => 5529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      '__toString' => 
      array (
        'name' => '__toString',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'ReturnTypeWillChange',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * To String Magic Method
 *
 * @return string
 */',
        'startLine' => 5536,
        'endLine' => 5540,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getResourceId' => 
      array (
        'name' => 'getResourceId',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get Resource ID
 *
 * We use {} because that symbols should not be in URL according to
 * {@link http://tools.ietf.org/html/rfc3986#section-2 RFC}.
 * It will safe us from any conflicts, because otherwise regexp will
 * match all alphanumeric domains.
 *
 * @return string
 */',
        'startLine' => 5552,
        'endLine' => 5555,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getConnectionByResourceId' => 
      array (
        'name' => 'getConnectionByResourceId',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5564,
            'endLine' => 5564,
            'startColumn' => 54,
            'endColumn' => 56,
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
 * Return existing connection
 *
 * @param string $id
 *
 * @return bool|SSH2 will return false if no such connection
 */',
        'startLine' => 5564,
        'endLine' => 5570,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getConnections' => 
      array (
        'name' => 'getConnections',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return all excising connections
 *
 * @return array<string, SSH2>
 */',
        'startLine' => 5577,
        'endLine' => 5588,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'updateLogHistory' => 
      array (
        'name' => 'updateLogHistory',
        'parameters' => 
        array (
          'old' => 
          array (
            'name' => 'old',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5596,
            'endLine' => 5596,
            'startColumn' => 39,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'new' => 
          array (
            'name' => 'new',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5596,
            'endLine' => 5596,
            'startColumn' => 45,
            'endColumn' => 48,
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
        'startLine' => 5596,
        'endLine' => 5605,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'getAuthMethodsToContinue' => 
      array (
        'name' => 'getAuthMethodsToContinue',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the list of authentication methods that may productively continue authentication.
 *
 * @see https://tools.ietf.org/html/rfc4252#section-5.1
 * @return array|null
 */',
        'startLine' => 5613,
        'endLine' => 5616,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'enableSmartMFA' => 
      array (
        'name' => 'enableSmartMFA',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enables "smart" multi-factor authentication (MFA)
 */',
        'startLine' => 5621,
        'endLine' => 5624,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'disableSmartMFA' => 
      array (
        'name' => 'disableSmartMFA',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disables "smart" multi-factor authentication (MFA)
 */',
        'startLine' => 5629,
        'endLine' => 5632,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
        'aliasName' => NULL,
      ),
      'bytesUntilKeyReexchange' => 
      array (
        'name' => 'bytesUntilKeyReexchange',
        'parameters' => 
        array (
          'bytes' => 
          array (
            'name' => 'bytes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 5639,
            'endLine' => 5639,
            'startColumn' => 45,
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
 * How many bytes until the next key re-exchange?
 *
 * @param int $bytes
 */',
        'startLine' => 5639,
        'endLine' => 5642,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SSH2',
        'implementingClassName' => 'phpseclib3\\Net\\SSH2',
        'currentClassName' => 'phpseclib3\\Net\\SSH2',
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