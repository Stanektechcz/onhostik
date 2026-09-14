<?php

declare(strict_types=1);

namespace Onhost\Platform\Eloquent;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Base model for every ONhost domain entity. Mass-assignment is governed by the
 * service layer (commands/actions), so models are unguarded on purpose; HTTP
 * input never reaches a model without passing a FormRequest first.
 */
abstract class Model extends EloquentModel
{
    use HasPrefixedUlid;

    protected static string $idPrefix = 'id';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}
