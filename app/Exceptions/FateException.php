<?php

namespace App\Exceptions;

use DomainException;

/** A Fate action the rules or the table's state do not allow (a card that is not where it was said to be, a hand over the limit). Becomes a 422. */
class FateException extends DomainException {}
