<?php
/**
 * Joomshopping iDEAL Easy payment class
 *
 * @package		JoomShopping
 * @subpackage	payment
 * @author		Yos Okusanya
 * @copyright	Copyright (C) 2013-2014 Yos Okusanya. All rights reserved.
 * @license		GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die('Restricted access');

class pm_idealeasy extends PaymentRoot
{
    /**
     * Display payment plugin parameters in admin interface
     *
     * @param array $pluginConfig   payment plugin config
     */
    function showAdminFormParams($pluginConfig)
    {
        $this->loadLanguageFile();

        include(__DIR__ . "/adminparamsform.php");
    }

    /**
     * Shows the form payment. Checkout Step3
     *
     * @param array $params         entered params
     * @param array $pluginConfig   payment plugin config
     */
    function showPaymentForm($params, $pluginConfig)
    {
        include(__DIR__ . "/paymentform.php");
    }

    /**
     * Creates the payment form and redirect to the payment page.
     * Checkout Step6.
     *
     * @param array         $pluginConfig   payment plugin config
     * @param JshopOrder    $order
     */
    function showEndForm($pluginConfig, $order)
    {
        $orderId = $order->order_id;
        $orderNumber = $order->order_number;
        $orderHash = $order->order_hash;

        $orderTotal = round((float)$order->order_total, 2);
        $orderTotal = $orderTotal * 100;

        $merchantId = $pluginConfig['merchant_id'];
        $testMode = (int)$pluginConfig['test_mode'];

        if ($testMode) {
            $merchantId = 'TESTiDEALEASY';
        }

        $postVariables = array(
                                "PSPID" => $merchantId,	// merchant id
                                "orderID" => $orderNumber, // reference id
                                "amount" => $orderTotal, 	// order total
                                "language" => 'NL_NL', 		// language (static field)
                                "currency" => 'EUR', 		// order currency (static field)
                                "PM" => 'iDEAL',			// (static field)
                                "COM" => sprintf(_JSHOP_PAYMENT_NUMBER, $order->order_number) // order description
                            );

        $pmClass = 'pm_idealeasy';
        $returnUrl = 'index.php?option=com_jshopping&controller=checkout&task=step7'
            . '&js_paymentclass='. $pmClass . '&order_id=' . $orderId . '&hash=' . $orderHash;

        // return urls
        $successUrl = $returnUrl . "&act=return";
        $cancelUrl = $returnUrl . "&act=cancel";
        $errorUrl = $returnUrl . "&act=error";

        // sef urls
        $successUrl = SEFLink($successUrl, 0, 1, -1);
        $cancelUrl = SEFLink($cancelUrl, 0, 1, -1);
        $errorUrl = SEFLink($errorUrl, 0, 1, -1);

        $postVariables['accepturl'] = $successUrl;
        $postVariables['cancelurl'] = $cancelUrl;
        $postVariables['exceptionurl'] = $errorUrl;
        $postVariables['declineurl'] = $errorUrl;

        if ((int)$pluginConfig['send_user_info']) {
            $customerName = $order->f_name;

            if ($order->m_name) {
                $customerName .= ' ' . $order->m_name;
            }
            if ($order->l_name) {
                $customerName .=  ' ' . $order->l_name;
            }

            $postVariables["CN"] = $this->escapeHtml($customerName);
            $postVariables["EMAIL"] = $this->escapeHtml($order->email);

            $address = array();
            if ($order->home) {
                $address[] = $order->home;
            }
            if ($order->apartment) {
                $address[] = $order->apartment;
            }
            if ($order->street) {
                $address[] = $order->street;
            }

            if (!empty($address)) {
                $ownerAddress = implode( ', ', $address);

                $postVariables["owneraddress"] = $this->escapeHtml($ownerAddress);
                $postVariables["ownertown"] = $this->escapeHtml($order->city);
                $postVariables["ownerzip"] = $this->escapeHtml($order->zip);

                $countryId = (int) $order->d_country;

                if ($countryId) {
                    $country = JTable::getInstance('country', 'jshop');
                    $country->load($countryId);

                    $countryName = $country->get('name_en-GB');
                    if ($countryName) {
                        $postVariables["ownercty"] = $this->escapeHtml($countryName);
                    }
                }
            }
        }

?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    </head>
    <body>

        <form name="paymentform" id="paymentform" action="https://internetkassa.abnamro.nl/ncol/prod/orderstandard.asp" method="post" >
        <?php

        $logComment = "Order ID:{$orderId} | Order Total:{$order->order_total}";

        if ($testMode) {
            $logComment = "Test Mode | " . $logComment;
        }

        foreach ($postVariables as $name => $value) {
            echo "\n".'<input type="hidden" name="' . $name . '" value="' . $value . '" />';
            $logComment .= "\n{$name}={$value}";	// log parameter
        }

        //log transaction
        saveToLog("paymentdata.log", $logComment);

        ?>
        </form>

        <script type="text/javascript">
            document.getElementById('paymentform').submit();
        </script>
    </body>
</html>
<?php
        die();
    }

    /**
     * Returns the payment options from the url. Step7
     *
     * @param array $pluginConfig
     *
     * @return array
     */
    function getUrlParams($pluginConfig)
    {
        $input = JFactory::getApplication()->input;

        $params = array();
        $params['order_id'] = $input->getInt('order_id','');
        $params['hash'] = $input->getVar('hash','');
        $params['checkHash'] = 1;
        $params['checkReturnParams'] = 1;

        return $params;
    }

    /**
     * Returns the joomshopping transaction status code.
     *
     * joomshopping status codes
     * 1 => transaction_end_status
     * 3 => transaction_failed_status
     * 4 => transaction_cancel_status
     *
     * @param array         $pluginConfig
     * @param JshopOrder    $order
     * @param string        $act           joomshopping controller action
     *
     * @return array (jshop_status_code, comment)
     */
    function checkTransaction($pluginConfig, $order, $act)
    {
        $this->loadLanguageFile();

        if ('return' == $act) {
            // return success code and log transaction
            return array(1, "Order ID {$order->order_id} transaction complete");
        } elseif ('cancel' == $act) {
            // return cancel code and log transaction
            return array(4, "Order ID {$order->order_id} transaction canceled");
        }

        // return error code, log transaction and raise warning
        return array(3, _JSHOPPING_IDEALEASY_ERROR_PROCESSING_PAYMENT);
    }

    /**
     * Escapes a HTML string
     *
     * @param string $html
     *
     * @return string
     */
    function escapeHtml($html)
    {
        return htmlspecialchars($html, ENT_QUOTES);
    }

    /**
     * Load language file
    */
    function loadLanguageFile()
    {
        $langDir  = __DIR__ . '/lang/';
        $langFile = $langDir . JFactory::getLanguage()->getTag() . '.php';

        if (file_exists($langFile)) {
            require_once $langFile;
        } else {
            require_once $langDir . 'en-GB.php';	//load default language
        }
    }
}
?>
