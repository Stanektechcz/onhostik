<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/PendingDispatch.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Foundation\Bus\PendingDispatch
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-b131bb2536b6df1e0527a1936e388c1f9c41eb81221b3800d66a30711c308e21-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/PendingDispatch.php',
      ),
    ),
    'namespace' => 'Illuminate\\Foundation\\Bus',
    'name' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
    'shortName' => 'PendingDispatch',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 313,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Conditionable',
      1 => 'Illuminate\\Foundation\\Queue\\InteractsWithUniqueJobs',
      2 => 'Illuminate\\Queue\\Attributes\\ReadsQueueAttributes',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'job' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'name' => 'job',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The job.
 *
 * @var mixed
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'afterResponse' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'name' => 'afterResponse',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 108,
            'startFilePos' => 938,
            'endTokenPos' => 108,
            'endFilePos' => 942,
          ),
        ),
        'docComment' => '/**
 * Indicates if the job should be dispatched immediately after sending the response.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
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
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 33,
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
 * Create a new pending job dispatch.
 *
 * @param  mixed  $job
 */',
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'onConnection' => 
      array (
        'name' => 'onConnection',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 34,
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
 * Set the desired connection for the job.
 *
 * @param  \\BackedEnum|string|null  $connection
 * @return $this
 */',
        'startLine' => 53,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'onQueue' => 
      array (
        'name' => 'onQueue',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 29,
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
 * Set the desired queue for the job.
 *
 * @param  \\BackedEnum|string|null  $queue
 * @return $this
 */',
        'startLine' => 66,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'onGroup' => 
      array (
        'name' => 'onGroup',
        'parameters' => 
        array (
          'group' => 
          array (
            'name' => 'group',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 81,
            'endLine' => 81,
            'startColumn' => 29,
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
 * Set the desired job "group".
 *
 * This feature is only supported by some queues, such as Amazon SQS.
 *
 * @param  \\UnitEnum|string  $group
 * @return $this
 */',
        'startLine' => 81,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'withDeduplicator' => 
      array (
        'name' => 'withDeduplicator',
        'parameters' => 
        array (
          'deduplicator' => 
          array (
            'name' => 'deduplicator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 96,
            'endLine' => 96,
            'startColumn' => 38,
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
 * Set the desired job deduplicator callback.
 *
 * This feature is only supported by some queues, such as Amazon SQS FIFO.
 *
 * @param  callable|null  $deduplicator
 * @return $this
 */',
        'startLine' => 96,
        'endLine' => 101,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'allOnConnection' => 
      array (
        'name' => 'allOnConnection',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 37,
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
 * Set the desired connection for the chain.
 *
 * @param  \\BackedEnum|string|null  $connection
 * @return $this
 */',
        'startLine' => 109,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'allOnQueue' => 
      array (
        'name' => 'allOnQueue',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 122,
            'endLine' => 122,
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
 * Set the desired queue for the chain.
 *
 * @param  \\BackedEnum|string|null  $queue
 * @return $this
 */',
        'startLine' => 122,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'delay' => 
      array (
        'name' => 'delay',
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 27,
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
 * Set the desired delay in seconds for the job.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int|null  $delay
 * @return $this
 */',
        'startLine' => 135,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'withoutDelay' => 
      array (
        'name' => 'withoutDelay',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the delay for the job to zero seconds.
 *
 * @return $this
 */',
        'startLine' => 147,
        'endLine' => 152,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'afterCommit' => 
      array (
        'name' => 'afterCommit',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that the job should be dispatched after all database transactions have committed.
 *
 * @return $this
 */',
        'startLine' => 159,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'beforeCommit' => 
      array (
        'name' => 'beforeCommit',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that the job should not wait until database transactions have been committed before dispatching.
 *
 * @return $this
 */',
        'startLine' => 171,
        'endLine' => 176,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'chain' => 
      array (
        'name' => 'chain',
        'parameters' => 
        array (
          'chain' => 
          array (
            'name' => 'chain',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 184,
            'endLine' => 184,
            'startColumn' => 27,
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
 * Set the jobs that should run if this job is successful.
 *
 * @param  array  $chain
 * @return $this
 */',
        'startLine' => 184,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'afterResponse' => 
      array (
        'name' => 'afterResponse',
        'parameters' => 
        array (
          'afterResponse' => 
          array (
            'name' => 'afterResponse',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 197,
                'endLine' => 197,
                'startTokenPos' => 471,
                'startFilePos' => 4279,
                'endTokenPos' => 471,
                'endFilePos' => 4282,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 197,
            'endLine' => 197,
            'startColumn' => 35,
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
 * Indicate that the job should be dispatched after the response is sent to the browser.
 *
 * @param  bool  $afterResponse
 * @return $this
 */',
        'startLine' => 197,
        'endLine' => 202,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'shouldDispatch' => 
      array (
        'name' => 'shouldDispatch',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the job should be dispatched.
 *
 * @return bool
 */',
        'startLine' => 209,
        'endLine' => 232,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'acquireDebounceLock' => 
      array (
        'name' => 'acquireDebounceLock',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Acquire a debounce lock for the job and set its delay.
 *
 * @return void
 *
 * @throws LogicException
 */',
        'startLine' => 241,
        'endLine' => 264,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'aliasName' => NULL,
      ),
      'getJob' => 
      array (
        'name' => 'getJob',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the underlying job instance.
 *
 * @return mixed
 */',
        'startLine' => 271,
        'endLine' => 274,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
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
            'startLine' => 283,
            'endLine' => 283,
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
            'startLine' => 283,
            'endLine' => 283,
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
 * Dynamically proxy methods to the underlying job.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return $this
 */',
        'startLine' => 283,
        'endLine' => 288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
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
 * Handle the object\'s destruction.
 *
 * @return void
 */',
        'startLine' => 295,
        'endLine' => 312,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingDispatch',
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