<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\PrestaShop\Adapter\OrderState\QueryHandler;

use OrderState;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\OrderStateNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Query\GetOrderStateForEditing;
use PrestaShop\PrestaShop\Core\Domain\OrderState\QueryHandler\GetOrderStateForEditingHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\OrderState\QueryResult\EditableOrderState;

/**
 * Handles command that gets orderState for editing
 *
 * @internal
 */
final class GetOrderStateForEditingHandler implements GetOrderStateForEditingHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(GetOrderStateForEditing $query)
    {
        $orderStateId = $query->getOrderStateId();
        $orderState = new OrderState($orderStateId->getValue());

        if ($orderState->id !== $orderStateId->getValue()) {
            throw new OrderStateNotFoundException($orderStateId, sprintf('OrderState with id "%s" was not found', $orderStateId->getValue()));
        }

        return new EditableOrderState(
            $orderStateId,
            $orderState->name,
            $orderState->color,
            $orderState->logable,
            $orderState->invoice,
            $orderState->hidden,
            $orderState->send_email,
            $orderState->pdf_invoice,
            $orderState->pdf_delivery,
            $orderState->shipped,
            $orderState->paid,
            $orderState->delivery,
            $orderState->template
        );
    }
}
