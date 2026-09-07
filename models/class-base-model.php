<?php
/**
 * Abstract base model.
 *
 * A thin, safe data-access layer over $wpdb that every concrete model extends.
 * It centralises three things so individual models stay tiny and consistent:
 *
 *   1. Prepared statements for every query that touches a value.
 *   2. A fillable-column whitelist, so only known columns are ever written and
 *      only known columns are ever interpolated into SQL (values are always
 *      parameterised; identifiers are validated against the whitelist).
 *   3. Automatic created_at / updated_at handling and correct $wpdb formats
 *      (%d / %f / %s) derived from each model's declared column types.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all data models.
 */
abstract class SBMS_Base_Model {

	/**
	 * Unqualified table key, e.g. "products". Subclasses MUST set this.
	 *
	 * @var string
	 */
	protected $table = '';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Writable columns mapped to their $wpdb placeholder type.
	 * Type is one of '%d' (int), '%f' (decimal/float), '%s' (string/datetime).
	 * Subclasses MUST define this.
	 *
	 * @var array<string,string>
	 */
	protected $columns = array();

	/**
	 * Ownership column, or null when the table is owned indirectly
	 * (e.g. sale_items belong to a sale). Used by the *_for_user helpers.
	 *
	 * @var string|null
	 */
	protected $user_column = 'user_id';

	/**
	 * created_at column name, or null to disable.
	 *
	 * @var string|null
	 */
	protected $created_column = 'created_at';

	/**
	 * updated_at column name, or null to disable.
	 *
	 * @var string|null
	 */
	protected $updated_column = 'updated_at';

	/**
	 * Shared $wpdb instance.
	 *
	 * @return wpdb
	 */
	protected function db() {
		global $wpdb;

		return $wpdb;
	}

	/**
	 * Fully-qualified, prefix-aware table name.
	 *
	 * @return string
	 */
	public function table() {
		return SBMS_Database::table_name( $this->table );
	}

	/* --------------------------------------------------------------------- *
	 * Internal helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Every column name this model is allowed to reference in SQL identifiers.
	 * Used to validate ORDER BY / WHERE column names before interpolation.
	 *
	 * @return string[]
	 */
	protected function known_columns() {
		$known = array_keys( $this->columns );
		$known[] = $this->primary_key;

		foreach ( array( $this->created_column, $this->updated_column ) as $ts ) {
			if ( $ts ) {
				$known[] = $ts;
			}
		}

		return array_values( array_unique( $known ) );
	}

	/**
	 * The $wpdb placeholder ('%d'/'%f'/'%s') for a column, defaulting to '%s'.
	 *
	 * @param string $column Column name.
	 * @return string
	 */
	protected function format_for( $column ) {
		if ( isset( $this->columns[ $column ] ) ) {
			return $this->columns[ $column ];
		}

		if ( $column === $this->primary_key || $column === $this->user_column ) {
			return '%d';
		}

		return '%s';
	}

	/**
	 * Reduce arbitrary input to only the model's writable columns.
	 *
	 * @param array<string,mixed> $data Raw input.
	 * @return array<string,mixed> Filtered input.
	 */
	protected function only_fillable( array $data ) {
		return array_intersect_key( $data, $this->columns );
	}

	/**
	 * Build an ordered $wpdb format array matching $data's keys.
	 *
	 * @param array<string,mixed> $data Column => value pairs.
	 * @return string[] Ordered placeholder list.
	 */
	protected function formats_for( array $data ) {
		$formats = array();

		foreach ( array_keys( $data ) as $column ) {
			$formats[] = $this->format_for( $column );
		}

		return $formats;
	}

	/**
	 * Current site time in MySQL datetime format.
	 *
	 * @return string
	 */
	protected function now() {
		return current_time( 'mysql' );
	}

	/**
	 * Validate an identifier against the model's known columns.
	 *
	 * @param string $column   Candidate column name.
	 * @param string $fallback Column used when $column is not recognised.
	 * @return string A safe, known column name.
	 */
	protected function safe_column( $column, $fallback ) {
		return in_array( $column, $this->known_columns(), true ) ? $column : $fallback;
	}

	/**
	 * Normalise sort direction to ASC or DESC.
	 *
	 * @param string $order Requested order.
	 * @return string
	 */
	protected function safe_order( $order ) {
		return ( is_string( $order ) && 'ASC' === strtoupper( $order ) ) ? 'ASC' : 'DESC';
	}

	/* --------------------------------------------------------------------- *
	 * Read
	 * --------------------------------------------------------------------- */

