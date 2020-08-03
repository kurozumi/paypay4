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


use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractShoppingController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use PayPay\OpenPaymentAPI\Client;
use PayPay\OpenPaymentAPI\Models\CreateQrCodePayload;
use PayPay\OpenPaymentAPI\Models\OrderItem;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Repository\PaymentStatusRepository;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class PaymentController
 * @package Plugin\paypay4\Controller
 *
 * @Route("/shopping")
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

    public function __construct(
        EccubeConfig $eccubeConfig,
        ParameterBag $parameterBag,
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository
    )
    {
        $this->eccubeConfig = $eccubeConfig;

        // ステージング環境
        $this->client = new Client([
            'API_KEY' => $this->eccubeConfig['paypay_api_key'],
            'API_SECRET' => $this->eccubeConfig['paypay_api_secret'],
            'MERCHANT_ID' => $this->eccubeConfig['paypay_merchant_id']
        ], true);

        $this->parameterBag = $parameterBag;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     *
     * @Route("/paypay/payment", name="paypay_payment")
     */
    public function payment(Request $request)
    {
        /** @var Order $Order */
        $Order = $this->parameterBag->get('PayPay.Order');

        if (!$Order) {
            return $this->redirectToRoute('shopping_error');
        }

        $payload = new CreateQrCodePayload();
        $payload
            ->setMerchantPaymentId($Order->getOrderNo())
            ->setRequestedAt()
            ->setCodeType();

        $orderItems = [];
        foreach ($Order->getOrderItems() as $orderItem) {
            if ($orderItem->isProduct()) {
                $orderItems[] = (new OrderItem())
                    ->setName($orderItem->getProductName())
                    ->setQuantity(intval($orderItem->getQuantity()))
                    ->setUnitPrice(['amount' => intval($orderItem->getPriceIncTax()), 'currency' => $this->eccubeConfig['currency']]);
            }
        }
        $payload->setOrderItems($orderItems);

        $payload->setAmount([
            'amount' => intval($Order->getPaymentTotal()),
            'currency' => $this->eccubeConfig['currency']
        ]);
        $payload->setRedirectType('WEB_LINK');
        $payload->setRedirectUrl($this->generateUrl('paypay_complete', [], UrlGeneratorInterface::ABSOLUTE_URL));

        // 仮売上にする
//        $payload->setIsAuthorization(true);

        $response = $this->client->code->createQRCode($payload);

        if ($response['resultInfo']["code"] === "SUCCESS") {
            // QRコードID保存
            $Order->setPaypayCodeId($response["data"]["codeId"]);

            // 支払いステータスをQRコード生成にする
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CREATED);
            $Order->setPaypayPaymentStatus($PaymentStatus);

            $this->entityManager->persist($Order);

            $this->entityManager->flush();

            return $this->redirect($response['data']['url']);
        } else {
            log_error(sprintf("PayPay: %s", $response["resultInfo"]["code"]));

            $this->rollbackOrder($Order);

            return $this->redirectToRoute("shopping_error");
        }

    }

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("paypay/complete", name="paypay_complete")
     */
    public function complete(Request $request)
    {
        // ここでPayPayから取得できるデータがわからない。。

        // 仮売上にして注文完了画面へ。
        return $this->redirectToRoute("shopping_complete");
    }

    private function rollbackOrder(Order $Order)
    {
        // 受注ステータスを購入処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        $this->entityManager->flush();
    }
}
