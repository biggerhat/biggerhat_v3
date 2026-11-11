<?php

namespace App\Services;

use RuntimeException;

/** A top-cut action the bracket cannot do right now; the message is meant for the organizer. */
class CutException extends RuntimeException {}
