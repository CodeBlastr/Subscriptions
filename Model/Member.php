<?php
class Member extends MembersAppModel {
	
	var $name = 'Member';
	var $useTable = false;

		
	/** 
	 * Change User Role
	 * 
	 * @param {$user_id} user_id 
	 * @param {$action} action = suspend/paid 
	 */ 
 	function changeUserRole($user_id, $action ){

 			App::Import('Model', 'Users.User');
			$user = new User();
			
			if($action == 'suspend') {
				$user_role_id = __USERS_PAID_EXPIRED_ROLE_ID;				
			} else if ($action == 'paid'){
				$user_role_id = __USERS_PAID_ROLE_ID;
			}
				
			$userRole = $user->UserRole->findById($user_role_id);
			$usr['User']['id'] = $user_id ;
			$usr['User']['role'] = $userRole['UserRole']['name'];
			return $user->changeRole($usr);
 	}
 	
	/** 
	 * Suspend Subscription
	 * 
	 * @param {$user_id} user_id 
	 * @param {$suspend_gateway} suspend_gateway default value = true 
	 */
 	function suspendSubscription($user_id, $suspend_gateway = true){
 		
 		if($suspend_gateway == true) {
 			
 			App::Import('Model', 'Orders.OrderTransaction');
			$this->OrderTransaction = new OrderTransaction();
	
	 		$order_transaction_id = $this->OrderTransaction->getArbTransactionId($user_id);
	 		$mode = $this->OrderTransaction->getArbPaymentMode($order_transaction_id);
	 		$profileId = $this->OrderTransaction->OrderPayment->getArbProfileId($order_transaction_id);
 			
 			App::import('Component', 'Orders.Payments');
		    $component = new PaymentsComponent();
			$suspend_subscription_data = array('Mode' => $mode, 'profileId' => $profileId, 
			    								'action' => 'suspend');
			$response = $component->ManageRecurringPaymentsProfileStatus($suspend_subscription_data);	
	 		if($response['response_code'] == 1) {
	 			$status = 'cancelled';
	 			$processor_response = $response['description']; 
	 			$this->OrderTransaction->changeStatus($order_transaction_id, $status, $processor_response);
				return $this->changeUserRole($user_id, 'suspend');
			}
 		} else {
 			return $this->changeUserRole($user_id, 'suspend');
 		}
		
 	}
 	
 	/** 
	 * Activate Subscription
	 * @param {$user_id} user_id
	 * @param {$activate_gateway} activate_gateway default = true 
	 */
 	function activateSubscription($user_id, $activate_gateway = true){
 		
 		 	if($activate_gateway == true) {
 		 		App::Import('Model', 'Orders.OrderTransaction');
				$this->OrderTransaction = new OrderTransaction();
		
		 		$order_transaction_id = $this->OrderTransaction->getArbTransactionId($user_id);
		 		$mode = $this->OrderTransaction->getArbPaymentMode($order_transaction_id);
		 		$profileId = $this->OrderTransaction->OrderPayment->getArbProfileId($order_transaction_id);
		 		
		 		App::import('Component', 'Orders.Payments');
			    $component = new PaymentsComponent();
			    
			    $suspend_subscription_data = array('Mode' => $mode, 'profileId' => $profileId, 
			    								'action' => 'reactivate');
			    $response = $component->ManageRecurringPaymentsProfileStatus($suspend_subscription_data);
		 		if($response['response_code'] == 1) {
					$this->changeUserRole($user_id, 'paid');
				} 		
 		 	} else {
 		 		$this->changeUserRole($user_id, 'paid');
 		 	}
 		
 	}
	
}
?>