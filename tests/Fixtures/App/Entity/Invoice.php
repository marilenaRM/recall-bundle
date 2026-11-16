<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Invoice
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $id,
        #[ORM\Column(length: 32)]
        private string $number,
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        private Customer $customer,
    ) {
    }

    public function describe(): string
    {
        return \sprintf('#%d %s for customer %d', $this->id, $this->number, $this->customer->id());
    }
}
