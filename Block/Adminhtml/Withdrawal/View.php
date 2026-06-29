<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Block\Adminhtml\Withdrawal;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use PH2M\OrderWithdrawal\Model\Withdrawal;

class View extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $coreRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWithdrawal(): ?Withdrawal
    {
        $withdrawal = $this->coreRegistry->registry('cdb_current_withdrawal');

        return $withdrawal instanceof Withdrawal ? $withdrawal : null;
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getOrderUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }
}
