<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Exception;

use RuntimeException;

/**
 * Le BackOffice est injoignable, refuse le jeton de l'entité ou renvoie une réponse inattendue.
 */
class BoApiException extends RuntimeException
{
}
