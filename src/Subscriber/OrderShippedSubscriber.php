<?php
/*
 * Copyright (c) Gent Islami 2025
 * All rights reserved.
 */
declare(strict_types=1);

namespace GentIslami\OrderShippingWebhookReport\Subscriber;

use GentIslami\OrderShippingWebhookReport\Queue\Message\OrderShippedMessage;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderShippedSubscriber implements EventSubscriberInterface
{
    private $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            "state_enter.order_delivery.state.shipped" => "onOrderShipped"
        ];
    }

    public function onOrderShipped(OrderStateMachineStateChangeEvent $event): void
    {
        $this->bus->dispatch(new OrderShippedMessage($event->getOrderId()));
    }



}