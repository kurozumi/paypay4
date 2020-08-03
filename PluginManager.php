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

namespace Plugin\paypay4;


use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\paypay4\Entity\PaymentStatus;
use Plugin\paypay4\Service\Method\PayPay;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    public function enable(array $meta, ContainerInterface $container)
    {
        $this->createPayment($container, 'PayPay', PayPay::class);
        $this->createPaymentStatuses($container);
    }

    private function createPayment(ContainerInterface $container, $method, $methodClass)
    {
        /** @var EntityManagerInterface $entityManager */
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

    private function createMasterData(ContainerInterface $container, array $statuses, $class)
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $i = 0;
        foreach ($statuses as $id => $name) {
            $PaymentStatus = $entityManager->find($class, $id);
            if (!$PaymentStatus) {
                $PaymentStatus = new $class;
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($i++);
            $entityManager->persist($PaymentStatus);
        }
        $entityManager->flush();
    }

    private function createPaymentStatuses(ContainerInterface $container)
    {
        $statuses = [
            PaymentStatus::CREATED => 'QRコード生成',
            PaymentStatus::AUTHORIZED => '仮売上',
            PaymentStatus::COMPLETED => '実売上',
            PaymentStatus::EXPIRED => '有効期限超過',
            PaymentStatus::CANCELED => 'キャンセル',
            PaymentStatus::REFUNDED => '返金',
            PaymentStatus::FAILED => '決済失敗'
        ];
        $this->createMasterData($container, $statuses, PaymentStatus::class);
    }
}
