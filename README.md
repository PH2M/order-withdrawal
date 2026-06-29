# PH2M_OrderWithdrawal — Right of Withdrawal for Magento 2

---

## Features

- **Withdrawal button** displayed on the order detail page in the customer account, within the configured legal window
- **Request form** with item selection, quantity choice, mandatory reason and configurable additional questions
- **Real-time eligibility check**: allowed order status + withdrawal window (calculated from the order date or the last shipment date)
- **Per-product exclusion** via the `is_withdrawable` product attribute (non-returnable items: digital goods, personalised products…)
- **Automatic emails**: customer confirmation and customer service notification
- **Dedicated admin section**: list and detail view of withdrawal requests in the Magento backend
- **Public holidays**: integration with the [Nager.Date](https://date.nager.at) public API for deadline calculation
- **Multi-store**: all settings are configurable per store view

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ~8.0 |
| Magento Open Source / Adobe Commerce | 2.4.x |
| Magento modules | `Magento_Sales`, `Magento_Customer`, `Magento_Catalog`, `Magento_Email` |

---

## Installation

```bash
composer require ph2m/order-withdrawal
bin/magento module:enable PH2M_OrderWithdrawal
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

---

## Configuration

Path: **Stores > Configuration > Extensions > Order Withdrawal**

### General

| Setting | Description |
|---|---|
| Enable Withdrawal | Enable or disable the module |
| Withdrawal Window (days) | Number of calendar days during which the withdrawal button is shown |
| Start window from last shipment date | If enabled, the window starts from the last shipment date instead of the order creation date |
| Eligible Order Statuses | Order statuses for which withdrawal is allowed |
| Withdrawal Reasons | List of reasons (one per line) displayed as a dropdown on the form |
| Withdrawal Questions | Additional questions: a single occurrence → free text field; repeated occurrences → dropdown |

### Email

| Setting | Description |
|---|---|
| Send Withdrawal Request To | Customer service address that receives withdrawal notifications (defaults to the store contact address) |
| Email Sender | Sender identity |
| Customer Service Email Template | Email template sent to the customer service team |
| Customer Confirmation Email Template | Confirmation email template sent to the customer |

---

## Product attribute

The data patch `AddIsWithdrawableProductAttribute` creates the `is_withdrawable` attribute on products (group **Product Details**). This attribute allows specific products to be excluded from the withdrawal form (e.g. digital goods, personalised products).

---

## Database tables

| Table | Description |
|---|---|
| `ph2m_order_withdrawal` | Header of each withdrawal request (order, customer, store, date) |
| `ph2m_order_withdrawal_item` | Request lines: item, SKU, quantity, reason and question answers |

---

## Eligibility calculation

An order is eligible for withdrawal when all three conditions are met:

1. The module is enabled for the store
2. The order status is in the configured list
3. The current date is within the withdrawal window:
   - **Start date**: order creation date, or last shipment date if the option is enabled and a shipment exists
   - **Duration**: number of days set in the back-office

---

## Public holidays

The `Holidays` class queries the public `date.nager.at` API to retrieve bank holidays for a given country. Results are cached in Magento for one year. This class can be used to refine deadline calculations by excluding non-working days.

---

## Authors

**PH2M** — [contact@ph2m.com](mailto:contact@ph2m.com)

---

## License

MIT