	/**
	 * Find a single row by primary key.
	 *
	 * @param int $id Primary key value.
	 * @return array<string,mixed>|null
	 */
	public function find( $id ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$this->primary_key} = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Find the first row where $column = $value.
	 *
	 * @param string $column Column to match (validated against known columns).
	 * @param mixed  $value  Value to match (parameterised).
	 * @return array<string,mixed>|null
	 */
	public function find_by( $column, $value ) {
		$wpdb   = $this->db();
		$table  = $this->table();
		$column = $this->safe_column( $column, $this->primary_key );
		$format = $this->format_for( $column );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$column} = {$format} LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$value
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Retrieve rows with optional WHERE / ORDER BY / LIMIT / OFFSET.
	 *
	 * @param array{
	 *   where?: array<string,mixed>,
	 *   orderby?: string,
	 *   order?: string,
	 *   limit?: int,
	 *   offset?: int
	 * } $args Query arguments.
	 * @return array<int,array<string,mixed>>
	 */
	public function all( array $args = array() ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$where_sql = '';
		$values    = array();

		if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
			$clauses = array();

			foreach ( $args['where'] as $column => $value ) {
				if ( ! in_array( $column, $this->known_columns(), true ) ) {
					continue; // Ignore unknown columns rather than trust them.
				}
				$format    = $this->format_for( $column );
				$clauses[] = "{$column} = {$format}";
				$values[]  = $value;
			}

			if ( $clauses ) {
				$where_sql = ' WHERE ' . implode( ' AND ', $clauses );
			}
		}

		$orderby = $this->safe_column( isset( $args['orderby'] ) ? $args['orderby'] : $this->primary_key, $this->primary_key );
		$order   = $this->safe_order( isset( $args['order'] ) ? $args['order'] : 'DESC' );
		$limit   = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 100;
		$offset  = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql  = "SELECT * FROM {$table}{$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$args_for_prepare = array_merge( $values, array( $limit, $offset ) );

		// $sql always contains at least the LIMIT/OFFSET placeholders, so
		// prepare() always has something to bind.
		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $args_for_prepare ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Retrieve rows owned by a given user.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $args    Same shape as all(); a user_id WHERE is added.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_user( $user_id, array $args = array() ) {
		if ( ! $this->user_column ) {
			return $this->all( $args );
		}

		$args['where'] = isset( $args['where'] ) && is_array( $args['where'] ) ? $args['where'] : array();
		$args['where'][ $this->user_column ] = (int) $user_id;

		return $this->all( $args );
	}

	/**
	 * Count rows, optionally filtered by an exact-match WHERE array.
	 *
	 * @param array<string,mixed> $where Column => value filters.
	 * @return int
	 */
	public function count( array $where = array() ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$clauses = array();
		$values  = array();

		foreach ( $where as $column => $value ) {
			if ( ! in_array( $column, $this->known_columns(), true ) ) {
				continue;
			}
			$format    = $this->format_for( $column );
			$clauses[] = "{$column} = {$format}";
			$values[]  = $value;
		}

		$where_sql = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
		$sql       = "SELECT COUNT(*) FROM {$table}{$where_sql}";

		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}

		// No values to bind: prepare() would warn, so query the fixed SQL
		// (the only interpolated token is our own prefixed table name).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Count rows owned by a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int
	 */
	public function count_for_user( $user_id ) {
		if ( ! $this->user_column ) {
			return $this->count();
		}

		return $this->count( array( $this->user_column => (int) $user_id ) );
	}

	/**
	 * Whether a row with the given primary key exists.
	 *
	 * @param int $id Primary key value.
	 * @return bool
	 */
	public function exists( $id ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT {$this->primary_key} FROM {$table} WHERE {$this->primary_key} = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id
			)
		);

		return null !== $found;
	}

	/* --------------------------------------------------------------------- *
	 * Write
	 * --------------------------------------------------------------------- */

	/**
	 * Insert a row. Unknown keys are dropped; timestamps are set automatically.
	 *
	 * @param array<string,mixed> $data Column => value pairs.
	 * @return int|false New row ID, or false on failure.
	 */
	public function create( array $data ) {
		$wpdb = $this->db();
		$data = $this->only_fillable( $data );

		// Nothing valid to insert — bail before touching the database.
		if ( empty( $data ) ) {
			return false;
		}

		$now = $this->now();
		if ( $this->created_column ) {
			$data[ $this->created_column ] = $now;
		}
		if ( $this->updated_column ) {
			$data[ $this->updated_column ] = $now;
		}

		$result = $wpdb->insert( $this->table(), $data, $this->formats_for( $data ) );

		return ( false === $result ) ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Update a row by primary key. Unknown keys are dropped; updated_at is
	 * refreshed automatically.
	 *
	 * @param int                 $id   Primary key value.
	 * @param array<string,mixed> $data Column => value pairs.
	 * @return int|false Rows affected, or false on failure.
	 */
	public function update( $id, array $data ) {
		$wpdb = $this->db();
		$data = $this->only_fillable( $data );

		if ( $this->updated_column ) {
			$data[ $this->updated_column ] = $this->now();
		}

		if ( empty( $data ) ) {
			return false;
		}

		return $wpdb->update(
			$this->table(),
			$data,
			array( $this->primary_key => (int) $id ),
			$this->formats_for( $data ),
			array( '%d' )
		);
	}

	/**
	 * Delete a row by primary key.
	 *
	 * @param int $id Primary key value.
	 * @return int|false Rows deleted, or false on failure.
	 */
	public function delete( $id ) {
		$wpdb = $this->db();

		return $wpdb->delete(
			$this->table(),
			array( $this->primary_key => (int) $id ),
			array( '%d' )
		);
	}
}
