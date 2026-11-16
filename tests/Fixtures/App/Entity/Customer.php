<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Customer
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $id,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
