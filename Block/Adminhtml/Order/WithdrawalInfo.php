<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use PH2M\OrderWithdrawal\Model\Config;

class WithdrawalInfo extends Template
{
    private ?OrderInterface $order       = null;
    private bool            $orderLoaded = false;

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly Config                      $config,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder       $searchCriteriaBuilder,
        private readonly SortOrderBuilder            $sortOrderBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        if ($this->orderLoaded) {
            return $this->order;
        }

        $this->orderLoaded = true;
        $orderId = (int) $this->getRequest()->getParam('order_id');

        if ($orderId === 0) {
            return null;
        }

        try {
            $this->order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            $this->order = null;
        }

        return $this->order;
    }

    public function isEnabled(): bool
    {
        $order = $this->getOrder();
        return $order !== null && $this->config->isEnabled((int) $order->getStoreId());
    }

    public function getDelayDays(): int
    {
        $order = $this->getOrder();
        return $order !== null ? $this->config->getDelayDays((int) $order->getStoreId()) : 0;
    }

    public function isShipmentDateEnabled(): bool
    {
        $order = $this->getOrder();
        return $order !== null && $this->config->isShipmentDateEnabled((int) $order->getStoreId());
    }

    /**
     * Returns the creation date of the most recent shipment, or null if none.
     */
    public function getLastShipmentCreatedAt(): ?string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return null;
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField('created_at')
            ->setDirection(SortOrder::SORT_DESC)
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $order->getEntityId())
            ->addSortOrder($sortOrder)
            ->setPageSize(1)
            ->create();

        $shipments = $this->shipmentRepository->getList($searchCriteria);

        if ($shipments->getTotalCount() > 0) {
            $items        = $shipments->getItems();
            $lastShipment = reset($items);
            $createdAt    = (string) $lastShipment->getCreatedAt();

            return $createdAt !== '' ? $createdAt : null;
        }

        return null;
    }

    /**
     * Returns the start date of the withdrawal window.
     * Uses the last shipment date when the feature flag is on and a shipment exists,
     * otherwise falls back to the order creation date.
     */
    public function getStartDate(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '';
        }

        if ($this->isShipmentDateEnabled()) {
            $shipmentDate = $this->getLastShipmentCreatedAt();
            if ($shipmentDate !== null) {
                return $shipmentDate;
            }
        }

        return (string) $order->getCreatedAt();
    }

    /**
     * Returns the deadline of the withdrawal window (start date + delay days).
     */
    public function getEndDate(): string
    {
        $startDate = $this->getStartDate();
        $delayDays = $this->getDelayDays();

        if ($startDate === '' || $delayDays <= 0) {
            return '';
        }

        $startTimestamp = strtotime($startDate);
        if ($startTimestamp === false) {
            return '';
        }

        return date('Y-m-d H:i:s', $startTimestamp + ($delayDays * 86400));
    }

    public function isBasedOnShipment(): bool
    {
        return $this->isShipmentDateEnabled() && $this->getLastShipmentCreatedAt() !== null;
    }

    public function isWindowActive(): bool
    {
        $endDate = $this->getEndDate();
        if ($endDate === '') {
            return false;
        }

        $endTimestamp = strtotime($endDate);
        return $endTimestamp !== false && time() <= $endTimestamp;
    }

    protected function _toHtml(): string
    {
        if (!$this->isEnabled() || $this->getDelayDays() <= 0) {
            return '';
        }

        return parent::_toHtml();
    }
}
