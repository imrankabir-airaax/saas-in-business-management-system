<?php
/**
 * AI report model (insights & recommendations produced by the AI service).
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for ai_reports.
 */
class SBMS_AI_Report_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'ai_reports';

	/**
	 * Reports record creation time only.
	 *
	 * @var string|null
	 */
	protected $updated_column = null;

	/**
	 * @var array<string,string>
	 */
	protected $columns = array(
		'user_id'     => '%d',
		'report_type' => '%s',
		'context'     => '%s',
		'result'      => '%s',
		'metadata'    => '%s',
		'model'       => '%s',
	);

	/**
	 * Most recent reports of a given type for a user.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $report_type Report type key.
	 * @param int    $limit       Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent_for_user( $user_id, $report_type = '', $limit = 20 ) {
		$where = array( 'user_id' => (int) $user_id );

		if ( '' !== $report_type ) {
			$where['report_type'] = (string) $report_type;
		}

		return $this->all(
			array(
				'where'   => $where,
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'limit'   => (int) $limit,
			)
		);
	}
}
