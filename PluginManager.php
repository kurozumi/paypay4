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

namespace Plugin\paypay4;


use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\paypay4\Service\Method\PayPay;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    public function enable(array $meta, ContainerInterface $container)
    {
        $this->createPayment($container, 'PayPay', PayPay::class);
    }

    private function createPayment(ContainerInterface $container, $method, $methodClass)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $paymentRepository = $entityManager->getRepository(Payment::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => $methodClass]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod($method);
        $Payment->setMethodClass($methodClass);

        $entityManager->persist($Payment);
        $entityManager->flush();
    }
}
