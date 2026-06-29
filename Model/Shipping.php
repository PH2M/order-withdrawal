<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PH2M\OrderWithdrawal\Exception\GetHolidaysApiFailed;

class Shipping
{
    private const CUTOFF_HOUR         = 11;
    private const TIMEZONE_CONFIG_KEY = 'order_withdrawal/shipping/cutoff_timezone';

    private ?OrderInterface $order = null;

    /** @var DateTimeImmutable[] */
    private array $holidays = [];

    public function __construct(
        private readonly ScopeConfigInterface       $scopeConfig,
        private readonly StoreManagerInterface      $storeManager,
        private readonly Holidays                   $holidaysModel,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder       $searchCriteriaBuilder,
        private readonly SortOrderBuilder            $sortOrderBuilder,
    ) {
    }

    /**
     * Allows callers to provide the order so that country-specific
     * holidays (Monaco vs France) can be resolved via the API.
     */
    public function setOrder(OrderInterface $order): void
    {
        $this->order    = $order;
        $this->holidays = [];
    }

    public function getShipping(DateTimeImmutable $startDate): string
    {
        $startDate = $this->resolveStartDate($startDate);

        $this->initHolidays($startDate->format('Y'));

        $startDate = $startDate->add(new DateInterval(sprintf('P%dD', $this->getCutOffDays($startDate))));

        return $startDate
            ->add(new DateInterval(sprintf('P%dD', $this->getNbDaysToAdd($startDate))))
            ->format('Y-m-d');
    }

    public function validateDate(?string $date, string $format = 'Y-m-d H:i:s'): bool
    {
        if ($date === null) {
            return false;
        }

        $d = DateTime::createFromFormat($format, $date);

        return $d !== false && $d->format($format) === $date;
    }

    /**
     * If the feature flag is on and an order with at least one shipment is set,
     * returns the creation date of the most recent shipment. Falls back to $default otherwise.
     */
    private function resolveStartDate(DateTimeImmutable $default): DateTimeImmutable
    {
        if ($this->order === null) {
            return $default;
        }

        $storeId = (int) $this->order->getStoreId();

        if (!$this->config->isShipmentDateEnabled($storeId)) {
            return $default;
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField('created_at')
            ->setDirection(SortOrder::SORT_DESC)
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $this->order->getEntityId())
            ->addSortOrder($sortOrder)
            ->setPageSize(1)
            ->create();

        $shipments = $this->shipmentRepository->getList($searchCriteria);

        if ($shipments->getTotalCount() > 0) {
            $items        = $shipments->getItems();
            $lastShipment = reset($items);
            $createdAt    = (string) $lastShipment->getCreatedAt();
            $date         = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdAt);
            if ($date !== false) {
                return $date;
            }
        }

