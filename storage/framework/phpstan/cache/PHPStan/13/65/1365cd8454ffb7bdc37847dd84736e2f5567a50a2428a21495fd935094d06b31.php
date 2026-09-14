<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Files\FileStore.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Files\FileStore
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-a4276920b5572cc9678ad09aee506adcbda5222e3432faec245c3d5094058f90',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Files\\FileStore',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Files/FileStore.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Files',
    'name' => 'Onhost\\Platform\\Files\\FileStore',
    'shortName' => 'FileStore',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The one disk behind customer files (audit §5q-4): marketplace evidence and data exports live on
 * `ONHOST_FILES_DISK` — the local private disk by default, an S3-compatible bucket in production. A download is a
 * short signed link when the disk can sign (S3, MinIO, R2) and a stream otherwise, so the application server never
 * proxies large files; retention deletes evidence older than the configured months and exports past their expiry.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 86,
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
      'EVIDENCE_PREFIX' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'implementingClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'name' => 'EVIDENCE_PREFIX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'marketplace-evidence\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 68,
            'startFilePos' => 922,
            'endTokenPos' => 68,
            'endFilePos' => 943,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 58,
      ),
      'EXPORT_PREFIX' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'implementingClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'name' => 'EXPORT_PREFIX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'exports\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 79,
            'startFilePos' => 980,
            'endTokenPos' => 79,
            'endFilePos' => 988,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'disk' => 
      array (
        'name' => 'disk',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Contracts\\Filesystem\\Filesystem',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'implementingClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'currentClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'aliasName' => NULL,
      ),
      'diskName' => 
      array (
        'name' => 'diskName',
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
        ),
        'docComment' => NULL,
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'implementingClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'currentClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'aliasName' => NULL,
      ),
      'download' => 
      array (
        'name' => 'download',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 30,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 44,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'mime' => 
          array (
            'name' => 'mime',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 58,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 38,
                'endLine' => 38,
                'startTokenPos' => 173,
                'startFilePos' => 1423,
                'endTokenPos' => 174,
                'endFilePos' => 1424,
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 72,
            'endColumn' => 90,
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
            'name' => 'Symfony\\Component\\HttpFoundation\\Response',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** A signed link when the disk provides them (S3 and friends), otherwise a stream through the application. */',
        'startLine' => 38,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'implementingClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'currentClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'aliasName' => NULL,
      ),
      'prune' => 
      array (
        'name' => 'prune',
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
 * Retention (rule `files.prune`): evidence files older than `evidence_retention_months`, empty evidence folders,
 * export files whose request expired (the compliance service already forgets the row\'s path).
 *
 * @return array{evidence_deleted:int, exports_deleted:int, disk:string}
 */',
        'startLine' => 61,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Files',
        'declaringClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'implementingClassName' => 'Onhost\\Platform\\Files\\FileStore',
        'currentClassName' => 'Onhost\\Platform\\Files\\FileStore',
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