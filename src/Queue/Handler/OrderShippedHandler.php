<?php
/*
 * Copyright (c) Gent Islami 2025
 * All rights reserved.
 */
declare(strict_types=1);

namespace GentIslami\OrderShippingWebhookReport\Queue\Handler;

use GentIslami\OrderShippingWebhookReport\Queue\Message\OrderShippedMessage;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class OrderShippedHandler
{
    private EntityRepository $orderRepo;
    private HttpClientInterface $httpClient;
    private SystemConfigService $config;

    public function __construct(
        EntityRepository $repo,
        HttpClientInterface $httpClient,
        SystemConfigService $config
    )
    {
        $this->orderRepo = $repo;
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    public function __invoke(OrderShippedMessage $message)
    {

        $criteria = new Criteria([$message->getOrderId()]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.price');
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('billingAddress.countryState');
        $criteria->addAssociation('shippingAddress');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');
        /**
         * @var $order OrderEntity
         */
        $order = $this->orderRepo->search($criteria, Context::createDefaultContext())->first();

        $payload = [
            'order' => [
                'order_id' => $order->getId(),
                'order_date' => $order->getOrderDate(),
                'customer' => [
                    'customer_id' => $order->getOrderCustomer()?->getId(),
                    'name' => $order->getOrderCustomer()?->getFirstName() . ' ' . $order->getOrderCustomer()?->getLastName(),
                    'email' => $order->getOrderCustomer()?->getEmail(),
                    'phone' => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getPhoneNumber()
                ],
                'shipping_address' => [
                    'name' => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getFirstName()
                        . ' ' . $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getLastName(),
                    "street" => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getStreet(),
                    "city" => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getCity(),
                    "state" => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getCountryState()?->getShortCode(),
                    "postal_code" => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getZipcode(),
                    "country" => $order->getDeliveries()?->last()?->getShippingOrderAddress()?->getCountry()?->getName()
                ],
                'billing_address' => [
                    'name' => $order->getBillingAddress()?->getFirstName()
                        . ' ' . $order->getBillingAddress()?->getLastName(),
                    "street" => $order->getBillingAddress()?->getStreet(),
                    "city" => $order->getBillingAddress()?->getCity(),
                    "state" => $order->getBillingAddress()?->getCountryState()?->getShortCode(),
                    "postal_code" => $order->getBillingAddress()?->getZipcode(),
                    "country" => $order->getBillingAddress()?->getCountry()?->getIso3()
                ],
                'products' => [],
                'total_amount' => $order->getAmountTotal(),
                'currency' => $order->getCurrency()?->getIsoCode(),
                'payment' => [
                    'payment_method' => $order->getTransactions()?->last()?->getPaymentMethod()?->getName(),
                    'payment_provider' => $order->getTransactions()?->last()?->getPaymentMethod()?->getName(),
                    'payment_state' => $order->getTransactions()?->last()?->getStateMachineState()?->getName()
                ],
                'delivery' => [
                    'delivery_method' => $order->getDeliveries()?->last()?->getShippingMethod()?->getName(),
                    'tracking_number' => implode(',', $order->getDeliveries()?->last()?->getTrackingCodes() ?: []),
                    'delivery_status' => 'Shipped', //this triggers only on shipped
                    'estimated_delivery_date' => $order->getDeliveries()?->last()?->getShippingDateLatest()->format('Y-m-d')
                ],
                'order_status' => $order->getStateMachineState()?->getName()
            ]
        ];

        foreach ($order->getLineItems() as $lineItem) {
            $product = [
                'product_id' => $lineItem->getProduct()->getProductNumber(),
                'name' => $lineItem->getProduct()->getName(),
                'quantity' => $lineItem->getQuantity(),
                'price' => $lineItem->getPrice()->getUnitPrice(),
                'currency' => $order->getCurrency()->getIsoCode()
            ];
            $payload['order']['products'][] = $product;
        }

        $this->httpClient->request(
            'POST',
            $this->config->get('OrderShippingWebhookReportPlugin.config.webhookUrl', $order->getSalesChannelId()),
            [
                'json' => $payload
            ]
        );
    }
}