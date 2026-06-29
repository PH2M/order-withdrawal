<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PH2M\OrderWithdrawal\Controller\Order\AbstractOrder;
use PH2M\OrderWithdrawal\Model\Eligibility;
use PH2M\OrderWithdrawal\Model\Registry;

class Form extends AbstractOrder implements HttpGetActionInterface
{
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CustomerSession $customerSession,
        OrderRepositoryInterface $orderRepository,
        Eligibility $eligibility,
        ManagerInterface $messageManager,
        private readonly Registry $registry
    ) {
        parent::__construct($request, $resultFactory, $customerSession, $orderRepository, $eligibility, $messageManager);
    }

    public function execute(): ResultInterface
    {
        if ($redirect = $this->requireCustomerRedirect()) {
            return $redirect;
        }

        $order = $this->loadEligibleOrder();
        if ($order === null) {
            $this->messageManager->addErrorMessage((string) __('This order is not eligible for withdrawal.'));

            return $this->redirectToHistory();
        }

        $this->registry->setOrder($order);

        /** @var \Magento\Framework\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->getConfig()->getTitle()->set(
            (string) __('Withdrawal request - Order %1', $order->getIncrementId())
        );

        return $page;
    }
}
