<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PH2M\OrderWithdrawal\Model\Config;
use PH2M\OrderWithdrawal\Model\Eligibility;

class Withdrawal implements ArgumentInterface
{
    public function __construct(
        private readonly Eligibility $eligibility,
        private readonly Config $config,
        private readonly UrlInterface $url
    ) {
    }

    public function canWithdraw(OrderInterface $order): bool
    {
        return $this->eligibility->canWithdraw($order);
    }

    public function getWithdrawalUrl(OrderInterface $order): string
    {
        return $this->url->getUrl('withdrawal/order/form', ['order_id' => (int) $order->getEntityId()]);
    }

    /**
     * @return string[]
     */
    public function getReasons(?int $storeId = null): array
    {
        return $this->config->getReasons($storeId);
    }
}
