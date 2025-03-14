<?php
/*
 * Copyright (c) Gent Islami 2025
 * All rights reserved.
 */
declare(strict_types=1);

namespace GentIslami\OrderShippingWebhookReport\Queue\Message;

class OrderShippedMessage
{
    private $orderId;

    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

}