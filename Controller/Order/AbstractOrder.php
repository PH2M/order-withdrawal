<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PH2M\OrderWithdrawal\Model\Eligibility;

abstract class AbstractOrder
{
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly ResultFactory $resultFactory,
        protected readonly CustomerSession $customerSession,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly Eligibility $eligibility,
        protected readonly ManagerInterface $messageManager
    ) {
    }

    abstract public function execute(): ResultInterface;

    protected function loadEligibleOrder(): ?OrderInterface
    {
        $orderId = (int) $this->request->getParam('order_id');
        if ($orderId === 0) {
            return null;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            return null;
        }

        if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
            return null;
        }

        if (!$this->eligibility->canWithdraw($order)) {
            return null;
        }

        return $order;
    }

    protected function requireCustomerRedirect(): ?Redirect
    {
        if ($this->customerSession->isLoggedIn()) {
            return null;
        }

        $this->customerSession->setBeforeAuthUrl($this->request->getUriString());

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath('customer/account/login');
    }

    protected function redirectToHistory(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath('sales/order/history');
    }

    protected function redirectToOrderView(int $orderId): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
