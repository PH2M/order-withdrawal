<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model\ResourceModel\Withdrawal\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    private const CUSTOMER_NAME_EXPRESSION =
        "TRIM(CONCAT(COALESCE(so.customer_firstname, ''), ' ', COALESCE(so.customer_lastname, '')))";

    protected function _initSelect()
    {
        parent::_initSelect();

        $this->getSelect()->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'main_table.order_id = so.entity_id',
            ['customer_name' => new \Zend_Db_Expr(self::CUSTOMER_NAME_EXPRESSION)]
        );

        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('increment_id', 'main_table.increment_id');
        $this->addFilterToMap('customer_id', 'main_table.customer_id');
        $this->addFilterToMap('created_at', 'main_table.created_at');
        // Zend_Db_Expr keeps the CONCAT unquoted; addFilterToMap is typed string but accepts an expression at runtime.
        $this->addFilterToMap('customer_name', new \Zend_Db_Expr(self::CUSTOMER_NAME_EXPRESSION)); // @phpstan-ignore-line
    }
}
