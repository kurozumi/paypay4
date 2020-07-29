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
use Eccube\Entity\Order;
use PayPay\OpenPaymentAPI\Client;
use PayPay\OpenPaymentAPI\Models\CreateQrCodePayload;
use PayPay\OpenPaymentAPI\Models\OrderItem;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

    public function __construct(
        EccubeConfig $eccubeConfig,
        ParameterBag $parameterBag
    ){
        $this->eccubeConfig = $eccubeConfig;
        $this->parameterBag = $parameterBag;

        $this->client = new Client([
            'API_KEY' => $this->eccubeConfig['paypay_api_key'],
            'API_SECRET' => $this->eccubeConfig['paypay_api_secret'],
            'MERCHANT_ID' => $this->eccubeConfig['paypay_merchant_id']
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     *
     * @Route("/paypay_payment", name="paypay_payment")
     */
    public function payment(Request $request)
    {
        /** @var Order $Order */
        $Order = $this->parameterBag->get('PayPay.Order');

        if(!$Order) {
            return $this->redirectToRoute('shopping_error');
        }

        $payload = new CreateQrCodePayload();
        $payload
            ->setMerchantPaymentId($Order->getOrderNo())
            ->setRequestedAt()
            ->setCodeType('ORDER_QR');

        $orderItems = [];
        foreach($Order->getOrderItems() as $orderItem) {
            if($orderItem->isProduct()) {
                $orderItems[] = (new OrderItem())
                    ->setName($orderItem->getProductName())
                    ->setQuantity(intval($orderItem->getQuantity()))
                    ->setUnitPrice(['amount' => intval($orderItem->getPrice()), 'currency' => $this->eccubeConfig['currency']]);
            }
        }
        $payload->setOrderItems($orderItems);

        $payload->setAmount([
            'amount' => intval($Order->getPaymentTotal()),
            'currency' => $this->eccubeConfig['currency']
        ]);
        $payload->setRedirectType('WEB_LINK');
        $payload->setRedirectUrl($this->generateUrl('shopping_complete'));

        $response = $this->client->code->createQRCode($payload);

        return new Response($response['data']);
    }
}
