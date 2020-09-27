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
use Eccube\Entity\Payment;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Service\PurchaseFlow\Processor\AbstractPurchaseProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use PayPay\OpenPaymentAPI\Client;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Repository\PaymentStatusRepository;
use Plugin\paypay4\Service\Method\PayPay;

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

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Payment
     */
    private $payment;

    public function __construct(
        PaymentStatusRepository $paymentStatusRepository,
        OrderStatusRepository $orderStatusRepository,
        Client $client,
        PaymentRepository $paymentRepository
    )
    {
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->client = $client;
        $this->payment = $paymentRepository->findOneBy([
            'method_class' => PayPay::class
        ]);
    }

    /**
     * 決済の前処理
     *
     * @param ItemHolderInterface $target
     * @param PurchaseContext $context
     */
    public function prepare(ItemHolderInterface $target, PurchaseContext $context): void
    {
        if (!$target instanceof Order) {
            return;
        }

        if ($target->getPaymentMethod() === $this->payment->getMethod()) {
            // 受注ステータスを決済処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
            $target->setOrderStatus($OrderStatus);

            // 支払いステータスをQRコード生成にする
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CREATED);
            $target->setPaypayPaymentStatus($PaymentStatus);
        }
    }

    /**
     * 決済処理
     *
     * @param ItemHolderInterface $target
     * @param PurchaseContext $context
     * @throws \Exception
     */
    public function commit(ItemHolderInterface $target, PurchaseContext $context): void
    {
        if (!$target instanceof Order) {
            return;
        }

        if ($target->getPaymentMethod() === $this->payment->getMethod()) {
            $response = $this->client->payment->getPaymentDetails($target->getOrderNo());

            // PayPayの受注IDを登録
            $target->setPaypayOrderId($response["data"]["paymentId"]);
            // PayPayの入金日時を登録
            $target->setPaymentDate(new \DateTime("@" . $response["data"]["acceptedAt"]));

            // 支払いステータスを実売上に変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::COMPLETED);
            $target->setPaypayPaymentStatus($PaymentStatus);
        }
    }

    /**
     * 決済失敗処理
     *
     * @param ItemHolderInterface $itemHolder
     * @param PurchaseContext $context
     */
    public function rollback(ItemHolderInterface $itemHolder, PurchaseContext $context): void
    {
        if (!$itemHolder instanceof Order) {
            return;
        }

        if ($itemHolder->getPaymentMethod() === $this->payment->getMethod()) {
            $response = $this->client->payment->getPaymentDetails($itemHolder->getOrderNo());

            if ($response['resultInfo']["code"] === "SUCCESS") {
                switch ($response["data"]["status"]) {
                    case "EXPIRED":
                        $paymentStatus = $this->paymentStatusRepository->find(PaymentStatus::EXPIRED);
                        break;
                    default:
                        $paymentStatus = $this->paymentStatusRepository->find(PaymentStatus::FAILED);
                }
            } else {
                $paymentStatus = $this->paymentStatusRepository->find(PaymentStatus::FAILED);
            }

            // 支払いステータスを変更
            $itemHolder->setPaypayPaymentStatus($paymentStatus);

            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $itemHolder->setOrderStatus($OrderStatus);
        }
    }
}
