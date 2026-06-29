<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use PH2M\OrderWithdrawal\Model\Config;
use PH2M\OrderWithdrawal\Model\ItemProvider;
use PH2M\OrderWithdrawal\Model\Registry;

class Form extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly ItemProvider $itemProvider,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->registry->getOrder();
    }

    /**
     * All visible order items (fresh ones included, displayed grayed out).
     *
     * @return OrderItemInterface[]
     */
    public function getItems(): array
    {
        $order = $this->getOrder();

        return $order ? $this->itemProvider->getAllVisibleItems($order) : [];
    }

    public function isExcludedItem(OrderItemInterface $item): bool
    {
        return $this->itemProvider->isExcludedItem($item);
    }

    public function hasWithdrawableItems(): bool
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }

        return count($this->itemProvider->getWithdrawableItems($order)) > 0;
    }

    /**
     * @return string[]
     */
    public function getReasons(): array
    {
        $order = $this->getOrder();

        return $this->config->getReasons($order ? (int) $order->getStoreId() : null);
    }

    /**
     * @return array<string, string[]|null>
     */
    public function getQuestions(): array
    {
        $order = $this->getOrder();

        return $this->config->getQuestions($order ? (int) $order->getStoreId() : null);
    }

    public function getFormattedOrderDate(): string
    {
        $order = $this->getOrder();

        return $order ? $this->formatDate($order->getCreatedAt(), \IntlDateFormatter::SHORT) : '';
    }

    public function getProductImageUrl(OrderItemInterface $item): string
    {
        return $this->itemProvider->getImagePath($item);
    }

    public function getSubmitUrl(): string
    {
        $order = $this->getOrder();

        return $this->getUrl('withdrawal/order/submit', ['order_id' => $order ? (int) $order->getEntityId() : 0]);
    }

    public function getBackUrl(): string
    {
        $order = $this->getOrder();

        return $this->getUrl('sales/order/view', ['order_id' => $order ? (int) $order->getEntityId() : 0]);
    }
}
