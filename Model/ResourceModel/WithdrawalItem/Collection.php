<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model\ResourceModel\WithdrawalItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PH2M\OrderWithdrawal\Model\ResourceModel\WithdrawalItem as WithdrawalItemResource;
use PH2M\OrderWithdrawal\Model\WithdrawalItem;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(WithdrawalItem::class, WithdrawalItemResource::class);
    }
}
