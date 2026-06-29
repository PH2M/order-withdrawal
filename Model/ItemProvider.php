<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use PH2M\OrderWithdrawal\Setup\Patch\Data\AddIsWithdrawableProductAttribute;

class ItemProvider
{
    public function __construct(
        private readonly ProductResource $productResource
    ) {
    }

    /**
     * Order items eligible for withdrawal (products excluded).
     *
     * @return OrderItemInterface[]
     */
    public function getWithdrawableItems(OrderInterface $order): array
    {
        $items = [];
        $storeId = (int) $order->getStoreId();

        foreach ($order->getAllVisibleItems() as $item) {
            if ($this->isExcluded((int) $item->getProductId(), $storeId)) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * All visible order items regardless of freshness.
     *
     * @return OrderItemInterface[]
     */
    public function getAllVisibleItems(OrderInterface $order): array
    {
        return array_values($order->getAllVisibleItems());
    }

    public function isExcludedItem(OrderItemInterface $item): bool
    {
        return $this->isExcluded((int) $item->getProductId(), (int) $item->getStoreId());
    }

    public function getImagePath(OrderItemInterface $item): ?string
    {
        $productId = (int) $item->getProductId();
        if ($productId === 0) {
            return null;
        }

        $value = $this->productResource->getAttributeRawValue($productId, 'image', (int) $item->getStoreId());

        if (!is_string($value) || $value === '' || $value === 'no_selection') {
            return null;
        }

        return $value;
    }

    private function isExcluded(int $productId, int $storeId): bool
    {
        if ($productId === 0) {
            return false;
        }

        $value = $this->productResource->getAttributeRawValue($productId,
            AddIsWithdrawableProductAttribute::ATTRIBUTE_CODE,
            $storeId
        );

        return (int) $value === 1;
    }
}
