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

namespace Plugin\paypay4\Repository;


use Eccube\Repository\AbstractRepository;
use Plugin\paypay4\Entity\PaymentStatus;
use Symfony\Bridge\Doctrine\RegistryInterface;

class PaymentStatusRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PaymentStatus::class);
    }

}
