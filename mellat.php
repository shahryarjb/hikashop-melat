<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Hikashop
 * @subpackage 	trangell_Mellat
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

if (!class_exists ('checkHack')){
	require_once JPATH_SITE . '/plugins/hikashoppayment/mellat/trangell_inputcheck.php';
}


class plgHikashoppaymentMellat extends hikashopPaymentPlugin {
	var $accepted_currencies = array( "IRR" ); 
	var $multiple = true; 
	var $name = 'mellat';
	var $pluginConfig = array(
		'melatterminalId' => array("شماره ترمینال",'input'),
		'melatuser' => array("نام کاربری",'input'),
		'melatpass' => array("کلمه عبور",'input')
	);

	function __construct(&$subject, $config) {	
		return parent::__construct($subject, $config);
	}

	function onBeforeOrderCreate(&$order,&$do){
		if(parent::onBeforeOrderCreate($order, $do) === true)
			return true;

		if (
			empty($this->payment_params->melatterminalId) || 
			empty($this->payment_params->melatuser) ||
			empty($this->payment_params->melatpass) ){
			$this->app->enqueueMessage('لطفا تنظیمات پلاگین ملت را وارد نمایید','error');
			$do = false;
		}
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id) {
		parent::onAfterOrderConfirm($order,$methods,$method_id); 
		$notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment='.$this->name.'&tmpl=component&lang='.$this->locale . $this->url_itemid.'&orderid='.$order->order_id;
		$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id . $this->url_itemid;
		$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id . $this->url_itemid;

		$app	= JFactory::getApplication();
		$dateTime = JFactory::getDate();
		$Amount = $order->cart->full_total->prices[0]->price_value_with_tax; 
		
		$fields = array( 
			'terminalId' => $this->payment_params->melatterminalId,
			'userName' => $this->payment_params->melatuser,
			'userPassword' => $this->payment_params->melatpass,
			'orderId' => time(),
			'amount' => $Amount,
			'localDate' => $dateTime->format('Ymd'),
			'localTime' => $dateTime->format('His'),
			'additionalData' => '',
			'callBackUrl' => $notify_url,
			'payerId' => 0,
			);
			
		try {
			$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
			$response = $soap->bpPayRequest($fields);
			
			$response = explode(',', $response->return);
			if ($response[0] != '0') { // if transaction fail
				$msg = $this->getGateMsg($response[0]); 
				$this->modifyOrder($orderId, 'cancelled', false, false);
				$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error'); 	
			}
			else { // if success
				$refId = $response[1];	
				$vars['mellat'] = 						
								'
									<script>
										var form = document.createElement("form");
										form.setAttribute("method", "POST");
										form.setAttribute("action", "https://bpm.shaparak.ir/pgwchannel/startpay.mellat");
										form.setAttribute("target", "_self");

										var hiddenField = document.createElement("input");
										hiddenField.setAttribute("name", "RefId");
										hiddenField.setAttribute("value", "'.$refId.'");

										form.appendChild(hiddenField);

										document.body.appendChild(form);
										form.submit();
										document.body.removeChild(form);
									</script>'
								;
				$this->vars = $vars;
				return $this->showPage('end'); 
			}
		}
		catch(\SoapFault $e)  {
			$msg= $this->getGateMsg('error'); 
			$this->modifyOrder($orderId, 'cancelled', false, false);
			$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error'); 	
		}
	}

	function onPaymentNotification(&$statuses)	{
		$app	= JFactory::getApplication();		
		$jinput = $app->input;
		$orderId = $jinput->get->get('orderid', '0', 'INT');
		if($orderId != null){
			$Order = $this->getOrder($orderId);
			$this->loadPaymentParams($Order);
			// $mobile = $this->getInfo($Order->order_user_id);
			$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$orderId.$this->url_itemid;
			$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$orderId.$this->url_itemid;
			$history = new stdClass();
			//------------------------------------------------------

			$ResCode = $jinput->post->get('ResCode', '1', 'INT'); 
			$SaleOrderId = $jinput->post->get('SaleOrderId', '1', 'INT'); 
			$SaleReferenceId = $jinput->post->get('SaleReferenceId', '1', 'INT'); 
			$RefId = $jinput->post->get('RefId', 'empty', 'STRING'); 
			if (checkHack::strip($RefId) != $RefId )
				$RefId = "illegal";
			$CardNumber = $jinput->post->get('CardHolderPan', 'empty', 'STRING'); 
			if (checkHack::strip($CardNumber) != $CardNumber )
				$CardNumber = "illegal";
			
			if (
				checkHack::checkNum($ResCode) &&
				checkHack::checkNum($SaleOrderId) &&
				checkHack::checkNum($SaleReferenceId) 
				){
					if ($ResCode != '0') {
						$msg= $this->getGateMsg($ResCode); 
						$this->modifyOrder($orderId, 'cancelled', false, false);
						$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
					}
					else {
						$fields = array(		
						'terminalId' => $this->payment_params->melatterminalId,
						'userName' => $this->payment_params->melatuser,
						'userPassword' => $this->payment_params->melatpass,
						'orderId' => $SaleOrderId, 
						'saleOrderId' =>  $SaleOrderId, 
						'saleReferenceId' => $SaleReferenceId
						);
						try {
							$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
							$response = $soap->bpVerifyRequest($fields);

							if ($response->return != '0') {
								$msg= $this->getGateMsg($response->return); 
								$this->modifyOrder($orderId, 'cancelled', false, false);
								$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
							}
							else {	
								$response = $soap->bpSettleRequest($fields);
								if ($response->return == '0' || $response->return == '45') {
									$msg= $this->getGateMsg($response->return); 
									$history->notified = 1;
									$history->data =  'شماره پیگیری '.$SaleReferenceId;
									$this->modifyOrder($orderId, 'confirmed', $history, true); 
									$app->redirect($return_url, '<h2>'.$msg.'</h2>'.'<h3>'. $SaleReferenceId .'شماره پیگری ' .'</h3>' , $msgType='Message'); 
								}
								else {
									$msg= $this->getGateMsg($response->return); 
									$this->modifyOrder($orderId, 'cancelled', false, false);
									$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
								}
							}
						}
						catch(\SoapFault $e)  {
							$msg= $this->getGateMsg('error'); 
							$this->modifyOrder($orderId, 'cancelled', false, false);
							$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
						}
					}
				}
				else {
					$msg = $this->getGateMsg('hck2'); 
					$this->modifyOrder($orderId, 'cancelled', false, false); 
					$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
				}
			}
			else {
				$msg= $this->getGateMsg('notff'); 
				$this->modifyOrder($orderId, 'cancelled', false, false); 
				$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			}
	}

