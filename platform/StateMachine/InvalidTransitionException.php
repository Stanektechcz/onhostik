<?php

declare(strict_types=1);

namespace Onhost\Platform\StateMachine;

use DomainException;

final class InvalidTransitionException extends DomainException {}
