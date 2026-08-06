<?php
/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay (https://flizpay.de).
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH (https://flizpay.de)
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 * @link    https://flizpay.de
 */

declare(strict_types=1);

namespace FlizPay\Payment\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the FLIZpay onboarding instructions card as a full-width row.
 */
class Welcome extends Field
{
    private const TEMPLATE = "FlizPay_Payment::system/config/welcome.phtml";

    /**
     * Render the welcome card across the whole row instead of a form input.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        /** @var Template $template */
        $template = $this->getLayout()->createBlock(Template::class);
        $template->setTemplate(self::TEMPLATE);
        $template->setData(
            "payment_methods_url",
            $this->getUrl("adminhtml/system_config/edit", [
                "section" => "payment",
            ]),
        );

        return sprintf(
            '<tr id="row_%s"><td colspan="5" class="flizpay-full-width-cell">%s</td></tr>',
            $this->escapeHtmlAttr($element->getHtmlId()),
            $template->toHtml(),
        );
    }
}