	public function getGateMsg ($msgId) {
		switch($msgId){
			case '0': $out =  'تراکنش با موفقیت انجام شد'; break;
			case '11': $out =  'شماره کارت نامعتبر است'; break;
			case '12': $out =  'موجودی کافی نیست'; break;
			case '13': $out =  'رمز نادرست است'; break;
			case '14': $out =  'تعداد دفعات وارد کردن رمز بیش از حد مجاز است'; break;
			case '15': $out =  'کارت نامعتبر است'; break;
			case '16': $out =  'دفعات برداشت وجه بیش از حد مجاز است'; break;
			case '17': $out =  'کاربر از انجام تراکنش منصرف شده است'; break;
			case '18': $out =  'تاریخ انقضای کارت گذشته است'; break;
			case '19': $out =  'مبلغ برداشت وجه بیش از حد مجاز است'; break;
			case '21': $out =  'پذیرنده نامعتبر است'; break;
			case '23': $out =  'خطای امنیتی رخ داده است'; break;
			case '24': $out =  'اطلاعات کاربری پذیرنده نادرست است'; break;
			case '25': $out =  'مبلغ نامتعبر است'; break;
			case '31': $out =  'پاسخ نامتعبر است'; break;
			case '32': $out =  'فرمت اطلاعات وارد شده صحیح نمی باشد'; break;
			case '33': $out =  'حساب نامعتبر است'; break;
			case '34': $out =  'خطای سیستمی'; break;
			case '35': $out =  'تاریخ نامعتبر است'; break;
			case '41': $out =  'شماره درخواست تکراری است'; break;
			case '42': $out =  'تراکنش Sale‌ یافت نشد'; break;
			case '43': $out =  'قبلا درخواست Verify‌ داده شده است'; break;
			case '44': $out =  'درخواست Verify‌ یافت نشد'; break;
			case '45': $out =  'تراکنش Settle‌ شده است'; break;
			case '46': $out =  'تراکنش Settle‌ نشده است'; break;
			case '47': $out =  'تراکنش  Settle یافت نشد'; break;
			case '48': $out =  'تراکنش Reverse شده است'; break;
			case '49': $out =  'تراکنش Refund یافت نشد'; break;
			case '51': $out =  'تراکنش تکراری است'; break;
			case '54': $out =  'تراکنش مرجع موجود نیست'; break;
			case '55': $out =  'تراکنش نامعتبر است'; break;
			case '61': $out =  'خطا در واریز'; break;
			case '111': $out =  'صادر کننده کارت نامعتبر است'; break;
			case '112': $out =  'خطا سوییج صادر کننده کارت'; break;
			case '113': $out =  'پاسخی از صادر کننده کارت دریافت نشد'; break;
			case '114': $out =  'دارنده کارت مجاز به انجام این تراکنش نیست'; break;
			case '412': $out =  'شناسه قبض نادرست است'; break;
			case '413': $out =  'شناسه پرداخت نادرست است'; break;
			case '414': $out =  'سازمان صادر کننده قبض نادرست است'; break;
			case '415': $out =  'زمان جلسه کاری به پایان رسیده است'; break;
			case '416': $out =  'خطا در ثبت اطلاعات'; break;
			case '417': $out =  'شناسه پرداخت کننده نامعتبر است'; break;
			case '418': $out =  'اشکال در تعریف اطلاعات مشتری'; break;
			case '419': $out =  'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است'; break;
			case '421': $out =  'IP‌ نامعتبر است';  break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case '1':
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case 'notff': $out = 'سفارش پیدا نشد';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}

	public function getInfo ($id){
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('address_telephone');
		$query->from($db->qn('#__hikashop_address'));
		$query->where($db->qn('address_user_id') .  '=' . $db->q(intval($id)));
		$db->setQuery((string)$query); 
		$result = $db->Loadresult();
		return $result;
	}
}
