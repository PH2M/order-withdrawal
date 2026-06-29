<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use Magento\Sales\Api\Data\OrderInterface;

class Registry
{
    private ?OrderInterface $order = null;

    public function setOrder(OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }
}
