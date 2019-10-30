<?php


class TechSpace_Square {

	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	/** Hook WordPress
	 * @return void
	 */
	public function __construct() {
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_action( 'parse_request', array( $this, 'sniff_requests' ), 0 );
		add_action( 'init', array( $this, 'add_endpoint' ), 0 );
	}

	/** Add public query vars
	 *
	 * @param array $vars List of current public query vars
	 *
	 * @return array $vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = '__square';

		return $vars;
	}

	/** Add API Endpoint
	 *  This is where the magic happens - brush up on your regex skillz
	 * @return void
	 */
	public function add_endpoint() {
		add_rewrite_rule( '^api/square/webhook', 'index.php?__square=1', 'top' );
	}

	/**  Sniff Requests
	 *  This is where we hijack all API requests
	 *  If $_GET['__api'] is set, we kill WP and serve up api results
	 * @return die if API request
	 */
	public function sniff_requests() {
		global $wp;
		if ( isset( $wp->query_vars['__square'] ) ) {
			echo 'square';
			ini_set( 'display_errors', true );
			ini_set( 'error_reporting', E_ALL );
			$callbackBody = file_get_contents( 'php://input' );
			mail( 'dtbaker@gmail.com', 'Square Webhook', $callbackBody );
			exit;
		}
	}

	public function _square_api_init() {
		require( __DIR__ . '/../connect-php-sdk-master/autoload.php' );
		\SquareConnect\Configuration::getDefaultConfiguration()->setAccessToken( get_option( 'techspace_membership_square_access_token' ) );
	}

	public function get_all_contacts( $force = false ) {
		$this->_square_api_init();
		$all_contacts = get_transient( 'techspace_square_contacts' );
		if ( ! $force && $all_contacts && is_array( $all_contacts ) ) {
			return $all_contacts;
		} else {
			$all_contacts = array();
		}
		$api_instance = new SquareConnect\Api\CustomersApi();
		try {
			$result = $api_instance->listCustomers();
			foreach ( $result->getCustomers() as $customer ) {
				$all_contacts[ $customer->getId() ] = array(
					'name'  => $customer->getGivenName() . ' ' . $customer->getFamilyName(),
					'email' => $customer->getEmailAddress(),
				);
			}
		} catch ( Exception $e ) {

		}
		if ( $all_contacts ) {
			// sort by name.
			uasort( $all_contacts, function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			} );
			set_transient( 'techspace_square_contacts', $all_contacts, 12 * HOUR_IN_SECONDS );
		}

		return $all_contacts;
	}


	public function get_contact_invoices( $contact_id, $force = false ) {
		$this->_square_api_init();
		$all_invoices = get_transient( 'techspace_square_invoices' );
		if ( ! $force && $all_invoices && isset( $all_invoices[ $contact_id ] ) ) {
			return $all_invoices[ $contact_id ];
		} else if ( ! $all_invoices ) {
			$all_invoices = array();
		}

		// techspace location
		$location_id     = 'DQYMWM0W27CD8';
		$translation_api = new SquareConnect\Api\TransactionsApi();
		try {
			$begin_transaction_time = strtotime( '-120 days' );
			$next_cursor            = null;
			do {
				$result      = $translation_api->listTransactions( $location_id, date( 'Y-m-d\TH:i:sP', $begin_transaction_time ), null, null, $next_cursor );
				$next_cursor = $result->getCursor();
				foreach ( $result->getTransactions() as $transaction ) {
					foreach ( $transaction->getTenders() as $tender ) {
						$customer_id             = $tender->getCustomerId();
						$transaction_description = $tender->getNote();
						$money                   = $tender->getAmountMoney();
						$transaction_price       = (int) ( $money->getAmount() / 100 );
						$transaction_time        = strtotime( $transaction->getCreatedAt() );
						$tender_id= $tender->getId();

						if($customer_id) {
							if ( ! isset( $all_invoices[ $customer_id ] ) ) {
								$all_invoices[ $customer_id ] = array();
							}
							$all_invoices[ $customer_id ][ $tender_id ] = array(
								'number'    => (string) $tender_id,
								'time'      => $transaction_time,
								'paid_time' => $transaction_time,
								'status'    => 'PAID',
								'total'     => $transaction_price,
								'due'       => 0,
								'emailed'   => true,
							);

							//echo "Customer: $customer_id ($transaction_description) for $transaction_price ( " . date( 'Y-m-d', $transaction_time ) . ") <br>\n";
						}
					}
				}
			} while ( $next_cursor );
		} catch ( Exception $e ) {
			echo 'Exception when calling TransactionApi->listTransactions: ', $e->getMessage(), PHP_EOL;
		}


		if ( $all_invoices ) {
			set_transient( 'techspace_square_invoices', $all_invoices, 12 * HOUR_IN_SECONDS );
		}

		return isset( $all_invoices[ $contact_id ] ) ? $all_invoices[ $contact_id ] : array();
	}



	public function create_contact( $details ) {

		$bits = explode(' ',$details['name']);

		$this->_square_api_init();
		$customer_api = new \SquareConnect\Api\CustomersApi();
		$body = new \SquareConnect\Model\CreateCustomerRequest(); // \SquareConnect\Model\CreateCustomerRequest | An object containing the fields to POST for the request.  See the corresponding object definition for field details.
		$body->setGivenName(array_shift($bits));
		$body->setFamilyName(implode(' ',$bits));
		$body->setEmailAddress($details['email']);
		$body->setPhoneNumber($details['phone']);

		try {
			$customer    = $customer_api->createCustomer( $body );
			$customer_id = $customer->getCustomer()->getId();
			if ( $customer_id ) {
				return $customer_id;
			}
		}catch(Exception $e){
			echo 'Exception when calling create customer: ', $e->getMessage(), PHP_EOL;
			exit;
		}
		return false;
	}

}

TechSpace_Square::get_instance();
