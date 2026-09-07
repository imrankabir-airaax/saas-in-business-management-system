<?php
/**
 * Business profile model.
 *
 * One row per WordPress user, holding business/application data. Login
 * credentials are handled entirely by WordPress core — never stored here.
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for business_profiles.
 */
class SBMS_Business_Profile_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'business_profiles';

	/**
	 * Writable columns and their $wpdb formats.
	 *
	 * @var array<string,string>
	 */
	protected $columns = array(
		'user_id'        => '%d',
		'business_name'  => '%s',
		'business_email' => '%s',
		'business_phone' => '%s',
		'address'        => '%s',
		'currency'       => '%s',
		'tax_number'     => '%s',
	);

	/**
	 * Get the profile belonging to a WordPress user (1:1), or null.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string,mixed>|null
	 */
	public function find_for_user( $user_id ) {
		return $this->find_by( 'user_id', (int) $user_id );
	}

	/**
	 * Create the profile if absent, otherwise update the existing one.
	 *
	 * @param int                 $user_id WordPress user ID.
	 * @param array<string,mixed> $data    Profile fields.
	 * @return int|false Profile row ID on success, false on failure.
	 */
	public function upsert_for_user( $user_id, array $data ) {
		$user_id          = (int) $user_id;
		$data['user_id']  = $user_id;
		$existing         = $this->find_for_user( $user_id );

		if ( $existing ) {
			$updated = $this->update( (int) $existing[ $this->primary_key ], $data );
			return ( false === $updated ) ? false : (int) $existing[ $this->primary_key ];
		}

		return $this->create( $data );
	}
}
