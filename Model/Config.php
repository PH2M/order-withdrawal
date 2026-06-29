<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'ph2m_order_withdrawal/general/enabled';
    private const XML_PATH_DELAY_DAYS = 'ph2m_order_withdrawal/general/delay_days';
    private const XML_PATH_ELIGIBLE_STATUSES = 'ph2m_order_withdrawal/general/eligible_statuses';
    private const XML_PATH_REASONS    = 'ph2m_order_withdrawal/general/reasons';
    private const XML_PATH_QUESTIONS        = 'ph2m_order_withdrawal/general/questions';
    private const XML_PATH_USE_SHIPMENT_DATE = 'ph2m_order_withdrawal/general/use_shipment_date';
    private const XML_PATH_RECIPIENT_EMAIL = 'ph2m_order_withdrawal/email/recipient_email';
    private const XML_PATH_SENDER_IDENTITY = 'ph2m_order_withdrawal/email/sender_identity';
    private const XML_PATH_CUSTOMER_TEMPLATE = 'ph2m_order_withdrawal/email/customer_template';
    private const XML_PATH_ADMIN_TEMPLATE = 'ph2m_order_withdrawal/email/admin_template';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isShipmentDateEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SHIPMENT_DATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getDelayDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_DELAY_DAYS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string[]
     */
    public function getEligibleStatuses(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_ELIGIBLE_STATUSES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return array_filter(array_map('trim', explode(',', $value)));
    }

    /**
     * @return string[]
     */
    public function getReasons(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_REASONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $reasons = preg_split('/\R/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $reasons), static fn ($line) => $line !== ''));
    }

    /**
     * Returns withdrawal questions parsed from the matrix config field.
     *
     * The return value is keyed by question label:
     *   - string[] value  → question appears multiple times → render as <select>
     *   - null value      → question appears once          → render as <input type="text">
     *
     * @return array<string, string[]|null>
     */
    public function getQuestions(?int $storeId = null): array
    {
        $raw = $this->scopeConfig->getValue(
            self::XML_PATH_QUESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($raw)) {
            return [];
        }

        $rows = json_decode((string) $raw, true);
        if (!is_array($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $question = trim((string) ($row['question'] ?? ''));
            $value    = trim((string) ($row['value'] ?? ''));
            if ($question === '') {
                continue;
            }
            $grouped[$question][] = $value;
        }

        $result = [];
        foreach ($grouped as $question => $values) {
            $result[$question] = count($values) > 1
                ? array_values(array_filter($values, static fn (string $v) => $v !== ''))
                : null;
        }

        return $result;
    }

    public function getRecipientEmail(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_RECIPIENT_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: $this->scopeConfig->getValue(
            'contact/email/recipient_email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )  ?: '';
    }

    public function getSenderIdentity(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_SENDER_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'general';
    }

    public function getCustomerTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOMER_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAdminTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ADMIN_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
