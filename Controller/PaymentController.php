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

namespace Plugin\paypay4\Controller;


use Eccube\Controller\AbstractShoppingController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use PayPay\OpenPaymentAPI\Client;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Repository\PaymentStatusRepository;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PaymentController
 * @package Plugin\paypay4\Controller
 *
 * @Route("/shopping/paypay")
 */
class PaymentController extends AbstractShoppingController
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var MailService
     */
    private $mailService;

    public function __construct(
        Client $client,
        ParameterBag $parameterBag,
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        OrderRepository $orderRepository,
        CartService $cartService,
        MailService $mailService
    )
    {
        $this->client = $client;
        $this->parameterBag = $parameterBag;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->orderRepository = $orderRepository;
        $this->cartService = $cartService;
        $this->mailService = $mailService;
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/complete/{order_no}", name="paypay_checkout", methods={"GET"})
     */
    public function complete(Request $request, $order_no)
    {
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);

        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $order_no,
            'Customer' => $this->getUser(),
            'OrderStatus' => $OrderStatus
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        $response = $this->client->payment->getPaymentDetails($Order->getOrderNo());

        if ($response['resultInfo']["code"] !== "SUCCESS") {
            log_error("[PayPay][注文処理]決済エラー");
            $this->addError("決済エラー");

            return $this->rollbackOrder($Order, PaymentStatus::FAILED);
        }

        switch ($response["data"]["status"]) {
            case "COMPLETED":
                // 受注ステータスを新規受付へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $Order->setOrderStatus($orderStatus);

                // 支払いステータスを実売上へ変更
                $paymentStatus = $this->paymentStatusRepository->find(PaymentStatus::COMPLETED);
                $Order->setPaypayPaymentStatus($paymentStatus);

                // PayPayの受注IDを登録
                $Order->setPaypayOrderId($response["data"]["paymentId"]);

                // purchaseFlow::commitを呼び出し、購入処理をさせる
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                log_info('[PayPay][注文処理] カートをクリアします.', [$Order->getId()]);
                $this->cartService->clear();

                // 受注IDをセッションにセット
                $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

                // メール送信
                $Order->appendCompleteMailMessage();
                log_info('[PayPay][注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                log_info('[PayPay][注文処理] 注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);
                break;
            case "EXPIRED":
                return $this->rollbackOrder($Order, PaymentStatus::EXPIRED);
                break;
            default:
                return $this->rollbackOrder($Order, PaymentStatus::FAILED);
        }

        return $this->redirectToRoute("shopping_complete");
    }

    /**
     * @param Order $Order
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    private function rollbackOrder(Order $Order, $paymentStatusId)
    {
        // 受注ステータスを購入処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        // 支払いステータスを変更
        $paymentStatus = $this->paymentStatusRepository->find($paymentStatusId);
        $Order->setPaypayPaymentStatus($paymentStatus);

        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        $this->entityManager->flush();

        return $this->redirectToRoute("shopping_error");
    }
}
