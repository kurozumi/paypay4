<?php
/**
 * This file is part of Paypay4
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\paypay4\Service\Method;


use Eccube\Common\EccubeConfig;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use PayPay\OpenPaymentAPI\Client;
use PayPay\OpenPaymentAPI\Models\CreateQrCodePayload;
use PayPay\OpenPaymentAPI\Models\OrderItem;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Repository\PaymentStatusRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PayPay implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var BaseInfo
     */
    private $baseInfo;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

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
     * @var EccubeConfig
     */
    private $eccubeConfig;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        BaseInfoRepository $baseInfoRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        ParameterBag $parameterBag,
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        EccubeConfig $eccubeConfig,
        Client $client,
        RouterInterface $router
    ) {
        $this->baseInfo = $baseInfoRepository->get();
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->parameterBag = $parameterBag;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->client = $client;
        $this->router = $router;
    }

    /**
     * @inheritDoc
     *
     * 注文確認画面遷移時に呼び出される
     */
    public function verify()
    {
        // TODO: Implement verify() method.

        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * @inheritDoc
     *
     * 注文時に呼び出される
     */
    public function checkout()
    {
        // TODO: Implement checkout() method.

        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        // TODO: Implement apply() method.

        $Order = $this->Order;

        // 決済ステータスをQRコード生成へ変更
        $payload = new CreateQrCodePayload();
        $payload
            ->setMerchantPaymentId($Order->getOrderNo())
            ->setRequestedAt()
            ->setCodeType()
            ->setRedirectType('WEB_LINK')
            ->setRedirectUrl($this->router->generate('paypay_checkout', ["order_no" => $Order->getOrderNo()], UrlGeneratorInterface::ABSOLUTE_URL))
            ->setOrderDescription($this->baseInfo->getShopName());

//        仮売上(残高ブロック)にする
//        $payload->setIsAuthorization(true);

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

        // QRコード生成
        $response = $this->client->code->createQRCode($payload);

        if ($response['resultInfo']["code"] !== "SUCCESS") {
            $error_message = sprintf("PayPay: %s", $response["resultInfo"]["message"]);
            throw new ShoppingException($error_message);
        }

        // QRコードID保存
        $Order->setPaypayCodeId($response["data"]["codeId"]);
        $url = $response['data']['url'];

        $this->purchaseFlow->prepare($Order, new PurchaseContext());

        // PayPay決済ページへリダイレクト
        $response = new RedirectResponse($url);
        $dispatcher = new PaymentDispatcher();
        $dispatcher->setResponse($response);

        return $dispatcher;
    }

    /**
     * @inheritDoc
     */
    public function setFormType(FormInterface $form)
    {
        // TODO: Implement setFormType() method.
        $this->form = $form;
    }

    /**
     * @inheritDoc
     */
    public function setOrder(Order $Order)
    {
        // TODO: Implement setOrder() method.
        $this->Order = $Order;
    }
}
