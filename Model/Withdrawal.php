<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use PH2M\OrderWithdrawal\Model\ResourceModel\WithdrawalItem\CollectionFactory as ItemCollectionFactory;
use Magento\Framework\Model\AbstractModel;
use PH2M\OrderWithdrawal\Model\ResourceModel\Withdrawal as WithdrawalResource;
use PH2M\OrderWithdrawal\Model\ResourceModel\WithdrawalItem\Collection as ItemCollection;
use PH2M\OrderWithdrawal\Model\WithdrawalItem;

class Withdrawal extends AbstractModel
{
    public const ORDER_ID = 'order_id';
    public const INCREMENT_ID = 'increment_id';
    public const CUSTOMER_ID = 'customer_id';
    public const STORE_ID = 'store_id';
    public const CREATED_AT = 'created_at';

    /**
     * @var WithdrawalItem[]|null
     */
    private ?array $items = null;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct(): void
    {
        $this->_init(WithdrawalResource::class);
    }

    /**
     * @return WithdrawalItem[]
     */
    public function getItems(): array
    {
        if ($this->items === null) {
            /** @var ItemCollection $collection */
            $collection = $this->itemCollectionFactory->create();
            $collection->addFieldToFilter(WithdrawalItem::WITHDRAWAL_ID, ['eq' => (int) $this->getId()]);
            /** @var WithdrawalItem[] $items */
            $items = array_values($collection->getItems());
            $this->items = $items;
        }

        return $this->items;
    }
}
