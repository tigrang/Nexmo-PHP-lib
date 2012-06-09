<?php
/**
 * Class NexmoAccount handles interaction with your Nexmo account
 *
 * Usage: $var = new NexmoAccount($account_key, $account_secret);
 * Methods:
 *     balance()
 *     smsPricing($country_code)
 *     getCountryDialingCode($country_code)
 *     numbersList()
 *     numbersSearch($country_code, $pattern)
 *     numbersBuy($country_code, $msisdn)
 *     numbersCancel($country_code, $msisdn)
 *     searchMessage($message_id)
 *     searchMessages($ids)
 *     searchMessagesByRecipient($to, $date)
 *
 */
class NexmoAccount {

	/**
	 * Nexmo API Key
	 * @var string
	 */
	private $nx_key = '';

	/**
	 * Nexmo API Secret
	 * @var string
	 */
	private $nx_secret = '';

	/**
	 * Nexmo REST API base URL
	 * @var string
	 */
	public $rest_base_url = 'https://rest.nexmo.com/';

	/**
	 * Nexmo REST commands
	 * @var array
	 */
	private $rest_commands = array (
		'get_balance' => array('method' => 'GET', 'url' => '/account/get-balance/{k}/{s}'),
		'get_pricing' => array('method' => 'GET', 'url' => '/account/get-pricing/outbound/{k}/{s}/{country_code}'),
		'get_own_numbers' => array('method' => 'GET', 'url' => '/account/numbers/{k}/{s}'),
		'search_numbers' => array('method' => 'GET', 'url' => '/number/search/{k}/{s}/{country_code}?pattern={pattern}'),
		'buy_number' => array('method' => 'POST', 'url' => '/number/buy/{k}/{s}/{country_code}/{msisdn}'),
		'cancel_number' => array('method' => 'POST', 'url' => '/number/cancel/{k}/{s}/{country_code}/{msisdn}'),
		'search_message' => array('method' => 'GET', 'url' => '/search/message/{k}/{s}/{message_id}'),
		'search_messages' => array('method' => 'GET', 'url' => '/search/messages/{k}/{s}'),
	);

	/**
	 * Cache results of API calls
	 * @var array
	 */
	private $cache = array();

	/**
	 * @param $nx_key Your Nexmo account key
	 * @param $nx_secret Your Nexmo secret
	 */
	public function __construct($api_key, $api_secret) {
		$this->nx_key = $api_key;
		$this->nx_secret = $api_secret;
	}

	/**
	 * Return your account balance in Euros
	 * @return float|bool
	 */
	public function balance() {
		if (!isset($this->cache['balance'])) {
			$tmp = $this->apiCall('get_balance');
			if (!$tmp['data']) {
				return false;
			}
			$this->cache['balance'] = $tmp['data']['value'];
		}
		return (float)$this->cache['balance'];
	}

	/**
	 * Find out the price to send a message to a country
	 * @param $country_code Country code to return the SMS price for
	 * @return float|bool
	 */
	public function smsPricing($country_code) {
		$country_code = strtoupper($country_code);
		if (!isset($this->cache['country_codes'])) {
			$this->cache['country_codes'] = array();
		}
		if (!isset($this->cache['country_codes'][$country_code])) {
			$tmp = $this->apiCall('get_pricing', array('country_code'=>$country_code));
			if (!$tmp['data']) {
				return false;
			}
			$this->cache['country_codes'][$country_code] = $tmp['data'];
		}
		return (float)$this->cache['country_codes'][$country_code]['mt'];
	}

	/**
	 * Return a countries international dialing code
	 * @param $country_code Country code to return the dialing code for
	 * @return string|bool
	 */
	public function getCountryDialingCode($country_code) {
		$country_code = strtoupper($country_code);
		if (!isset($this->cache['country_codes'])) {
			$this->cache['country_codes'] = array();
		}
		if (!isset($this->cache['country_codes'][$country_code])) {
			$tmp = $this->apiCall('get_pricing', array('country_code'=>$country_code));
			if (!$tmp['data']) {
				return false;
			}
			$this->cache['country_codes'][$country_code] = $tmp['data'];
		}
		return (string)$this->cache['country_codes'][$country_code]['prefix'];
	}

	/**
	 * Get an array of all purchased numbers for your account
	 * @return array|bool
	 */
	public function numbersList() {
		if (!isset($this->cache['own_numbers'])) {
			$tmp = $this->apiCall('get_own_numbers');
			if (!$tmp['data']) {
				return false;
			}
			$this->cache['own_numbers'] = $tmp['data'];
		}
		if (!$this->cache['own_numbers']['numbers']) {
			return array();
		}
		return $this->cache['own_numbers']['numbers'];
	}

	/**
	 * Search available numbers to purchase for your account
	 * @param $country_code Country code to search available numbers in
	 * @param $pattern Number pattern to search for
	 * @return bool
	 */
	public function numbersSearch($country_code, $pattern) {
		$country_code = strtoupper($country_code);
		$tmp = $this->apiCall('search_numbers', array('country_code'=>$country_code, 'pattern'=>$pattern));
		if (!$tmp['data'] || !isset($tmp['data']['numbers'])) {
			return false;
		}
		return $tmp['data']['numbers'];
	}

