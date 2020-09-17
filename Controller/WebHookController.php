<?php
/**
 * This file is part of Plugin
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\paypay4\Controller;


use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use PayPay\OpenPaymentAPI\Client;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Repository\PaymentStatusRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebHookController extends AbstractController
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    public function __construct(
        Client $client,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository
    )
    {
        $this->client = $client;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
    }

    /**
     * @param Request $request
     *
     * @Route("/paypay/webhook/transaction")
     */
    public function index(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        if ($payload["notification_type"] === "Transaction") {
            switch ($payload["state"]) {
                case "AUTHORIZED":
                    break;
                case "COMPLETED":
                    $this->orderComplete($payload);
                    break;
                case "CANCELED":
                    break;
                case "EXPIRED":
                    break;
            }

        }

        return new Response();
    }

    /**
     * @param array $payload
     * @throws \Exception
     */
    protected function orderComplete(array $payload)
    {
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            "order_no" => $payload["merchant_order_id"],
            "PaypayPaymentStatus" => [PaymentStatus::CREATED, PaymentStatus::AUTHORIZED]
        ]);

        if ($Order) {
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::COMPLETED);

            $Order
                ->setOrderStatus($OrderStatus)
                ->setOrderDate(new \DateTime())
                ->setPaypayOrderId($payload["order_id"])
                ->setPaypayPaymentStatus($PaymentStatus)
                ->setPaymentDate(new \DateTime($payload["paid_at"]));
            $this->entityManager->persist($Order);
            $this->entityManager->flush();
        }
    }
}
