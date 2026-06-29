<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use PH2M\OrderWithdrawal\Model\Config;
use PH2M\OrderWithdrawal\Model\ItemProvider;
use PH2M\OrderWithdrawal\Model\ResourceModel\Withdrawal as WithdrawalResource;
use PH2M\OrderWithdrawal\Model\ResourceModel\WithdrawalItem as WithdrawalItemResource;
use PH2M\OrderWithdrawal\Model\Withdrawal;
use PH2M\OrderWithdrawal\Model\WithdrawalItem;

class WithdrawalManagement
{
    public function __construct(
        private readonly WithdrawalFactory      $withdrawalFactory,
        private readonly WithdrawalResource     $withdrawalResource,
        private readonly WithdrawalItemFactory  $withdrawalItemFactory,
        private readonly WithdrawalItemResource $withdrawalItemResource,
        private readonly ItemProvider           $itemProvider,
        private readonly Config                 $config,
    ) {
    }

    /**
     * Persist a withdrawal request from validated selections.
     *
     * @param array<int, array{qty?: int|string, reason?: string, questions?: array<string, string>}> $selections keyed by order item id
     *
     * @throws LocalizedException
     */
    public function create(OrderInterface $order, array $selections): Withdrawal
    {
        $storeId      = (int) $order->getStoreId();
        $configQues   = $this->config->getQuestions($storeId);

        $allowedItems = [];
        foreach ($this->itemProvider->getWithdrawableItems($order) as $orderItem) {
            $allowedItems[(int) $orderItem->getItemId()] = $orderItem;
        }

        $lines = [];
        foreach ($selections as $orderItemId => $data) {
            $orderItemId = (int) $orderItemId;
            if (!isset($allowedItems[$orderItemId])) {
                continue;
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw new LocalizedException(__('Please select a reason for each selected product.'));
            }

            $answers = is_array($data['questions'] ?? null) ? $data['questions'] : [];
            $reason  = $this->appendQuestions($reason, $answers, $configQues);

            $orderItem = $allowedItems[$orderItemId];
            $maxQty = max(1, (int) $orderItem->getQtyOrdered());
            $qty = (int) ($data['qty'] ?? 1);
            $qty = max(1, min($qty, $maxQty));

            $lines[] = [
                'order_item_id' => $orderItemId,
                'sku'           => (string) $orderItem->getSku(),
                'product_name'  => (string) $orderItem->getName(),
                'qty'           => $qty,
                'reason'        => $reason,
            ];
        }

        if (!$lines) {
            throw new LocalizedException(__('Please select at least one product to withdraw.'));
        }

        $withdrawal = $this->withdrawalFactory->create();
        $withdrawal->setData([
            Withdrawal::ORDER_ID => (int) $order->getEntityId(),
            Withdrawal::INCREMENT_ID => (string) $order->getIncrementId(),
            Withdrawal::CUSTOMER_ID => $order->getCustomerId() ? (int) $order->getCustomerId() : null,
            Withdrawal::STORE_ID => (int) $order->getStoreId(),
        ]);
        $this->withdrawalResource->save($withdrawal);

        foreach ($lines as $line) {
            $item = $this->withdrawalItemFactory->create();
            $item->setData([
                WithdrawalItem::WITHDRAWAL_ID => (int) $withdrawal->getId(),
                WithdrawalItem::ORDER_ITEM_ID => $line['order_item_id'],
                WithdrawalItem::SKU => $line['sku'],
                WithdrawalItem::PRODUCT_NAME => $line['product_name'],
                WithdrawalItem::QTY => $line['qty'],
                WithdrawalItem::REASON => $line['reason'],
            ]);
            $this->withdrawalItemResource->save($item);
        }

        return $withdrawal;
    }

    /**
     * Appends each configured question and its answer to $reason.
     *
     * Format:
     *   {reason}
     *   {question}
     *   {answer}
     *   ...
     *
     * @param array<string, string>      $answers     submitted answers keyed by question label
     * @param array<string, string[]|null> $configQues questions from admin config
     *
     * @throws LocalizedException when a required question has no answer
     */
    private function appendQuestions(string $reason, array $answers, array $configQues): string
    {
        if (empty($configQues)) {
            return $reason;
        }

        $parts = [$reason];

        foreach ($configQues as $question => $options) {
            $answer = trim((string) ($answers[$question] ?? ''));

            if ($answer === '') {
                throw new LocalizedException(
                    __('Please answer the required question: %1', $question)
                );
            }

            $parts[] = $question;
            $parts[] = $answer;
        }

        return implode("\n", $parts);
    }
}
