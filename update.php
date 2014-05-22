<?php
/**
 * @package 	JoomShopping
 * @subpackage 	payment
 * @author 		Yos Okusanya
 * @copyright 	Copyright (C) 2013-2014 Yos Okusanya. All rights reserved.
 * @license 	GNU General Public License version 2 or later
 */

$pmDb = JFactory::getDBO();
$pmQuery = "SELECT payment_id FROM #__jshopping_payment_method WHERE payment_class = 'pm_idealeasy'";
$pmDb->setQuery($pmQuery);
$hasPlugin = (int)$pmDb->loadResult();

if(!$hasPlugin)
{
    $pmQuery = "INSERT INTO `#__jshopping_payment_method` (
`payment_id`, `payment_code`, `payment_class`, `payment_params`, 
`name_en-GB`, `name_de-DE`, `description_en-GB`, `description_de-DE`, 
`payment_publish`, `payment_type`, `price`, `price_type`, `tax_id`, `show_descr_in_email`
) 
VALUES (
NULL, 'ideal_easy', 'pm_idealeasy', 'test_mode=1
merchant_id=
sub_id=0
transaction_end_status=6
transaction_failed_status=1
send_user_info=0', 
'iDEAL Easy', 'iDEAL Easy', '', '', 
0, 2, 0.00, 1, -1, 0
)";

    $pmDb->setQuery($pmQuery);
    $pmDb->execute();

}
