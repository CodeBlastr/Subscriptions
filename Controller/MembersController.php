<?php
class MembersController extends MembersAppController {

	var $name = 'Members';
	
	/*
	 *  function is_member 
	 *			to check the role of member currently login
	 *			if user_role is __USERS_PAID_ROLE_ID then it will redirect to __USERS_PAID_ROLE_REDIRECT
	 * 			else it will call is_item_in_cart() function
	 */
	function is_member($user_id = null) {
		$user = ClassRegistry::init('Users.User');

		$user_id = isset($user_id) ? $user_id : $this->Auth->user('id');
		$userInfo = $user->findById($user_id);
		
		if(defined('__USERS_PAID_ROLE_ID') &&  __USERS_PAID_ROLE_ID == $userInfo['User']['user_role_id'] ) {
			// if user has already paid, no need for any processing
			$this->redirect(__USERS_PAID_ROLE_REDIRECT);
		} else if(defined('__USERS_PAID_EXPIRED_ROLE_ID') && __USERS_PAID_EXPIRED_ROLE_ID == $userInfo['User']['user_role_id']) {
			// user hasnt paid anything yet. Check if its in cart or not
			$this->is_item_in_cart($user_id);
		} else {
			$this->redirect(__USERS_PAID_ROLE_REDIRECT);
		}
	}

	/*
	 * function is_item_in_cart() 
	 * 		finds items in cart if any for logged in user
	 * 		if not found then redirect to catalog_item page
	 */
	function is_item_in_cart($user_id = null){
		$oi = ClassRegistry::init('Orders.OrderItem');
		$item_in_cart = $oi->find('first', array(
			'conditions' => array(
				'OrderItem.customer_id' => $user_id,
				'OrderItem.status' => 'incart', 
				'OrderItem.arb_settings is not null'
				)
			));
		if(!empty($item_in_cart)) {
			$this->redirect('/orders/order_transactions/checkout');
		} else {
			$this->redirect(__APP_MEMBERSHIP_CATALOG_ITEM_REDIRECT);
		}
	}

	/*
	 * function set_paid_user_role()
	 * Checkout page redirects here.
	 */
	function set_paid_user_role(){
		App::import('Model', 'Orders.OrderTransaction');
		$this->OrderTransaction = new OrderTransaction();

		$user_id = $this->Session->read('Auth.User.id');
		
		//get last OrderTransaction for logged in user
		$ot = $this->OrderTransaction->find('first', array(
			'conditions' => array(
				'OrderTransaction.is_arb' => 1,
				'OrderTransaction.customer_id' => $user_id
				), 
			'order' => array(
				'OrderTransaction.created DESC'
				)
			));

		if($ot['OrderTransaction']['status'] == 'paid'){

			if($this->Member->changeUserRole($user_id, $ot['OrderTransaction']['status'])){
				$this->redirect(__USERS_PAID_ROLE_REDIRECT);
			} else {
				$this->Session->setFlash("There Is Some Problem With Your Membership Contact to Admin");
				$this->redirect(__APP_DEFAULT_LOGIN_REDIRECT_URL);
			}
		} else {
			$this->is_item_in_cart($user_id);
		}
	}
	
	/*
	 * Cancel Subscription 
	 * 
	 * @param {$user_id} user_id 
	 * @param {$suspend_gateway} suspend_gateway default value true 
	 */
	function cancelSubscription($user_id = null, $suspend_gateway = true){
			if($this->Member->suspendSubscription($user_id, $suspend_gateway)){
				$this->Session->setFlash("Your Subscription Is Cancelled");
				$this->is_item_in_cart();
			} else {
				$this->Session->setFlash("There Is Some Problem Your Subscription is not cancelled Contact to Admin");
				$this->redirect(__APP_DEFAULT_LOGIN_REDIRECT_URL);
			}
	}
	
}
?>