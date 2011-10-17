<?php
 
 Class IpnNotificationsController extends MembersAppController{
 	
 	var $name = 'IpnNotifications';
	var $uses = array();
 	var $check = 'begin';

	var $allowedActions = array('paypal','authorize');

	/* Paypal
	 * Get CallBack Response from paypal
	 *	and validate the response
	 */
 	function paypal(){
 		
		$filename = APP_DIR. DS .'tmp' . DS .'logs' . DS . 'paypal' . DS .'paypalLogs' . date("Ymd hms") . '.log';
		$fhnew = fopen($filename, 'w');
				
		if(!empty($_POST)){
 			$req = 'cmd=_notify-validate';
			foreach ($_POST as $key => $value) {
				$value = urlencode(stripslashes($value));
				$req .= "&$key=$value";
			}
			$notification = $this->_sendAcknowledgement($req);
			
			if (!empty($notification)) {
				$this->_process($notification);
			}
		}
		fwrite($fhnew, $this->check);
		fclose ($fhnew);
	}

	/* _sendAcknowledgement
 	 * Send acknowledgement to paypal
 	 *
 	 */
 	function _sendAcknowledgement($req){
 		$notification = array();
		$verified = false;
		$header = '';

 		$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

		$fp = fsockopen ('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
		fputs ($fp, $header . $req);
		
		while (!feof($fp)) {
			$res = fgets ($fp, 1024);
			if (strcmp ($res, "VERIFIED") == 0) {
				$verified = true;
			} elseif (strcmp ($res, "INVALID") == 0) {
				$verified = false;
				break;
		  	}
		}
		fclose ($fp);
		
		if ($verified) {	
			foreach($_POST as $key => $value){
				$this->check .= " . . {$key} ==> {$value} \n\r .  . ";
				$notification[$key] = $value;
			}
			fwrite($fhnew, $this->check);
		}
		return $notification;
 	}
 	
 	/* Authorize
	 * Get CallBack Response from authorize 
	 *	and validate the response 
	 */
 	function authorize(){

		$filename = APP_DIR. DS .'tmp' . DS .'logs' . DS . 'authorize' . DS .'authorizeLogs' . '(' . date("Ymd hms") . ').log';
		$fh = fopen($filename, 'w');
		foreach($_POST as $key => $value){
			$this->check .= " . . {$key} ==> {$value} \n\r .  . ";
		}
		fwrite($fh, $this->check);
		fclose($fh);
 		$subscription_id = (int) $_POST['x_subscription_id'];
		$response_reason_text = $_POST['x_response_reason_text'];
		$response_code = (int) $_POST['x_response_code'];
		$amount = (int) $_POST['x_amount'];
		if($subscription_id){
			// Get the response code. 1 is success, 2 is decline, 3 is error
			if ($response_code == 1) {
				$status = 'Active';
			} else {
				$status = 'Suspended';
			}
		}
		$notification = array();
		$notification['recurring_payment_id'] = $subscription_id ;
		$notification['profile_status'] = $status;
		$notification['txn_type'] = $response_reason_text;
		$notification['amount'] = $amount;
		$this->_process($notification);
	}

 	
 	/*
 	 * Process Notifications
 	 * *@param {$notification} Array of response from paypal server 
 	 */
 	function _process($notification) {
 		
 		App::import('Model', 'OrderTransaction');
 		$this->OrderTransaction = new OrderTransaction();
 		
 		App::import('Model', 'members.Member');
 		$this->Member = new Member();
 		
 		if(isset($notification['recurring_payment_id'])){
 			$profileid = $notification['recurring_payment_id'];
			$status = $notification['profile_status'];
			$amount = $notification['amount'];
			$processor_response = str_replace("_", " ", $notification['txn_type']);
			
			$is_recurring = 1;
			$order_transaction_id = $this->OrderTransaction->OrderPayment->getOrderTransactionId($profileid, $is_recurring);
									
			$suspend_user = false;
			$suspend_gateway = false;
			$activate_gateway = false;
			
			if($status == 'Active'){
				if($amount != $this->OrderTransaction->getArbTransactionAmount($order_transaction_id)){
					$processor_response = 'The amount of subscription doesnt match our records. Contact Administrator';
					$suspend_user = true;
					$suspend_gateway = true;
				}	
			} else {
				$suspend_user = true;
			}

			$user_id = $this->OrderTransaction->getArbTransactionUserId($order_transaction_id) ;
	
			if($suspend_user){
				$this->Member->suspendSubscription($user_id, $suspend_gateway);
	    
			} else {
				// don not need to activate hjere as already activated at gateway
				 $this->Member->activateSubscription($user_id, $activate_gateway);
			}
			if($status == 'Suspended' || $status == 'Pending' || $status == 'Cancelled' || $status == 'Expired'){
				 $status = 'cancelled';
			} else {
				 $status = 'paid';
			}
		
		    $this->OrderTransaction->changeStatus($order_transaction_id, $status, $processor_response);
			
 		}
	}

}
 
?>