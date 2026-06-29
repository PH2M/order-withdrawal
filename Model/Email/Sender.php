<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model\Email;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PH2M\OrderWithdrawal\Model\Config;
use PH2M\OrderWithdrawal\Model\Withdrawal;
use Psr\Log\LoggerInterface;

class Sender
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function send(Withdrawal $withdrawal, OrderInterface $order): void
    {
        $storeId = (int) $order->getStoreId();
        $vars = $this->buildVars($withdrawal, $order);

        $customerEmail = (string) $order->getCustomerEmail();
        $customerTemplate = $this->config->getCustomerTemplate($storeId);
        if ($customerEmail !== '' && $customerTemplate !== '') {
            $this->dispatch($customerTemplate, $customerEmail, $vars, $storeId);
        }

        $recipientEmail = $this->config->getRecipientEmail($storeId);
        $adminTemplate = $this->config->getAdminTemplate($storeId);
        if ($recipientEmail !== '' && $adminTemplate !== '') {
            $this->dispatch($adminTemplate, $recipientEmail, $vars, $storeId);
        }

        $test = 'test';
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function dispatch(string $templateId, string $recipient, array $vars, int $storeId): void
    {
        $this->inlineTranslation->suspend();
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->getSenderIdentity($storeId), $storeId)
                ->addTo($recipient)
                ->getTransport();
            $transport->sendMessage();
        } catch (MailException $e) {
            $this->logger->error('Order withdrawal email failed: ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVars(Withdrawal $withdrawal, OrderInterface $order): array
    {
        $items = [];
        foreach ($withdrawal->getItems() as $item) {
            $items[] = [
                'product_name' => (string) $item->getData(\PH2M\OrderWithdrawal\Model\WithdrawalItem::PRODUCT_NAME),
                'sku' => (string) $item->getData(\PH2M\OrderWithdrawal\Model\WithdrawalItem::SKU),
                'qty' => (int) $item->getData(\PH2M\OrderWithdrawal\Model\WithdrawalItem::QTY),
                'reason' =>  nl2br((string) $item->getData(\PH2M\OrderWithdrawal\Model\WithdrawalItem::REASON)),
            ];
        }

        return [
            'increment_id' => (string) $order->getIncrementId(),
            'customer_name' => trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()),
            'items' => $items,
            'company' => (string) $order->getStore()->getName(),
        ];
    }
}
