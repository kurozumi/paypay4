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


use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

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
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    public function __construct(
        PurchaseFlow $shoppingPurchaseFlow,
        ParameterBag $parameterBag
    ) {
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->parameterBag = $parameterBag;
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

        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        $this->parameterBag->set('PayPay.Order', $this->Order);

        $dispatcher = new PaymentDispatcher();
        $dispatcher->setRoute('paypay_payment');
        $dispatcher->setForward(true);

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
