<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WithdrawalItem extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('ph2m_order_withdrawal_item', 'entity_id');
    }
}
