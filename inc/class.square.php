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
		}
	}

	public function get_square_location_id() {
		return get_option( 'techspace_membership_square_location_id' );
	}

	public function get_square_client() {
		$client = new \Square\SquareClient( [
			'accessToken' => get_option( 'techspace_membership_square_access_token' ),
			'environment' => get_option( 'techspace_membership_square_environment' ),
		] );

		return $client;
	}

	public function get_all_contacts( $force = false ) {
		$square_client = $this->get_square_client();
		$all_contacts  = get_transient( 'techspace_square_contacts' );
		if ( ! $force && $all_contacts && is_array( $all_contacts ) ) {
			return $all_contacts;
		} else {
			$all_contacts = array();
		}

		$customersApi = $square_client->getCustomersApi();
		$cursor       = false;
		do {
			$apiResponse = $customersApi->listCustomers( $cursor );
			$cursor      = '';
			if ( $apiResponse->isSuccess() ) {
				/** @var $listCustomersResponse \Square\Models\ListCustomersResponse */
				$listCustomersResponse = $apiResponse->getResult();
				$cursor                = $listCustomersResponse->getCursor();
				foreach ( $listCustomersResponse->getCustomers() as $customer ) {
					$all_contacts[ $customer->getId() ] = array(
						'name'  => $customer->getGivenName() . ' ' . $customer->getFamilyName(),
						'email' => $customer->getEmailAddress(),
					);
				}
			}
		} while ( $cursor );

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
		$square_client = $this->get_square_client();

		if ( ! $force ) {
			$all_invoices = get_transient( 'techspace_square_invoices' );
			if ( $all_invoices && isset( $all_invoices[ $contact_id ] ) ) {
				return $all_invoices[ $contact_id ];
			}
		}
		if ( ! isset( $all_invoices ) || ! is_array( $all_invoices ) ) {
			$all_invoices = array();
		}

		if ( ! isset( $all_invoices[ $contact_id ] ) ) {
			$all_invoices[ $contact_id ] = array();
		}

		$body_query_filter_locationIds = [ $this->get_square_location_id() ];
		$body_query_filter             = new \Square\Models\InvoiceFilter(
			$body_query_filter_locationIds
		);
		$body_query_filter->setCustomerIds( [ $contact_id ] );
		$body_query            = new \Square\Models\InvoiceQuery(
			$body_query_filter
		);
		$body_query_sort_field = 'INVOICE_SORT_DATE';
		$body_query->setSort( new \Square\Models\InvoiceSort(
			$body_query_sort_field
		) );
		$body_query->getSort()->setOrder( \Square\Models\SortOrder::DESC );
		$body = new \Square\Models\SearchInvoicesRequest(
			$body_query
		);
		$body->setLimit( 164 );
		$body->setCursor( 'cursor0' );

		$invoicesApi = $square_client->getInvoicesApi();
		$apiResponse = $invoicesApi->searchInvoices( $body );

		if ( $apiResponse->isSuccess() ) {
			$searchInvoicesResponse = $apiResponse->getResult();
			$searchInvoices         = $searchInvoicesResponse->getInvoices();
			if ( $searchInvoices ) {
				/** @var $invoice \Square\Models\Invoice */
				foreach ( $searchInvoices as $invoice ) {

					$invoice_id        = $invoice->getId();
					$invoice_status    = $invoice->getStatus();
					$invoice_timestamp = strtotime( $invoice->getCreatedAt() );
					$payment_requests  = $invoice->getPaymentRequests();
					if ( $payment_requests ) {
						$payment_request   = $payment_requests[0];
						$invoice_total     = $payment_request->getComputedAmountMoney()->getAmount() / 100;
						$invoice_timestamp = strtotime( $payment_request->getDueDate() );
					} else {
						$invoice_total = 0;
					}

					$all_invoices[ $contact_id ] [ $invoice_id ] = array(
						'number'    => $invoice->getInvoiceNumber(),
						'time'      => $invoice_timestamp,
						'paid_time' => $invoice_status === 'PAID' ? $invoice_timestamp : false,
						'status'    => $invoice_status,
						'total'     => $invoice_total,
						'due'       => $invoice_status === 'PAID' ? 0 : $invoice_total,
						'emailed'   => ! empty( $invoice->getPaymentRequests() ),
					);
				}
			}
		} else {
			$errors = $apiResponse->getErrors();
			echo 'Failed to get contact errors';
			print_r( $errors );
			exit;
		}

		if ( $all_invoices ) {
			set_transient( 'techspace_square_invoices', $all_invoices, 12 * HOUR_IN_SECONDS );
		}

		return isset( $all_invoices[ $contact_id ] ) ? $all_invoices[ $contact_id ] : array();
	}

	public function get_contact_metadata( $contact_id ) {
		$square_client = $this->get_square_client();

		$api_response = $square_client->getCustomersApi()->retrieveCustomer( $contact_id );

		$data = [
			'rfid_codes'     => [],
			'slack_username' => ''
		];
		if ( $api_response->isSuccess() ) {
			/** @var $result \Square\Models\RetrieveCustomerResponse */
			$result = $api_response->getResult();
			$customer = $result->getCustomer();
			$notes  = explode( "\n", $customer->getNote() );
			foreach ( $notes as $note ) {
				$bits = explode( ": ", $note );
				if(count($bits) === 2) {
					if ( $bits[0] === "RFID" ) {
						$data['rfid_codes'][] = trim( $bits[1] );
					} else if ( $bits[1] === "Slack" ) {
						$data['slack_username'] = trim( $bits[1] );
					}
				}
			}
		} else {
			$errors = $api_response->getErrors();
		}

		return $data;
	}

	public function create_contact( $details ) {

		$bits = explode( ' ', $details['name'] );

		$square_client = $this->get_square_client();
		$customersApi  = $square_client->getCustomersApi();

		$body = new \Square\Models\CreateCustomerRequest;
		$body->setGivenName( array_shift( $bits ) );
		$body->setFamilyName( implode( ' ', $bits ) );
		$body->setEmailAddress( $details['email'] );
		$body->setPhoneNumber( $details['phone'] );
		$body->setNote( 'Customer created from WordPress' );

		$apiResponse = $customersApi->createCustomer( $body );

		if ( $apiResponse->isSuccess() ) {
			$createCustomerResponse = $apiResponse->getResult();
			$customer_id            = $createCustomerResponse->getCustomer()->getId();
			if ( $customer_id ) {
				return $customer_id;
			} else {
				echo 'Failed to get customer ID';
				exit;
			}
		} else {
			$errors = $apiResponse->getErrors();
			echo 'Exception when calling create customer: ';
			print_r( $errors );
			exit;
		}
	}


	/**
	 * @param $member_database_id int
	 * @param $square_customer_id string
	 * @param $invoice_details array
	 *  name  line item name
	 *  money  in cents
	 *  due_date  Y-m-d
	 *
	 * @return false|mixed
	 */
	public function create_invoice( $member_database_id, $square_customer_id, $invoice_details ) {
		$square_client = $this->get_square_client();

		// CREATE ORDER FIRST:

		$base_price_money = new \Square\Models\Money();
		$base_price_money->setAmount( $invoice_details['money'] );
		$base_price_money->setCurrency( 'AUD' );

		$order_line_item = new \Square\Models\OrderLineItem( '1' );
		$order_line_item->setName( $invoice_details['name'] );
		$order_line_item->setBasePriceMoney( $base_price_money );

		$tax = new \Square\Models\OrderLineItemTax();
		$tax->setName( 'GST' );
		$tax->setPercentage( '10' );
		$tax->setType( 'INCLUSIVE' );

		$line_items = [ $order_line_item ];
		$order      = new \Square\Models\Order( $this->get_square_location_id() );
		$order->setCustomerId( $square_customer_id );
		$order->setLineItems( $line_items );
		$order->setTaxes( [ $tax ] );

		$body = new \Square\Models\CreateOrderRequest();
		$body->setOrder( $order );
		$body->setIdempotencyKey( md5( $square_customer_id . serialize( $invoice_details ) ) );

		$api_response = $square_client->getOrdersApi()->createOrder( $body );

		if ( ! $api_response->isSuccess() ) {
			$errors = $api_response->getErrors();
			echo "Failed to create square order";
			print_r( $errors );
			exit;
		}

		/** @var $create_order_result \Square\Models\CreateOrderResponse */
		$create_order_result = $api_response->getResult();
		$created_order       = $create_order_result->getOrder();
		$created_order_id    = $created_order->getId();

		// CREATE INVOICE FOR THE ORDER:

		$primary_recipient = new \Square\Models\InvoiceRecipient();
		$primary_recipient->setCustomerId( $square_customer_id );

		$invoice_payment_request = new \Square\Models\InvoicePaymentRequest();
		$invoice_payment_request->setRequestType( 'BALANCE' );
		$invoice_payment_request->setDueDate( $invoice_details['due_date'] );

		$payment_requests         = [ $invoice_payment_request ];
		$accepted_payment_methods = new \Square\Models\InvoiceAcceptedPaymentMethods();
		$accepted_payment_methods->setCard( true );

		$invoice = new \Square\Models\Invoice();
		$invoice->setOrderId( $created_order_id );
		$invoice->setTitle( $invoice_details['name'] );
		$invoice->setPrimaryRecipient( $primary_recipient );
		$invoice->setPaymentRequests( $payment_requests );
		$invoice->setDeliveryMethod( 'EMAIL' );
		$invoice->setAcceptedPaymentMethods( $accepted_payment_methods );

		$body = new \Square\Models\CreateInvoiceRequest( $invoice );
		$body->setIdempotencyKey( md5( $square_customer_id . $created_order_id ) );

		$api_response = $square_client->getInvoicesApi()->createInvoice( $body );

		if ( ! $api_response->isSuccess() ) {
			$errors = $api_response->getErrors();
			echo "Failed to create square invoice";
			print_r( $errors );
			exit;
		}

		/** @var $create_invoice_result \Square\Models\CreateInvoiceResponse */
		$create_invoice_result = $api_response->getResult();
		$created_invoice       = $create_invoice_result->getInvoice();
		$created_invoice_id    = $created_invoice->getId();

		// PUBLISH THE DRAFT INVOICE! YAY

		$body = new \Square\Models\PublishInvoiceRequest( 0 );
		$body->setIdempotencyKey( md5( $square_customer_id . $created_invoice_id ) );

		$api_response = $square_client->getInvoicesApi()->publishInvoice( $created_invoice_id, $body );

		if ( ! $api_response->isSuccess() ) {
			$errors = $api_response->getErrors();
			echo "Failed to publish square invoice";
			print_r( $errors );
			exit;
		}

		// reload invoice cache for member
		sleep( 5 ); // Unforunately square takes a little while to actually publish things.
		$invoices = TechSpace_Square::get_instance()->get_contact_invoices( $square_customer_id, true );
		if ( $invoices ) {
			$member_manager = dtbaker_member::get_instance();
			$member_manager->cache_member_invoices( $member_database_id, $invoices );
		}

		return $created_invoice_id;
	}

}

TechSpace_Square::get_instance();
