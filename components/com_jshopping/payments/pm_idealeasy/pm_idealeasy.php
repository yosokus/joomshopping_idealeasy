<?php
/**
 * Joomshopping iDEAL Basic payment class
 *
 * @package		JoomShopping
 * @subpackage	payment
 * @author		Yos
 * @copyright	Copyright (C) 2013-2014 Yos. All rights reserved.
 * @license		GNU General Public License version 2 or later.
*/

defined('_JEXEC') or die('Restricted access');

class pm_idealeasy extends PaymentRoot
{
    /**
     * Display payment plugin parameters in admin interface
	 *
     * @param array $pluginConfig - payment plugin config
    */
	function showAdminFormParams($pluginConfig)
	{
		// load language file
		$this->loadLanguageFile();

		//initialize some variables
		if (!isset($pluginConfig['test_mode'])) { $pluginConfig['test_mode'] = 1; }
		if (!isset($pluginConfig['send_user_info'])) { $pluginConfig['send_user_info'] = 0; }
		if (!isset($pluginConfig['transaction_end_status'])) { $pluginConfig['transaction_end_status'] = 6; }
		if (!isset($pluginConfig['transaction_failed_status'])) { $pluginConfig['transaction_failed_status'] = 1; }

		$orders = JModelLegacy::getInstance('orders', 'JshoppingModel'); //admin model
		$order_status = $orders->getAllOrderStatus();

		include(dirname(__FILE__)."/adminparamsform.php");
	}

    /**
     * show form payment. Checkout Step3
     * @param array $params - entered params
     * @param array $pluginConfig - payment plugin config
    */
    function showPaymentForm($params, $pluginConfig)
	{
        include(dirname(__FILE__)."/paymentform.php");
    }

    /**
     * Start payment form. Checkout Step6.
	 *
     * @param array $pluginConfig - payment plugin config
     * @param jshopOrder $order
    */
	function showEndForm($pluginConfig, $order)
	{
        $jshopConfig = JSFactory::getConfig();

		$order_id = $order->order_id;
		$order_number = $order->order_number;
		$order_currency_code = $order->currency_code_iso;
		$order_hash = $order->order_hash;

		$order_total = round((float) $order->order_total, 2);
		$order_total = $order_total * 100;

		$merchant_id = $pluginConfig['merchant_id'];

		$test_mode = (int) $pluginConfig['test_mode'];

		if($test_mode)$merchant_id = "TESTiDEALEASY";

		$post_variables = array(
								"PSPID" => $merchant_id,	// merchant id
								"orderID" => $order_number, // reference id
								"amount" => $order_total, 	// order total
								"language" => 'NL_NL', 		// language (static field)
								"currency" => 'EUR', 		// order currency (static field)
								"PM" => 'iDEAL',			// (static field)
								"COM" => sprintf(_JSHOP_PAYMENT_NUMBER, $order->order_number) // order description
							);

		$pm_class = 'pm_idealeasy';

        $return_url = "index.php?option=com_jshopping&controller=checkout&task=step7";
		$return_url .= "&js_paymentclass={$pm_class}&order_id={$order_id}&hash={$order_hash}";

		// return urls
		$url_cancel = $return_url . "&act=cancel";
		$url_success = $return_url . "&act=return";
		$url_error = $return_url . "&act=error";

		// sef urls
		$url_success = SEFLink($url_success,0,1,-1);
		$url_cancel = SEFLink($url_cancel,0,1,-1);
		$url_error = SEFLink($url_error,0,1,-1);

		$post_variables['accepturl'] = $url_success;
		$post_variables['cancelurl'] = $url_cancel;
		$post_variables['exceptionurl'] = $url_error;
		$post_variables['declineurl'] = $url_error;

		if ((int) $pluginConfig['send_user_info'])
		{
			$customer_name = $order->f_name;
			if ($order->m_name) $customer_name .= ' ' . $order->m_name;
			if ($order->l_name) $customer_name .=  ' ' . $order->l_name;

			$post_variables["CN"] = $this->escapeHtml($customer_name);
			$post_variables["EMAIL"] = $this->escapeHtml($order->email);

			$address = array();
			if ($order->home) $address[] = $order->home;
			if ($order->apartment) $address[] = $order->apartment;
			if ($order->street) $address[] = $order->street;

			if (!empty($address))
			{
				$owner_address = implode( ', ', $address);

				$post_variables["owneraddress"] = $this->escapeHtml($owner_address);
				$post_variables["ownertown"] = $this->escapeHtml($order->city);
				$post_variables["ownerzip"] = $this->escapeHtml($order->zip);

				$country_id = (int) $order->d_country;

				if ($country_id)
				{
					$country = JTable::getInstance('country', 'jshop');
					$country->load($country_id);

					$country_name = $country->get('name_en-GB');
					$post_variables["ownercty"] = $this->escapeHtml($country_name);
				}

			}
		}

?>

<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	</head>
	<body>

		<form id="paymentform" action="https://internetkassa.abnamro.nl/ncol/prod/orderstandard.asp" method="post" name="paymentform" >
		<?php

		$log_comment = "Order ID:{$order_id} | Order Total:{$order->order_total}";

		if ($test_mode)
		{
			$log_comment = "Test Mode | " . $log_comment;
		}

		foreach ($post_variables as $name => $value)
		{
			echo "\n".'<input type="hidden" name="' . $name . '" value="' . $value . '" />';

			$log_comment .= "\n{$name}={$value}";	// log parameter
		}

		//log transaction
		saveToLog("paymentdata.log", $log_comment); // joomshopping log function

		?>
		</form>

		<script type="text/javascript">document.getElementById("paymentform").submit();</script>


	</body>
</html>
<?php
		die();
	}

    /**
     * get url parameters for payment. Step7
	 *
     * @param array $pluginConfig - Payment plugin config
	 *
	 * @return array
    */
    function getUrlParams($pluginConfig)
	{
		$app = JFactory::getApplication();
		$input = $app->input;

		$params = array();
		$params['order_id'] = $input->getInt('order_id','');
		$params['hash'] = $input->getVar('hash','');
        $params['checkHash'] = 1;
		$params['checkReturnParams'] = 1;

		return $params;
    }

    /**
     * Check Transaction
	 *
     * @param array $pluginConfig - Payment plugin config
     * @param jshopOrder $order - Jshop order
     * @param string $act - jshop task
	 *
	 * @return array (jshop_status_code, comment)
    */
	function checkTransaction($pluginConfig, $order, $act)
	{
        // load language file
		$this->loadLanguageFile();

		$jshopConfig = JSFactory::getConfig();

		$app = JFactory::getApplication();
		$input = $app->input;

		// joomshopping status codes
		// 1=>transaction_end_status, 3=>transaction_failed_status, 4=>transaction_cancel_status

		if($act == 'return')
		{
			return array(1, "Order ID {$order->order_id} transaction complete"); //success - log transaction
		}

		return array(3, _JSHOPPING_IDEALEASY_ERROR_PROCESSING_PAYMENT); //error - log transaction and raise warning
	}

    /**
     * Escape HTML string
	 *
     * @param string
    */
	function escapeHtml($string)
	{
		return htmlspecialchars($string,ENT_QUOTES);
	}

    /**
     * Load language file
    */
	function loadLanguageFile()
    {
        $lang_dir  = dirname(__FILE__) . '/lang/';
        $lang_file = $lang_dir . JFactory::getLanguage()->getTag() . '.php';

		if (file_exists($lang_file))
		{
			require_once $lang_file;
		}
		else
		{
			require_once $lang_dir . 'en-GB.php';	//load default language
		}
    }

}
?>
