<?php
/**
 * This file is part of paypay4
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\paypay4\Service\PurchaseFlow\Processor;


use Eccube\Annotation\ShoppingFlow;
use Eccube\Entity\ItemHolderInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\PurchaseFlow\Processor\AbstractPurchaseProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Repository\PaymentStatusRepository;

/**
 * Class OrderUpdateProcessor
 * @package Plugin\paypay4\Service\PurchaseFlow\Processor
 *
 * @ShoppingFlow()
 */
class OrderUpdateProcessor extends AbstractPurchaseProcessor
{
    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    public function __construct(
        PaymentStatusRepository $paymentStatusRepository,
        OrderStatusRepository $orderStatusRepository
    )
    {
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->orderStatusRepository = $orderStatusRepository;
    }

    public function commit(ItemHolderInterface $target, PurchaseContext $context)
    {
        if (!$target instanceof Order) {
            return;
        }
        // 支払いステータスを実売上に変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::COMPLETED);
        $target->setPaypayPaymentStatus($PaymentStatus);
    }

    public function rollback(ItemHolderInterface $itemHolder, PurchaseContext $context)
    {
        if(!$itemHolder instanceof Order) {
            return;
        }

        // 受注ステータスを購入処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $itemHolder->setOrderStatus($OrderStatus);
    }
}
