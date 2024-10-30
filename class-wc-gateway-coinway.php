<?php

if (!defined('ABSPATH')) {
    exit;
}

function Load_Coinway_Gateway()
{

    if (!function_exists('Woocommerce_Add_Coinway_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_CWay')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Coinway_Gateway');

        function Woocommerce_Add_Coinway_Gateway($methods)
        {
            $methods[] = 'WC_CWay';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_EUR_currency');

        function add_EUR_currency($currencies)
        {
            $currencies['EUR'] = __('EUR', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_EUR_currency_symbol', 10, 2);

        function add_EUR_currency_symbol($currency_symbol, $currency)
        {
            return '€';
        }

        class WC_CWay extends WC_Payment_Gateway
        {
            private $username;
            private $password;
            private $wallet_id;
            private $failedMassage;
            private $successMassage;
            private $signature_key;

            public function __construct()
            {

                $this->id = 'WC_CWay';
                $this->method_title = __('CoinwayPay', 'woocommerce');
                $this->method_description = __('CoinwayPAY is the first app that allows you to receive payments easily and safely.', 'woocommerce');
                $this->icon = apply_filters('WC_CWay_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->username = $this->settings['username'];
                $this->password = $this->settings['password'];
                $this->wallet_id = $this->settings['wallet_id'];
                $this->signature_key = $this->settings['signature_key'];

                $this->successMassage = $this->settings['success_massage'];
                $this->failedMassage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Coinway_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Coinway_Gateway'));

            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_CWay_Config', array(
                        'base_config' => array(
                            'title' => __('Basic Settings', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('Enabled', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('Enable CoinwayPAY Gateway', 'woocommerce'),
                            'description' => __('If you want to use CoinwayPAY you have to enable this gateway.', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('Gateway Title', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Customer will see this title when chooseing a payment method.', 'woocommerce'),
                            'default' => __('CoinwayPAY Gateway', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('Gateway Details', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('Customer will see this details during payment.', 'woocommerce'),
                            'default' => __('Pay easily with CoinwayPAY.', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('CoinwayPAY Settings', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'username' => array(
                            'title' => __('CoinwayPAY Username', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Username for CoinwayPAY', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'password' => array(
                            'title' => __('CoinwayPAY Password', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Password for CoinwayPAY', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'wallet_id' => array(
                            'title' => __('CoinwayPAY Wallet ID', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Your CoinwayPAY wallet ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'signature_key' => array(
                            'title' => __('CoinwayPAY Signature Key', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Your CoinwayPAY Signature Key', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'payment_config' => array(
                            'title' => __('Payment Configurations', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('Successful Payment Message', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('Successful Payment message. Note than you can also use {transaction_id} parameter to show customer Order ID.', 'woocommerce'),
                            'default' => __('Payment was successful. Thank you for your Payment.', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('Failed Payment Message', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('Failed Payment Message. Note that you can also use {fault} short code for more details about the error cause.', 'woocommerce'),
                            'default' => __('There was a problem with your payment. Please Contact support for further assistant.', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            /**
             * @param $action (PaymentRequest, )
             * @param $params string
             *
             * @return mixed
             */

            public function custom_dump($anything)
            {
                add_action('shutdown', function () use ($anything) {
                    echo "<div style='position: absolute; z-index: 100; left: 30px; bottom: 30px; right: 30px; background-color: white;'>";
                    var_dump($anything);
                    echo "</div>";
                });
            }

            public function SendRequestToCoinWay($action, $params)
            {
                try {
                    if ($action == 'PaymentRequest') {
                        $args = [
                            'body'        => $params,
                            'data_format' => 'body',
                            'timeout'     => '60',
                            'method'      => 'POST',
                            'redirection' => '5',
                            'httpversion' => '1.0',
                            'blocking'    => true,
                            'headers'     => [
                                'Content-Type' => 'application/json; charset=utf-8',
                                'Authorization' => ' Basic ' . base64_encode($this->username . ':' . $this->password),
                            ],
                            'cookies'     => [],
                        ];
                        $response = wp_remote_post( 'https://p.coinwaypay.com/w/register', $args );


                        $status_code = wp_remote_retrieve_response_code( $response );
                        if ($status_code == 200){
                            return json_decode(wp_remote_retrieve_body( $response ), true);
                        } else {
                            return "Received $status_code code from CoinwayPay.";
                        }
                    }
                } catch (Exception $ex) {
                    return false;
                }
                return false;
            }

            public function Send_to_Coinway_Gateway($order_id)
            {
                global $woocommerce;
                $woocommerce->session->order_id_coinwaypay = $order_id;
                $order = new WC_Order($order_id);

                $form = '<form action="" method="POST" class="cwp-checkout-form" id="cwp-checkout-form">
						<input type="submit" name="cwp_submit" class="button alt" id="cwp-payment-button" value="' . __('PAY', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('CANCEL', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_CWay_Form', $form, $order_id, $woocommerce);

                do_action('WC_CWay_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_CWay_Gateway_After_Form', $order_id, $woocommerce);

                $Amount = (real)$order->order_total;

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url(strtolower(get_class($this))));

                $data = [
                    'walletId' => $this->wallet_id,
                    'referenceId' => (string)$order_id,
                    'currency' => 'EUR', //todo: get from settings
                    'amount' => $Amount,
                    'successRedirectUrl' => $CallbackUrl,
                    'failRedirectUrl' => $CallbackUrl,
                    'cancelRedirectUrl' => $CallbackUrl,                    
                ];

                $result = $this->SendRequestToCoinWay('PaymentRequest', json_encode($data));
                if ($result === false) {
                    $Message = 'cURL Error';
                } else if (isset($result['success']) && $result['success']) {
                    wp_redirect($result['redirectUrl']);
                    exit;
                } else {
                    $Message = 'Error in processing your request: ' . $result;
                    $Fault = '';
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('Gateway Failure %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_CWay_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('Gateway Failiure <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_CWay_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice) {
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_CWay_Send_to_Gateway_Failed', $order_id, $Fault);
                }
                do_action('WC_CWay_Send_to_Gateway_Failed', $order_id, $Fault);
            }


            public function errorPayment($order_id)
            {
                global $woocommerce;

                $Fault = __('Invalid Payment!', 'woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_CWay_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                if ($Notice) {
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_CWay_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }

            public function Return_from_Coinway_Gateway()
            {
                global $woocommerce;
                $InvoiceNumber = isset($_POST['InvoiceNumber']) ? sanitize_text_field($_POST['InvoiceNumber']) : '';

                if (isset($_GET['wc_order'])) {
                    $order_id = sanitize_text_field($_GET['wc_order']);
                } else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_coinwaypay;
                    unset($woocommerce->session->order_id_coinwaypay);
                }
                if (!$order_id) {
                    $this->errorPayment($order_id);
                    exit;
                }

                //validate hmac
                $dataBase64 = sanitize_text_field($_POST['dataB64']);
                $signatureKey = $this->signature_key;
                $transmittedSignature = sanitize_text_field($_POST['signature']);

                $calculatedSignature = base64_encode(hash_hmac('sha256', base64_decode($dataBase64), base64_decode($signatureKey), true));

                if ($transmittedSignature != $calculatedSignature) {
                    $this->errorPayment($order_id);
                    exit;
                }

                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_CWay_Currency', $currency, $order_id);

                if ($order->get_status() !== 'completed') {
                    $dataDecoded = json_decode(base64_decode(sanitize_text_field($_POST['dataB64'])), true);
                    if (isset($dataDecoded['state']) && $dataDecoded['state'] == 'Received') {
                        $Transaction_ID = $dataDecoded['paymentId'];

                        update_post_meta($order_id, '_transaction_id', $Transaction_ID);

                        $order->payment_complete($Transaction_ID);
                        $woocommerce->cart->empty_cart();

                        $Note = sprintf(__('Payment was successful<br/> Transaction ID: %s', 'woocommerce'), $Transaction_ID);
                        $Note = apply_filters('WC_CWay_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                        if ($Note)
                            $order->add_order_note($Note, 1);


                        $Notice = wpautop(wptexturize($this->successMassage));

                        $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                        $Notice = apply_filters('WC_CWay_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                        if ($Notice)
                            wc_add_notice($Notice, 'success');

                        do_action('WC_CWay_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;


                    } else {
                        $tr_id = isset($dataDecoded['paymentId']) ? $dataDecoded['paymentId'] : '-';
                        $Note = sprintf(__('Payment Verification Error %s', 'woocommerce'), $tr_id);

                        $Note = apply_filters('WC_CWay_Return_from_Gateway_Failed_Note', $Note, $order_id, $tr_id, 'Error');
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));

                        $Notice = str_replace(array('{transaction_id}', '{fault}'), array($tr_id, 'Error'), $Notice);
                        $Notice = apply_filters('WC_CWay_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $tr_id, 'Error');
                        if ($Notice) {
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_CWay_Return_from_Gateway_Failed', $order_id, $tr_id, 'Error');

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }
                }
            }

        }

    }
}

add_action('plugins_loaded', 'Load_Coinway_Gateway', 0);
