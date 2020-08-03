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

namespace Plugin\paypay4\Entity;


use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * Trait OrderTrait
 * @package Plugin\paypay4\Entity
 *
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $paypay_code_id;

    /**
     * @var PaymentStatus
     *
     * @ORM\ManyToOne(targetEntity="Plugin\paypay4\Entity\PaymentStatus")
     * @ORM\JoinColumn(name="paypay_payment_status_id", referencedColumnName="id", nullable=true)
     */
    private $PaypayPaymentStatus;

    /**
     * @return string
     */
    public function getPaypayCodeId(): string
    {
        return $this->paypay_code_id;
    }

    /**
     * @param string $codeId
     * @return $this
     */
    public function setPaypayCodeId(string $codeId): self
    {
        $this->paypay_code_id = $codeId;

        return $this;
    }

    /**
     * @return PaymentStatus|null
     */
    public function getPaypayPaymentStatus(): ?PaymentStatus
    {
        return $this->PaypayPaymentStatus;
    }

    /**
     * @param PaymentStatus|null $paymentStatus
     * @return $this
     */
    public function setPaypayPaymentStatus(?PaymentStatus $paymentStatus): self
    {
        $this->PaypayPaymentStatus = $paymentStatus;

        return $this;
    }
}
