<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

interface FactExtractorInterface
{
    /**
     * @return iterable<Fact>
     */
    public function extract(): iterable;
}
