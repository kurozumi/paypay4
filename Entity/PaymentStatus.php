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

namespace Plugin\paypay4\Entity;


use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;

/**
 * Class PaymentStatus
 * @package Plugin\paypay4\Entity
 *
 * @ORM\Table(name="plg_paypay_payment_status")
 * @ORM\Entity(repositoryClass="Plugin\paypay4\Repository\PaymentStatusRepository")
 */
class PaymentStatus extends AbstractMasterEntity
{
    /**
     * QRコード生成
     */
    const CREATED = 1;

    /**
     * 仮売上
     */
    const AUTHORIZED = 2;

    /**
     * 実売上
     */
    const COMPLETED = 3;

    /**
     * 有効期限超過
     */
    const EXPIRED = 4;

    /**
     * キャンセル
     */
    const CANCELED = 5;

    /**
     * 返金
     */
    const REFUNDED = 6;

    /**
     * 決済失敗
     */
    const FAILED = 7;
}
