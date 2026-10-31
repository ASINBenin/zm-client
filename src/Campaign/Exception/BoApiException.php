<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Exception;

use Monarc\Core\Exception\Exception;
use RuntimeException;

/**
 * Le BackOffice est injoignable, refuse le jeton de l'entité ou renvoie une réponse inattendue.
 */
class BoApiException extends RuntimeException
{
    /**
     * Erreur destinée à l'utilisateur : le motif du BO pour un refus (412), sinon un BO injoignable (503).
     */
    public function toUserFacingException(): Exception
    {
        return $this->getCode() === 412
            ? new Exception($this->getMessage(), 412, $this)
            : new Exception('Le Back-Office est injoignable, réessayez plus tard.', 503, $this);
    }
}
