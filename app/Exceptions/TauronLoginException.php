<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Tauron refused the login: wrong credentials or a temporary block. Retrying soon only
 * makes it worse (each attempt during a block extends it by 8 hours).
 */
class TauronLoginException extends RuntimeException {}
