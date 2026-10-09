<?php

namespace App\Exceptions;

use DomainException;

/**
 * A refusal by the application-tracking domain. The HTTP layer maps each
 * subclass to a client error; none of them indicates a server fault.
 */
abstract class ApplicationException extends DomainException {}
