<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Net/SFTP.php-PHPStan\BetterReflection\Reflection\ReflectionClass-phpseclib3\Net\SFTP
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-0c46014ef9b5756c45db295ff91a8bb5bc7e16dd40687e05f929bde198c23a3e-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'phpseclib3\\Net\\SFTP',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../phpseclib/phpseclib/phpseclib/Net/SFTP.php',
      ),
    ),
    'namespace' => 'phpseclib3\\Net',
    'name' => 'phpseclib3\\Net\\SFTP',
    'shortName' => 'SFTP',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Pure-PHP implementations of SFTP.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 45,
    'endLine' => 3925,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'phpseclib3\\Net\\SSH2',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'CHANNEL' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'CHANNEL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x100',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 44,
            'startFilePos' => 1399,
            'endTokenPos' => 44,
            'endFilePos' => 1403,
          ),
        ),
        'docComment' => '/**
 * SFTP channel constant
 *
 * \\phpseclib3\\Net\\SSH2::exec() uses 0 and \\phpseclib3\\Net\\SSH2::read() / \\phpseclib3\\Net\\SSH2::write() use 1.
 *
 * @see SSH2::send_channel_packet()
 * @see SSH2::get_channel_packet()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 26,
      ),
      'SOURCE_LOCAL_FILE' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'SOURCE_LOCAL_FILE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 55,
            'startFilePos' => 1521,
            'endTokenPos' => 55,
            'endFilePos' => 1521,
          ),
        ),
        'docComment' => '/**
 * Reads data from a local file.
 *
 * @see SFTP::put()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'SOURCE_STRING' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'SOURCE_STRING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 69,
            'endLine' => 69,
            'startTokenPos' => 68,
            'startFilePos' => 1725,
            'endTokenPos' => 68,
            'endFilePos' => 1725,
          ),
        ),
        'docComment' => '/**
 * Reads data from a string.
 *
 * @see SFTP::put()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
      'SOURCE_CALLBACK' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'SOURCE_CALLBACK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 76,
            'endLine' => 76,
            'startTokenPos' => 79,
            'startFilePos' => 1910,
            'endTokenPos' => 79,
            'endFilePos' => 1911,
          ),
        ),
        'docComment' => '/**
 * Reads data from callback:
 * function callback($length) returns string to proceed, null for EOF
 *
 * @see SFTP::put()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 76,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'RESUME' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'RESUME',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 82,
            'endLine' => 82,
            'startTokenPos' => 90,
            'startFilePos' => 2005,
            'endTokenPos' => 90,
            'endFilePos' => 2005,
          ),
        ),
        'docComment' => '/**
 * Resumes an upload
 *
 * @see SFTP::put()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 21,
      ),
      'RESUME_START' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'RESUME_START',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 88,
            'endLine' => 88,
            'startTokenPos' => 101,
            'startFilePos' => 2142,
            'endTokenPos' => 101,
            'endFilePos' => 2142,
          ),
        ),
        'docComment' => '/**
 * Append a local file to an already existing remote file
 *
 * @see SFTP::put()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 88,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
    ),
    'immediateProperties' => 
    array (
      'packet_types' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'packet_types',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 97,
            'endLine' => 97,
            'startTokenPos' => 114,
            'startFilePos' => 2297,
            'endTokenPos' => 115,
            'endFilePos' => 2298,
          ),
        ),
        'docComment' => '/**
 * Packet Types
 *
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 97,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'status_codes' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'status_codes',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 106,
            'endLine' => 106,
            'startTokenPos' => 128,
            'startFilePos' => 2453,
            'endTokenPos' => 129,
            'endFilePos' => 2454,
          ),
        ),
        'docComment' => '/**
 * Status Codes
 *
 * @see self::__construct()
 * @var array
 * @access private
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 106,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'attributes' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'attributes',
        'modifiers' => 20,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var array<int, string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 109,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'open_flags' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'open_flags',
        'modifiers' => 20,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var array<int, string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 112,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'open_flags5' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'open_flags5',
        'modifiers' => 20,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var array<int, string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 115,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'file_types' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'file_types',
        'modifiers' => 20,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var array<int, string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 118,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'use_request_id' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'use_request_id',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 129,
            'endLine' => 129,
            'startTokenPos' => 176,
            'startFilePos' => 3055,
            'endTokenPos' => 176,
            'endFilePos' => 3059,
          ),
        ),
        'docComment' => '/**
 * The Request ID
 *
 * The request ID exists in the off chance that a packet is sent out-of-order.  Of course, this library doesn\'t support
 * concurrent actions, so it\'s somewhat academic, here.
 *
 * @var boolean
 * @see self::_send_sftp_packet()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 129,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'packet_type' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'packet_type',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 140,
            'endLine' => 140,
            'startTokenPos' => 187,
            'startFilePos' => 3380,
            'endTokenPos' => 188,
            'endFilePos' => 3381,
          ),
        ),
        'docComment' => '/**
 * The Packet Type
 *
 * The request ID exists in the off chance that a packet is sent out-of-order.  Of course, this library doesn\'t support
 * concurrent actions, so it\'s somewhat academic, here.
 *
 * @var int
 * @see self::_get_sftp_packet()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 140,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'packet_buffer' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'packet_buffer',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 148,
            'endLine' => 148,
            'startTokenPos' => 199,
            'startFilePos' => 3514,
            'endTokenPos' => 199,
            'endFilePos' => 3515,
          ),
        ),
        'docComment' => '/**
 * Packet Buffer
 *
 * @var string
 * @see self::_get_sftp_packet()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 148,
        'endLine' => 148,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'extensions' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'extensions',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 156,
            'endLine' => 156,
            'startTokenPos' => 210,
            'startFilePos' => 3661,
            'endTokenPos' => 211,
            'endFilePos' => 3662,
          ),
        ),
        'docComment' => '/**
 * Extensions supported by the server
 *
 * @var array
 * @see self::_initChannel()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 156,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'version' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'version',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Server SFTP version
 *
 * @var int
 * @see self::_initChannel()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 164,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultVersion' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'defaultVersion',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Default Server SFTP version
 *
 * @var int
 * @see self::_initChannel()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 172,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preferredVersion' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'preferredVersion',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 180,
            'endLine' => 180,
            'startTokenPos' => 236,
            'startFilePos' => 4059,
            'endTokenPos' => 236,
            'endFilePos' => 4059,
          ),
        ),
        'docComment' => '/**
 * Preferred SFTP version
 *
 * @var int
 * @see self::_initChannel()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 180,
        'endLine' => 180,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pwd' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'pwd',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 189,
            'endLine' => 189,
            'startTokenPos' => 247,
            'startFilePos' => 4217,
            'endTokenPos' => 247,
            'endFilePos' => 4221,
          ),
        ),
        'docComment' => '/**
 * Current working directory
 *
 * @var string|bool
 * @see self::realpath()
 * @see self::chdir()
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 189,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'packet_type_log' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'packet_type_log',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 197,
            'endLine' => 197,
            'startTokenPos' => 258,
            'startFilePos' => 4347,
            'endTokenPos' => 259,
            'endFilePos' => 4348,
          ),
        ),
        'docComment' => '/**
 * Packet Type Log
 *
 * @see self::getLog()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 197,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'packet_log' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'packet_log',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 205,
            'endLine' => 205,
            'startTokenPos' => 270,
            'startFilePos' => 4464,
            'endTokenPos' => 271,
            'endFilePos' => 4465,
          ),
        ),
        'docComment' => '/**
 * Packet Log
 *
 * @see self::getLog()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 205,
        'endLine' => 205,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'realtime_log_file' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
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
        'startLine' => 213,
        'endLine' => 213,
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
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
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
        'startLine' => 221,
        'endLine' => 221,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'realtime_log_wrap' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
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
        'startLine' => 229,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'log_size' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'log_size',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Current log size
 *
 * Should never exceed self::LOG_MAX_SIZE
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 238,
        'endLine' => 238,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'sftp_errors' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'sftp_errors',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 247,
            'endLine' => 247,
            'startTokenPos' => 310,
            'startFilePos' => 5211,
            'endTokenPos' => 311,
            'endFilePos' => 5212,
          ),
        ),
        'docComment' => '/**
 * Error information
 *
 * @see self::getSFTPErrors()
 * @see self::getLastSFTPError()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 247,
        'endLine' => 247,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stat_cache' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'stat_cache',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 260,
            'endLine' => 260,
            'startTokenPos' => 322,
            'startFilePos' => 5583,
            'endTokenPos' => 323,
            'endFilePos' => 5584,
          ),
        ),
        'docComment' => '/**
 * Stat Cache
 *
 * Rather than always having to open a directory and close it immediately there after to see if a file is a directory
 * we\'ll cache the results.
 *
 * @see self::_update_stat_cache()
 * @see self::_remove_from_stat_cache()
 * @see self::_query_stat_cache()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 260,
        'endLine' => 260,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'max_sftp_packet' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'max_sftp_packet',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Max SFTP Packet Size
 *
 * @see self::__construct()
 * @see self::get()
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 269,
        'endLine' => 269,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'use_stat_cache' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'use_stat_cache',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 278,
            'endLine' => 278,
            'startTokenPos' => 341,
            'startFilePos' => 5908,
            'endTokenPos' => 341,
            'endFilePos' => 5911,
          ),
        ),
        'docComment' => '/**
 * Stat Cache Flag
 *
 * @see self::disableStatCache()
 * @see self::enableStatCache()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 278,
        'endLine' => 278,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'sortOptions' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'sortOptions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 287,
            'endLine' => 287,
            'startTokenPos' => 352,
            'startFilePos' => 6070,
            'endTokenPos' => 353,
            'endFilePos' => 6071,
          ),
        ),
        'docComment' => '/**
 * Sort Options
 *
 * @see self::_comparator()
 * @see self::setListOrder()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 287,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'canonicalize_paths' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'canonicalize_paths',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 300,
            'endLine' => 300,
            'startTokenPos' => 364,
            'startFilePos' => 6424,
            'endTokenPos' => 364,
            'endFilePos' => 6427,
          ),
        ),
        'docComment' => '/**
 * Canonicalization Flag
 *
 * Determines whether or not paths should be canonicalized before being
 * passed on to the remote server.
 *
 * @see self::enablePathCanonicalization()
 * @see self::disablePathCanonicalization()
 * @see self::realpath()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 300,
        'endLine' => 300,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'requestBuffer' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'requestBuffer',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 308,
            'endLine' => 308,
            'startTokenPos' => 375,
            'startFilePos' => 6561,
            'endTokenPos' => 376,
            'endFilePos' => 6562,
          ),
        ),
        'docComment' => '/**
 * Request Buffers
 *
 * @see self::_get_sftp_packet()
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 308,
        'endLine' => 308,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preserveTime' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'preserveTime',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 317,
            'endLine' => 317,
            'startTokenPos' => 387,
            'startFilePos' => 6737,
            'endTokenPos' => 387,
            'endFilePos' => 6741,
          ),
        ),
        'docComment' => '/**
 * Preserve timestamps on file downloads / uploads
 *
 * @see self::get()
 * @see self::put()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 317,
        'endLine' => 317,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allow_arbitrary_length_packets' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'allow_arbitrary_length_packets',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 331,
            'endLine' => 331,
            'startTokenPos' => 398,
            'startFilePos' => 7249,
            'endTokenPos' => 398,
            'endFilePos' => 7253,
          ),
        ),
        'docComment' => '/**
 * Arbitrary Length Packets Flag
 *
 * Determines whether or not packets of any length should be allowed,
 * in cases where the server chooses the packet length (such as
 * directory listings). By default, packets are only allowed to be
 * 256 * 1024 bytes (SFTP_MAX_MSG_LENGTH from OpenSSH\'s sftp-common.h)
 *
 * @see self::enableArbitraryLengthPackets()
 * @see self::_get_sftp_packet()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 331,
        'endLine' => 331,
        'startColumn' => 5,
        'endColumn' => 52,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'channel_close' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'channel_close',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 340,
            'endLine' => 340,
            'startTokenPos' => 409,
            'startFilePos' => 7454,
            'endTokenPos' => 409,
            'endFilePos' => 7458,
          ),
        ),
        'docComment' => '/**
 * Was the last packet due to the channels being closed or not?
 *
 * @see self::get()
 * @see self::get_sftp_packet()
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 340,
        'endLine' => 340,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'partial_init' => 
      array (
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'name' => 'partial_init',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 347,
            'endLine' => 347,
            'startTokenPos' => 420,
            'startFilePos' => 7585,
            'endTokenPos' => 420,
            'endFilePos' => 7589,
          ),
        ),
        'docComment' => '/**
 * Has the SFTP channel been partially negotiated?
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 347,
        'endLine' => 347,
        'startColumn' => 5,
        'endColumn' => 34,
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
            'startLine' => 360,
            'endLine' => 360,
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
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 438,
                'startFilePos' => 7898,
                'endTokenPos' => 438,
                'endFilePos' => 7899,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 360,
            'endLine' => 360,
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
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 445,
                'startFilePos' => 7913,
                'endTokenPos' => 445,
                'endFilePos' => 7914,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 360,
            'endLine' => 360,
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
 * Connects to an SFTP server
 *
 * $host can either be a string, representing the host, or a stream resource.
 *
 * @param mixed $host
 * @param int $port
 * @param int $timeout
 */',
        'startLine' => 360,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'precheck' => 
      array (
        'name' => 'precheck',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check a few things before SFTP functions are called
 *
 * @return bool
 */',
        'startLine' => 531,
        'endLine' => 542,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'partial_init_sftp_connection' => 
      array (
        'name' => 'partial_init_sftp_connection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Partially initialize an SFTP connection
 *
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return bool
 */',
        'startLine' => 550,
        'endLine' => 620,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'init_sftp_connection' => 
      array (
        'name' => 'init_sftp_connection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * (Re)initializes the SFTP channel
 *
 * @return bool
 */',
        'startLine' => 627,
        'endLine' => 723,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'disableStatCache' => 
      array (
        'name' => 'disableStatCache',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable the stat cache
 *
 */',
        'startLine' => 729,
        'endLine' => 732,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'enableStatCache' => 
      array (
        'name' => 'enableStatCache',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable the stat cache
 *
 */',
        'startLine' => 738,
        'endLine' => 741,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'clearStatCache' => 
      array (
        'name' => 'clearStatCache',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clear the stat cache
 *
 */',
        'startLine' => 747,
        'endLine' => 750,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'enablePathCanonicalization' => 
      array (
        'name' => 'enablePathCanonicalization',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable path canonicalization
 *
 */',
        'startLine' => 756,
        'endLine' => 759,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'disablePathCanonicalization' => 
      array (
        'name' => 'disablePathCanonicalization',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable path canonicalization
 *
 * If this is enabled then $sftp->pwd() will not return the canonicalized absolute path
 *
 */',
        'startLine' => 767,
        'endLine' => 770,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'enableArbitraryLengthPackets' => 
      array (
        'name' => 'enableArbitraryLengthPackets',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable arbitrary length packets
 *
 */',
        'startLine' => 776,
        'endLine' => 779,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'disableArbitraryLengthPackets' => 
      array (
        'name' => 'disableArbitraryLengthPackets',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable arbitrary length packets
 *
 */',
        'startLine' => 785,
        'endLine' => 788,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'pwd' => 
      array (
        'name' => 'pwd',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the current directory name
 *
 * @return string|bool
 */',
        'startLine' => 795,
        'endLine' => 802,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'logError' => 
      array (
        'name' => 'logError',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 810,
            'endLine' => 810,
            'startColumn' => 31,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '-1',
              'attributes' => 
              array (
                'startLine' => 810,
                'endLine' => 810,
                'startTokenPos' => 2766,
                'startFilePos' => 26039,
                'endTokenPos' => 2767,
                'endFilePos' => 26040,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 810,
            'endLine' => 810,
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
 * Logs errors
 *
 * @param string $response
 * @param int $status
 */',
        'startLine' => 810,
        'endLine' => 824,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'realpath' => 
      array (
        'name' => 'realpath',
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
            'startLine' => 840,
            'endLine' => 840,
            'startColumn' => 30,
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
 * Canonicalize the Server-Side Path Name
 *
 * SFTP doesn\'t provide a mechanism by which the current working directory can be changed, so we\'ll emulate it.  Returns
 * the absolute (canonicalized) path.
 *
 * If canonicalize_paths has been disabled using disablePathCanonicalization(), $path is returned as-is.
 *
 * @see self::chdir()
 * @see self::disablePathCanonicalization()
 * @param string $path
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return mixed
 */',
        'startLine' => 840,
        'endLine' => 923,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'chdir' => 
      array (
        'name' => 'chdir',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 932,
            'endLine' => 932,
            'startColumn' => 27,
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
 * Changes the current directory
 *
 * @param string $dir
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return bool
 */',
        'startLine' => 932,
        'endLine' => 991,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'nlist' => 
      array (
        'name' => 'nlist',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => 
            array (
              'code' => '\'.\'',
              'attributes' => 
              array (
                'startLine' => 1000,
                'endLine' => 1000,
                'startTokenPos' => 3842,
                'startFilePos' => 32602,
                'endTokenPos' => 3842,
                'endFilePos' => 32604,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1000,
            'endLine' => 1000,
            'startColumn' => 27,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1000,
                'endLine' => 1000,
                'startTokenPos' => 3849,
                'startFilePos' => 32620,
                'endTokenPos' => 3849,
                'endFilePos' => 32624,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1000,
            'endLine' => 1000,
            'startColumn' => 39,
            'endColumn' => 56,
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
 * Returns a list of files in the given directory
 *
 * @param string $dir
 * @param bool $recursive
 * @return array|false
 */',
        'startLine' => 1000,
        'endLine' => 1003,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'nlist_helper' => 
      array (
        'name' => 'nlist_helper',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1013,
            'endLine' => 1013,
            'startColumn' => 35,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1013,
            'endLine' => 1013,
            'startColumn' => 41,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'relativeDir' => 
          array (
            'name' => 'relativeDir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1013,
            'endLine' => 1013,
            'startColumn' => 53,
            'endColumn' => 64,
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
 * Helper method for nlist
 *
 * @param string $dir
 * @param bool $recursive
 * @param string $relativeDir
 * @return array|false
 */',
        'startLine' => 1013,
        'endLine' => 1043,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'rawlist' => 
      array (
        'name' => 'rawlist',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => 
            array (
              'code' => '\'.\'',
              'attributes' => 
              array (
                'startLine' => 1052,
                'endLine' => 1052,
                'startTokenPos' => 4147,
                'startFilePos' => 34102,
                'endTokenPos' => 4147,
                'endFilePos' => 34104,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1052,
            'endLine' => 1052,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1052,
                'endLine' => 1052,
                'startTokenPos' => 4154,
                'startFilePos' => 34120,
                'endTokenPos' => 4154,
                'endFilePos' => 34124,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1052,
            'endLine' => 1052,
            'startColumn' => 41,
            'endColumn' => 58,
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
 * Returns a detailed list of files in the given directory
 *
 * @param string $dir
 * @param bool $recursive
 * @return array|false
 */',
        'startLine' => 1052,
        'endLine' => 1093,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'readlist' => 
      array (
        'name' => 'readlist',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1103,
            'endLine' => 1103,
            'startColumn' => 31,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'raw' => 
          array (
            'name' => 'raw',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1103,
                'endLine' => 1103,
                'startTokenPos' => 4478,
                'startFilePos' => 35671,
                'endTokenPos' => 4478,
                'endFilePos' => 35674,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1103,
            'endLine' => 1103,
            'startColumn' => 37,
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
 * Reads a list, be it detailed or not, of files in the given directory
 *
 * @param string $dir
 * @param bool $raw
 * @return array|false
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 */',
        'startLine' => 1103,
        'endLine' => 1203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'comparator' => 
      array (
        'name' => 'comparator',
        'parameters' => 
        array (
          'a' => 
          array (
            'name' => 'a',
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
            'startLine' => 1214,
            'endLine' => 1214,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'b' => 
          array (
            'name' => 'b',
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
            'startLine' => 1214,
            'endLine' => 1214,
            'startColumn' => 43,
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
 * Compares two rawlist entries using parameters set by setListOrder()
 *
 * Intended for use with uasort()
 *
 * @param array $a
 * @param array $b
 * @return int
 */',
        'startLine' => 1214,
        'endLine' => 1266,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'setListOrder' => 
      array (
        'name' => 'setListOrder',
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
            'startLine' => 1288,
            'endLine' => 1288,
            'startColumn' => 34,
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
 * Defines how nlist() and rawlist() will be sorted - if at all.
 *
 * If sorting is enabled directories and files will be sorted independently with
 * directories appearing before files in the resultant array that is returned.
 *
 * Any parameter returned by stat is a valid sort parameter for this function.
 * Filename comparisons are case insensitive.
 *
 * Examples:
 *
 * $sftp->setListOrder(\'filename\', SORT_ASC);
 * $sftp->setListOrder(\'size\', SORT_DESC, \'filename\', SORT_ASC);
 * $sftp->setListOrder(true);
 *    Separates directories from files but doesn\'t do any sorting beyond that
 * $sftp->setListOrder();
 *    Don\'t do any sort of sorting
 *
 * @param string ...$args
 */',
        'startLine' => 1288,
        'endLine' => 1301,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'update_stat_cache' => 
      array (
        'name' => 'update_stat_cache',
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
            'startLine' => 1309,
            'endLine' => 1309,
            'startColumn' => 40,
            'endColumn' => 44,
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
            'startLine' => 1309,
            'endLine' => 1309,
            'startColumn' => 47,
            'endColumn' => 52,
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
 * Save files / directories to cache
 *
 * @param string $path
 * @param mixed $value
 */',
        'startLine' => 1309,
        'endLine' => 1344,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'remove_from_stat_cache' => 
      array (
        'name' => 'remove_from_stat_cache',
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
            'startLine' => 1352,
            'endLine' => 1352,
            'startColumn' => 45,
            'endColumn' => 49,
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
 * Remove files / directories from cache
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 1352,
        'endLine' => 1371,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'query_stat_cache' => 
      array (
        'name' => 'query_stat_cache',
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
            'startLine' => 1381,
            'endLine' => 1381,
            'startColumn' => 39,
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
 * Checks cache for path
 *
 * Mainly used by file_exists
 *
 * @param string $path
 * @return mixed
 */',
        'startLine' => 1381,
        'endLine' => 1396,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'stat' => 
      array (
        'name' => 'stat',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1406,
            'endLine' => 1406,
            'startColumn' => 26,
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
 * Returns general information about a file.
 *
 * Returns an array on success and false otherwise.
 *
 * @param string $filename
 * @return array|false
 */',
        'startLine' => 1406,
        'endLine' => 1452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'lstat' => 
      array (
        'name' => 'lstat',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1462,
            'endLine' => 1462,
            'startColumn' => 27,
            'endColumn' => 35,
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
 * Returns general information about a file or symbolic link.
 *
 * Returns an array on success and false otherwise.
 *
 * @param string $filename
 * @return array|false
 */',
        'startLine' => 1462,
        'endLine' => 1516,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'stat_helper' => 
      array (
        'name' => 'stat_helper',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1529,
            'endLine' => 1529,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1529,
            'endLine' => 1529,
            'startColumn' => 45,
            'endColumn' => 49,
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
 * Returns general information about a file or symbolic link
 *
 * Determines information without calling \\phpseclib3\\Net\\SFTP::realpath().
 * The second parameter can be either NET_SFTP_STAT or NET_SFTP_LSTAT.
 *
 * @param string $filename
 * @param int $type
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return array|false
 */',
        'startLine' => 1529,
        'endLine' => 1549,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'truncate' => 
      array (
        'name' => 'truncate',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1558,
            'endLine' => 1558,
            'startColumn' => 30,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'new_size' => 
          array (
            'name' => 'new_size',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1558,
            'endLine' => 1558,
            'startColumn' => 41,
            'endColumn' => 49,
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
 * Truncates a file to a given length
 *
 * @param string $filename
 * @param int $new_size
 * @return bool
 */',
        'startLine' => 1558,
        'endLine' => 1563,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'touch' => 
      array (
        'name' => 'touch',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1576,
            'endLine' => 1576,
            'startColumn' => 27,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1576,
                'endLine' => 1576,
                'startTokenPos' => 7515,
                'startFilePos' => 52133,
                'endTokenPos' => 7515,
                'endFilePos' => 52136,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1576,
            'endLine' => 1576,
            'startColumn' => 38,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'atime' => 
          array (
            'name' => 'atime',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1576,
                'endLine' => 1576,
                'startTokenPos' => 7522,
                'startFilePos' => 52148,
                'endTokenPos' => 7522,
                'endFilePos' => 52151,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1576,
            'endLine' => 1576,
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
 * Sets access and modification time of file.
 *
 * If the file does not exist, it will be created.
 *
 * @param string $filename
 * @param int $time
 * @param int $atime
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return bool
 */',
        'startLine' => 1576,
        'endLine' => 1622,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'chown' => 
      array (
        'name' => 'chown',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1639,
            'endLine' => 1639,
            'startColumn' => 27,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'uid' => 
          array (
            'name' => 'uid',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1639,
            'endLine' => 1639,
            'startColumn' => 38,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1639,
                'endLine' => 1639,
                'startTokenPos' => 7891,
                'startFilePos' => 54310,
                'endTokenPos' => 7891,
                'endFilePos' => 54314,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1639,
            'endLine' => 1639,
            'startColumn' => 44,
            'endColumn' => 61,
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
 * Changes file or directory owner
 *
 * $uid should be an int for SFTPv3 and a string for SFTPv4+. Ideally the string
 * would be of the form "user@dns_domain" but it does not need to be.
 * `$sftp->getSupportedVersions()[\'version\']` will return the specific version
 * that\'s being used.
 *
 * Returns true on success or false on error.
 *
 * @param string $filename
 * @param int|string $uid
 * @param bool $recursive
 * @return bool
 */',
        'startLine' => 1639,
        'endLine' => 1669,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'chgrp' => 
      array (
        'name' => 'chgrp',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1686,
            'endLine' => 1686,
            'startColumn' => 27,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'gid' => 
          array (
            'name' => 'gid',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1686,
            'endLine' => 1686,
            'startColumn' => 38,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1686,
                'endLine' => 1686,
                'startTokenPos' => 7994,
                'startFilePos' => 56564,
                'endTokenPos' => 7994,
                'endFilePos' => 56568,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1686,
            'endLine' => 1686,
            'startColumn' => 44,
            'endColumn' => 61,
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
 * Changes file or directory group
 *
 * $gid should be an int for SFTPv3 and a string for SFTPv4+. Ideally the string
 * would be of the form "user@dns_domain" but it does not need to be.
 * `$sftp->getSupportedVersions()[\'version\']` will return the specific version
 * that\'s being used.
 *
 * Returns true on success or false on error.
 *
 * @param string $filename
 * @param int|string $gid
 * @param bool $recursive
 * @return bool
 */',
        'startLine' => 1686,
        'endLine' => 1693,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'chmod' => 
      array (
        'name' => 'chmod',
        'parameters' => 
        array (
          'mode' => 
          array (
            'name' => 'mode',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1708,
            'endLine' => 1708,
            'startColumn' => 27,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1708,
            'endLine' => 1708,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1708,
                'endLine' => 1708,
                'startTokenPos' => 8083,
                'startFilePos' => 57287,
                'endTokenPos' => 8083,
                'endFilePos' => 57291,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1708,
            'endLine' => 1708,
            'startColumn' => 45,
            'endColumn' => 62,
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
 * Set permissions on a file.
 *
 * Returns the new file permissions on success or false on error.
 * If $recursive is true than this just returns true or false.
 *
 * @param int $mode
 * @param string $filename
 * @param bool $recursive
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return mixed
 * @changed in phpseclib 4.0.0
 */',
        'startLine' => 1708,
        'endLine' => 1746,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'setstat' => 
      array (
        'name' => 'setstat',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1757,
            'endLine' => 1757,
            'startColumn' => 30,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attr' => 
          array (
            'name' => 'attr',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1757,
            'endLine' => 1757,
            'startColumn' => 41,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1757,
            'endLine' => 1757,
            'startColumn' => 48,
            'endColumn' => 57,
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
 * Sets information about a file
 *
 * @param string $filename
 * @param string $attr
 * @param bool $recursive
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return bool
 */',
        'startLine' => 1757,
        'endLine' => 1806,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'setstat_recursive' => 
      array (
        'name' => 'setstat_recursive',
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
            'startLine' => 1818,
            'endLine' => 1818,
            'startColumn' => 40,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attr' => 
          array (
            'name' => 'attr',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1818,
            'endLine' => 1818,
            'startColumn' => 47,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'i' => 
          array (
            'name' => 'i',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1818,
            'endLine' => 1818,
            'startColumn' => 54,
            'endColumn' => 56,
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
 * Recursively sets information on directories on the SFTP server
 *
 * Minimizes directory lookups and SSH_FXP_STATUS requests for speed.
 *
 * @param string $path
 * @param string $attr
 * @param int $i
 * @return bool
 */',
        'startLine' => 1818,
        'endLine' => 1881,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'readlink' => 
      array (
        'name' => 'readlink',
        'parameters' => 
        array (
          'link' => 
          array (
            'name' => 'link',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1890,
            'endLine' => 1890,
            'startColumn' => 30,
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
 * Return the target of a symbolic link
 *
 * @param string $link
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return mixed
 */',
        'startLine' => 1890,
        'endLine' => 1924,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'symlink' => 
      array (
        'name' => 'symlink',
        'parameters' => 
        array (
          'target' => 
          array (
            'name' => 'target',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1936,
            'endLine' => 1936,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'link' => 
          array (
            'name' => 'link',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1936,
            'endLine' => 1936,
            'startColumn' => 38,
            'endColumn' => 42,
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
 * Create a symlink
 *
 * symlink() creates a symbolic link to the existing target with the specified name link.
 *
 * @param string $target
 * @param string $link
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return bool
 */',
        'startLine' => 1936,
        'endLine' => 1993,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'mkdir' => 
      array (
        'name' => 'mkdir',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2003,
            'endLine' => 2003,
            'startColumn' => 27,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => 
            array (
              'code' => '-1',
              'attributes' => 
              array (
                'startLine' => 2003,
                'endLine' => 2003,
                'startTokenPos' => 9660,
                'startFilePos' => 67312,
                'endTokenPos' => 9661,
                'endFilePos' => 67313,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2003,
            'endLine' => 2003,
            'startColumn' => 33,
            'endColumn' => 42,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2003,
                'endLine' => 2003,
                'startTokenPos' => 9668,
                'startFilePos' => 67329,
                'endTokenPos' => 9668,
                'endFilePos' => 67333,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2003,
            'endLine' => 2003,
            'startColumn' => 45,
            'endColumn' => 62,
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
 * Creates a directory.
 *
 * @param string $dir
 * @param int $mode
 * @param bool $recursive
 * @return bool
 */',
        'startLine' => 2003,
        'endLine' => 2026,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'mkdir_helper' => 
      array (
        'name' => 'mkdir_helper',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2035,
            'endLine' => 2035,
            'startColumn' => 35,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2035,
            'endLine' => 2035,
            'startColumn' => 41,
            'endColumn' => 45,
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
 * Helper function for directory creation
 *
 * @param string $dir
 * @param int $mode
 * @return bool
 */',
        'startLine' => 2035,
        'endLine' => 2060,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'rmdir' => 
      array (
        'name' => 'rmdir',
        'parameters' => 
        array (
          'dir' => 
          array (
            'name' => 'dir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2069,
            'endLine' => 2069,
            'startColumn' => 27,
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
 * Removes a directory.
 *
 * @param string $dir
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return bool
 */',
        'startLine' => 2069,
        'endLine' => 2105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'put' => 
      array (
        'name' => 'put',
        'parameters' => 
        array (
          'remote_file' => 
          array (
            'name' => 'remote_file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 25,
            'endColumn' => 36,
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
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => 
            array (
              'code' => 'self::SOURCE_STRING',
              'attributes' => 
              array (
                'startLine' => 2156,
                'endLine' => 2156,
                'startTokenPos' => 10306,
                'startFilePos' => 73524,
                'endTokenPos' => 10308,
                'endFilePos' => 73542,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 46,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'start' => 
          array (
            'name' => 'start',
            'default' => 
            array (
              'code' => '-1',
              'attributes' => 
              array (
                'startLine' => 2156,
                'endLine' => 2156,
                'startTokenPos' => 10315,
                'startFilePos' => 73554,
                'endTokenPos' => 10316,
                'endFilePos' => 73555,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 75,
            'endColumn' => 85,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'local_start' => 
          array (
            'name' => 'local_start',
            'default' => 
            array (
              'code' => '-1',
              'attributes' => 
              array (
                'startLine' => 2156,
                'endLine' => 2156,
                'startTokenPos' => 10323,
                'startFilePos' => 73573,
                'endTokenPos' => 10324,
                'endFilePos' => 73574,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 88,
            'endColumn' => 104,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'progressCallback' => 
          array (
            'name' => 'progressCallback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2156,
                'endLine' => 2156,
                'startTokenPos' => 10331,
                'startFilePos' => 73597,
                'endTokenPos' => 10331,
                'endFilePos' => 73600,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 107,
            'endColumn' => 130,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Uploads a file to the SFTP server.
 *
 * By default, \\phpseclib3\\Net\\SFTP::put() does not read from the local filesystem.  $data is dumped directly into $remote_file.
 * So, for example, if you set $data to \'filename.ext\' and then do \\phpseclib3\\Net\\SFTP::get(), you will get a file, twelve bytes
 * long, containing \'filename.ext\' as its contents.
 *
 * Setting $mode to self::SOURCE_LOCAL_FILE will change the above behavior.  With self::SOURCE_LOCAL_FILE, $remote_file will
 * contain as many bytes as filename.ext does on your local filesystem.  If your filename.ext is 1MB then that is how
 * large $remote_file will be, as well.
 *
 * Setting $mode to self::SOURCE_CALLBACK will use $data as callback function, which gets only one parameter -- number
 * of bytes to return, and returns a string if there is some data or null if there is no more data
 *
 * If $data is a resource then it\'ll be used as a resource instead.
 *
 * Currently, only binary mode is supported.  As such, if the line endings need to be adjusted, you will need to take
 * care of that, yourself.
 *
 * $mode can take an additional two parameters - self::RESUME and self::RESUME_START. These are bitwise AND\'d with
 * $mode. So if you want to resume upload of a 300mb file on the local file system you\'d set $mode to the following:
 *
 * self::SOURCE_LOCAL_FILE | self::RESUME
 *
 * If you wanted to simply append the full contents of a local file to the full contents of a remote file you\'d replace
 * self::RESUME with self::RESUME_START.
 *
 * If $mode & (self::RESUME | self::RESUME_START) then self::RESUME_START will be assumed.
 *
 * $start and $local_start give you more fine grained control over this process and take precident over self::RESUME
 * when they\'re non-negative. ie. $start could let you write at the end of a file (like self::RESUME) or in the middle
 * of one. $local_start could let you start your reading from the end of a file (like self::RESUME_START) or in the
 * middle of one.
 *
 * Setting $local_start to > 0 or $mode | self::RESUME_START doesn\'t do anything unless $mode | self::SOURCE_LOCAL_FILE.
 *
 * {@internal ASCII mode for SFTPv4/5/6 can be supported by adding a new function - \\phpseclib3\\Net\\SFTP::setMode().}
 *
 * @param string $remote_file
 * @param string|resource $data
 * @param int $mode
 * @param int $start
 * @param int $local_start
 * @param callable|null $progressCallback
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @throws \\BadFunctionCallException if you\'re uploading via a callback and the callback function is invalid
 * @throws FileNotFoundException if you\'re uploading via a file and the file doesn\'t exist
 * @return bool
 */',
        'startLine' => 2156,
        'endLine' => 2338,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'read_put_responses' => 
      array (
        'name' => 'read_put_responses',
        'parameters' => 
        array (
          'i' => 
          array (
            'name' => 'i',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2350,
            'endLine' => 2350,
            'startColumn' => 41,
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
 * Reads multiple successive SSH_FXP_WRITE responses
 *
 * Sending an SSH_FXP_WRITE packet and immediately reading its response isn\'t as efficient as blindly sending out $i
 * SSH_FXP_WRITEs, in succession, and then reading $i responses.
 *
 * @param int $i
 * @return bool
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 */',
        'startLine' => 2350,
        'endLine' => 2370,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'close_handle' => 
      array (
        'name' => 'close_handle',
        'parameters' => 
        array (
          'handle' => 
          array (
            'name' => 'handle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2379,
            'endLine' => 2379,
            'startColumn' => 35,
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
 * Close handle
 *
 * @param string $handle
 * @return bool
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 */',
        'startLine' => 2379,
        'endLine' => 2401,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'remote_file' => 
          array (
            'name' => 'remote_file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2420,
            'endLine' => 2420,
            'startColumn' => 25,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'local_file' => 
          array (
            'name' => 'local_file',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2420,
                'endLine' => 2420,
                'startTokenPos' => 12099,
                'startFilePos' => 83315,
                'endTokenPos' => 12099,
                'endFilePos' => 83319,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2420,
            'endLine' => 2420,
            'startColumn' => 39,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 2420,
                'endLine' => 2420,
                'startTokenPos' => 12106,
                'startFilePos' => 83332,
                'endTokenPos' => 12106,
                'endFilePos' => 83332,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2420,
            'endLine' => 2420,
            'startColumn' => 60,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'length' => 
          array (
            'name' => 'length',
            'default' => 
            array (
              'code' => '-1',
              'attributes' => 
              array (
                'startLine' => 2420,
                'endLine' => 2420,
                'startTokenPos' => 12113,
                'startFilePos' => 83345,
                'endTokenPos' => 12114,
                'endFilePos' => 83346,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2420,
            'endLine' => 2420,
            'startColumn' => 73,
            'endColumn' => 84,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'progressCallback' => 
          array (
            'name' => 'progressCallback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2420,
                'endLine' => 2420,
                'startTokenPos' => 12121,
                'startFilePos' => 83369,
                'endTokenPos' => 12121,
                'endFilePos' => 83372,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2420,
            'endLine' => 2420,
            'startColumn' => 87,
            'endColumn' => 110,
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
 * Downloads a file from the SFTP server.
 *
 * Returns a string containing the contents of $remote_file if $local_file is left undefined or a boolean false if
 * the operation was unsuccessful.  If $local_file is defined, returns true or false depending on the success of the
 * operation.
 *
 * $offset and $length can be used to download files in chunks.
 *
 * @param string $remote_file
 * @param string|bool|resource|callable $local_file
 * @param int $offset
 * @param int $length
 * @param callable|null $progressCallback
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 * @return string|bool
 */',
        'startLine' => 2420,
        'endLine' => 2571,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
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
            'startLine' => 2581,
            'endLine' => 2581,
            'startColumn' => 28,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 2581,
                'endLine' => 2581,
                'startTokenPos' => 13189,
                'startFilePos' => 89180,
                'endTokenPos' => 13189,
                'endFilePos' => 89183,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2581,
            'endLine' => 2581,
            'startColumn' => 35,
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
 * Deletes a file on the SFTP server.
 *
 * @param string $path
 * @param bool $recursive
 * @return bool
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 */',
        'startLine' => 2581,
        'endLine' => 2630,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'delete_recursive' => 
      array (
        'name' => 'delete_recursive',
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
            'startLine' => 2641,
            'endLine' => 2641,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'i' => 
          array (
            'name' => 'i',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2641,
            'endLine' => 2641,
            'startColumn' => 46,
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
        'docComment' => '/**
 * Recursively deletes directories on the SFTP server
 *
 * Minimizes directory lookups and SSH_FXP_STATUS requests for speed.
 *
 * @param string $path
 * @param int $i
 * @return bool
 */',
        'startLine' => 2641,
        'endLine' => 2699,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'file_exists' => 
      array (
        'name' => 'file_exists',
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
            'startLine' => 2707,
            'endLine' => 2707,
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
 * Checks whether a file or directory exists
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2707,
        'endLine' => 2725,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'is_dir' => 
      array (
        'name' => 'is_dir',
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
            'startLine' => 2733,
            'endLine' => 2733,
            'startColumn' => 28,
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
 * Tells whether the filename is a directory
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2733,
        'endLine' => 2740,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'is_file' => 
      array (
        'name' => 'is_file',
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
            'startLine' => 2748,
            'endLine' => 2748,
            'startColumn' => 29,
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
 * Tells whether the filename is a regular file
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2748,
        'endLine' => 2755,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'is_link' => 
      array (
        'name' => 'is_link',
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
            'startLine' => 2763,
            'endLine' => 2763,
            'startColumn' => 29,
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
 * Tells whether the filename is a symbolic link
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2763,
        'endLine' => 2770,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'is_readable' => 
      array (
        'name' => 'is_readable',
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
            'startLine' => 2778,
            'endLine' => 2778,
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
 * Tells whether a file exists and is readable
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2778,
        'endLine' => 2800,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'is_writable' => 
      array (
        'name' => 'is_writable',
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
            'startLine' => 2808,
            'endLine' => 2808,
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
 * Tells whether the filename is writable
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2808,
        'endLine' => 2830,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'is_writeable' => 
      array (
        'name' => 'is_writeable',
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
            'startLine' => 2840,
            'endLine' => 2840,
            'startColumn' => 34,
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
 * Tells whether the filename is writeable
 *
 * Alias of is_writable
 *
 * @param string $path
 * @return bool
 */',
        'startLine' => 2840,
        'endLine' => 2843,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'fileatime' => 
      array (
        'name' => 'fileatime',
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
            'startLine' => 2851,
            'endLine' => 2851,
            'startColumn' => 31,
            'endColumn' => 35,
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
 * Gets last access time of file
 *
 * @param string $path
 * @return mixed
 */',
        'startLine' => 2851,
        'endLine' => 2854,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'filemtime' => 
      array (
        'name' => 'filemtime',
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
            'startLine' => 2862,
            'endLine' => 2862,
            'startColumn' => 31,
            'endColumn' => 35,
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
 * Gets file modification time
 *
 * @param string $path
 * @return mixed
 */',
        'startLine' => 2862,
        'endLine' => 2865,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'fileperms' => 
      array (
        'name' => 'fileperms',
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
            'startLine' => 2873,
            'endLine' => 2873,
            'startColumn' => 31,
            'endColumn' => 35,
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
 * Gets file permissions
 *
 * @param string $path
 * @return mixed
 */',
        'startLine' => 2873,
        'endLine' => 2876,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'fileowner' => 
      array (
        'name' => 'fileowner',
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
            'startLine' => 2884,
            'endLine' => 2884,
            'startColumn' => 31,
            'endColumn' => 35,
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
 * Gets file owner
 *
 * @param string $path
 * @return mixed
 */',
        'startLine' => 2884,
        'endLine' => 2887,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'filegroup' => 
      array (
        'name' => 'filegroup',
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
            'startLine' => 2895,
            'endLine' => 2895,
            'startColumn' => 31,
            'endColumn' => 35,
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
 * Gets file group
 *
 * @param string $path
 * @return mixed
 */',
        'startLine' => 2895,
        'endLine' => 2898,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'recursiveFilesize' => 
      array (
        'name' => 'recursiveFilesize',
        'parameters' => 
        array (
          'files' => 
          array (
            'name' => 'files',
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
            'startLine' => 2905,
            'endLine' => 2905,
            'startColumn' => 47,
            'endColumn' => 58,
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
 * Recursively go through rawlist() output to get the total filesize
 *
 * @return int
 */',
        'startLine' => 2905,
        'endLine' => 2917,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'filesize' => 
      array (
        'name' => 'filesize',
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
            'startLine' => 2926,
            'endLine' => 2926,
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'recursive' => 
          array (
            'name' => 'recursive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2926,
                'endLine' => 2926,
                'startTokenPos' => 14771,
                'startFilePos' => 98519,
                'endTokenPos' => 14771,
                'endFilePos' => 98523,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2926,
            'endLine' => 2926,
            'startColumn' => 37,
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
 * Gets file size
 *
 * @param string $path
 * @param bool $recursive
 * @return mixed
 */',
        'startLine' => 2926,
        'endLine' => 2931,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'filetype' => 
      array (
        'name' => 'filetype',
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
            'startLine' => 2939,
            'endLine' => 2939,
            'startColumn' => 30,
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
 * Gets file type
 *
 * @param string $path
 * @return string|false
 */',
        'startLine' => 2939,
        'endLine' => 2962,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'get_stat_cache_prop' => 
      array (
        'name' => 'get_stat_cache_prop',
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
            'startLine' => 2973,
            'endLine' => 2973,
            'startColumn' => 42,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prop' => 
          array (
            'name' => 'prop',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2973,
            'endLine' => 2973,
            'startColumn' => 49,
            'endColumn' => 53,
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
 * Return a stat properity
 *
 * Uses cache if appropriate.
 *
 * @param string $path
 * @param string $prop
 * @return mixed
 */',
        'startLine' => 2973,
        'endLine' => 2976,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'get_lstat_cache_prop' => 
      array (
        'name' => 'get_lstat_cache_prop',
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
            'startLine' => 2987,
            'endLine' => 2987,
            'startColumn' => 43,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prop' => 
          array (
            'name' => 'prop',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2987,
            'endLine' => 2987,
            'startColumn' => 50,
            'endColumn' => 54,
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
 * Return an lstat properity
 *
 * Uses cache if appropriate.
 *
 * @param string $path
 * @param string $prop
 * @return mixed
 */',
        'startLine' => 2987,
        'endLine' => 2990,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'get_xstat_cache_prop' => 
      array (
        'name' => 'get_xstat_cache_prop',
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
            'startLine' => 3002,
            'endLine' => 3002,
            'startColumn' => 43,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prop' => 
          array (
            'name' => 'prop',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3002,
            'endLine' => 3002,
            'startColumn' => 50,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3002,
            'endLine' => 3002,
            'startColumn' => 57,
            'endColumn' => 61,
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
 * Return a stat or lstat properity
 *
 * Uses cache if appropriate.
 *
 * @param string $path
 * @param string $prop
 * @param string $type
 * @return mixed
 */',
        'startLine' => 3002,
        'endLine' => 3025,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'rename' => 
      array (
        'name' => 'rename',
        'parameters' => 
        array (
          'oldname' => 
          array (
            'name' => 'oldname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3037,
            'endLine' => 3037,
            'startColumn' => 28,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'newname' => 
          array (
            'name' => 'newname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3037,
            'endLine' => 3037,
            'startColumn' => 38,
            'endColumn' => 45,
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
 * Renames a file or a directory on the SFTP server.
 *
 * If the file already exists this will return false
 *
 * @param string $oldname
 * @param string $newname
 * @return bool
 * @throws \\UnexpectedValueException on receipt of unexpected packets
 */',
        'startLine' => 3037,
        'endLine' => 3088,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'parseTime' => 
      array (
        'name' => 'parseTime',
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
            'startLine' => 3100,
            'endLine' => 3100,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3100,
            'endLine' => 3100,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3100,
            'endLine' => 3100,
            'startColumn' => 46,
            'endColumn' => 55,
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
 * Parse Time
 *
 * See \'7.7.  Times\' of draft-ietf-secsh-filexfer-13 for more info.
 *
 * @param string $key
 * @param int $flags
 * @param string $response
 * @return array
 */',
        'startLine' => 3100,
        'endLine' => 3108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'parseAttributes' => 
      array (
        'name' => 'parseAttributes',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3118,
            'endLine' => 3118,
            'startColumn' => 40,
            'endColumn' => 49,
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
 * Parse Attributes
 *
 * See \'7.  File Attributes\' of draft-ietf-secsh-filexfer-13 for more info.
 *
 * @param string $response
 * @return array
 */',
        'startLine' => 3118,
        'endLine' => 3256,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'parseMode' => 
      array (
        'name' => 'parseMode',
        'parameters' => 
        array (
          'mode' => 
          array (
            'name' => 'mode',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3266,
            'endLine' => 3266,
            'startColumn' => 32,
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
 * Attempt to identify the file type
 *
 * Quoting the SFTP RFC, "Implementations MUST NOT send bits that are not defined" but they seem to anyway
 *
 * @param int $mode
 * @return int
 */',
        'startLine' => 3266,
        'endLine' => 3296,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'parseLongname' => 
      array (
        'name' => 'parseLongname',
        'parameters' => 
        array (
          'longname' => 
          array (
            'name' => 'longname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3312,
            'endLine' => 3312,
            'startColumn' => 36,
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
 * Parse Longname
 *
 * SFTPv3 doesn\'t provide any easy way of identifying a file type.  You could try to open
 * a file as a directory and see if an error is returned or you could try to parse the
 * SFTPv3-specific longname field of the SSH_FXP_NAME packet.  That\'s what this function does.
 * The result is returned using the
 * {@link http://tools.ietf.org/html/draft-ietf-secsh-filexfer-04#section-5.2 SFTPv4 type constants}.
 *
 * If the longname is in an unrecognized format bool(false) is returned.
 *
 * @param string $longname
 * @return mixed
 */',
        'startLine' => 3312,
        'endLine' => 3330,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'send_sftp_packet' => 
      array (
        'name' => 'send_sftp_packet',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3344,
            'endLine' => 3344,
            'startColumn' => 39,
            'endColumn' => 43,
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
            'startLine' => 3344,
            'endLine' => 3344,
            'startColumn' => 46,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'request_id' => 
          array (
            'name' => 'request_id',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 3344,
                'endLine' => 3344,
                'startTokenPos' => 16885,
                'startFilePos' => 114895,
                'endTokenPos' => 16885,
                'endFilePos' => 114895,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3344,
            'endLine' => 3344,
            'startColumn' => 53,
            'endColumn' => 67,
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
 * Sends SFTP Packets
 *
 * See \'6. General Packet Format\' of draft-ietf-secsh-filexfer-13 for more info.
 *
 * @param int $type
 * @param string $data
 * @param int $request_id
 * @see self::_get_sftp_packet()
 * @see self::send_channel_packet()
 * @return void
 */',
        'startLine' => 3344,
        'endLine' => 3364,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'reset_sftp' => 
      array (
        'name' => 'reset_sftp',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resets the SFTP channel for re-use
 */',
        'startLine' => 3369,
        'endLine' => 3375,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
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
        'startLine' => 3380,
        'endLine' => 3384,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'get_sftp_packet' => 
      array (
        'name' => 'get_sftp_packet',
        'parameters' => 
        array (
          'request_id' => 
          array (
            'name' => 'request_id',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3398,
                'endLine' => 3398,
                'startTokenPos' => 17160,
                'startFilePos' => 116655,
                'endTokenPos' => 17160,
                'endFilePos' => 116658,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3398,
            'endLine' => 3398,
            'startColumn' => 38,
            'endColumn' => 55,
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
 * Receives SFTP Packets
 *
 * See \'6. General Packet Format\' of draft-ietf-secsh-filexfer-13 for more info.
 *
 * Incidentally, the number of SSH_MSG_CHANNEL_DATA messages has no bearing on the number of SFTP packets present.
 * There can be one SSH_MSG_CHANNEL_DATA messages containing two SFTP packets or there can be two SSH_MSG_CHANNEL_DATA
 * messages containing one SFTP packet.
 *
 * @see self::_send_sftp_packet()
 * @return string
 */',
        'startLine' => 3398,
        'endLine' => 3485,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
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
            'startLine' => 3495,
            'endLine' => 3495,
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
            'startLine' => 3495,
            'endLine' => 3495,
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
        'startLine' => 3495,
        'endLine' => 3508,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'getSFTPLog' => 
      array (
        'name' => 'getSFTPLog',
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
 * Returns a string if NET_SFTP_LOGGING == self::LOG_COMPLEX, an array if NET_SFTP_LOGGING == self::LOG_SIMPLE and false if !defined(\'NET_SFTP_LOGGING\')
 *
 * @return array|string|false
 */',
        'startLine' => 3517,
        'endLine' => 3531,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'getSFTPErrors' => 
      array (
        'name' => 'getSFTPErrors',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns all errors on the SFTP layer
 *
 * @return array
 * @removed in phpseclib 4.0.0
 */',
        'startLine' => 3538,
        'endLine' => 3541,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'getLastSFTPError' => 
      array (
        'name' => 'getLastSFTPError',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the last error on the SFTP layer
 *
 * @return string
 * @removed in phpseclib 4.0.0
 */',
        'startLine' => 3549,
        'endLine' => 3552,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'getSupportedVersions' => 
      array (
        'name' => 'getSupportedVersions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get supported SFTP versions
 *
 * @return array
 */',
        'startLine' => 3559,
        'endLine' => 3574,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'getSupportedExtensions' => 
      array (
        'name' => 'getSupportedExtensions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get supported SFTP extensions
 *
 * @return array
 */',
        'startLine' => 3581,
        'endLine' => 3592,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'getNegotiatedVersion' => 
      array (
        'name' => 'getNegotiatedVersion',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get supported SFTP versions
 *
 * @return int|false
 */',
        'startLine' => 3599,
        'endLine' => 3606,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'setPreferredVersion' => 
      array (
        'name' => 'setPreferredVersion',
        'parameters' => 
        array (
          'version' => 
          array (
            'name' => 'version',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3617,
            'endLine' => 3617,
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
 * Set preferred version
 *
 * If you\'re preferred version isn\'t supported then the highest supported
 * version of SFTP will be utilized. Set to null or false or int(0) to
 * unset the preferred version
 *
 * @param int $version
 */',
        'startLine' => 3617,
        'endLine' => 3620,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
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
            'startLine' => 3628,
            'endLine' => 3628,
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
        'startLine' => 3628,
        'endLine' => 3632,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'enableDatePreservation' => 
      array (
        'name' => 'enableDatePreservation',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable Date Preservation
 */',
        'startLine' => 3637,
        'endLine' => 3640,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'disableDatePreservation' => 
      array (
        'name' => 'disableDatePreservation',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable Date Preservation
 */',
        'startLine' => 3645,
        'endLine' => 3648,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'copy' => 
      array (
        'name' => 'copy',
        'parameters' => 
        array (
          'oldname' => 
          array (
            'name' => 'oldname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3659,
            'endLine' => 3659,
            'startColumn' => 26,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'newname' => 
          array (
            'name' => 'newname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3659,
            'endLine' => 3659,
            'startColumn' => 36,
            'endColumn' => 43,
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
 * Copy
 *
 * This method (currently) only works if the copy-data extension is available
 *
 * @param string $oldname
 * @param string $newname
 * @return bool
 */',
        'startLine' => 3659,
        'endLine' => 3746,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'posix_rename' => 
      array (
        'name' => 'posix_rename',
        'parameters' => 
        array (
          'oldname' => 
          array (
            'name' => 'oldname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3760,
            'endLine' => 3760,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'newname' => 
          array (
            'name' => 'newname',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3760,
            'endLine' => 3760,
            'startColumn' => 44,
            'endColumn' => 51,
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
 * POSIX Rename
 *
 * Where rename() fails "if there already exists a file with the name specified by newpath"
 * (draft-ietf-secsh-filexfer-02#section-6.5), posix_rename() overwrites the existing file in an atomic fashion.
 * ie. "there is no observable instant in time where the name does not refer to either the old or the new file"
 * (draft-ietf-secsh-filexfer-13#page-39).
 *
 * @param string $oldname
 * @param string $newname
 * @return bool
 */',
        'startLine' => 3760,
        'endLine' => 3811,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'statvfs' => 
      array (
        'name' => 'statvfs',
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
            'startLine' => 3822,
            'endLine' => 3822,
            'startColumn' => 29,
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
 * Returns general information about a file system.
 *
 * The function statvfs() returns information about a mounted filesystem.
 * @see https://man7.org/linux/man-pages/man3/statvfs.3.html
 *
 * @param string $path
 * @return false|array{bsize: int, frsize: int, blocks: int, bfree: int, bavail: int, files: int, ffree: int, favail: int, fsid: int, flag: int, namemax: int}
 */',
        'startLine' => 3822,
        'endLine' => 3875,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
        'aliasName' => NULL,
      ),
      'hardlink' => 
      array (
        'name' => 'hardlink',
        'parameters' => 
        array (
          'oldpath' => 
          array (
            'name' => 'oldpath',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3877,
            'endLine' => 3877,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'newpath' => 
          array (
            'name' => 'newpath',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3877,
            'endLine' => 3877,
            'startColumn' => 40,
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
        'docComment' => NULL,
        'startLine' => 3877,
        'endLine' => 3924,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'phpseclib3\\Net',
        'declaringClassName' => 'phpseclib3\\Net\\SFTP',
        'implementingClassName' => 'phpseclib3\\Net\\SFTP',
        'currentClassName' => 'phpseclib3\\Net\\SFTP',
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