<?php

namespace App\Payments;

use Stripe\Source;
use Stripe\Stripe;

class StripeWepay {
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'currency' => [
                'label' => '货币单位',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_sk_live' => [
                'label' => 'SK_LIVE',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_webhook_key' => [
                'label' => 'WebHook密钥签名',
                'description' => '',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $currency = $this->config['currency'];
        $exchange = $this->exchange('CNY', strtoupper($currency));
        if (!$exchange) {
            abort(500, __('Currency conversion has timed out, please try again later'));
        }

        $customFieldName = isset($this->config['stripe_custom_field_name']) ? $this->config['stripe_custom_field_name'] : 'Contact Infomation';
        
        Stripe::setApiKey($this->config['stripe_sk_live']);
        
        /** OLD
        $source = Source::create([
            'amount' => floor($order['total_amount'] * $exchange),
            'currency' => $currency,
            'type' => 'wechat',
            'statement_descriptor' => $order['trade_no'],
            'metadata' => [
                'user_id' => $order['user_id'],
                'out_trade_no' => $order['trade_no'],
                'identifier' => ''
            ],
            'redirect' => [
                'return_url' => $order['return_url']
            ]
        ]);
        */
            
        try {
            
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['wechat_pay'],
            
            'payment_method_options' => [
                'wechat_pay' => [
                'client' => "web"
                ],
            ],
            'line_items' => [[
                'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => $order['trade_no']
                ],
                'unit_amount' => floor($order['total_amount'] * $exchange),
                ],
            'quantity' => 1,
            ]],
            'mode' => 'payment',
            'client_reference_id' => $order['trade_no'],
            'success_url' => $order['return_url'],
            'cancel_url' => $order['return_url'],
            
            'invoice_creation' => ['enabled' => true],
            'phone_number_collection' => ['enabled' => true],
            'custom_fields' => [
                [
                    'key' => 'contactinfo',
                    'label' => ['type' => 'custom', 'custom' => $customFieldName],
                    'type' => 'text',
                ],
            ],
        ]);
            
        } catch (\Exception $e) {
            info($e);
            abort(500, "Failed to create order. Error: {$e->getMessage}");
        }
        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $session->url
        ];
        

        

        /** OLD
        if (!$source['wechat']['qr_code_url']) {
            abort(500, __('Payment gateway request failed'));
        }
        return [
            'type' => 0,
            'data' => $source['wechat']['qr_code_url']
        ];
        */
    }
    

    public function notify($params)
    {
        \Stripe\Stripe::setApiKey($this->config['stripe_sk_live']);
        
        /*
        try {
            $event = \Stripe\Webhook::constructEvent(
                file_get_contents('php://input'),
                $_SERVER['HTTP_STRIPE_SIGNATURE'],
                $this->config['stripe_webhook_key']
            );
        } catch (\Stripe\Error\SignatureVerification $e) {
            abort(400);
        }
        */
        
        $event = json_decode(file_get_contents('php://input'));
        
        /*
        switch ($event->type) {
            case 'source.chargeable':
                $object = $event->data->object;
                \Stripe\Charge::create([
                    'amount' => $object->amount,
                    'currency' => $object->currency,
                    'source' => $object->id,
                    'metadata' => json_decode($object->metadata, true)
                ]);
                break;
            case 'charge.succeeded':
                $object = $event->data->object;
                if ($object->status === 'succeeded') {
                    if (!isset($object->metadata->out_trade_no) && !isset($object->source->metadata)) {
                        die('order error');
                    }
                    $metaData = isset($object->metadata->out_trade_no) ? $object->metadata : $object->source->metadata;
                    $tradeNo = $metaData->out_trade_no;
                    return [
                        'trade_no' => $tradeNo,
                        'callback_no' => $object->id
                    ];
                }
                break;
            default:
                abort(500, 'event is not support');
        }
        */
        
        switch ($event->type) {
            case 'checkout.session.completed':
                $object = $event->data->object;
                if ($object->payment_status === 'paid') {
                    return [
                        'trade_no' => $object->client_reference_id,
                        'callback_no' => $object->payment_intent
                    ];
                }
                break;
            case 'checkout.session.async_payment_succeeded':
                $object = $event->data->object;
                return [
                    'trade_no' => $object->client_reference_id,
                    'callback_no' => $object->payment_intent
                ];
                break;
            default:
                abort(500, 'event is not support');
        }
        
        die('success');
    }

    private function exchange($from, $to)
    {
        //$result = file_get_contents('https://api.exchangerate.host/latest?symbols=' . $to . '&base=' . $from);
        //$result = json_decode($result, true);
        //return $result['rates'][$to];
        return 0.4; //Force AUD TO CNY;
    }
}
