<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PH2M\OrderWithdrawal\Controller\Order\AbstractOrder;
use PH2M\OrderWithdrawal\Model\Eligibility;
use PH2M\OrderWithdrawal\Model\Email\Sender;
use PH2M\OrderWithdrawal\Model\WithdrawalManagement;

class Submit extends AbstractOrder implements HttpPostActionInterface
{
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CustomerSession $customerSession,
        OrderRepositoryInterface $orderRepository,
        Eligibility $eligibility,
        ManagerInterface $messageManager,
        private readonly WithdrawalManagement $withdrawalManagement,
        private readonly Sender $sender
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

        $selections = $this->collectSelections();

        try {
            $withdrawal = $this->withdrawalManagement->create($order, $selections);
            $this->sender->send($withdrawal, $order);
            $this->messageManager->addSuccessMessage(
                (string) __('Your withdrawal request has been submitted. Our customer service will contact you shortly.')
            );

            return $this->redirectToOrderView((int) $order->getEntityId());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage((string) __('We could not register your withdrawal request. Please try again.'));
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath('withdrawal/order/form', ['order_id' => (int) $order->getEntityId()]);
    }

    /**
     * @return array<int, array{qty: int, reason: string, questions: array<string, string>}>
     */
    private function collectSelections(): array
    {
        $items = (array) $this->request->getParam('items', []);
        $selections = [];

        foreach ($items as $orderItemId => $data) {
            if (!is_array($data) || empty($data['selected'])) {
                continue;
            }

            $rawQuestions = $data['questions'] ?? [];

            $selections[(int) $orderItemId] = [
                'qty'       => (int) ($data['qty'] ?? 1),
                'reason'    => (string) ($data['reason'] ?? ''),
                'questions' => is_array($rawQuestions) ? array_map('strval', $rawQuestions) : [],
            ];
        }

        return $selections;
    }
}
