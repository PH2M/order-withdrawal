<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;

class Eligibility
{
    public function __construct(
        private readonly Config                     $config,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder       $searchCriteriaBuilder,
        private readonly SortOrderBuilder            $sortOrderBuilder,
    ) {
    }

    public function canWithdraw(OrderInterface $order): bool
    {
        $storeId = (int) $order->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            return false;
        }

        $eligibleStatuses = $this->config->getEligibleStatuses($storeId);
        if ($eligibleStatuses && !in_array((string) $order->getStatus(), $eligibleStatuses, true)) {
            return false;
        }

        return $this->isWithinWindow($order);
    }

    private function isWithinWindow(OrderInterface $order): bool
    {
        $startDate = $this->resolveStartDate($order);
        $delayDays = $this->config->getDelayDays((int) $order->getStoreId());

        if ($delayDays <= 0 || $startDate === '') {
            return false;
        }

        $startTimestamp = strtotime($startDate);
        if ($startTimestamp === false) {
            return false;
        }

        return time() <= ($startTimestamp + ($delayDays * 86400));
    }

    /**
     * Returns the last shipment creation date if the feature flag is on and a shipment exists,
     * otherwise falls back to the order creation date.
     */
    private function resolveStartDate(OrderInterface $order): string
    {
        $storeId = (int) $order->getStoreId();

        if ($this->config->isShipmentDateEnabled($storeId)) {
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
                if ($createdAt !== '') {
                    return $createdAt;
                }
            }
        }

        return (string) $order->getCreatedAt();
    }
}
