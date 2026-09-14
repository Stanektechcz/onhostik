<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-ziparchive
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-dev-master@709e512-8.3.33',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'ZipArchive',
        'filename' => 'phpstorm-stubs:zip/zip.stub',
        'extensionName' => 'zip',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'ZipArchive',
    'shortName' => 'ZipArchive',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A file archive, compressed with Zip.
 * @link https://php.net/manual/en/class.ziparchive.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 1543,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Countable',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'LIBZIP_VERSION' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'LIBZIP_VERSION',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.11.4\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 28,
            'startFilePos' => 364,
            'endTokenPos' => 28,
            'endFilePos' => 371,
          ),
        ),
        'docComment' => '/**
 * Zip library version
 * @link https://php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 9,
        'endColumn' => 47,
      ),
      'CREATE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CREATE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 41,
            'startFilePos' => 541,
            'endTokenPos' => 41,
            'endFilePos' => 541,
          ),
        ),
        'docComment' => '/**
 * Create the archive if it does not exist.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 9,
        'endColumn' => 32,
      ),
      'EXCL' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EXCL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 54,
            'startFilePos' => 701,
            'endTokenPos' => 54,
            'endFilePos' => 701,
          ),
        ),
        'docComment' => '/**
 * Error if archive already exists.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 9,
        'endColumn' => 30,
      ),
      'CHECKCONS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CHECKCONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 67,
            'startFilePos' => 911,
            'endTokenPos' => 67,
            'endFilePos' => 911,
          ),
        ),
        'docComment' => '/**
 * Perform additional consistency checks on the archive, and error if they fail.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'OVERWRITE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OVERWRITE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 80,
            'startFilePos' => 1138,
            'endTokenPos' => 80,
            'endFilePos' => 1138,
          ),
        ),
        'docComment' => '/**
 * Always start a new archive, this mode will overwrite the file if
 * it already exists.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'FL_NOCASE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_NOCASE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 93,
            'startFilePos' => 1297,
            'endTokenPos' => 93,
            'endFilePos' => 1297,
          ),
        ),
        'docComment' => '/**
 * Ignore case on name lookup
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'FL_NODIR' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_NODIR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 106,
            'startFilePos' => 1455,
            'endTokenPos' => 106,
            'endFilePos' => 1455,
          ),
        ),
        'docComment' => '/**
 * Ignore directory component
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'FL_COMPRESSED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_COMPRESSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 51,
            'endLine' => 51,
            'startTokenPos' => 119,
            'startFilePos' => 1612,
            'endTokenPos' => 119,
            'endFilePos' => 1612,
          ),
        ),
        'docComment' => '/**
 * Read compressed data
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'FL_UNCHANGED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_UNCHANGED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 56,
            'endLine' => 56,
            'startTokenPos' => 132,
            'startFilePos' => 1784,
            'endTokenPos' => 132,
            'endFilePos' => 1784,
          ),
        ),
        'docComment' => '/**
 * Use original data, ignoring changes.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'FL_RECOMPRESS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_RECOMPRESS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 143,
            'startFilePos' => 1824,
            'endTokenPos' => 143,
            'endFilePos' => 1825,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
      'FL_ENCRYPTED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_ENCRYPTED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '32',
          'attributes' => 
          array (
            'startLine' => 58,
            'endLine' => 58,
            'startTokenPos' => 154,
            'startFilePos' => 1864,
            'endTokenPos' => 154,
            'endFilePos' => 1865,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 58,
        'endLine' => 58,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'FL_OVERWRITE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_OVERWRITE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8192',
          'attributes' => 
          array (
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 165,
            'startFilePos' => 1904,
            'endTokenPos' => 165,
            'endFilePos' => 1907,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'FL_LOCAL' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_LOCAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '256',
          'attributes' => 
          array (
            'startLine' => 60,
            'endLine' => 60,
            'startTokenPos' => 176,
            'startFilePos' => 1942,
            'endTokenPos' => 176,
            'endFilePos' => 1944,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 60,
        'endLine' => 60,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'FL_CENTRAL' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_CENTRAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '512',
          'attributes' => 
          array (
            'startLine' => 61,
            'endLine' => 61,
            'startTokenPos' => 187,
            'startFilePos' => 1981,
            'endTokenPos' => 187,
            'endFilePos' => 1983,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'EM_TRAD_PKWARE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EM_TRAD_PKWARE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 198,
            'startFilePos' => 2024,
            'endTokenPos' => 198,
            'endFilePos' => 2024,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
      'EM_UNKNOWN' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EM_UNKNOWN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '65535',
          'attributes' => 
          array (
            'startLine' => 63,
            'endLine' => 63,
            'startTokenPos' => 209,
            'startFilePos' => 2061,
            'endTokenPos' => 209,
            'endFilePos' => 2065,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
      'CM_DEFAULT' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_DEFAULT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 68,
            'endLine' => 68,
            'startTokenPos' => 222,
            'startFilePos' => 2226,
            'endTokenPos' => 223,
            'endFilePos' => 2227,
          ),
        ),
        'docComment' => '/**
 * better of deflate or store.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 68,
        'endLine' => 68,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CM_STORE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_STORE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 73,
            'endLine' => 73,
            'startTokenPos' => 236,
            'startFilePos' => 2381,
            'endTokenPos' => 236,
            'endFilePos' => 2381,
          ),
        ),
        'docComment' => '/**
 * stored (uncompressed).
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'CM_SHRINK' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_SHRINK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 78,
            'endLine' => 78,
            'startTokenPos' => 249,
            'startFilePos' => 2520,
            'endTokenPos' => 249,
            'endFilePos' => 2520,
          ),
        ),
        'docComment' => '/**
 * shrunk
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'CM_REDUCE_1' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_REDUCE_1',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 83,
            'endLine' => 83,
            'startTokenPos' => 262,
            'startFilePos' => 2676,
            'endTokenPos' => 262,
            'endFilePos' => 2676,
          ),
        ),
        'docComment' => '/**
 * reduced with factor 1
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 83,
        'endLine' => 83,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CM_REDUCE_2' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_REDUCE_2',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 88,
            'endLine' => 88,
            'startTokenPos' => 275,
            'startFilePos' => 2832,
            'endTokenPos' => 275,
            'endFilePos' => 2832,
          ),
        ),
        'docComment' => '/**
 * reduced with factor 2
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 88,
        'endLine' => 88,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CM_REDUCE_3' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_REDUCE_3',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 93,
            'endLine' => 93,
            'startTokenPos' => 288,
            'startFilePos' => 2988,
            'endTokenPos' => 288,
            'endFilePos' => 2988,
          ),
        ),
        'docComment' => '/**
 * reduced with factor 3
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 93,
        'endLine' => 93,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CM_REDUCE_4' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_REDUCE_4',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 98,
            'endLine' => 98,
            'startTokenPos' => 301,
            'startFilePos' => 3144,
            'endTokenPos' => 301,
            'endFilePos' => 3144,
          ),
        ),
        'docComment' => '/**
 * reduced with factor 4
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 98,
        'endLine' => 98,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CM_IMPLODE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_IMPLODE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '6',
          'attributes' => 
          array (
            'startLine' => 103,
            'endLine' => 103,
            'startTokenPos' => 314,
            'startFilePos' => 3286,
            'endTokenPos' => 314,
            'endFilePos' => 3286,
          ),
        ),
        'docComment' => '/**
 * imploded
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'CM_DEFLATE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_DEFLATE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 108,
            'endLine' => 108,
            'startTokenPos' => 327,
            'startFilePos' => 3428,
            'endTokenPos' => 327,
            'endFilePos' => 3428,
          ),
        ),
        'docComment' => '/**
 * deflated
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 108,
        'endLine' => 108,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'CM_DEFLATE64' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_DEFLATE64',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '9',
          'attributes' => 
          array (
            'startLine' => 113,
            'endLine' => 113,
            'startTokenPos' => 340,
            'startFilePos' => 3573,
            'endTokenPos' => 340,
            'endFilePos' => 3573,
          ),
        ),
        'docComment' => '/**
 * deflate64
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 113,
        'endLine' => 113,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'CM_PKWARE_IMPLODE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_PKWARE_IMPLODE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '10',
          'attributes' => 
          array (
            'startLine' => 118,
            'endLine' => 118,
            'startTokenPos' => 353,
            'startFilePos' => 3730,
            'endTokenPos' => 353,
            'endFilePos' => 3731,
          ),
        ),
        'docComment' => '/**
 * PKWARE imploding
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 118,
        'endLine' => 118,
        'startColumn' => 9,
        'endColumn' => 44,
      ),
      'CM_BZIP2' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_BZIP2',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '12',
          'attributes' => 
          array (
            'startLine' => 123,
            'endLine' => 123,
            'startTokenPos' => 366,
            'startFilePos' => 3878,
            'endTokenPos' => 366,
            'endFilePos' => 3879,
          ),
        ),
        'docComment' => '/**
 * BZIP2 algorithm
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 123,
        'endLine' => 123,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'CM_LZMA' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_LZMA',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '14',
          'attributes' => 
          array (
            'startLine' => 124,
            'endLine' => 124,
            'startTokenPos' => 377,
            'startFilePos' => 3913,
            'endTokenPos' => 377,
            'endFilePos' => 3914,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 124,
        'endLine' => 124,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'CM_TERSE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_TERSE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '18',
          'attributes' => 
          array (
            'startLine' => 125,
            'endLine' => 125,
            'startTokenPos' => 388,
            'startFilePos' => 3949,
            'endTokenPos' => 388,
            'endFilePos' => 3950,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 125,
        'endLine' => 125,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'CM_LZ77' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_LZ77',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '19',
          'attributes' => 
          array (
            'startLine' => 126,
            'endLine' => 126,
            'startTokenPos' => 399,
            'startFilePos' => 3984,
            'endTokenPos' => 399,
            'endFilePos' => 3985,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 126,
        'endLine' => 126,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'CM_WAVPACK' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_WAVPACK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '97',
          'attributes' => 
          array (
            'startLine' => 127,
            'endLine' => 127,
            'startTokenPos' => 410,
            'startFilePos' => 4022,
            'endTokenPos' => 410,
            'endFilePos' => 4023,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 127,
        'endLine' => 127,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'CM_PPMD' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_PPMD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '98',
          'attributes' => 
          array (
            'startLine' => 128,
            'endLine' => 128,
            'startTokenPos' => 421,
            'startFilePos' => 4057,
            'endTokenPos' => 421,
            'endFilePos' => 4058,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 128,
        'endLine' => 128,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_OK' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_OK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 133,
            'endLine' => 133,
            'startTokenPos' => 434,
            'startFilePos' => 4196,
            'endTokenPos' => 434,
            'endFilePos' => 4196,
          ),
        ),
        'docComment' => '/**
 * No error.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 133,
        'endLine' => 133,
        'startColumn' => 9,
        'endColumn' => 31,
      ),
      'ER_MULTIDISK' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_MULTIDISK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 138,
            'endLine' => 138,
            'startTokenPos' => 447,
            'startFilePos' => 4370,
            'endTokenPos' => 447,
            'endFilePos' => 4370,
          ),
        ),
        'docComment' => '/**
 * Multi-disk zip archives not supported.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 138,
        'endLine' => 138,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'ER_RENAME' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_RENAME',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 143,
            'endLine' => 143,
            'startTokenPos' => 460,
            'startFilePos' => 4534,
            'endTokenPos' => 460,
            'endFilePos' => 4534,
          ),
        ),
        'docComment' => '/**
 * Renaming temporary file failed.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 143,
        'endLine' => 143,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'ER_CLOSE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_CLOSE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 148,
            'endLine' => 148,
            'startTokenPos' => 473,
            'startFilePos' => 4692,
            'endTokenPos' => 473,
            'endFilePos' => 4692,
          ),
        ),
        'docComment' => '/**
 * Closing zip archive failed
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 148,
        'endLine' => 148,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_SEEK' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_SEEK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 153,
            'endLine' => 153,
            'startTokenPos' => 486,
            'startFilePos' => 4833,
            'endTokenPos' => 486,
            'endFilePos' => 4833,
          ),
        ),
        'docComment' => '/**
 * Seek error
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 153,
        'endLine' => 153,
        'startColumn' => 9,
        'endColumn' => 33,
      ),
      'ER_READ' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_READ',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 158,
            'endLine' => 158,
            'startTokenPos' => 499,
            'startFilePos' => 4974,
            'endTokenPos' => 499,
            'endFilePos' => 4974,
          ),
        ),
        'docComment' => '/**
 * Read error
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 158,
        'endLine' => 158,
        'startColumn' => 9,
        'endColumn' => 33,
      ),
      'ER_WRITE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_WRITE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '6',
          'attributes' => 
          array (
            'startLine' => 163,
            'endLine' => 163,
            'startTokenPos' => 512,
            'startFilePos' => 5117,
            'endTokenPos' => 512,
            'endFilePos' => 5117,
          ),
        ),
        'docComment' => '/**
 * Write error
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 163,
        'endLine' => 163,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_CRC' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_CRC',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '7',
          'attributes' => 
          array (
            'startLine' => 168,
            'endLine' => 168,
            'startTokenPos' => 525,
            'startFilePos' => 5256,
            'endTokenPos' => 525,
            'endFilePos' => 5256,
          ),
        ),
        'docComment' => '/**
 * CRC error
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 168,
        'endLine' => 168,
        'startColumn' => 9,
        'endColumn' => 32,
      ),
      'ER_ZIPCLOSED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_ZIPCLOSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 173,
            'endLine' => 173,
            'startTokenPos' => 538,
            'startFilePos' => 5425,
            'endTokenPos' => 538,
            'endFilePos' => 5425,
          ),
        ),
        'docComment' => '/**
 * Containing zip archive was closed
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 173,
        'endLine' => 173,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'ER_NOENT' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_NOENT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '9',
          'attributes' => 
          array (
            'startLine' => 178,
            'endLine' => 178,
            'startTokenPos' => 551,
            'startFilePos' => 5570,
            'endTokenPos' => 551,
            'endFilePos' => 5570,
          ),
        ),
        'docComment' => '/**
 * No such file.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 178,
        'endLine' => 178,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_EXISTS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_EXISTS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '10',
          'attributes' => 
          array (
            'startLine' => 183,
            'endLine' => 183,
            'startTokenPos' => 564,
            'startFilePos' => 5722,
            'endTokenPos' => 564,
            'endFilePos' => 5723,
          ),
        ),
        'docComment' => '/**
 * File already exists
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 183,
        'endLine' => 183,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'ER_OPEN' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_OPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '11',
          'attributes' => 
          array (
            'startLine' => 188,
            'endLine' => 188,
            'startTokenPos' => 577,
            'startFilePos' => 5869,
            'endTokenPos' => 577,
            'endFilePos' => 5870,
          ),
        ),
        'docComment' => '/**
 * Can\'t open file
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 188,
        'endLine' => 188,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_TMPOPEN' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_TMPOPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '12',
          'attributes' => 
          array (
            'startLine' => 193,
            'endLine' => 193,
            'startTokenPos' => 590,
            'startFilePos' => 6037,
            'endTokenPos' => 590,
            'endFilePos' => 6038,
          ),
        ),
        'docComment' => '/**
 * Failure to create temporary file.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 193,
        'endLine' => 193,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'ER_ZLIB' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_ZLIB',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '13',
          'attributes' => 
          array (
            'startLine' => 198,
            'endLine' => 198,
            'startTokenPos' => 603,
            'startFilePos' => 6179,
            'endTokenPos' => 603,
            'endFilePos' => 6180,
          ),
        ),
        'docComment' => '/**
 * Zlib error
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 198,
        'endLine' => 198,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_MEMORY' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_MEMORY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '14',
          'attributes' => 
          array (
            'startLine' => 203,
            'endLine' => 203,
            'startTokenPos' => 616,
            'startFilePos' => 6338,
            'endTokenPos' => 616,
            'endFilePos' => 6339,
          ),
        ),
        'docComment' => '/**
 * Memory allocation failure
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 203,
        'endLine' => 203,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'ER_CHANGED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_CHANGED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '15',
          'attributes' => 
          array (
            'startLine' => 208,
            'endLine' => 208,
            'startTokenPos' => 629,
            'startFilePos' => 6495,
            'endTokenPos' => 629,
            'endFilePos' => 6496,
          ),
        ),
        'docComment' => '/**
 * Entry has been changed
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 208,
        'endLine' => 208,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'ER_COMPNOTSUPP' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_COMPNOTSUPP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 213,
            'endLine' => 213,
            'startTokenPos' => 642,
            'startFilePos' => 6667,
            'endTokenPos' => 642,
            'endFilePos' => 6668,
          ),
        ),
        'docComment' => '/**
 * Compression method not supported.
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 213,
        'endLine' => 213,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'ER_EOF' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_EOF',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '17',
          'attributes' => 
          array (
            'startLine' => 218,
            'endLine' => 218,
            'startTokenPos' => 655,
            'startFilePos' => 6811,
            'endTokenPos' => 655,
            'endFilePos' => 6812,
          ),
        ),
        'docComment' => '/**
 * Premature EOF
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 218,
        'endLine' => 218,
        'startColumn' => 9,
        'endColumn' => 33,
      ),
      'ER_INVAL' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_INVAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '18',
          'attributes' => 
          array (
            'startLine' => 223,
            'endLine' => 223,
            'startTokenPos' => 668,
            'startFilePos' => 6960,
            'endTokenPos' => 668,
            'endFilePos' => 6961,
          ),
        ),
        'docComment' => '/**
 * Invalid argument
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 223,
        'endLine' => 223,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'ER_NOZIP' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_NOZIP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '19',
          'attributes' => 
          array (
            'startLine' => 228,
            'endLine' => 228,
            'startTokenPos' => 681,
            'startFilePos' => 7110,
            'endTokenPos' => 681,
            'endFilePos' => 7111,
          ),
        ),
        'docComment' => '/**
 * Not a zip archive
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 228,
        'endLine' => 228,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'ER_INTERNAL' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_INTERNAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '20',
          'attributes' => 
          array (
            'startLine' => 233,
            'endLine' => 233,
            'startTokenPos' => 694,
            'startFilePos' => 7260,
            'endTokenPos' => 694,
            'endFilePos' => 7261,
          ),
        ),
        'docComment' => '/**
 * Internal error
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 233,
        'endLine' => 233,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'ER_INCONS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_INCONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '21',
          'attributes' => 
          array (
            'startLine' => 238,
            'endLine' => 238,
            'startTokenPos' => 707,
            'startFilePos' => 7418,
            'endTokenPos' => 707,
            'endFilePos' => 7419,
          ),
        ),
        'docComment' => '/**
 * Zip archive inconsistent
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 238,
        'endLine' => 238,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'ER_REMOVE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_REMOVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '22',
          'attributes' => 
          array (
            'startLine' => 243,
            'endLine' => 243,
            'startTokenPos' => 720,
            'startFilePos' => 7569,
            'endTokenPos' => 720,
            'endFilePos' => 7570,
          ),
        ),
        'docComment' => '/**
 * Can\'t remove file
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 243,
        'endLine' => 243,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'ER_DELETED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_DELETED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '23',
          'attributes' => 
          array (
            'startLine' => 248,
            'endLine' => 248,
            'startTokenPos' => 733,
            'startFilePos' => 7726,
            'endTokenPos' => 733,
            'endFilePos' => 7727,
          ),
        ),
        'docComment' => '/**
 * Entry has been deleted
 * @link https://php.net/manual/en/zip.constants.php
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 248,
        'endLine' => 248,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'EM_NONE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EM_NONE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 254,
            'endLine' => 254,
            'startTokenPos' => 746,
            'startFilePos' => 7900,
            'endTokenPos' => 746,
            'endFilePos' => 7900,
          ),
        ),
        'docComment' => '/**
 * No encryption
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.2
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 254,
        'endLine' => 254,
        'startColumn' => 9,
        'endColumn' => 33,
      ),
      'EM_AES_128' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EM_AES_128',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '257',
          'attributes' => 
          array (
            'startLine' => 260,
            'endLine' => 260,
            'startTokenPos' => 759,
            'startFilePos' => 8081,
            'endTokenPos' => 759,
            'endFilePos' => 8083,
          ),
        ),
        'docComment' => '/**
 * AES 128 encryption
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.2
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 260,
        'endLine' => 260,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'EM_AES_192' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EM_AES_192',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '258',
          'attributes' => 
          array (
            'startLine' => 266,
            'endLine' => 266,
            'startTokenPos' => 772,
            'startFilePos' => 8264,
            'endTokenPos' => 772,
            'endFilePos' => 8266,
          ),
        ),
        'docComment' => '/**
 * AES 192 encryption
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.2
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 266,
        'endLine' => 266,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'EM_AES_256' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'EM_AES_256',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '259',
          'attributes' => 
          array (
            'startLine' => 272,
            'endLine' => 272,
            'startTokenPos' => 785,
            'startFilePos' => 8447,
            'endTokenPos' => 785,
            'endFilePos' => 8449,
          ),
        ),
        'docComment' => '/**
 * AES 256 encryption
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.2
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 272,
        'endLine' => 272,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'RDONLY' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'RDONLY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 278,
            'endLine' => 278,
            'startTokenPos' => 798,
            'startFilePos' => 8638,
            'endTokenPos' => 798,
            'endFilePos' => 8639,
          ),
        ),
        'docComment' => '/**
 * Open archive in read only mode
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 278,
        'endLine' => 278,
        'startColumn' => 9,
        'endColumn' => 33,
      ),
      'FL_ENC_GUESS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_ENC_GUESS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 284,
            'endLine' => 284,
            'startTokenPos' => 811,
            'startFilePos' => 8838,
            'endTokenPos' => 811,
            'endFilePos' => 8838,
          ),
        ),
        'docComment' => '/**
 * Guess string encoding (is default)
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.0
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 284,
        'endLine' => 284,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'FL_ENC_RAW' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_ENC_RAW',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '64',
          'attributes' => 
          array (
            'startLine' => 290,
            'endLine' => 290,
            'startTokenPos' => 824,
            'startFilePos' => 9022,
            'endTokenPos' => 824,
            'endFilePos' => 9023,
          ),
        ),
        'docComment' => '/**
 * Get unmodified string
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.0
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 290,
        'endLine' => 290,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'FL_ENC_STRICT' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_ENC_STRICT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '128',
          'attributes' => 
          array (
            'startLine' => 296,
            'endLine' => 296,
            'startTokenPos' => 837,
            'startFilePos' => 9218,
            'endTokenPos' => 837,
            'endFilePos' => 9220,
          ),
        ),
        'docComment' => '/**
 * Follow specification strictly
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.0
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 296,
        'endLine' => 296,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'FL_ENC_UTF_8' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_ENC_UTF_8',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2048',
          'attributes' => 
          array (
            'startLine' => 302,
            'endLine' => 302,
            'startTokenPos' => 850,
            'startFilePos' => 9408,
            'endTokenPos' => 850,
            'endFilePos' => 9411,
          ),
        ),
        'docComment' => '/**
 * String is UTF-8 encoded
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.0
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 302,
        'endLine' => 302,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'FL_ENC_CP437' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_ENC_CP437',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4096',
          'attributes' => 
          array (
            'startLine' => 308,
            'endLine' => 308,
            'startTokenPos' => 863,
            'startFilePos' => 9599,
            'endTokenPos' => 863,
            'endFilePos' => 9602,
          ),
        ),
        'docComment' => '/**
 * String is CP437 encoded
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.0
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 308,
        'endLine' => 308,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'CM_LZMA2' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_LZMA2',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '33',
          'attributes' => 
          array (
            'startLine' => 314,
            'endLine' => 314,
            'startTokenPos' => 876,
            'startFilePos' => 9778,
            'endTokenPos' => 876,
            'endFilePos' => 9779,
          ),
        ),
        'docComment' => '/**
 * LZMA2 algorithm
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 314,
        'endLine' => 314,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'CM_XZ' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_XZ',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '95',
          'attributes' => 
          array (
            'startLine' => 320,
            'endLine' => 320,
            'startTokenPos' => 889,
            'startFilePos' => 9949,
            'endTokenPos' => 889,
            'endFilePos' => 9950,
          ),
        ),
        'docComment' => '/**
 * XZ algorithm
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 320,
        'endLine' => 320,
        'startColumn' => 9,
        'endColumn' => 32,
      ),
      'ER_ENCRNOTSUPP' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_ENCRNOTSUPP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '24',
          'attributes' => 
          array (
            'startLine' => 326,
            'endLine' => 326,
            'startTokenPos' => 902,
            'startFilePos' => 10146,
            'endTokenPos' => 902,
            'endFilePos' => 10147,
          ),
        ),
        'docComment' => '/**
 * Encryption method not support
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 326,
        'endLine' => 326,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'ER_RDONLY' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_RDONLY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '25',
          'attributes' => 
          array (
            'startLine' => 332,
            'endLine' => 332,
            'startTokenPos' => 915,
            'startFilePos' => 10326,
            'endTokenPos' => 915,
            'endFilePos' => 10327,
          ),
        ),
        'docComment' => '/**
 * Read-only archive
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 332,
        'endLine' => 332,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'ER_NOPASSWD' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_NOPASSWD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '26',
          'attributes' => 
          array (
            'startLine' => 338,
            'endLine' => 338,
            'startTokenPos' => 928,
            'startFilePos' => 10511,
            'endTokenPos' => 928,
            'endFilePos' => 10512,
          ),
        ),
        'docComment' => '/**
 * No password provided
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 338,
        'endLine' => 338,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'ER_WRONGPASSWD' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_WRONGPASSWD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '27',
          'attributes' => 
          array (
            'startLine' => 344,
            'endLine' => 344,
            'startTokenPos' => 941,
            'startFilePos' => 10702,
            'endTokenPos' => 941,
            'endFilePos' => 10703,
          ),
        ),
        'docComment' => '/**
 * Wrong password provided
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 344,
        'endLine' => 344,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'ER_OPNOTSUPP' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_OPNOTSUPP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '28',
          'attributes' => 
          array (
            'startLine' => 350,
            'endLine' => 350,
            'startTokenPos' => 954,
            'startFilePos' => 10891,
            'endTokenPos' => 954,
            'endFilePos' => 10892,
          ),
        ),
        'docComment' => '/**
 * Operation not supported
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 350,
        'endLine' => 350,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'ER_INUSE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_INUSE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '29',
          'attributes' => 
          array (
            'startLine' => 356,
            'endLine' => 356,
            'startTokenPos' => 967,
            'startFilePos' => 11074,
            'endTokenPos' => 967,
            'endFilePos' => 11075,
          ),
        ),
        'docComment' => '/**
 * Resource still in use
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 356,
        'endLine' => 356,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'ER_TELL' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_TELL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '30',
          'attributes' => 
          array (
            'startLine' => 362,
            'endLine' => 362,
            'startTokenPos' => 980,
            'startFilePos' => 11245,
            'endTokenPos' => 980,
            'endFilePos' => 11246,
          ),
        ),
        'docComment' => '/**
 * Tell error
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 362,
        'endLine' => 362,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_COMPRESSED_DATA' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_COMPRESSED_DATA',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '31',
          'attributes' => 
          array (
            'startLine' => 368,
            'endLine' => 368,
            'startTokenPos' => 993,
            'startFilePos' => 11440,
            'endTokenPos' => 993,
            'endFilePos' => 11441,
          ),
        ),
        'docComment' => '/**
 * Compressed data invalid
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 368,
        'endLine' => 368,
        'startColumn' => 9,
        'endColumn' => 45,
      ),
      'ER_CANCELLED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '32',
          'attributes' => 
          array (
            'startLine' => 374,
            'endLine' => 374,
            'startTokenPos' => 1006,
            'startFilePos' => 11625,
            'endTokenPos' => 1006,
            'endFilePos' => 11626,
          ),
        ),
        'docComment' => '/**
 * Operation cancelled
 * @link https://secure.php.net/manual/en/zip.constants.php
 * @since 7.4
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 374,
        'endLine' => 374,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'OPSYS_DOS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_DOS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 379,
            'endLine' => 379,
            'startTokenPos' => 1019,
            'startFilePos' => 11808,
            'endTokenPos' => 1019,
            'endFilePos' => 11808,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 379,
        'endLine' => 379,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'OPSYS_AMIGA' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_AMIGA',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 384,
            'endLine' => 384,
            'startTokenPos' => 1032,
            'startFilePos' => 11992,
            'endTokenPos' => 1032,
            'endFilePos' => 11992,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 384,
        'endLine' => 384,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'OPSYS_OPENVMS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_OPENVMS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 389,
            'endLine' => 389,
            'startTokenPos' => 1045,
            'startFilePos' => 12178,
            'endTokenPos' => 1045,
            'endFilePos' => 12178,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 389,
        'endLine' => 389,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'OPSYS_UNIX' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_UNIX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 394,
            'endLine' => 394,
            'startTokenPos' => 1058,
            'startFilePos' => 12361,
            'endTokenPos' => 1058,
            'endFilePos' => 12361,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 394,
        'endLine' => 394,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'OPSYS_VM_CMS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_VM_CMS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 399,
            'endLine' => 399,
            'startTokenPos' => 1071,
            'startFilePos' => 12546,
            'endTokenPos' => 1071,
            'endFilePos' => 12546,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 399,
        'endLine' => 399,
        'startColumn' => 9,
        'endColumn' => 38,
      ),
      'OPSYS_ATARI_ST' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_ATARI_ST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 404,
            'endLine' => 404,
            'startTokenPos' => 1084,
            'startFilePos' => 12733,
            'endTokenPos' => 1084,
            'endFilePos' => 12733,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 404,
        'endLine' => 404,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
      'OPSYS_OS_2' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_OS_2',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '6',
          'attributes' => 
          array (
            'startLine' => 409,
            'endLine' => 409,
            'startTokenPos' => 1097,
            'startFilePos' => 12916,
            'endTokenPos' => 1097,
            'endFilePos' => 12916,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 409,
        'endLine' => 409,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'OPSYS_MACINTOSH' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_MACINTOSH',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '7',
          'attributes' => 
          array (
            'startLine' => 414,
            'endLine' => 414,
            'startTokenPos' => 1110,
            'startFilePos' => 13104,
            'endTokenPos' => 1110,
            'endFilePos' => 13104,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 414,
        'endLine' => 414,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'OPSYS_Z_SYSTEM' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_Z_SYSTEM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 419,
            'endLine' => 419,
            'startTokenPos' => 1123,
            'startFilePos' => 13291,
            'endTokenPos' => 1123,
            'endFilePos' => 13291,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 419,
        'endLine' => 419,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
      'OPSYS_WINDOWS_NTFS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_WINDOWS_NTFS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '10',
          'attributes' => 
          array (
            'startLine' => 424,
            'endLine' => 424,
            'startTokenPos' => 1136,
            'startFilePos' => 13482,
            'endTokenPos' => 1136,
            'endFilePos' => 13483,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 424,
        'endLine' => 424,
        'startColumn' => 9,
        'endColumn' => 45,
      ),
      'OPSYS_MVS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_MVS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '11',
          'attributes' => 
          array (
            'startLine' => 429,
            'endLine' => 429,
            'startTokenPos' => 1149,
            'startFilePos' => 13665,
            'endTokenPos' => 1149,
            'endFilePos' => 13666,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 429,
        'endLine' => 429,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'OPSYS_VSE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_VSE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '12',
          'attributes' => 
          array (
            'startLine' => 434,
            'endLine' => 434,
            'startTokenPos' => 1162,
            'startFilePos' => 13848,
            'endTokenPos' => 1162,
            'endFilePos' => 13849,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 434,
        'endLine' => 434,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'OPSYS_ACORN_RISC' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_ACORN_RISC',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '13',
          'attributes' => 
          array (
            'startLine' => 439,
            'endLine' => 439,
            'startTokenPos' => 1175,
            'startFilePos' => 14038,
            'endTokenPos' => 1175,
            'endFilePos' => 14039,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 439,
        'endLine' => 439,
        'startColumn' => 9,
        'endColumn' => 43,
      ),
      'OPSYS_VFAT' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_VFAT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '14',
          'attributes' => 
          array (
            'startLine' => 444,
            'endLine' => 444,
            'startTokenPos' => 1188,
            'startFilePos' => 14222,
            'endTokenPos' => 1188,
            'endFilePos' => 14223,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 444,
        'endLine' => 444,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'OPSYS_ALTERNATE_MVS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_ALTERNATE_MVS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '15',
          'attributes' => 
          array (
            'startLine' => 449,
            'endLine' => 449,
            'startTokenPos' => 1201,
            'startFilePos' => 14415,
            'endTokenPos' => 1201,
            'endFilePos' => 14416,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 449,
        'endLine' => 449,
        'startColumn' => 9,
        'endColumn' => 46,
      ),
      'OPSYS_BEOS' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_BEOS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 454,
            'endLine' => 454,
            'startTokenPos' => 1214,
            'startFilePos' => 14599,
            'endTokenPos' => 1214,
            'endFilePos' => 14600,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 454,
        'endLine' => 454,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'OPSYS_TANDEM' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_TANDEM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '17',
          'attributes' => 
          array (
            'startLine' => 459,
            'endLine' => 459,
            'startTokenPos' => 1227,
            'startFilePos' => 14785,
            'endTokenPos' => 1227,
            'endFilePos' => 14786,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 459,
        'endLine' => 459,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'OPSYS_OS_400' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_OS_400',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '18',
          'attributes' => 
          array (
            'startLine' => 464,
            'endLine' => 464,
            'startTokenPos' => 1240,
            'startFilePos' => 14971,
            'endTokenPos' => 1240,
            'endFilePos' => 14972,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 464,
        'endLine' => 464,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'OPSYS_OS_X' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_OS_X',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '19',
          'attributes' => 
          array (
            'startLine' => 469,
            'endLine' => 469,
            'startTokenPos' => 1253,
            'startFilePos' => 15155,
            'endTokenPos' => 1253,
            'endFilePos' => 15156,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 469,
        'endLine' => 469,
        'startColumn' => 9,
        'endColumn' => 37,
      ),
      'OPSYS_CPM' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_CPM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '9',
          'attributes' => 
          array (
            'startLine' => 473,
            'endLine' => 473,
            'startTokenPos' => 1266,
            'startFilePos' => 15316,
            'endTokenPos' => 1266,
            'endFilePos' => 15316,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 473,
        'endLine' => 473,
        'startColumn' => 9,
        'endColumn' => 35,
      ),
      'OPSYS_DEFAULT' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'OPSYS_DEFAULT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 478,
            'endLine' => 478,
            'startTokenPos' => 1279,
            'startFilePos' => 15502,
            'endTokenPos' => 1279,
            'endFilePos' => 15502,
          ),
        ),
        'docComment' => '/**
 * @link https://www.php.net/manual/en/zip.constants.php#ziparchive.constants.opsys.default
 * @since 5.6
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 478,
        'endLine' => 478,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'FL_OPEN_FILE_NOW' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'FL_OPEN_FILE_NOW',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1073741824',
          'attributes' => 
          array (
            'startLine' => 479,
            'endLine' => 479,
            'startTokenPos' => 1290,
            'startFilePos' => 15545,
            'endTokenPos' => 1290,
            'endFilePos' => 15554,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 479,
        'endLine' => 479,
        'startColumn' => 9,
        'endColumn' => 51,
      ),
      'CM_ZSTD' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'CM_ZSTD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '93',
          'attributes' => 
          array (
            'startLine' => 480,
            'endLine' => 480,
            'startTokenPos' => 1301,
            'startFilePos' => 15588,
            'endTokenPos' => 1301,
            'endFilePos' => 15589,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 480,
        'endLine' => 480,
        'startColumn' => 9,
        'endColumn' => 34,
      ),
      'ER_DATA_LENGTH' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_DATA_LENGTH',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '33',
          'attributes' => 
          array (
            'startLine' => 481,
            'endLine' => 481,
            'startTokenPos' => 1312,
            'startFilePos' => 15630,
            'endTokenPos' => 1312,
            'endFilePos' => 15631,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 481,
        'endLine' => 481,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'ER_NOT_ALLOWED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'ER_NOT_ALLOWED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '34',
          'attributes' => 
          array (
            'startLine' => 482,
            'endLine' => 482,
            'startTokenPos' => 1323,
            'startFilePos' => 15672,
            'endTokenPos' => 1323,
            'endFilePos' => 15673,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 482,
        'endLine' => 482,
        'startColumn' => 9,
        'endColumn' => 41,
      ),
      'AFL_RDONLY' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'AFL_RDONLY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 483,
            'endLine' => 483,
            'startTokenPos' => 1334,
            'startFilePos' => 15710,
            'endTokenPos' => 1334,
            'endFilePos' => 15710,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 483,
        'endLine' => 483,
        'startColumn' => 9,
        'endColumn' => 36,
      ),
      'AFL_IS_TORRENTZIP' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'AFL_IS_TORRENTZIP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 484,
            'endLine' => 484,
            'startTokenPos' => 1345,
            'startFilePos' => 15754,
            'endTokenPos' => 1345,
            'endFilePos' => 15754,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 484,
        'endLine' => 484,
        'startColumn' => 9,
        'endColumn' => 43,
      ),
      'AFL_WANT_TORRENTZIP' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'AFL_WANT_TORRENTZIP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 485,
            'endLine' => 485,
            'startTokenPos' => 1356,
            'startFilePos' => 15800,
            'endTokenPos' => 1356,
            'endFilePos' => 15800,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 485,
        'endLine' => 485,
        'startColumn' => 9,
        'endColumn' => 45,
      ),
      'AFL_CREATE_OR_KEEP_FILE_FOR_EMPTY_ARCHIVE' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'AFL_CREATE_OR_KEEP_FILE_FOR_EMPTY_ARCHIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 486,
            'endLine' => 486,
            'startTokenPos' => 1367,
            'startFilePos' => 15868,
            'endTokenPos' => 1367,
            'endFilePos' => 15869,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 486,
        'endLine' => 486,
        'startColumn' => 9,
        'endColumn' => 68,
      ),
      'LENGTH_TO_END' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'LENGTH_TO_END',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 487,
            'endLine' => 487,
            'startTokenPos' => 1378,
            'startFilePos' => 15909,
            'endTokenPos' => 1378,
            'endFilePos' => 15909,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 487,
        'endLine' => 487,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'LENGTH_UNCHECKED' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'LENGTH_UNCHECKED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '-2',
          'attributes' => 
          array (
            'startLine' => 488,
            'endLine' => 488,
            'startTokenPos' => 1389,
            'startFilePos' => 15952,
            'endTokenPos' => 1390,
            'endFilePos' => 15953,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 488,
        'endLine' => 488,
        'startColumn' => 9,
        'endColumn' => 43,
      ),
    ),
    'immediateProperties' => 
    array (
      'status' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'status',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Status of the Zip Archive
 * @var int
 */',
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.1\' => \'int\']',
                'attributes' => 
                array (
                  'startLine' => 493,
                  'endLine' => 493,
                  'startTokenPos' => 1398,
                  'startFilePos' => 16099,
                  'endTokenPos' => 1404,
                  'endFilePos' => 16114,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 493,
                  'endLine' => 493,
                  'startTokenPos' => 1410,
                  'startFilePos' => 16126,
                  'endTokenPos' => 1410,
                  'endFilePos' => 16127,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 493,
        'endLine' => 494,
        'startColumn' => 9,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'statusSys' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'statusSys',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * System status of the Zip Archive
 * @var int
 */',
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.1\' => \'int\']',
                'attributes' => 
                array (
                  'startLine' => 499,
                  'endLine' => 499,
                  'startTokenPos' => 1426,
                  'startFilePos' => 16309,
                  'endTokenPos' => 1432,
                  'endFilePos' => 16324,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 499,
                  'endLine' => 499,
                  'startTokenPos' => 1438,
                  'startFilePos' => 16336,
                  'endTokenPos' => 1438,
                  'endFilePos' => 16337,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 499,
        'endLine' => 500,
        'startColumn' => 9,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'numFiles' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'numFiles',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Number of files in archive
 * @var int
 */',
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.1\' => \'int\']',
                'attributes' => 
                array (
                  'startLine' => 505,
                  'endLine' => 505,
                  'startTokenPos' => 1454,
                  'startFilePos' => 16516,
                  'endTokenPos' => 1460,
                  'endFilePos' => 16531,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 505,
                  'endLine' => 505,
                  'startTokenPos' => 1466,
                  'startFilePos' => 16543,
                  'endTokenPos' => 1466,
                  'endFilePos' => 16544,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 505,
        'endLine' => 506,
        'startColumn' => 9,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'filename' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'filename',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * File name in the file system
 * @var string
 */',
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.1\' => \'string\']',
                'attributes' => 
                array (
                  'startLine' => 511,
                  'endLine' => 511,
                  'startTokenPos' => 1482,
                  'startFilePos' => 16727,
                  'endTokenPos' => 1488,
                  'endFilePos' => 16745,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 511,
                  'endLine' => 511,
                  'startTokenPos' => 1494,
                  'startFilePos' => 16757,
                  'endTokenPos' => 1494,
                  'endFilePos' => 16758,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 511,
        'endLine' => 512,
        'startColumn' => 9,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'comment' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'comment',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Comment for the archive
 * @var string
 */',
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.1\' => \'string\']',
                'attributes' => 
                array (
                  'startLine' => 517,
                  'endLine' => 517,
                  'startTokenPos' => 1510,
                  'startFilePos' => 16939,
                  'endTokenPos' => 1516,
                  'endFilePos' => 16957,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 517,
                  'endLine' => 517,
                  'startTokenPos' => 1522,
                  'startFilePos' => 16969,
                  'endTokenPos' => 1522,
                  'endFilePos' => 16970,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 517,
        'endLine' => 518,
        'startColumn' => 9,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lastId' => 
      array (
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'name' => 'lastId',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * @var int
 */',
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.1\' => \'int\']',
                'attributes' => 
                array (
                  'startLine' => 522,
                  'endLine' => 522,
                  'startTokenPos' => 1538,
                  'startFilePos' => 17112,
                  'endTokenPos' => 1544,
                  'endFilePos' => 17127,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 522,
                  'endLine' => 522,
                  'startTokenPos' => 1550,
                  'startFilePos' => 17139,
                  'endTokenPos' => 1550,
                  'endFilePos' => 17140,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 522,
        'endLine' => 523,
        'startColumn' => 9,
        'endColumn' => 27,
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
      'open' => 
      array (
        'name' => 'open',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 602,
                      'endLine' => 602,
                      'startTokenPos' => 1577,
                      'startFilePos' => 19195,
                      'endTokenPos' => 1583,
                      'endFilePos' => 19213,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 602,
                      'endLine' => 602,
                      'startTokenPos' => 1589,
                      'startFilePos' => 19225,
                      'endTokenPos' => 1589,
                      'endFilePos' => 19226,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 602,
            'endLine' => 603,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 605,
                'endLine' => 605,
                'startTokenPos' => 1623,
                'startFilePos' => 19383,
                'endTokenPos' => 1623,
                'endFilePos' => 19386,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 604,
                      'endLine' => 604,
                      'startTokenPos' => 1601,
                      'startFilePos' => 19326,
                      'endTokenPos' => 1607,
                      'endFilePos' => 19341,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 604,
                      'endLine' => 604,
                      'startTokenPos' => 1613,
                      'startFilePos' => 19353,
                      'endTokenPos' => 1613,
                      'endFilePos' => 19354,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 604,
            'endLine' => 605,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'bool',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Open a ZIP file archive
 *
 * @link https://php.net/manual/en/ziparchive.open.php
 *
 * @param string $filename <p>
 * The file name of the ZIP archive to open.
 * </p>
 * @param int $flags [optional] <p>
 * The mode to use to open the archive.
 * </p>
 * <p>
 * <b>ZipArchive::OVERWRITE</b>
 * </p>
 *
 * @return int|bool <i>Error codes</i>
 * <p>
 * Returns <b>TRUE</b> on success, <b>FALSE</b> or the error code on error.
 * </p>
 * <p>
 * <b>ZipArchive::ER_EXISTS</b>
 * </p>
 * <p>
 * File already exists.
 * </p>
 * <p>
 * <b>ZipArchive::ER_INCONS</b>
 * </p>
 * <p>
 * Zip archive inconsistent.
 * </p>
 * <p>
 * <b>ZipArchive::ER_INVAL</b>
 * </p>
 * <p>
 * Invalid argument.
 * </p>
 * <p>
 * <b>ZipArchive::ER_MEMORY</b>
 * </p>
 * <p>
 * Malloc failure.
 * </p>
 * <p>
 * <b>ZipArchive::ER_NOENT</b>
 * </p>
 * <p>
 * No such file.
 * </p>
 * <p>
 * <b>ZipArchive::ER_NOZIP</b>
 * </p>
 * <p>
 * Not a zip archive.
 * </p>
 * <p>
 * <b>ZipArchive::ER_OPEN</b>
 * </p>
 * <p>
 * Can\'t open file.
 * </p>
 * <p>
 * <b>ZipArchive::ER_READ</b>
 * </p>
 * <p>
 * Read error.
 * </p>
 * <p>
 * <b>ZipArchive::ER_SEEK</b>
 * </p>
 * <p>
 * Seek error.
 * </p>
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 600,
        'endLine' => 608,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'close' => 
      array (
        'name' => 'close',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Close the active archive (opened or newly created)
 * @link https://php.net/manual/en/ziparchive.close.php
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 616,
        'endLine' => 619,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'count' => 
      array (
        'name' => 'count',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 7 &gt;= 7.2.0, PECL zip &gt;= 1.15.0)<br/>
 * Counts the number of files in the archive.
 * @link https://www.php.net/manual/en/ziparchive.count.php
 * @return int
 * @since 7.2
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 628,
        'endLine' => 631,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getStatusString' => 
      array (
        'name' => 'getStatusString',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Returns the status error message, system and/or zip messages
 * @link https://php.net/manual/en/ziparchive.getstatusstring.php
 * @return string|false a string with the status message on success or <b>FALSE</b> on failure.
 * @since 5.2
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 639,
        'endLine' => 642,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'addEmptyDir' => 
      array (
        'name' => 'addEmptyDir',
        'parameters' => 
        array (
          'dirname' => 
          array (
            'name' => 'dirname',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 656,
                      'endLine' => 656,
                      'startTokenPos' => 1715,
                      'startFilePos' => 21442,
                      'endTokenPos' => 1721,
                      'endFilePos' => 21460,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 656,
                      'endLine' => 656,
                      'startTokenPos' => 1727,
                      'startFilePos' => 21472,
                      'endTokenPos' => 1727,
                      'endFilePos' => 21473,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 656,
            'endLine' => 657,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 659,
                'endLine' => 659,
                'startTokenPos' => 1761,
                'startFilePos' => 21629,
                'endTokenPos' => 1761,
                'endFilePos' => 21629,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 658,
                      'endLine' => 658,
                      'startTokenPos' => 1739,
                      'startFilePos' => 21572,
                      'endTokenPos' => 1745,
                      'endFilePos' => 21587,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 658,
                      'endLine' => 658,
                      'startTokenPos' => 1751,
                      'startFilePos' => 21599,
                      'endTokenPos' => 1751,
                      'endFilePos' => 21600,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 658,
            'endLine' => 659,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 1,
            'isOptional' => true,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.8.0)<br/>
 * Add a new directory
 * @link https://php.net/manual/en/ziparchive.addemptydir.php
 * @param string $dirname <p>
 * The directory to add.
 * </p>
 * @param int $flags [optional] Set how to manage name encoding (ZipArchive::FL_ENC_*) and entry replacement (ZipArchive::FL_OVERWRITE)
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 654,
        'endLine' => 662,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'addFromString' => 
      array (
        'name' => 'addFromString',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 680,
                      'endLine' => 680,
                      'startTokenPos' => 1788,
                      'startFilePos' => 22551,
                      'endTokenPos' => 1794,
                      'endFilePos' => 22569,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 680,
                      'endLine' => 680,
                      'startTokenPos' => 1800,
                      'startFilePos' => 22581,
                      'endTokenPos' => 1800,
                      'endFilePos' => 22582,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 680,
            'endLine' => 681,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'content' => 
          array (
            'name' => 'content',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 682,
                      'endLine' => 682,
                      'startTokenPos' => 1812,
                      'startFilePos' => 22678,
                      'endTokenPos' => 1818,
                      'endFilePos' => 22696,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 682,
                      'endLine' => 682,
                      'startTokenPos' => 1824,
                      'startFilePos' => 22708,
                      'endTokenPos' => 1824,
                      'endFilePos' => 22709,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 682,
            'endLine' => 683,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '8192',
              'attributes' => 
              array (
                'startLine' => 685,
                'endLine' => 685,
                'startTokenPos' => 1858,
                'startFilePos' => 22865,
                'endTokenPos' => 1858,
                'endFilePos' => 22868,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 684,
                      'endLine' => 684,
                      'startTokenPos' => 1836,
                      'startFilePos' => 22808,
                      'endTokenPos' => 1842,
                      'endFilePos' => 22823,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 684,
                      'endLine' => 684,
                      'startTokenPos' => 1848,
                      'startFilePos' => 22835,
                      'endTokenPos' => 1848,
                      'endFilePos' => 22836,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 684,
            'endLine' => 685,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Add a file to a ZIP archive using its contents
 * @link https://php.net/manual/en/ziparchive.addfromstring.php
 * @param string $name <p>
 * The name of the entry to create.
 * </p>
 * @param string $content <p>
 * The contents to use to create the entry. It is used in a binary
 * safe mode.
 * </p>
 * @param int $flags [optional] Set how to manage name encoding (ZipArchive::FL_ENC_*) and entry replacement (ZipArchive::FL_OVERWRITE)
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 678,
        'endLine' => 688,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'addFile' => 
      array (
        'name' => 'addFile',
        'parameters' => 
        array (
          'filepath' => 
          array (
            'name' => 'filepath',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 711,
                      'endLine' => 711,
                      'startTokenPos' => 1885,
                      'startFilePos' => 24093,
                      'endTokenPos' => 1891,
                      'endFilePos' => 24111,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 711,
                      'endLine' => 711,
                      'startTokenPos' => 1897,
                      'startFilePos' => 24123,
                      'endTokenPos' => 1897,
                      'endFilePos' => 24124,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 711,
            'endLine' => 712,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entryname' => 
          array (
            'name' => 'entryname',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 714,
                'endLine' => 714,
                'startTokenPos' => 1931,
                'startFilePos' => 24291,
                'endTokenPos' => 1931,
                'endFilePos' => 24294,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 713,
                      'endLine' => 713,
                      'startTokenPos' => 1909,
                      'startFilePos' => 24224,
                      'endTokenPos' => 1915,
                      'endFilePos' => 24242,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 713,
                      'endLine' => 713,
                      'startTokenPos' => 1921,
                      'startFilePos' => 24254,
                      'endTokenPos' => 1921,
                      'endFilePos' => 24255,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 713,
            'endLine' => 714,
            'startColumn' => 13,
            'endColumn' => 36,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'start' => 
          array (
            'name' => 'start',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 716,
                'endLine' => 716,
                'startTokenPos' => 1959,
                'startFilePos' => 24420,
                'endTokenPos' => 1959,
                'endFilePos' => 24420,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 715,
                      'endLine' => 715,
                      'startTokenPos' => 1937,
                      'startFilePos' => 24363,
                      'endTokenPos' => 1943,
                      'endFilePos' => 24378,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 715,
                      'endLine' => 715,
                      'startTokenPos' => 1949,
                      'startFilePos' => 24390,
                      'endTokenPos' => 1949,
                      'endFilePos' => 24391,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 715,
            'endLine' => 716,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'length' => 
          array (
            'name' => 'length',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 718,
                'endLine' => 718,
                'startTokenPos' => 1987,
                'startFilePos' => 24547,
                'endTokenPos' => 1987,
                'endFilePos' => 24547,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 717,
                      'endLine' => 717,
                      'startTokenPos' => 1965,
                      'startFilePos' => 24489,
                      'endTokenPos' => 1971,
                      'endFilePos' => 24504,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 717,
                      'endLine' => 717,
                      'startTokenPos' => 1977,
                      'startFilePos' => 24516,
                      'endTokenPos' => 1977,
                      'endFilePos' => 24517,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 717,
            'endLine' => 718,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '8192',
              'attributes' => 
              array (
                'startLine' => 720,
                'endLine' => 720,
                'startTokenPos' => 2015,
                'startFilePos' => 24673,
                'endTokenPos' => 2015,
                'endFilePos' => 24676,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 719,
                      'endLine' => 719,
                      'startTokenPos' => 1993,
                      'startFilePos' => 24616,
                      'endTokenPos' => 1999,
                      'endFilePos' => 24631,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 719,
                      'endLine' => 719,
                      'startTokenPos' => 2005,
                      'startFilePos' => 24643,
                      'endTokenPos' => 2005,
                      'endFilePos' => 24644,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 719,
            'endLine' => 720,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Adds a file to a ZIP archive from the given path
 * @link https://php.net/manual/en/ziparchive.addfile.php
 * @param string $filepath <p>
 * The path to the file to add.
 * </p>
 * @param string $entryname [optional] <p>
 * If supplied, this is the local name inside the ZIP archive that will override the <i>filename</i>.
 * </p>
 * @param int $start [optional] <p>
 * This parameter is not used but is required to extend <b>ZipArchive</b>.
 * </p>
 * @param int $length [optional] <p>
 * This parameter is not used but is required to extend <b>ZipArchive</b>.
 * </p>
 * @param int $flags [optional] Set how to manage name encoding (ZipArchive::FL_ENC_*) and entry replacement (ZipArchive::FL_OVERWRITE)
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 709,
        'endLine' => 723,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'addGlob' => 
      array (
        'name' => 'addGlob',
        'parameters' => 
        array (
          'pattern' => 
          array (
            'name' => 'pattern',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 751,
                      'endLine' => 751,
                      'startTokenPos' => 2042,
                      'startFilePos' => 25818,
                      'endTokenPos' => 2048,
                      'endFilePos' => 25836,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 751,
                      'endLine' => 751,
                      'startTokenPos' => 2054,
                      'startFilePos' => 25848,
                      'endTokenPos' => 2054,
                      'endFilePos' => 25849,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 751,
            'endLine' => 752,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 754,
                'endLine' => 754,
                'startTokenPos' => 2088,
                'startFilePos' => 26005,
                'endTokenPos' => 2088,
                'endFilePos' => 26005,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 753,
                      'endLine' => 753,
                      'startTokenPos' => 2066,
                      'startFilePos' => 25948,
                      'endTokenPos' => 2072,
                      'endFilePos' => 25963,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 753,
                      'endLine' => 753,
                      'startTokenPos' => 2078,
                      'startFilePos' => 25975,
                      'endTokenPos' => 2078,
                      'endFilePos' => 25976,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 753,
            'endLine' => 754,
            'startColumn' => 13,
            'endColumn' => 26,
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
                'startLine' => 755,
                'endLine' => 755,
                'startTokenPos' => 2097,
                'startFilePos' => 26037,
                'endTokenPos' => 2098,
                'endFilePos' => 26038,
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
            'startLine' => 755,
            'endLine' => 755,
            'startColumn' => 13,
            'endColumn' => 31,
            'parameterIndex' => 2,
            'isOptional' => true,
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
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.3.0, PECL zip &gt;= 1.9.0)<br/>
 * Add files from a directory by glob pattern
 * @link https://php.net/manual/en/ziparchive.addglob.php
 * @param string $pattern <p>
 * A <b>glob</b> pattern against which files will be matched.
 * </p>
 * @param int $flags [optional] <p>
 * A bit mask of glob() flags.
 * </p>
 * @param array $options [optional] <p>
 * An associative array of options. Available options are:
 * </p>
 * <p>
 * "add_path"
 * </p>
 * <p>
 * Prefix to prepend when translating to the local path of the file within
 * the archive. This is applied after any remove operations defined by the
 * "remove_path" or "remove_all_path"
 * options.
 * </p>
 * @return array|false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 749,
        'endLine' => 758,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'addPattern' => 
      array (
        'name' => 'addPattern',
        'parameters' => 
        array (
          'pattern' => 
          array (
            'name' => 'pattern',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 777,
                      'endLine' => 777,
                      'startTokenPos' => 2127,
                      'startFilePos' => 26939,
                      'endTokenPos' => 2133,
                      'endFilePos' => 26957,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 777,
                      'endLine' => 777,
                      'startTokenPos' => 2139,
                      'startFilePos' => 26969,
                      'endTokenPos' => 2139,
                      'endFilePos' => 26970,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 777,
            'endLine' => 778,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
            'default' => 
            array (
              'code' => '\'.\'',
              'attributes' => 
              array (
                'startLine' => 780,
                'endLine' => 780,
                'startTokenPos' => 2173,
                'startFilePos' => 27131,
                'endTokenPos' => 2173,
                'endFilePos' => 27133,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 779,
                      'endLine' => 779,
                      'startTokenPos' => 2151,
                      'startFilePos' => 27069,
                      'endTokenPos' => 2157,
                      'endFilePos' => 27087,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 779,
                      'endLine' => 779,
                      'startTokenPos' => 2163,
                      'startFilePos' => 27099,
                      'endTokenPos' => 2163,
                      'endFilePos' => 27100,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 779,
            'endLine' => 780,
            'startColumn' => 13,
            'endColumn' => 30,
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
                'startLine' => 781,
                'endLine' => 781,
                'startTokenPos' => 2182,
                'startFilePos' => 27165,
                'endTokenPos' => 2183,
                'endFilePos' => 27166,
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
            'startLine' => 781,
            'endLine' => 781,
            'startColumn' => 13,
            'endColumn' => 31,
            'parameterIndex' => 2,
            'isOptional' => true,
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
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.3.0, PECL zip &gt;= 1.9.0)<br/>
 * Add files from a directory by PCRE pattern
 * @link https://php.net/manual/en/ziparchive.addpattern.php
 * @param string $pattern <p>
 * A PCRE pattern against which files will be matched.
 * </p>
 * @param string $path [optional] <p>
 * The directory that will be scanned. Defaults to the current working directory.
 * </p>
 * @param array $options [optional] <p>
 * An associative array of options accepted by <b>ZipArchive::addGlob</b>.
 * </p>
 * @return array|false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 775,
        'endLine' => 784,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'renameIndex' => 
      array (
        'name' => 'renameIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 800,
                      'endLine' => 800,
                      'startTokenPos' => 2212,
                      'startFilePos' => 27858,
                      'endTokenPos' => 2218,
                      'endFilePos' => 27873,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 800,
                      'endLine' => 800,
                      'startTokenPos' => 2224,
                      'startFilePos' => 27885,
                      'endTokenPos' => 2224,
                      'endFilePos' => 27886,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 800,
            'endLine' => 801,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'new_name' => 
          array (
            'name' => 'new_name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 802,
                      'endLine' => 802,
                      'startTokenPos' => 2236,
                      'startFilePos' => 27980,
                      'endTokenPos' => 2242,
                      'endFilePos' => 27998,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 802,
                      'endLine' => 802,
                      'startTokenPos' => 2248,
                      'startFilePos' => 28010,
                      'endTokenPos' => 2248,
                      'endFilePos' => 28011,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 802,
            'endLine' => 803,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 1,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * Renames an entry defined by its index
 * @link https://php.net/manual/en/ziparchive.renameindex.php
 * @param int $index <p>
 * Index of the entry to rename.
 * </p>
 * @param string $new_name <p>
 * New name.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 798,
        'endLine' => 806,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'renameName' => 
      array (
        'name' => 'renameName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 822,
                      'endLine' => 822,
                      'startTokenPos' => 2281,
                      'startFilePos' => 28725,
                      'endTokenPos' => 2287,
                      'endFilePos' => 28743,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 822,
                      'endLine' => 822,
                      'startTokenPos' => 2293,
                      'startFilePos' => 28755,
                      'endTokenPos' => 2293,
                      'endFilePos' => 28756,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 822,
            'endLine' => 823,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'new_name' => 
          array (
            'name' => 'new_name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 824,
                      'endLine' => 824,
                      'startTokenPos' => 2305,
                      'startFilePos' => 28852,
                      'endTokenPos' => 2311,
                      'endFilePos' => 28870,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 824,
                      'endLine' => 824,
                      'startTokenPos' => 2317,
                      'startFilePos' => 28882,
                      'endTokenPos' => 2317,
                      'endFilePos' => 28883,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 824,
            'endLine' => 825,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 1,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * Renames an entry defined by its name
 * @link https://php.net/manual/en/ziparchive.renamename.php
 * @param string $name <p>
 * Name of the entry to rename.
 * </p>
 * @param string $new_name <p>
 * New name.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 820,
        'endLine' => 828,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setArchiveComment' => 
      array (
        'name' => 'setArchiveComment',
        'parameters' => 
        array (
          'comment' => 
          array (
            'name' => 'comment',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 841,
                      'endLine' => 841,
                      'startTokenPos' => 2350,
                      'startFilePos' => 29534,
                      'endTokenPos' => 2356,
                      'endFilePos' => 29552,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 841,
                      'endLine' => 841,
                      'startTokenPos' => 2362,
                      'startFilePos' => 29564,
                      'endTokenPos' => 2362,
                      'endFilePos' => 29565,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 841,
            'endLine' => 842,
            'startColumn' => 13,
            'endColumn' => 27,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.4.0)<br/>
 * Set the comment of a ZIP archive
 * @link https://php.net/manual/en/ziparchive.setarchivecomment.php
 * @param string $comment <p>
 * The contents of the comment.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 839,
        'endLine' => 845,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getArchiveComment' => 
      array (
        'name' => 'getArchiveComment',
        'parameters' => 
        array (
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 860,
                'endLine' => 860,
                'startTokenPos' => 2417,
                'startFilePos' => 30364,
                'endTokenPos' => 2417,
                'endFilePos' => 30367,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 859,
                      'endLine' => 859,
                      'startTokenPos' => 2395,
                      'startFilePos' => 30307,
                      'endTokenPos' => 2401,
                      'endFilePos' => 30322,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 859,
                      'endLine' => 859,
                      'startTokenPos' => 2407,
                      'startFilePos' => 30334,
                      'endTokenPos' => 2407,
                      'endFilePos' => 30335,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 859,
            'endLine' => 860,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => true,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Returns the Zip archive comment
 * @link https://php.net/manual/en/ziparchive.getarchivecomment.php
 * @param int $flags [optional] <p>
 * If flags is set to <b>ZipArchive::FL_UNCHANGED</b>, the original unchanged
 * comment is returned.
 * </p>
 * @return string|false the Zip archive comment or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 857,
        'endLine' => 863,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setCommentIndex' => 
      array (
        'name' => 'setCommentIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 879,
                      'endLine' => 879,
                      'startTokenPos' => 2446,
                      'startFilePos' => 31087,
                      'endTokenPos' => 2452,
                      'endFilePos' => 31102,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 879,
                      'endLine' => 879,
                      'startTokenPos' => 2458,
                      'startFilePos' => 31114,
                      'endTokenPos' => 2458,
                      'endFilePos' => 31115,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 879,
            'endLine' => 880,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'comment' => 
          array (
            'name' => 'comment',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 881,
                      'endLine' => 881,
                      'startTokenPos' => 2470,
                      'startFilePos' => 31209,
                      'endTokenPos' => 2476,
                      'endFilePos' => 31227,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 881,
                      'endLine' => 881,
                      'startTokenPos' => 2482,
                      'startFilePos' => 31239,
                      'endTokenPos' => 2482,
                      'endFilePos' => 31240,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 881,
            'endLine' => 882,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 1,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.4.0)<br/>
 * Set the comment of an entry defined by its index
 * @link https://php.net/manual/en/ziparchive.setcommentindex.php
 * @param int $index <p>
 * Index of the entry.
 * </p>
 * @param string $comment <p>
 * The contents of the comment.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 877,
        'endLine' => 885,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setCommentName' => 
      array (
        'name' => 'setCommentName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 901,
                      'endLine' => 901,
                      'startTokenPos' => 2515,
                      'startFilePos' => 31980,
                      'endTokenPos' => 2521,
                      'endFilePos' => 31998,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 901,
                      'endLine' => 901,
                      'startTokenPos' => 2527,
                      'startFilePos' => 32010,
                      'endTokenPos' => 2527,
                      'endFilePos' => 32011,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 901,
            'endLine' => 902,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'comment' => 
          array (
            'name' => 'comment',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 903,
                      'endLine' => 903,
                      'startTokenPos' => 2539,
                      'startFilePos' => 32107,
                      'endTokenPos' => 2545,
                      'endFilePos' => 32125,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 903,
                      'endLine' => 903,
                      'startTokenPos' => 2551,
                      'startFilePos' => 32137,
                      'endTokenPos' => 2551,
                      'endFilePos' => 32138,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 903,
            'endLine' => 904,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 1,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.4.0)<br/>
 * Set the comment of an entry defined by its name
 * @link https://php.net/manual/en/ziparchive.setcommentname.php
 * @param string $name <p>
 * Name of the entry.
 * </p>
 * @param string $comment <p>
 * The contents of the comment.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 899,
        'endLine' => 907,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setCompressionIndex' => 
      array (
        'name' => 'setCompressionIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 919,
            'endLine' => 919,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 919,
            'endLine' => 919,
            'startColumn' => 57,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'compflags' => 
          array (
            'name' => 'compflags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 919,
                'endLine' => 919,
                'startTokenPos' => 2596,
                'startFilePos' => 32921,
                'endTokenPos' => 2596,
                'endFilePos' => 32921,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 919,
            'endLine' => 919,
            'startColumn' => 70,
            'endColumn' => 87,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set the compression method of an entry defined by its index
 * @link https://php.net/manual/en/ziparchive.setcompressionindex.php
 * @param int $index Index of the entry.
 * @param int $method The compression method. Either ZipArchive::CM_DEFAULT, ZipArchive::CM_STORE or ZipArchive::CM_DEFLATE.
 * @param int $compflags [optional] Compression flags. Currently unused.
 * @return bool Returns TRUE on success or FALSE on failure.
 * @since 7.0
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 918,
        'endLine' => 921,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setCompressionName' => 
      array (
        'name' => 'setCompressionName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 933,
            'endLine' => 933,
            'startColumn' => 44,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 933,
            'endLine' => 933,
            'startColumn' => 58,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'compflags' => 
          array (
            'name' => 'compflags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 933,
                'endLine' => 933,
                'startTokenPos' => 2634,
                'startFilePos' => 33666,
                'endTokenPos' => 2634,
                'endFilePos' => 33666,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 933,
            'endLine' => 933,
            'startColumn' => 71,
            'endColumn' => 88,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set the compression method of an entry defined by its name
 * https://secure.php.net/manual/en/ziparchive.setcompressionname.php
 * @param string $name Name of the entry.
 * @param int $method The compression method. Either ZipArchive::CM_DEFAULT, ZipArchive::CM_STORE or ZipArchive::CM_DEFLATE.
 * @param int $compflags [optional] Compression flags. Currently unused.
 * @return bool Returns TRUE on success or FALSE on failure.
 * @since 7.0
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 932,
        'endLine' => 935,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setEncryptionIndex' => 
      array (
        'name' => 'setEncryptionIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 947,
            'endLine' => 947,
            'startColumn' => 44,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 947,
            'endLine' => 947,
            'startColumn' => 56,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 947,
                'endLine' => 947,
                'startTokenPos' => 2673,
                'startFilePos' => 34394,
                'endTokenPos' => 2673,
                'endFilePos' => 34397,
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
            'startLine' => 947,
            'endLine' => 947,
            'startColumn' => 69,
            'endColumn' => 92,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set the encryption method of an entry defined by its index
 * @link https://php.net/manual/en/ziparchive.setencryptionindex.php
 * @param int $index Index of the entry.
 * @param int $method The encryption method defined by one of the ZipArchive::EM_ constants.
 * @param string|null $password [optional] Optional password, default used when missing.
 * @return bool Returns TRUE on success or FALSE on failure.
 * @since 7.2
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 946,
        'endLine' => 949,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setEncryptionName' => 
      array (
        'name' => 'setEncryptionName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 961,
            'endLine' => 961,
            'startColumn' => 43,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 961,
            'endLine' => 961,
            'startColumn' => 57,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 961,
                'endLine' => 961,
                'startTokenPos' => 2712,
                'startFilePos' => 35125,
                'endTokenPos' => 2712,
                'endFilePos' => 35128,
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
            'startLine' => 961,
            'endLine' => 961,
            'startColumn' => 70,
            'endColumn' => 93,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set the encryption method of an entry defined by its name
 * @link https://php.net/manual/en/ziparchive.setencryptionname.php
 * @param string $name Name of the entry.
 * @param int $method The encryption method defined by one of the ZipArchive::EM_ constants.
 * @param string|null $password [optional] Optional password, default used when missing.
 * @return bool Returns TRUE on success or FALSE on failure.
 * @since 7.2
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 960,
        'endLine' => 963,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setPassword' => 
      array (
        'name' => 'setPassword',
        'parameters' => 
        array (
          'password' => 
          array (
            'name' => 'password',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 972,
                      'endLine' => 972,
                      'startTokenPos' => 2738,
                      'startFilePos' => 35504,
                      'endTokenPos' => 2744,
                      'endFilePos' => 35522,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 972,
                      'endLine' => 972,
                      'startTokenPos' => 2750,
                      'startFilePos' => 35534,
                      'endTokenPos' => 2750,
                      'endFilePos' => 35535,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 972,
            'endLine' => 973,
            'startColumn' => 13,
            'endColumn' => 28,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.6.0, PECL zip &gt;= 1.12.0)<br/>
 * @param string $password
 * @return bool
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 970,
        'endLine' => 976,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getCommentIndex' => 
      array (
        'name' => 'getCommentIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 993,
                      'endLine' => 993,
                      'startTokenPos' => 2783,
                      'startFilePos' => 36374,
                      'endTokenPos' => 2789,
                      'endFilePos' => 36389,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 993,
                      'endLine' => 993,
                      'startTokenPos' => 2795,
                      'startFilePos' => 36401,
                      'endTokenPos' => 2795,
                      'endFilePos' => 36402,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 993,
            'endLine' => 994,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 996,
                'endLine' => 996,
                'startTokenPos' => 2829,
                'startFilePos' => 36553,
                'endTokenPos' => 2829,
                'endFilePos' => 36556,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 995,
                      'endLine' => 995,
                      'startTokenPos' => 2807,
                      'startFilePos' => 36496,
                      'endTokenPos' => 2813,
                      'endFilePos' => 36511,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 995,
                      'endLine' => 995,
                      'startTokenPos' => 2819,
                      'startFilePos' => 36523,
                      'endTokenPos' => 2819,
                      'endFilePos' => 36524,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 995,
            'endLine' => 996,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.4.0)<br/>
 * Returns the comment of an entry using the entry index
 * @link https://php.net/manual/en/ziparchive.getcommentindex.php
 * @param int $index <p>
 * Index of the entry
 * </p>
 * @param int $flags [optional] <p>
 * If flags is set to <b>ZipArchive::FL_UNCHANGED</b>, the original unchanged
 * comment is returned.
 * </p>
 * @return string|false the comment on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 991,
        'endLine' => 999,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getCommentName' => 
      array (
        'name' => 'getCommentName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1016,
                      'endLine' => 1016,
                      'startTokenPos' => 2858,
                      'startFilePos' => 37370,
                      'endTokenPos' => 2864,
                      'endFilePos' => 37388,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1016,
                      'endLine' => 1016,
                      'startTokenPos' => 2870,
                      'startFilePos' => 37400,
                      'endTokenPos' => 2870,
                      'endFilePos' => 37401,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1016,
            'endLine' => 1017,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1019,
                'endLine' => 1019,
                'startTokenPos' => 2904,
                'startFilePos' => 37554,
                'endTokenPos' => 2904,
                'endFilePos' => 37557,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1018,
                      'endLine' => 1018,
                      'startTokenPos' => 2882,
                      'startFilePos' => 37497,
                      'endTokenPos' => 2888,
                      'endFilePos' => 37512,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1018,
                      'endLine' => 1018,
                      'startTokenPos' => 2894,
                      'startFilePos' => 37524,
                      'endTokenPos' => 2894,
                      'endFilePos' => 37525,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1018,
            'endLine' => 1019,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.4.0)<br/>
 * Returns the comment of an entry using the entry name
 * @link https://php.net/manual/en/ziparchive.getcommentname.php
 * @param string $name <p>
 * Name of the entry
 * </p>
 * @param int $flags [optional] <p>
 * If flags is set to <b>ZipArchive::FL_UNCHANGED</b>, the original unchanged
 * comment is returned.
 * </p>
 * @return string|false the comment on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1014,
        'endLine' => 1022,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'deleteIndex' => 
      array (
        'name' => 'deleteIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1035,
                      'endLine' => 1035,
                      'startTokenPos' => 2933,
                      'startFilePos' => 38183,
                      'endTokenPos' => 2939,
                      'endFilePos' => 38198,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1035,
                      'endLine' => 1035,
                      'startTokenPos' => 2945,
                      'startFilePos' => 38210,
                      'endTokenPos' => 2945,
                      'endFilePos' => 38211,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1035,
            'endLine' => 1036,
            'startColumn' => 13,
            'endColumn' => 22,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * delete an entry in the archive using its index
 * @link https://php.net/manual/en/ziparchive.deleteindex.php
 * @param int $index <p>
 * Index of the entry to delete.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1033,
        'endLine' => 1039,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'deleteName' => 
      array (
        'name' => 'deleteName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1052,
                      'endLine' => 1052,
                      'startTokenPos' => 2978,
                      'startFilePos' => 38852,
                      'endTokenPos' => 2984,
                      'endFilePos' => 38870,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1052,
                      'endLine' => 1052,
                      'startTokenPos' => 2990,
                      'startFilePos' => 38882,
                      'endTokenPos' => 2990,
                      'endFilePos' => 38883,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1052,
            'endLine' => 1053,
            'startColumn' => 13,
            'endColumn' => 24,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * delete an entry in the archive using its name
 * @link https://php.net/manual/en/ziparchive.deletename.php
 * @param string $name <p>
 * Name of the entry to delete.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1050,
        'endLine' => 1056,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'statName' => 
      array (
        'name' => 'statName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1076,
                      'endLine' => 1076,
                      'startTokenPos' => 3023,
                      'startFilePos' => 40008,
                      'endTokenPos' => 3029,
                      'endFilePos' => 40026,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1076,
                      'endLine' => 1076,
                      'startTokenPos' => 3035,
                      'startFilePos' => 40038,
                      'endTokenPos' => 3035,
                      'endFilePos' => 40039,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1076,
            'endLine' => 1077,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1079,
                'endLine' => 1079,
                'startTokenPos' => 3069,
                'startFilePos' => 40192,
                'endTokenPos' => 3069,
                'endFilePos' => 40195,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1078,
                      'endLine' => 1078,
                      'startTokenPos' => 3047,
                      'startFilePos' => 40135,
                      'endTokenPos' => 3053,
                      'endFilePos' => 40150,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1078,
                      'endLine' => 1078,
                      'startTokenPos' => 3059,
                      'startFilePos' => 40162,
                      'endTokenPos' => 3059,
                      'endFilePos' => 40163,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1078,
            'endLine' => 1079,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * Get the details of an entry defined by its name.
 * @link https://php.net/manual/en/ziparchive.statname.php
 * @param string $name <p>
 * Name of the entry
 * </p>
 * @param int $flags [optional] <p>
 * The flags argument specifies how the name lookup should be done.
 * Also, <b>ZipArchive::FL_UNCHANGED</b> may be ORed to it to request
 * information about the original file in the archive,
 * ignoring any changes made.
 * <b>ZipArchive::FL_NOCASE</b>
 * </p>
 * @return array{name: string, index: int, crc: int, size: int, mtime: int, comp_size: int, comp_method: int, encryption_method: int}|false an array containing the entry details or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1074,
        'endLine' => 1082,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'statIndex' => 
      array (
        'name' => 'statIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1100,
                      'endLine' => 1100,
                      'startTokenPos' => 3098,
                      'startFilePos' => 41180,
                      'endTokenPos' => 3104,
                      'endFilePos' => 41195,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1100,
                      'endLine' => 1100,
                      'startTokenPos' => 3110,
                      'startFilePos' => 41207,
                      'endTokenPos' => 3110,
                      'endFilePos' => 41208,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1100,
            'endLine' => 1101,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1103,
                'endLine' => 1103,
                'startTokenPos' => 3144,
                'startFilePos' => 41359,
                'endTokenPos' => 3144,
                'endFilePos' => 41362,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1102,
                      'endLine' => 1102,
                      'startTokenPos' => 3122,
                      'startFilePos' => 41302,
                      'endTokenPos' => 3128,
                      'endFilePos' => 41317,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1102,
                      'endLine' => 1102,
                      'startTokenPos' => 3134,
                      'startFilePos' => 41329,
                      'endTokenPos' => 3134,
                      'endFilePos' => 41330,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1102,
            'endLine' => 1103,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Get the details of an entry defined by its index.
 * @link https://php.net/manual/en/ziparchive.statindex.php
 * @param int $index <p>
 * Index of the entry
 * </p>
 * @param int $flags [optional] <p>
 * <b>ZipArchive::FL_UNCHANGED</b> may be ORed to it to request
 * information about the original file in the archive,
 * ignoring any changes made.
 * </p>
 * @return array{name: string, index: int, crc: int, size: int, mtime: int, comp_size: int, comp_method: int, encryption_method: int}|false an array containing the entry details or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1098,
        'endLine' => 1106,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'locateName' => 
      array (
        'name' => 'locateName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1124,
                      'endLine' => 1124,
                      'startTokenPos' => 3173,
                      'startFilePos' => 42205,
                      'endTokenPos' => 3179,
                      'endFilePos' => 42223,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1124,
                      'endLine' => 1124,
                      'startTokenPos' => 3185,
                      'startFilePos' => 42235,
                      'endTokenPos' => 3185,
                      'endFilePos' => 42236,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1124,
            'endLine' => 1125,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1127,
                'endLine' => 1127,
                'startTokenPos' => 3219,
                'startFilePos' => 42389,
                'endTokenPos' => 3219,
                'endFilePos' => 42392,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1126,
                      'endLine' => 1126,
                      'startTokenPos' => 3197,
                      'startFilePos' => 42332,
                      'endTokenPos' => 3203,
                      'endFilePos' => 42347,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1126,
                      'endLine' => 1126,
                      'startTokenPos' => 3209,
                      'startFilePos' => 42359,
                      'endTokenPos' => 3209,
                      'endFilePos' => 42360,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1126,
            'endLine' => 1127,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * Returns the index of the entry in the archive
 * @link https://php.net/manual/en/ziparchive.locatename.php
 * @param string $name <p>
 * The name of the entry to look up
 * </p>
 * @param int $flags [optional] <p>
 * The flags are specified by ORing the following values,
 * or 0 for none of them.
 * <b>ZipArchive::FL_NOCASE</b>
 * </p>
 * @return int|false the index of the entry on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1122,
        'endLine' => 1130,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getNameIndex' => 
      array (
        'name' => 'getNameIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1147,
                      'endLine' => 1147,
                      'startTokenPos' => 3248,
                      'startFilePos' => 43185,
                      'endTokenPos' => 3254,
                      'endFilePos' => 43200,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1147,
                      'endLine' => 1147,
                      'startTokenPos' => 3260,
                      'startFilePos' => 43212,
                      'endTokenPos' => 3260,
                      'endFilePos' => 43213,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1147,
            'endLine' => 1148,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1150,
                'endLine' => 1150,
                'startTokenPos' => 3294,
                'startFilePos' => 43364,
                'endTokenPos' => 3294,
                'endFilePos' => 43367,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1149,
                      'endLine' => 1149,
                      'startTokenPos' => 3272,
                      'startFilePos' => 43307,
                      'endTokenPos' => 3278,
                      'endFilePos' => 43322,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1149,
                      'endLine' => 1149,
                      'startTokenPos' => 3284,
                      'startFilePos' => 43334,
                      'endTokenPos' => 3284,
                      'endFilePos' => 43335,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1149,
            'endLine' => 1150,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * Returns the name of an entry using its index
 * @link https://php.net/manual/en/ziparchive.getnameindex.php
 * @param int $index <p>
 * Index of the entry.
 * </p>
 * @param int $flags [optional] <p>
 * If flags is set to <b>ZipArchive::FL_UNCHANGED</b>, the original unchanged
 * name is returned.
 * </p>
 * @return string|false the name on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1145,
        'endLine' => 1153,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'unchangeArchive' => 
      array (
        'name' => 'unchangeArchive',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Revert all global changes done in the archive.
 * @link https://php.net/manual/en/ziparchive.unchangearchive.php
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1161,
        'endLine' => 1164,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'unchangeAll' => 
      array (
        'name' => 'unchangeAll',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Undo all changes done in the archive
 * @link https://php.net/manual/en/ziparchive.unchangeall.php
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1172,
        'endLine' => 1175,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'unchangeIndex' => 
      array (
        'name' => 'unchangeIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1188,
                      'endLine' => 1188,
                      'startTokenPos' => 3365,
                      'startFilePos' => 44895,
                      'endTokenPos' => 3371,
                      'endFilePos' => 44910,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1188,
                      'endLine' => 1188,
                      'startTokenPos' => 3377,
                      'startFilePos' => 44922,
                      'endTokenPos' => 3377,
                      'endFilePos' => 44923,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1188,
            'endLine' => 1189,
            'startColumn' => 13,
            'endColumn' => 22,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Revert all changes done to an entry at the given index
 * @link https://php.net/manual/en/ziparchive.unchangeindex.php
 * @param int $index <p>
 * Index of the entry.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1186,
        'endLine' => 1192,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'unchangeName' => 
      array (
        'name' => 'unchangeName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1205,
                      'endLine' => 1205,
                      'startTokenPos' => 3410,
                      'startFilePos' => 45569,
                      'endTokenPos' => 3416,
                      'endFilePos' => 45587,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1205,
                      'endLine' => 1205,
                      'startTokenPos' => 3422,
                      'startFilePos' => 45599,
                      'endTokenPos' => 3422,
                      'endFilePos' => 45600,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1205,
            'endLine' => 1206,
            'startColumn' => 13,
            'endColumn' => 24,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.5.0)<br/>
 * Revert all changes done to an entry with the given name.
 * @link https://php.net/manual/en/ziparchive.unchangename.php
 * @param string $name <p>
 * Name of the entry.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1203,
        'endLine' => 1209,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'extractTo' => 
      array (
        'name' => 'extractTo',
        'parameters' => 
        array (
          'pathto' => 
          array (
            'name' => 'pathto',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1226,
                      'endLine' => 1226,
                      'startTokenPos' => 3455,
                      'startFilePos' => 46417,
                      'endTokenPos' => 3461,
                      'endFilePos' => 46435,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1226,
                      'endLine' => 1226,
                      'startTokenPos' => 3467,
                      'startFilePos' => 46447,
                      'endTokenPos' => 3467,
                      'endFilePos' => 46448,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1226,
            'endLine' => 1227,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1229,
                'endLine' => 1229,
                'startTokenPos' => 3505,
                'startFilePos' => 46631,
                'endTokenPos' => 3505,
                'endFilePos' => 46634,
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
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'array|string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 1228,
                      'endLine' => 1228,
                      'startTokenPos' => 3479,
                      'startFilePos' => 46546,
                      'endTokenPos' => 3485,
                      'endFilePos' => 46575,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1228,
                      'endLine' => 1228,
                      'startTokenPos' => 3491,
                      'startFilePos' => 46587,
                      'endTokenPos' => 3491,
                      'endFilePos' => 46588,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1228,
            'endLine' => 1229,
            'startColumn' => 13,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => true,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Extract the archive contents
 * @link https://php.net/manual/en/ziparchive.extractto.php
 * @param string $pathto <p>
 * Location where to extract the files.
 * </p>
 * @param string[]|string|null $files [optional] <p>
 * The entries to extract. It accepts either a single entry name or
 * an array of names.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1224,
        'endLine' => 1232,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getFromName' => 
      array (
        'name' => 'getFromName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1254,
                      'endLine' => 1254,
                      'startTokenPos' => 3532,
                      'startFilePos' => 47617,
                      'endTokenPos' => 3538,
                      'endFilePos' => 47635,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1254,
                      'endLine' => 1254,
                      'startTokenPos' => 3544,
                      'startFilePos' => 47647,
                      'endTokenPos' => 3544,
                      'endFilePos' => 47648,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1254,
            'endLine' => 1255,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'len' => 
          array (
            'name' => 'len',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1257,
                'endLine' => 1257,
                'startTokenPos' => 3578,
                'startFilePos' => 47799,
                'endTokenPos' => 3578,
                'endFilePos' => 47799,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1256,
                      'endLine' => 1256,
                      'startTokenPos' => 3556,
                      'startFilePos' => 47744,
                      'endTokenPos' => 3562,
                      'endFilePos' => 47759,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1256,
                      'endLine' => 1256,
                      'startTokenPos' => 3568,
                      'startFilePos' => 47771,
                      'endTokenPos' => 3568,
                      'endFilePos' => 47772,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1256,
            'endLine' => 1257,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1259,
                'endLine' => 1259,
                'startTokenPos' => 3606,
                'startFilePos' => 47925,
                'endTokenPos' => 3606,
                'endFilePos' => 47928,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1258,
                      'endLine' => 1258,
                      'startTokenPos' => 3584,
                      'startFilePos' => 47868,
                      'endTokenPos' => 3590,
                      'endFilePos' => 47883,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1258,
                      'endLine' => 1258,
                      'startTokenPos' => 3596,
                      'startFilePos' => 47895,
                      'endTokenPos' => 3596,
                      'endFilePos' => 47896,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1258,
            'endLine' => 1259,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 2,
            'isOptional' => true,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Returns the entry contents using its name
 * @link https://php.net/manual/en/ziparchive.getfromname.php
 * @param string $name <p>
 * Name of the entry
 * </p>
 * @param int $len [optional] <p>
 * The length to be read from the entry. If 0, then the
 * entire entry is read.
 * </p>
 * @param int $flags [optional] <p>
 * The flags to use to open the archive. the following values may
 * be ORed to it.
 * <b>ZipArchive::FL_UNCHANGED</b>
 * </p>
 * @return string|false the contents of the entry on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1252,
        'endLine' => 1262,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getFromIndex' => 
      array (
        'name' => 'getFromIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1286,
                      'endLine' => 1286,
                      'startTokenPos' => 3635,
                      'startFilePos' => 48952,
                      'endTokenPos' => 3641,
                      'endFilePos' => 48967,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1286,
                      'endLine' => 1286,
                      'startTokenPos' => 3647,
                      'startFilePos' => 48979,
                      'endTokenPos' => 3647,
                      'endFilePos' => 48980,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1286,
            'endLine' => 1287,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'len' => 
          array (
            'name' => 'len',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1289,
                'endLine' => 1289,
                'startTokenPos' => 3681,
                'startFilePos' => 49129,
                'endTokenPos' => 3681,
                'endFilePos' => 49129,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1288,
                      'endLine' => 1288,
                      'startTokenPos' => 3659,
                      'startFilePos' => 49074,
                      'endTokenPos' => 3665,
                      'endFilePos' => 49089,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1288,
                      'endLine' => 1288,
                      'startTokenPos' => 3671,
                      'startFilePos' => 49101,
                      'endTokenPos' => 3671,
                      'endFilePos' => 49102,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1288,
            'endLine' => 1289,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1291,
                'endLine' => 1291,
                'startTokenPos' => 3709,
                'startFilePos' => 49255,
                'endTokenPos' => 3709,
                'endFilePos' => 49258,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1290,
                      'endLine' => 1290,
                      'startTokenPos' => 3687,
                      'startFilePos' => 49198,
                      'endTokenPos' => 3693,
                      'endFilePos' => 49213,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1290,
                      'endLine' => 1290,
                      'startTokenPos' => 3699,
                      'startFilePos' => 49225,
                      'endTokenPos' => 3699,
                      'endFilePos' => 49226,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1290,
            'endLine' => 1291,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 2,
            'isOptional' => true,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.3.0)<br/>
 * Returns the entry contents using its index
 * @link https://php.net/manual/en/ziparchive.getfromindex.php
 * @param int $index <p>
 * Index of the entry
 * </p>
 * @param int $len [optional] <p>
 * The length to be read from the entry. If 0, then the
 * entire entry is read.
 * </p>
 * @param int $flags [optional] <p>
 * The flags to use to open the archive. the following values may
 * be ORed to it.
 * </p>
 * <p>
 * <b>ZipArchive::FL_UNCHANGED</b>
 * </p>
 * @return string|false the contents of the entry on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1284,
        'endLine' => 1294,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getStream' => 
      array (
        'name' => 'getStream',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1305,
                      'endLine' => 1305,
                      'startTokenPos' => 3734,
                      'startFilePos' => 49822,
                      'endTokenPos' => 3740,
                      'endFilePos' => 49840,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1305,
                      'endLine' => 1305,
                      'startTokenPos' => 3746,
                      'startFilePos' => 49852,
                      'endTokenPos' => 3746,
                      'endFilePos' => 49853,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1305,
            'endLine' => 1306,
            'startColumn' => 13,
            'endColumn' => 24,
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
 * (PHP 5 &gt;= 5.2.0, PECL zip &gt;= 1.1.0)<br/>
 * Get a file handler to the entry defined by its name (read only).
 * @link https://php.net/manual/en/ziparchive.getstream.php
 * @param string $name <p>
 * The name of the entry to use.
 * </p>
 * @return resource|false a file pointer (resource) on success or <b>FALSE</b> on failure.
 */',
        'startLine' => 1304,
        'endLine' => 1309,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getStreamIndex' => 
      array (
        'name' => 'getStreamIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1323,
                      'endLine' => 1323,
                      'startTokenPos' => 3772,
                      'startFilePos' => 50587,
                      'endTokenPos' => 3778,
                      'endFilePos' => 50602,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1323,
                      'endLine' => 1323,
                      'startTokenPos' => 3784,
                      'startFilePos' => 50614,
                      'endTokenPos' => 3784,
                      'endFilePos' => 50615,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1323,
            'endLine' => 1324,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1326,
                'endLine' => 1326,
                'startTokenPos' => 3818,
                'startFilePos' => 50766,
                'endTokenPos' => 3818,
                'endFilePos' => 50766,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1325,
                      'endLine' => 1325,
                      'startTokenPos' => 3796,
                      'startFilePos' => 50709,
                      'endTokenPos' => 3802,
                      'endFilePos' => 50724,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1325,
                      'endLine' => 1325,
                      'startTokenPos' => 3808,
                      'startFilePos' => 50736,
                      'endTokenPos' => 3808,
                      'endFilePos' => 50737,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1325,
            'endLine' => 1326,
            'startColumn' => 13,
            'endColumn' => 26,
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
 * (PHP 8 &gt;= 8.2.0, PECL zip &gt;= 1.20.0)<br/>
 * Get a file handler to the entry defined by its index (read only)
 * @link https://php.net/manual/en/ziparchive.getstreamindex.php
 * @param int $index <p>
 * Index of the entry
 * </p>
 * @param int $flags [optional] <p>
 * If flags is set to ZipArchive::FL_UNCHANGED, the original unchanged stream is returned.
 * </p>
 * @return resource|false a file pointer (resource) on success or <b>FALSE</b> on failure.
 */',
        'startLine' => 1322,
        'endLine' => 1329,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setExternalAttributesName' => 
      array (
        'name' => 'setExternalAttributesName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1342,
                      'endLine' => 1342,
                      'startTokenPos' => 3842,
                      'startFilePos' => 51601,
                      'endTokenPos' => 3848,
                      'endFilePos' => 51619,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1342,
                      'endLine' => 1342,
                      'startTokenPos' => 3854,
                      'startFilePos' => 51631,
                      'endTokenPos' => 3854,
                      'endFilePos' => 51632,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1342,
            'endLine' => 1343,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'opsys' => 
          array (
            'name' => 'opsys',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1344,
                      'endLine' => 1344,
                      'startTokenPos' => 3866,
                      'startFilePos' => 51728,
                      'endTokenPos' => 3872,
                      'endFilePos' => 51743,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1344,
                      'endLine' => 1344,
                      'startTokenPos' => 3878,
                      'startFilePos' => 51755,
                      'endTokenPos' => 3878,
                      'endFilePos' => 51756,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1344,
            'endLine' => 1345,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attr' => 
          array (
            'name' => 'attr',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1346,
                      'endLine' => 1346,
                      'startTokenPos' => 3890,
                      'startFilePos' => 51850,
                      'endTokenPos' => 3896,
                      'endFilePos' => 51865,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1346,
                      'endLine' => 1346,
                      'startTokenPos' => 3902,
                      'startFilePos' => 51877,
                      'endTokenPos' => 3902,
                      'endFilePos' => 51878,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1346,
            'endLine' => 1347,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1349,
                'endLine' => 1349,
                'startTokenPos' => 3936,
                'startFilePos' => 52028,
                'endTokenPos' => 3936,
                'endFilePos' => 52031,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1348,
                      'endLine' => 1348,
                      'startTokenPos' => 3914,
                      'startFilePos' => 51971,
                      'endTokenPos' => 3920,
                      'endFilePos' => 51986,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1348,
                      'endLine' => 1348,
                      'startTokenPos' => 3926,
                      'startFilePos' => 51998,
                      'endTokenPos' => 3926,
                      'endFilePos' => 51999,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1348,
            'endLine' => 1349,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set the external attributes of an entry defined by its name
 * @link https://www.php.net/manual/en/ziparchive.setexternalattributesname.php
 * @param string $name Name of the entry
 * @param int $opsys The operating system code defined by one of the ZipArchive::OPSYS_ constants.
 * @param int $attr The external attributes. Value depends on operating system.
 * @param int $flags [optional] Optional flags. Currently unused.
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1340,
        'endLine' => 1352,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getExternalAttributesName' => 
      array (
        'name' => 'getExternalAttributesName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1365,
                      'endLine' => 1365,
                      'startTokenPos' => 3963,
                      'startFilePos' => 52978,
                      'endTokenPos' => 3969,
                      'endFilePos' => 52996,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1365,
                      'endLine' => 1365,
                      'startTokenPos' => 3975,
                      'startFilePos' => 53008,
                      'endTokenPos' => 3975,
                      'endFilePos' => 53009,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1365,
            'endLine' => 1366,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'opsys' => 
          array (
            'name' => 'opsys',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1367,
                      'endLine' => 1367,
                      'startTokenPos' => 3987,
                      'startFilePos' => 53105,
                      'endTokenPos' => 3993,
                      'endFilePos' => 53120,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1367,
                      'endLine' => 1367,
                      'startTokenPos' => 3999,
                      'startFilePos' => 53132,
                      'endTokenPos' => 3999,
                      'endFilePos' => 53133,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1367,
            'endLine' => 1368,
            'startColumn' => 13,
            'endColumn' => 23,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attr' => 
          array (
            'name' => 'attr',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1369,
                      'endLine' => 1369,
                      'startTokenPos' => 4012,
                      'startFilePos' => 53228,
                      'endTokenPos' => 4018,
                      'endFilePos' => 53243,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1369,
                      'endLine' => 1369,
                      'startTokenPos' => 4024,
                      'startFilePos' => 53255,
                      'endTokenPos' => 4024,
                      'endFilePos' => 53256,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1369,
            'endLine' => 1370,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1372,
                'endLine' => 1372,
                'startTokenPos' => 4059,
                'startFilePos' => 53407,
                'endTokenPos' => 4059,
                'endFilePos' => 53410,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1371,
                      'endLine' => 1371,
                      'startTokenPos' => 4037,
                      'startFilePos' => 53350,
                      'endTokenPos' => 4043,
                      'endFilePos' => 53365,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1371,
                      'endLine' => 1371,
                      'startTokenPos' => 4049,
                      'startFilePos' => 53377,
                      'endTokenPos' => 4049,
                      'endFilePos' => 53378,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1371,
            'endLine' => 1372,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Retrieve the external attributes of an entry defined by its name
 * @link https://www.php.net/manual/en/ziparchive.getexternalattributesname.php
 * @param string $name Name of the entry
 * @param int &$opsys On success, receive the operating system code defined by one of the ZipArchive::OPSYS_ constants.
 * @param int &$attr On success, receive the external attributes. Value depends on operating system.
 * @param int $flags [optional] If flags is set to ZipArchive::FL_UNCHANGED, the original unchanged attributes are returned.
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1363,
        'endLine' => 1375,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setExternalAttributesIndex' => 
      array (
        'name' => 'setExternalAttributesIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1388,
                      'endLine' => 1388,
                      'startTokenPos' => 4086,
                      'startFilePos' => 54254,
                      'endTokenPos' => 4092,
                      'endFilePos' => 54269,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1388,
                      'endLine' => 1388,
                      'startTokenPos' => 4098,
                      'startFilePos' => 54281,
                      'endTokenPos' => 4098,
                      'endFilePos' => 54282,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1388,
            'endLine' => 1389,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'opsys' => 
          array (
            'name' => 'opsys',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1390,
                      'endLine' => 1390,
                      'startTokenPos' => 4110,
                      'startFilePos' => 54376,
                      'endTokenPos' => 4116,
                      'endFilePos' => 54391,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1390,
                      'endLine' => 1390,
                      'startTokenPos' => 4122,
                      'startFilePos' => 54403,
                      'endTokenPos' => 4122,
                      'endFilePos' => 54404,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1390,
            'endLine' => 1391,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attr' => 
          array (
            'name' => 'attr',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1392,
                      'endLine' => 1392,
                      'startTokenPos' => 4134,
                      'startFilePos' => 54498,
                      'endTokenPos' => 4140,
                      'endFilePos' => 54513,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1392,
                      'endLine' => 1392,
                      'startTokenPos' => 4146,
                      'startFilePos' => 54525,
                      'endTokenPos' => 4146,
                      'endFilePos' => 54526,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1392,
            'endLine' => 1393,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1395,
                'endLine' => 1395,
                'startTokenPos' => 4180,
                'startFilePos' => 54676,
                'endTokenPos' => 4180,
                'endFilePos' => 54679,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1394,
                      'endLine' => 1394,
                      'startTokenPos' => 4158,
                      'startFilePos' => 54619,
                      'endTokenPos' => 4164,
                      'endFilePos' => 54634,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1394,
                      'endLine' => 1394,
                      'startTokenPos' => 4170,
                      'startFilePos' => 54646,
                      'endTokenPos' => 4170,
                      'endFilePos' => 54647,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1394,
            'endLine' => 1395,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Set the external attributes of an entry defined by its index
 * @link https://www.php.net/manual/en/ziparchive.setexternalattributesindex.php
 * @param int $index Index of the entry.
 * @param int $opsys The operating system code defined by one of the ZipArchive::OPSYS_ constants.
 * @param int $attr The external attributes. Value depends on operating system.
 * @param int $flags [optional] Optional flags. Currently unused.
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1386,
        'endLine' => 1398,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getExternalAttributesIndex' => 
      array (
        'name' => 'getExternalAttributesIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1411,
                      'endLine' => 1411,
                      'startTokenPos' => 4207,
                      'startFilePos' => 55629,
                      'endTokenPos' => 4213,
                      'endFilePos' => 55644,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1411,
                      'endLine' => 1411,
                      'startTokenPos' => 4219,
                      'startFilePos' => 55656,
                      'endTokenPos' => 4219,
                      'endFilePos' => 55657,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1411,
            'endLine' => 1412,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'opsys' => 
          array (
            'name' => 'opsys',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1413,
                      'endLine' => 1413,
                      'startTokenPos' => 4231,
                      'startFilePos' => 55751,
                      'endTokenPos' => 4237,
                      'endFilePos' => 55766,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1413,
                      'endLine' => 1413,
                      'startTokenPos' => 4243,
                      'startFilePos' => 55778,
                      'endTokenPos' => 4243,
                      'endFilePos' => 55779,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1413,
            'endLine' => 1414,
            'startColumn' => 13,
            'endColumn' => 23,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attr' => 
          array (
            'name' => 'attr',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1415,
                      'endLine' => 1415,
                      'startTokenPos' => 4256,
                      'startFilePos' => 55874,
                      'endTokenPos' => 4262,
                      'endFilePos' => 55889,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1415,
                      'endLine' => 1415,
                      'startTokenPos' => 4268,
                      'startFilePos' => 55901,
                      'endTokenPos' => 4268,
                      'endFilePos' => 55902,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1415,
            'endLine' => 1416,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1418,
                'endLine' => 1418,
                'startTokenPos' => 4303,
                'startFilePos' => 56053,
                'endTokenPos' => 4303,
                'endFilePos' => 56056,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1417,
                      'endLine' => 1417,
                      'startTokenPos' => 4281,
                      'startFilePos' => 55996,
                      'endTokenPos' => 4287,
                      'endFilePos' => 56011,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1417,
                      'endLine' => 1417,
                      'startTokenPos' => 4293,
                      'startFilePos' => 56023,
                      'endTokenPos' => 4293,
                      'endFilePos' => 56024,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1417,
            'endLine' => 1418,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Retrieve the external attributes of an entry defined by its index
 * @link https://www.php.net/manual/en/ziparchive.getexternalattributesindex.php
 * @param int $index Index of the entry.
 * @param int &$opsys On success, receive the operating system code defined by one of the ZipArchive::OPSYS_ constants.
 * @param int &$attr On success, receive the external attributes. Value depends on operating system.
 * @param int $flags [optional] If flags is set to ZipArchive::FL_UNCHANGED, the original unchanged attributes are returned.
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 1409,
        'endLine' => 1421,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'isEncryptionMethodSupported' => 
      array (
        'name' => 'isEncryptionMethodSupported',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1424,
                      'endLine' => 1424,
                      'startTokenPos' => 4345,
                      'startFilePos' => 56315,
                      'endTokenPos' => 4351,
                      'endFilePos' => 56330,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1424,
                      'endLine' => 1424,
                      'startTokenPos' => 4357,
                      'startFilePos' => 56342,
                      'endTokenPos' => 4357,
                      'endFilePos' => 56343,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1424,
            'endLine' => 1425,
            'startColumn' => 13,
            'endColumn' => 23,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'enc' => 
          array (
            'name' => 'enc',
            'default' => 
            array (
              'code' => '\\true',
              'attributes' => 
              array (
                'startLine' => 1427,
                'endLine' => 1427,
                'startTokenPos' => 4391,
                'startFilePos' => 56495,
                'endTokenPos' => 4391,
                'endFilePos' => 56498,
              ),
            ),
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'bool\']',
                    'attributes' => 
                    array (
                      'startLine' => 1426,
                      'endLine' => 1426,
                      'startTokenPos' => 4369,
                      'startFilePos' => 56438,
                      'endTokenPos' => 4375,
                      'endFilePos' => 56454,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1426,
                      'endLine' => 1426,
                      'startTokenPos' => 4381,
                      'startFilePos' => 56466,
                      'endTokenPos' => 4381,
                      'endFilePos' => 56467,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1426,
            'endLine' => 1427,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => true,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.0\' => \'bool\']',
                'attributes' => 
                array (
                  'startLine' => 1422,
                  'endLine' => 1422,
                  'startTokenPos' => 4317,
                  'startFilePos' => 56156,
                  'endTokenPos' => 4323,
                  'endFilePos' => 56172,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 1422,
                  'endLine' => 1422,
                  'startTokenPos' => 4329,
                  'startFilePos' => 56184,
                  'endTokenPos' => 4329,
                  'endFilePos' => 56185,
                ),
              ),
            ),
          ),
        ),
        'docComment' => NULL,
        'startLine' => 1422,
        'endLine' => 1430,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'isCompressionMethodSupported' => 
      array (
        'name' => 'isCompressionMethodSupported',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1433,
                      'endLine' => 1433,
                      'startTokenPos' => 4433,
                      'startFilePos' => 56758,
                      'endTokenPos' => 4439,
                      'endFilePos' => 56773,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1433,
                      'endLine' => 1433,
                      'startTokenPos' => 4445,
                      'startFilePos' => 56785,
                      'endTokenPos' => 4445,
                      'endFilePos' => 56786,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1433,
            'endLine' => 1434,
            'startColumn' => 13,
            'endColumn' => 23,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'enc' => 
          array (
            'name' => 'enc',
            'default' => 
            array (
              'code' => '\\true',
              'attributes' => 
              array (
                'startLine' => 1436,
                'endLine' => 1436,
                'startTokenPos' => 4479,
                'startFilePos' => 56938,
                'endTokenPos' => 4479,
                'endFilePos' => 56941,
              ),
            ),
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'bool\']',
                    'attributes' => 
                    array (
                      'startLine' => 1435,
                      'endLine' => 1435,
                      'startTokenPos' => 4457,
                      'startFilePos' => 56881,
                      'endTokenPos' => 4463,
                      'endFilePos' => 56897,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1435,
                      'endLine' => 1435,
                      'startTokenPos' => 4469,
                      'startFilePos' => 56909,
                      'endTokenPos' => 4469,
                      'endFilePos' => 56910,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1435,
            'endLine' => 1436,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => true,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.0\' => \'bool\']',
                'attributes' => 
                array (
                  'startLine' => 1431,
                  'endLine' => 1431,
                  'startTokenPos' => 4405,
                  'startFilePos' => 56598,
                  'endTokenPos' => 4411,
                  'endFilePos' => 56614,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 1431,
                  'endLine' => 1431,
                  'startTokenPos' => 4417,
                  'startFilePos' => 56626,
                  'endTokenPos' => 4417,
                  'endFilePos' => 56627,
                ),
              ),
            ),
          ),
        ),
        'docComment' => NULL,
        'startLine' => 1431,
        'endLine' => 1439,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'registerCancelCallback' => 
      array (
        'name' => 'registerCancelCallback',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'callable\']',
                    'attributes' => 
                    array (
                      'startLine' => 1443,
                      'endLine' => 1443,
                      'startTokenPos' => 4506,
                      'startFilePos' => 57199,
                      'endTokenPos' => 4512,
                      'endFilePos' => 57219,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1443,
                      'endLine' => 1443,
                      'startTokenPos' => 4518,
                      'startFilePos' => 57231,
                      'endTokenPos' => 4518,
                      'endFilePos' => 57232,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1443,
            'endLine' => 1444,
            'startColumn' => 13,
            'endColumn' => 30,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/** @betterReflectionTentativeReturnType */',
        'startLine' => 1441,
        'endLine' => 1447,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'registerProgressCallback' => 
      array (
        'name' => 'registerProgressCallback',
        'parameters' => 
        array (
          'rate' => 
          array (
            'name' => 'rate',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'float\']',
                    'attributes' => 
                    array (
                      'startLine' => 1451,
                      'endLine' => 1451,
                      'startTokenPos' => 4551,
                      'startFilePos' => 57525,
                      'endTokenPos' => 4557,
                      'endFilePos' => 57542,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1451,
                      'endLine' => 1451,
                      'startTokenPos' => 4563,
                      'startFilePos' => 57554,
                      'endTokenPos' => 4563,
                      'endFilePos' => 57555,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1451,
            'endLine' => 1452,
            'startColumn' => 13,
            'endColumn' => 23,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'callable\']',
                    'attributes' => 
                    array (
                      'startLine' => 1453,
                      'endLine' => 1453,
                      'startTokenPos' => 4575,
                      'startFilePos' => 57650,
                      'endTokenPos' => 4581,
                      'endFilePos' => 57670,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1453,
                      'endLine' => 1453,
                      'startTokenPos' => 4587,
                      'startFilePos' => 57682,
                      'endTokenPos' => 4587,
                      'endFilePos' => 57683,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1453,
            'endLine' => 1454,
            'startColumn' => 13,
            'endColumn' => 30,
            'parameterIndex' => 1,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/** @betterReflectionTentativeReturnType */',
        'startLine' => 1449,
        'endLine' => 1457,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setMtimeName' => 
      array (
        'name' => 'setMtimeName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1461,
                      'endLine' => 1461,
                      'startTokenPos' => 4620,
                      'startFilePos' => 57964,
                      'endTokenPos' => 4626,
                      'endFilePos' => 57982,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1461,
                      'endLine' => 1461,
                      'startTokenPos' => 4632,
                      'startFilePos' => 57994,
                      'endTokenPos' => 4632,
                      'endFilePos' => 57995,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1461,
            'endLine' => 1462,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'timestamp' => 
          array (
            'name' => 'timestamp',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1463,
                      'endLine' => 1463,
                      'startTokenPos' => 4644,
                      'startFilePos' => 58091,
                      'endTokenPos' => 4650,
                      'endFilePos' => 58106,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1463,
                      'endLine' => 1463,
                      'startTokenPos' => 4656,
                      'startFilePos' => 58118,
                      'endTokenPos' => 4656,
                      'endFilePos' => 58119,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1463,
            'endLine' => 1464,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1466,
                'endLine' => 1466,
                'startTokenPos' => 4690,
                'startFilePos' => 58274,
                'endTokenPos' => 4690,
                'endFilePos' => 58277,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1465,
                      'endLine' => 1465,
                      'startTokenPos' => 4668,
                      'startFilePos' => 58217,
                      'endTokenPos' => 4674,
                      'endFilePos' => 58232,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1465,
                      'endLine' => 1465,
                      'startTokenPos' => 4680,
                      'startFilePos' => 58244,
                      'endTokenPos' => 4680,
                      'endFilePos' => 58245,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1465,
            'endLine' => 1466,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/** @betterReflectionTentativeReturnType */',
        'startLine' => 1459,
        'endLine' => 1469,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setMtimeIndex' => 
      array (
        'name' => 'setMtimeIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1473,
                      'endLine' => 1473,
                      'startTokenPos' => 4717,
                      'startFilePos' => 58526,
                      'endTokenPos' => 4723,
                      'endFilePos' => 58541,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1473,
                      'endLine' => 1473,
                      'startTokenPos' => 4729,
                      'startFilePos' => 58553,
                      'endTokenPos' => 4729,
                      'endFilePos' => 58554,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1473,
            'endLine' => 1474,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'timestamp' => 
          array (
            'name' => 'timestamp',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1475,
                      'endLine' => 1475,
                      'startTokenPos' => 4741,
                      'startFilePos' => 58648,
                      'endTokenPos' => 4747,
                      'endFilePos' => 58663,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1475,
                      'endLine' => 1475,
                      'startTokenPos' => 4753,
                      'startFilePos' => 58675,
                      'endTokenPos' => 4753,
                      'endFilePos' => 58676,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1475,
            'endLine' => 1476,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1478,
                'endLine' => 1478,
                'startTokenPos' => 4787,
                'startFilePos' => 58831,
                'endTokenPos' => 4787,
                'endFilePos' => 58834,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1477,
                      'endLine' => 1477,
                      'startTokenPos' => 4765,
                      'startFilePos' => 58774,
                      'endTokenPos' => 4771,
                      'endFilePos' => 58789,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1477,
                      'endLine' => 1477,
                      'startTokenPos' => 4777,
                      'startFilePos' => 58801,
                      'endTokenPos' => 4777,
                      'endFilePos' => 58802,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1477,
            'endLine' => 1478,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/** @betterReflectionTentativeReturnType */',
        'startLine' => 1471,
        'endLine' => 1481,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'replaceFile' => 
      array (
        'name' => 'replaceFile',
        'parameters' => 
        array (
          'filepath' => 
          array (
            'name' => 'filepath',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1485,
                      'endLine' => 1485,
                      'startTokenPos' => 4814,
                      'startFilePos' => 59081,
                      'endTokenPos' => 4820,
                      'endFilePos' => 59099,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1485,
                      'endLine' => 1485,
                      'startTokenPos' => 4826,
                      'startFilePos' => 59111,
                      'endTokenPos' => 4826,
                      'endFilePos' => 59112,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1485,
            'endLine' => 1486,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1487,
                      'endLine' => 1487,
                      'startTokenPos' => 4838,
                      'startFilePos' => 59212,
                      'endTokenPos' => 4844,
                      'endFilePos' => 59227,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1487,
                      'endLine' => 1487,
                      'startTokenPos' => 4850,
                      'startFilePos' => 59239,
                      'endTokenPos' => 4850,
                      'endFilePos' => 59240,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1487,
            'endLine' => 1488,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'start' => 
          array (
            'name' => 'start',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1490,
                'endLine' => 1490,
                'startTokenPos' => 4884,
                'startFilePos' => 59391,
                'endTokenPos' => 4884,
                'endFilePos' => 59394,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1489,
                      'endLine' => 1489,
                      'startTokenPos' => 4862,
                      'startFilePos' => 59334,
                      'endTokenPos' => 4868,
                      'endFilePos' => 59349,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1489,
                      'endLine' => 1489,
                      'startTokenPos' => 4874,
                      'startFilePos' => 59361,
                      'endTokenPos' => 4874,
                      'endFilePos' => 59362,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1489,
            'endLine' => 1490,
            'startColumn' => 13,
            'endColumn' => 29,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'length' => 
          array (
            'name' => 'length',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1492,
                'endLine' => 1492,
                'startTokenPos' => 4912,
                'startFilePos' => 59521,
                'endTokenPos' => 4912,
                'endFilePos' => 59524,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1491,
                      'endLine' => 1491,
                      'startTokenPos' => 4890,
                      'startFilePos' => 59463,
                      'endTokenPos' => 4896,
                      'endFilePos' => 59478,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1491,
                      'endLine' => 1491,
                      'startTokenPos' => 4902,
                      'startFilePos' => 59490,
                      'endTokenPos' => 4902,
                      'endFilePos' => 59491,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1491,
            'endLine' => 1492,
            'startColumn' => 13,
            'endColumn' => 30,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 1494,
                'endLine' => 1494,
                'startTokenPos' => 4940,
                'startFilePos' => 59650,
                'endTokenPos' => 4940,
                'endFilePos' => 59653,
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
                      'name' => 'int',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1493,
                      'endLine' => 1493,
                      'startTokenPos' => 4918,
                      'startFilePos' => 59593,
                      'endTokenPos' => 4924,
                      'endFilePos' => 59608,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1493,
                      'endLine' => 1493,
                      'startTokenPos' => 4930,
                      'startFilePos' => 59620,
                      'endTokenPos' => 4930,
                      'endFilePos' => 59621,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1493,
            'endLine' => 1494,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/** @betterReflectionTentativeReturnType */',
        'startLine' => 1483,
        'endLine' => 1497,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'clearError' => 
      array (
        'name' => 'clearError',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.0\' => \'void\']',
                'attributes' => 
                array (
                  'startLine' => 1498,
                  'endLine' => 1498,
                  'startTokenPos' => 4954,
                  'startFilePos' => 59753,
                  'endTokenPos' => 4960,
                  'endFilePos' => 59769,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 1498,
                  'endLine' => 1498,
                  'startTokenPos' => 4966,
                  'startFilePos' => 59781,
                  'endTokenPos' => 4966,
                  'endFilePos' => 59782,
                ),
              ),
            ),
          ),
        ),
        'docComment' => NULL,
        'startLine' => 1498,
        'endLine' => 1501,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'setArchiveFlag' => 
      array (
        'name' => 'setArchiveFlag',
        'parameters' => 
        array (
          'flag' => 
          array (
            'name' => 'flag',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.3\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1509,
                      'endLine' => 1509,
                      'startTokenPos' => 5016,
                      'startFilePos' => 60155,
                      'endTokenPos' => 5022,
                      'endFilePos' => 60170,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1509,
                      'endLine' => 1509,
                      'startTokenPos' => 5028,
                      'startFilePos' => 60182,
                      'endTokenPos' => 5028,
                      'endFilePos' => 60183,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1509,
            'endLine' => 1510,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.3\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1511,
                      'endLine' => 1511,
                      'startTokenPos' => 5040,
                      'startFilePos' => 60276,
                      'endTokenPos' => 5046,
                      'endFilePos' => 60291,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1511,
                      'endLine' => 1511,
                      'startTokenPos' => 5052,
                      'startFilePos' => 60303,
                      'endTokenPos' => 5052,
                      'endFilePos' => 60304,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1511,
            'endLine' => 1512,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 1,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.0\' => \'bool\']',
                'attributes' => 
                array (
                  'startLine' => 1507,
                  'endLine' => 1507,
                  'startTokenPos' => 4990,
                  'startFilePos' => 60016,
                  'endTokenPos' => 4996,
                  'endFilePos' => 60032,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 1507,
                  'endLine' => 1507,
                  'startTokenPos' => 5002,
                  'startFilePos' => 60044,
                  'endTokenPos' => 5002,
                  'endFilePos' => 60045,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * @param int $flag
 * @param int $value
 * @return bool
 */',
        'startLine' => 1507,
        'endLine' => 1515,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getArchiveFlag' => 
      array (
        'name' => 'getArchiveFlag',
        'parameters' => 
        array (
          'flag' => 
          array (
            'name' => 'flag',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.3\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1523,
                      'endLine' => 1523,
                      'startTokenPos' => 5100,
                      'startFilePos' => 60671,
                      'endTokenPos' => 5106,
                      'endFilePos' => 60686,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1523,
                      'endLine' => 1523,
                      'startTokenPos' => 5112,
                      'startFilePos' => 60698,
                      'endTokenPos' => 5112,
                      'endFilePos' => 60699,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1523,
            'endLine' => 1524,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1526,
                'endLine' => 1526,
                'startTokenPos' => 5146,
                'startFilePos' => 60849,
                'endTokenPos' => 5146,
                'endFilePos' => 60849,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.3\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1525,
                      'endLine' => 1525,
                      'startTokenPos' => 5124,
                      'startFilePos' => 60792,
                      'endTokenPos' => 5130,
                      'endFilePos' => 60807,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1525,
                      'endLine' => 1525,
                      'startTokenPos' => 5136,
                      'startFilePos' => 60819,
                      'endTokenPos' => 5136,
                      'endFilePos' => 60820,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1525,
            'endLine' => 1526,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.0\' => \'int\']',
                'attributes' => 
                array (
                  'startLine' => 1521,
                  'endLine' => 1521,
                  'startTokenPos' => 5074,
                  'startFilePos' => 60533,
                  'endTokenPos' => 5080,
                  'endFilePos' => 60548,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 1521,
                  'endLine' => 1521,
                  'startTokenPos' => 5086,
                  'startFilePos' => 60560,
                  'endTokenPos' => 5086,
                  'endFilePos' => 60561,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * @param int $flag
 * @param int $flags
 * @return int
 */',
        'startLine' => 1521,
        'endLine' => 1529,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
        'aliasName' => NULL,
      ),
      'getStreamName' => 
      array (
        'name' => 'getStreamName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.2\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 1536,
                      'endLine' => 1536,
                      'startTokenPos' => 5169,
                      'startFilePos' => 61099,
                      'endTokenPos' => 5175,
                      'endFilePos' => 61117,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1536,
                      'endLine' => 1536,
                      'startTokenPos' => 5181,
                      'startFilePos' => 61129,
                      'endTokenPos' => 5181,
                      'endFilePos' => 61130,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1536,
            'endLine' => 1537,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 1539,
                'endLine' => 1539,
                'startTokenPos' => 5215,
                'startFilePos' => 61283,
                'endTokenPos' => 5215,
                'endFilePos' => 61283,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.2\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 1538,
                      'endLine' => 1538,
                      'startTokenPos' => 5193,
                      'startFilePos' => 61226,
                      'endTokenPos' => 5199,
                      'endFilePos' => 61241,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 1538,
                      'endLine' => 1538,
                      'startTokenPos' => 5205,
                      'startFilePos' => 61253,
                      'endTokenPos' => 5205,
                      'endFilePos' => 61254,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 1538,
            'endLine' => 1539,
            'startColumn' => 13,
            'endColumn' => 26,
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
 * @param string $name
 * @param int $flags
 * @return void
 */',
        'startLine' => 1535,
        'endLine' => 1542,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ZipArchive',
        'implementingClassName' => 'ZipArchive',
        'currentClassName' => 'ZipArchive',
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