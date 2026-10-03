<?php

namespace Modules\Shared\Exceptions;

class InvalidStateTransition extends DomainException
{
    public static function from(string $subject, string $from, string $to): self
    {
        return new self("Cannot change {$subject} from {$from} to {$to}.");
    }
}
