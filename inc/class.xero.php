<?php

define ( "XRO_APP_TYPE", "Private" );

class dtbaker_xero{

	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function _xero_api_init(){

		require_once plugin_dir_path(__DIR__).'XeroOAuth-PHP/lib/XeroOAuth.php';

		$useragent = "GCTechSpace WordPress Plugin";

		$signatures = array (
			'consumer_key' => get_option( 'techspace_membership_consumer_key' ),
			'shared_secret' => get_option( 'techspace_membership_secret_key' ),
			'public_key' => get_option( 'techspace_membership_public_key' ),
			'private_key' => get_option( 'techspace_membership_private_key' ),
			// API versions
			'core_version' => '2.0',
			'payroll_version' => '1.0',
			'file_version' => '1.0',
		);

		$XeroOAuth = new XeroOAuth ( array_merge ( array (
			'application_type' => XRO_APP_TYPE,
			'oauth_callback' => 'oob', // not needed for private app
			'user_agent' => $useragent
		), $signatures ) );

		$XeroOAuth->config ['access_token'] = $XeroOAuth->config ['consumer_key'];
		$XeroOAuth->config ['access_token_secret'] = $XeroOAuth->config ['shared_secret'];
		return $XeroOAuth;
	}

	public function get_all_contacts( $force = false ){
		$XeroOAuth = $this->_xero_api_init();
		$all_contacts = get_transient( 'techspace_xero_contacts' );
		if(!$force && $all_contacts && is_array($all_contacts)){
			return $all_contacts;
		}else{
			$all_contacts = array();
		}
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array());
		if ($XeroOAuth && isset($XeroOAuth->response['code']) && $XeroOAuth->response['code'] == 200) {
			$contacts = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			if(count($contacts->Contacts[0]) > 1){
				foreach($contacts->Contacts[0] as $contact){
					$contact_id = (string)$contact->ContactID;
					$all_contacts[$contact_id] = array(
						'name' => !empty($contact->Name) ? (string)$contact->Name : '',
						'email' => !empty($contact->EmailAddress) ? (string)$contact->EmailAddress : '',
					);
				}
			}
		} else {
			// log error?
		}
		if($all_contacts){
			// sort by name.
			uasort($all_contacts, function($a, $b) {
				return strnatcasecmp($a['name'], $b['name']);
			});
			set_transient( 'techspace_xero_contacts', $all_contacts, 12 * HOUR_IN_SECONDS );
		}
		return $all_contacts;
	}

	public function get_contact_invoices( $contact_id,  $force = false ) {
		$XeroOAuth    = $this->_xero_api_init();
		$all_invoices = get_transient( 'techspace_xero_invoices' );
		if ( ! $force && $all_invoices && isset( $all_invoices[ $contact_id ] ) ) {
			return $all_invoices[ $contact_id ];
		} else {
			$all_invoices = array();
		}
		$response = $XeroOAuth->request( 'GET', $XeroOAuth->url( 'Invoices', 'core' ), array(
			'Where' => 'Contact.ContactID = Guid("' . $contact_id . '")',
		) );
		if ( $XeroOAuth && isset( $XeroOAuth->response['code'] ) && $XeroOAuth->response['code'] == 200 ) {
			$invoices = $XeroOAuth->parseResponse( $XeroOAuth->response['response'], $XeroOAuth->response['format'] );
			if ( count( $invoices->Invoices[0] ) > 1 ) {
				foreach ( $invoices->Invoices[0] as $invoice ) {
					if ( $invoice ) {
						$invoice_id   = (string) $invoice->InvoiceID;
						$invoice_type = (string) $invoice->Type;
						if ( $invoice_type == 'ACCREC' ) {
							if ( ! isset( $all_invoices[ $contact_id ] ) ) {
								$all_invoices[ $contact_id ] = array();
							}
							$all_invoices[ $contact_id ][ $invoice_id ] = array(
								'number'  => (string) $invoice->InvoiceNumber,
								'time'    => strtotime( (string) $invoice->Date ),
								'status'  => (string) $invoice->Status,
								'total'   => (string) $invoice->Total,
								'due'     => (string) $invoice->AmountDue,
								'emailed' => ( (string) $invoice->SentToContact === "true" ), // convert string to bool
							);
						}
					}
				}
				if ( $all_invoices ) {
					// sort by name.
					/*uasort($all_invoices, function($a, $b) {
						return strnatcasecmp($a['name'], $b['name']);
					});*/
					set_transient( 'techspace_xero_invoices', $all_invoices, 12 * HOUR_IN_SECONDS );
				}
			}
		} else {
			// log error?
		}

		return isset( $all_invoices[ $contact_id ] ) ? $all_invoices[ $contact_id ] : array();
	}
}