	/**
	 * Purchase an available number to your account
	 * @param $country_code Country code for your desired number
	 * @param $msisdn Full number which you wish to purchase
	 * @return bool
	 */
	public function numbersBuy($country_code, $msisdn) {
		$country_code = strtoupper($country_code);
		$tmp = $this->apiCall('buy_number', array('country_code'=>$country_code, 'msisdn'=>$msisdn));
		return ($tmp['http_code'] === 200);
	}

	/**
	 * Cancel an existing number on your account
	 * @param $country_code Country code for which the number is for
	 * @param $msisdn The number to cancel
	 * @return bool
	 */
	public function numbersCancel($country_code, $msisdn) {
		$country_code = strtoupper($country_code);
		$tmp = $this->apiCall('cancel_number', array('country_code'=>$country_code, 'msisdn'=>$msisdn));
		return ($tmp['http_code'] === 200);
	}

	/**
	 * Search message by id
	 * @param $message_id Message id to retrieve
	 * @return boolean|array
	 */
	public function searchMessage($message_id) {
		if (!isset($this->cache['message'][$message_id])) {
			$tmp = $this->apiCall('search_message', array('message_id'=>$message_id));
			$message = ($tmp['http_code'] === 200) ? $tmp['data'] : false;
			$this->cache['message'][$message_id] = $message;
		}
		return $this->cache['message'][$message_id];
	}

	/**
	 * Search message multiple ids
	 * @param $ids Array of message ids
	 * @return boolean|array
	 */
	public function searchMessages($ids) {
		$tmp = $this->apiCall('search_messages', null, compact('ids'));
		return ($tmp['http_code'] === 200) ? $tmp['data'] : false;
	}

	/**
	 * Search message by recipient and date
	 * @param $to Recipent's phone number
	 * @param $date Date when sms was sent in YYYY-MM-DD format
	 * @return boolean|array
	 */
	public function searchMessagesByRecipient($to, $date) {
		$tmp = $this->apiCall('search_messages', null, compact('to', 'date'));
		return ($tmp['http_code'] === 200) ? $tmp['data'] : false;
	}

	/**
	 * Run a REST command on Nexmo SMS services
	 * @param string $command REST command to call
	 * @param array $data POST data to send
	 * @param array $query GET paramaters
	 * @return array|bool
	 */
	private function apiCall($command, $data=array(), $query=array()) {
		if (!isset($this->rest_commands[$command])) {
			return false;
		}

		$cmd = $this->rest_commands[$command];
		$url = $cmd['url'];
		$url = str_replace(array('{k}', '{s}'), array($this->nx_key, $this->nx_secret), $url);

		$parsed_data = array();
		foreach ((array)$data as $k => $v) {
			$parsed_data['{'.$k.'}'] = $v;
		}
		$url = str_replace(array_keys($parsed_data), array_values($parsed_data), $url);

		$url = trim($this->rest_base_url, '/') . $url;
		$queryString = '';
		foreach($query as $k => $v) {
			if (is_array($v)) {
				foreach($v as $i) {
					$queryString .= "$k=$i&";
				}
			} else {
				$queryString .= "$k=$v&";
			}
		}
		if ($queryString) {
			$url .= '?' . substr($queryString, 0, -1);
		}
		$post_data = '';

		// If available, use CURL
		if (function_exists('curl_version')) {
			$to_nexmo = curl_init( $url );
			curl_setopt($to_nexmo, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($to_nexmo, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($to_nexmo, CURLOPT_HTTPHEADER, array('Accept: application/json'));
			if ($cmd['method'] == 'POST') {
				curl_setopt($to_nexmo, CURLOPT_POST, true);
				curl_setopt($to_nexmo, CURLOPT_POSTFIELDS, $post_data);
			}
			$from_nexmo = curl_exec($to_nexmo);
			$curl_info = curl_getinfo($to_nexmo);
			$http_response_code = $curl_info['http_code'];
			curl_close ($to_nexmo);
		} elseif (ini_get('allow_url_fopen')) {
			// No CURL available so try the awesome file_get_contents
			$opts = array('http' =>
				array(
					'method' => 'GET',
					'header' => 'Accept: application/json'
				)
			);
			if ($cmd['method'] == 'POST') {
				$opts['http']['method'] = 'POST';
				$opts['http']['header'] .= "\r\nContent-type: application/x-www-form-urlencoded";
				$opts['http']['content'] = $post_data;
			}
			$context = stream_context_create($opts);
			$from_nexmo = file_get_contents($url, false, $context);
			// get the response code
			preg_match('/HTTP\/[^ ]+ ([0-9]+)/i', $http_response_header[0], $m);
			$http_response_code = $m[1];
		} else {
			// No way of sending a HTTP post :`(
			return false;
		}
		$data = json_decode($from_nexmo, true);
		return array(
			'data' => $data,
			'http_code' => (int)$http_response_code
		);
	}

}