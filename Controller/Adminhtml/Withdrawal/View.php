<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Controller\Adminhtml\Withdrawal;

use PH2M\OrderWithdrawal\Model\WithdrawalFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use PH2M\OrderWithdrawal\Model\ResourceModel\Withdrawal as WithdrawalResource;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'PH2M_OrderWithdrawal::withdrawal';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly WithdrawalFactory $withdrawalFactory,
        private readonly WithdrawalResource $withdrawalResource,
        private readonly Registry $coreRegistry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        $withdrawal = $this->withdrawalFactory->create();
        $this->withdrawalResource->load($withdrawal, $id);

        if (!$withdrawal->getId()) {
            $this->messageManager->addErrorMessage((string) __('This withdrawal request no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $this->coreRegistry->register('cdb_current_withdrawal', $withdrawal);

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->getConfig()->getTitle()->prepend(
            (string) __('Withdrawal request #%1', $withdrawal->getId())
        );

        return $resultPage;
    }
}
