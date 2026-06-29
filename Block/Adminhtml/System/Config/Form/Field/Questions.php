<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class Questions extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('question', [
            'label' => __('Question'),
            'style' => 'width:320px',
        ]);

        $this->addColumn('value', [
            'label' => __('Value / Option'),
            'style' => 'width:240px',
        ]);

        $this->_addAfter      = false;
        $this->_addButtonLabel = (string) __('Add');
    }
}
