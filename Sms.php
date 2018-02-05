<?php
	/**
	 *
	 * Send Custom SMS from innna the site
	 *
	 * @author         Akanji Michael <matscode@gmail.com>
	 * @category       Source
	 * @version        v1.0
	 *
	 */
	
	class Sms
	{
		
		protected
			$server 		= 'https://smstube.ng/api/',
			$serverAction 	= '',
			$resultType 	= 'json', // json | xml | v2 : $this is coded for json compat only
			
			$lowBalanceAlertTo 	= '', // mobile number of comma sepearated user to recieve low unit alert
			$lowBalanceMessage 	= "Visit www.smstube.ng to Top Up sms Unit. Message may stop to send in time. Current Balance is %s",
			
			$authNormal 		= [
				'username' => '',
				'password' => ''
			],
			$authKey 			= [
				'key' => '' // hardcode your api key here
			],
			$authType			= 'key', // key | normal
			$defaultSender 		= 'MAT', // set sms default sender
			
			$balance,
			
			$httpQuery 	= '',
			$error 		= '';
		
		public function __construct ($server_action = 'message') {
			// set default server action
			$this->setServerAction($server_action);
		}
		
		/**
		 * Set request action before making request to API endpoint
		 *
		 * @param $action
		 *
		 * @return string
		 *
		 */
		public function setServerAction ($action) {
			$server_action = '';
			switch ($action){
				case 'balance':
						$server_action = 'balance/get';
					break;
				
				case 'status':
						$server_action = 'status/get';
					break;
				
				case 'key_get':
						$server_action = 'key/get';
						
						$this->authType = 'normal';
					break;
				
				case 'key_reset':
						$server_action = 'key/reset';
						
						$this->authType = 'normal';
					break;
				
				case 'message':
						$server_action = 'sms/send';
				default:
					
					break;
			}
			
			$this->serverAction = $server_action . '?';
			
			return $this;
		}
		
		public function setAuthType ($auth_type) {
			$auths = [
				'key',
				'normal'
			];
			
			if (in_array($auth_type, $auths)) {
				$this->authType = $auth_type;
			} else {
				// set the default auth type as key
				$this->authType = 'key';
			}
			
			return $this;
		}
		
		/**
		 * the request HTTP Query
		 *
		 * for debugging purpose
		 *
		 * @return string
		 */
		public function getHttpQuery () {
			if($this->httpQuery) {
				return $this->httpQuery;
			}
			
			return 'No query initiated yet';
		}
		
		/**
		 * Initiate GET request to API endpoint
		 *
		 * @param array $query_key_value
		 *
		 * @return object
		 * @throws \Exception
		 */
		private function request ($query_key_value = []) {
			
			$query_key_value += [
				'type' 		=> $this->resultType // dealing with result objectly
			];
			
			switch ($this->authType){
				case 'normal':
					$auth = $this->authNormal;
					break;
				
				case 'key':
					$auth = $this->authKey;
				
				default:
					// the $auth default is key
					break;
			}
			
			if (!$this->serverAction) throw new Exception('SMS server action not set');
			
			$this->httpQuery =
				$this->server .
				$this->serverAction .
				http_build_query(array_merge($auth, $query_key_value), null, '&', PHP_QUERY_RFC3986);
			
			// $result get the response from the api server after message sent
			$result = json_decode(@file_get_contents($this->httpQuery));
			
				$dummy = json_decode("
					{
						'status': '1',
   						'id' : '1234567890',
						'balance': '3445',
						'remarks': 'success'
					}
					");

			return $result;
			
		}
		
		/**
		 * Sends the sms to designated phone number
		 *
		 * @param string $to       phone number of the recipient
		 * @param string $content  message body
		 * @param string $from     customized message sender
		 *
		 * @param null   $schedule ability to set a time in future to for sms to be sent
		 * @param bool   $is_flash set to true to make message flash message
		 *
		 * @return bool return true if msg sent else return false
		 *
		 * @throws \Exception
		 */
		public function send ($to, $content, $from = null, $schedule = null, $is_flash = false) {
			$query_key_value = [
				'from' 		=> ($from) ? $from : $this->defaultSender,
				'to' 		=> $to,
				'text' 		=> $content
			];
			
			// schedule message and make sure its not in the passed
			if($schedule){
				if ($schedule < time()) throw new Exception('Schedule time must be in future') ; // schedule time cannot be less than current time
				$query_key_value += [
					'timestamp' => $schedule
				];
			}
			
			// check if message is to be flash
			if($is_flash){
				$query_key_value += [
					'flash' => true
				];
			}
			
			$response = $this->request($query_key_value);
			
			// OR operator used to be soft of message sent confirmation
			if ((int)$response->status === 1 || strtolower($response->remarks) == 'success'){
				return true;
				
				$this->balance = $response->balance; // current balance after sending a message
			}
			$this->error = $response->remarks;
			return false;
			
		}
		
		/**
		 * This method is used to check and return the remaining sms unit on
		 * the bulk sms server
		 *
		 * @return int return the amount of the credit left on the bulk sms server
		 *
		 */
		public function balance () {
			// get the response from the api server in json format
			$response = $this->request();
			if ($response->status == 1 || $response->remarks == 'success') {
				return $response->balance;
			}
			$this->error = $response->remarks;
			
			return 0;
		}
		
		/**
		 * Gets and return the error message from the sms provider
		 *
		 * @return String The error message returned by the sms provider server
		 *
		 */
		public function getError () {
			return $this->error; // seems useless for now
		}
		
		/**
		 * this method sends low balance alert message
		 *
		 * @return void It just promote the action to send an alert of low sms unit
		 *
		 */
		public function lowBalanceAlert () {
			//check the sms balance before sending message out
			$balance = (int)$this->balance();
			if ($balance < 300) {
				// build the query
				if (!$this->lowBalanceAlertTo) return;
				
				$query_key_value = [
					'to' 	=> $this->lowBalanceAlertTo,
					'text' 	=> sprintf($this->lowBalanceMessage, $balance)
				];
				
				// get the response from the api server in json format
				$this->request($query_key_value);
			}
		}
		
	}
	
	/**
	 * Sample Sms Class Usage
	 *
	 */
	$sms = new Sms(); // initialize
	
	// send a sample serious sms
	if(!$sms->send('08186074929', 'Happy New Year MAT', 'Preshy')){
		// if message not sent, show us the error
		echo $sms->getError() . ' - ';
		// also print the composed URI query that made the request
		echo $sms->getHttpQuery() . ' <br><br> ';
	} else {
		echo 'Message sent succeccful' . ' - ';
		echo $sms->getHttpQuery() . ' <br><br> ';
	}
	
	// send a sample unserious sms
	if(!$sms
		->setAuthType('normal')
		->send('08186074929', 'Happy New Year Preshy', '@matscode')){
		echo $sms->getError() . ' - ';
		echo $sms->getHttpQuery() . ' <br><br> ';
	}
	
	// check balance
	echo
	$sms
		->setAuthType('key') // reset the auth type back to key
		->setServerAction('balance')
		->balance();
