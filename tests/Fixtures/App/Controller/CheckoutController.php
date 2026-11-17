<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fixtures\App\Controller;

use MarilenaRM\RecallBundle\Tests\Fixtures\App\Attribute\Audited;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutController
{
    /**
     * Orders are confirmed only once the payment provider called back.
     */
    #[Audited('payments', 'checkout', level: 2)]
    public function confirm(string $reference): Response
    {
        return new Response('pending '.$reference);
    }
}
