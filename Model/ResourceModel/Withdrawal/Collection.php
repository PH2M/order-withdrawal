<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model\ResourceModel\Withdrawal;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PH2M\OrderWithdrawal\Model\ResourceModel\Withdrawal as WithdrawalResource;
use PH2M\OrderWithdrawal\Model\Withdrawal;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(Withdrawal::class, WithdrawalResource::class);
    }
}