        return $default;
    }

    /**
     * Returns the number of days to add as a cutoff buffer depending on the
     * current day of week, hour, and whether we are in the rush period
     * (last day of September → December 31).
     */
    private function getCutOffDays(DateTimeImmutable $startDate): int
    {
        $year = $startDate->format('Y');

        try {
            $firstDayNextYear = new DateTime(sprintf('%d-01-01', (int)$year + 1));
            $lastDayOfSeptember = new DateTime(sprintf('last day of %s-09', $year));
        } catch (\Exception) {
            return 0;
        }

        $hour = $this->getCurrentHour();
        $day = (int)$startDate->format('N'); // 1 = Monday … 7 = Sunday
        $isRush = $startDate >= $lastDayOfSeptember && $startDate <= $firstDayNextYear;
        $isWeekday = !in_array($day, [6, 7], true);

        switch (true) {
            // out-of-rush period, weekday
            case !$isRush && $isWeekday:
                return $hour < self::CUTOFF_HOUR ? 0 : ($day === 5 ? 3 : 1);

            // out-of-rush period, weekend
            case !$isRush && !$isWeekday:
                return $day === 6 ? 2 : 1;

            // rush period, weekday
            case $isRush && $isWeekday:
                return $hour < self::CUTOFF_HOUR ? ($day === 1 ? 1 : 0) : ($day === 5 ? 3 : 1);

            // rush period, weekend
            default:
                return $day === 6 ? 3 : 2;
        }
    }

    /**
     * Scans $delay working days forward from $startDate and extends the window
     * for each non-working day encountered (weekend or public holiday).
     */
    private function getNbDaysToAdd(DateTimeImmutable $startDate, int $delay = 0): int
    {
        $days = $delay;

        if ($delay === 0) {
            $i = 0;
            do {
                if ($this->isDayOff($startDate, $i)) {
                    $days++;
                }
                $i++;
            } while ($this->isDayOff($startDate, $i - 1) && $this->isDayOff($startDate, $i));

            return $days;
        }

        $remaining = $delay;
        $i = 1;
        do {
            if ($remaining === 1 && $this->isDayOff($startDate, $i)) {
                $days++;
                $remaining++;
            }
            $i++;
            $remaining--;
        } while ($remaining > 0);

        return $days;
    }

    private function isDayOff(DateTimeImmutable $startDate, int $i): bool
    {
        $target = $startDate->add(new DateInterval('P' . $i . 'D'));

        if (in_array((int)$target->format('N'), [6, 7], true)) {
            return true;
        }

        foreach ($this->holidays as $holiday) {
            if ($target->format('Y-m-d') === $holiday->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }

    private function initHolidays(string $year): void
    {
        if (!empty($this->holidays)) {
            return;
        }

        $nextYear = (string)((int)$year + 1);

        $this->holidays = array_merge(
            $this->resolveHolidays($year),
            $this->resolveHolidays($nextYear),
        );
    }

    /**
     * Returns public holidays for $year as DateTimeImmutable objects.
     * Uses the Holidays API when an order is available; falls back to the
     * hardcoded French calendar otherwise.
     *
     * @return DateTimeImmutable[]
     */
    private function resolveHolidays(string $year): array
    {
        if ($this->order !== null) {
            $countryCode = $this->order->getShippingAddress()?->getCountryId() === 'MC' ? 'MC' : 'FR';
            try {
                $apiResult = $this->holidaysModel->getHolidays([$year], $countryCode);
                if (!empty($apiResult[$year])) {
                    return $apiResult[$year];
                }
            } catch (GetHolidaysApiFailed) {
                // fall through to hardcoded calendar
            }
        }

        return $this->frenchHolidaysFallback($year);
    }

    /** @return DateTimeImmutable[] */
    private function frenchHolidaysFallback(string $year): array
    {
        $easter = new DateTimeImmutable('@' . easter_date((int)$year));

        return [
            new DateTimeImmutable($year . '-01-01'), // 1er janvier
            new DateTimeImmutable($year . '-05-01'), // Fête du travail
            new DateTimeImmutable($year . '-05-08'), // Victoire des alliés
            new DateTimeImmutable($year . '-07-14'), // Fête nationale
            new DateTimeImmutable($year . '-08-15'), // Assomption
            new DateTimeImmutable($year . '-11-01'), // Toussaint
            new DateTimeImmutable($year . '-11-11'), // Armistice
            new DateTimeImmutable($year . '-12-25'), // Noël
            $easter->add(new DateInterval('P1D')),   // Lundi de Pâques   (+1)
            $easter->add(new DateInterval('P39D')),  // Ascension          (+39)
            $easter->add(new DateInterval('P50D')),  // Lundi de Pentecôte (+50)
        ];
    }

    private function getCurrentHour(): int
    {
        $timezone = $this->getCutOffTimeZone();

        if ($timezone === '') {
            return (int)(new DateTime())->format('H');
        }

        return (int)(new DateTime('now', new DateTimeZone($timezone)))->format('H');
    }

    private function getCutOffTimeZone(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TIMEZONE_CONFIG_KEY,
            ScopeInterface::SCOPE_STORE,
            (int)$this->storeManager->getStore()->getId(),
        );
    }
}
