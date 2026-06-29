<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use Magento\Framework\Model\AbstractModel;
use PH2M\OrderWithdrawal\Model\ResourceModel\WithdrawalItem as WithdrawalItemResource;

class WithdrawalItem extends AbstractModel
{
    public const WITHDRAWAL_ID = 'withdrawal_id';
    public const ORDER_ITEM_ID = 'order_item_id';
    public const SKU = 'sku';
    public const PRODUCT_NAME = 'product_name';
    public const QTY = 'qty';
    public const REASON = 'reason';

    protected function _construct(): void
    {
        $this->_init(WithdrawalItemResource::class);
    }
}
