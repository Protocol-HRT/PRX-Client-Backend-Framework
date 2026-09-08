<?php

namespace App\Actions\Exceptions;

use RuntimeException;

/**
 * A failure whose MESSAGE was deliberately written to be shown to the caller.
 *
 * That is the whole distinction, and it is load-bearing. `CheckoutController`
 * used to relay `$e->getMessage()` from any `RuntimeException` straight to the
 * browser on a 422, which is a rule about a PHP class rather than about who a
 * sentence was written for. Three different things went out through it:
 *
 *   * messages genuinely meant for a shopper ("Cart is empty.") — correct;
 *   * an operator diagnostic telling a customer to map the catalog and naming
 *     our provider id columns;
 *   * `PrescribeRxException`, which is also a `RuntimeException` and carries
 *     the clinical provider's own error text — absolute filesystem paths, and
 *     on one endpoint the full SQL statement with a patient_chart_id in it.
 *
 * So: throw this ONLY with a sentence you would be happy to show a customer.
 * Anything else stays an ordinary exception, gets logged, and the caller is
 * told something generic.
 */
class ActionException extends RuntimeException
{
    /**
     * @param  int  $code  MUST be a valid HTTP status. `CheckoutController`
     *                     passes it straight to `response()->json()`, so a
     *                     non-HTTP code here throws and 500s the checkout.
     */
    public static function failed(string $message, int $code = 422): self
    {
        return new self($message, $code);
    }
}
