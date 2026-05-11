<?php
/**
 * Fluent query builder wrapper for $wpdb.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pixel_Scout_Query {
	/**
	 * WordPress DB instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Table name for the query.
	 *
	 * @var string
	 */
	private string $table = '';

	/**
	 * Selected fields.
	 *
	 * @var string
	 */
	private string $select_fields = '*';

	/**
	 * Join clauses.
	 *
	 * @var array<string>
	 */
	private array $joins = [];

	/**
	 * AND where clauses.
	 *
	 * @var array<int, array{sql: string, values: array<int, mixed>}>
	 */
	private array $where_clauses = [];

	/**
	 * OR groups, each group is AND-joined with base where clauses.
	 *
	 * @var array<int, array<int, array{sql: string, values: array<int, mixed>}>>
	 */
	private array $or_groups = [];

	/**
	 * GROUP BY columns.
	 *
	 * @var array<string>
	 */
	private array $group_by = [];

	/**
	 * HAVING clauses.
	 *
	 * @var array<int, array{sql: string, values: array<int, mixed>}>
	 */
	private array $having_clauses = [];

	/**
	 * ORDER BY expressions.
	 *
	 * @var array<string>
	 */
	private array $order_by = [];

	/**
	 * Limit.
	 *
	 * @var int|null
	 */
	private ?int $limit_value = null;

	/**
	 * Offset.
	 *
	 * @var int|null
	 */
	private ?int $offset_value = null;

	/**
	 * Skip cache marker for repository-level integrations.
	 *
	 * @var bool
	 */
	private bool $skip_cache = false;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb_instance Optional DB handle.
	 */
	private function __construct( ?wpdb $wpdb_instance = null ) {
		global $wpdb;
		$this->wpdb = $wpdb_instance instanceof wpdb ? $wpdb_instance : $wpdb;
	}

	/**
	 * Factory constructor.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self();
	}

	/**
	 * Select fields.
	 *
	 * @param string|array<int, string> $fields Fields.
	 *
	 * @return self
	 */
	public function select( $fields ): self {
		if ( is_array( $fields ) ) {
			$fields = implode( ', ', array_map( 'trim', $fields ) );
		}

		$this->select_fields = trim( (string) $fields );
		return $this;
	}

	/**
	 * Set table.
	 *
	 * @param string      $table Table name.
	 * @param string|null $alias Optional alias.
	 *
	 * @return self
	 */
	public function from( string $table, ?string $alias = null ): self {
		$resolved = $this->resolve_table_name( $table );
		$this->table = $resolved . ( $alias ? ' ' . sanitize_key( $alias ) : '' );
		return $this;
	}

	/**
	 * Set subquery as table.
	 *
	 * @param self   $query Subquery instance.
	 * @param string $alias Alias.
	 *
	 * @return self
	 */
	public function from_subquery( self $query, string $alias ): self {
		list( $sub_sql, $sub_values ) = $query->build_sql_with_values();
		$this->table = '( ' . $this->prepare_sql( $sub_sql, $sub_values ) . ' ) ' . sanitize_key( $alias );
		return $this;
	}

	/**
	 * Add INNER JOIN.
	 *
	 * @param string      $table Join table.
	 * @param string      $condition Join condition.
	 * @param string|null $alias Optional alias.
	 *
	 * @return self
	 */
	public function inner_join( string $table, string $condition, ?string $alias = null ): self {
		$resolved      = $this->resolve_table_name( $table );
		$alias_segment = $alias ? ' ' . sanitize_key( $alias ) : '';
		$this->joins[] = 'INNER JOIN ' . $resolved . $alias_segment . ' ON ' . $condition;
		return $this;
	}

	/**
	 * Add LEFT JOIN.
	 *
	 * @param string      $table Join table.
	 * @param string      $condition Join condition.
	 * @param string|null $alias Optional alias.
	 *
	 * @return self
	 */
	public function left_join( string $table, string $condition, ?string $alias = null ): self {
		$resolved      = $this->resolve_table_name( $table );
		$alias_segment = $alias ? ' ' . sanitize_key( $alias ) : '';
		$this->joins[] = 'LEFT JOIN ' . $resolved . $alias_segment . ' ON ' . $condition;
		return $this;
	}

	/**
	 * Add where condition.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value Value.
	 * @param string $operator SQL operator.
	 * @param string $type Placeholder.
	 *
	 * @return self
	 */
	public function where( string $column, $value, string $operator = '=', string $type = '%s' ): self {
		$clause = $this->sanitize_identifier( $column ) . ' ' . strtoupper( trim( $operator ) ) . ' ' . $type;
		$this->where_clauses[] = [
			'sql'    => $clause,
			'values' => [ $value ],
		];
		return $this;
	}

	/**
	 * Add BETWEEN condition.
	 *
	 * @param string $column Column name.
	 * @param mixed  $start Start value.
	 * @param mixed  $end End value.
	 * @param string $type Placeholder type.
	 *
	 * @return self
	 */
	public function where_between( string $column, $start, $end, string $type = '%d' ): self {
		$clause = $this->sanitize_identifier( $column ) . ' BETWEEN ' . $type . ' AND ' . $type;
		$this->where_clauses[] = [
			'sql'    => $clause,
			'values' => [ $start, $end ],
		];
		return $this;
	}

	/**
	 * Add raw where SQL.
	 *
	 * @param string                $sql SQL expression.
	 * @param array<int, mixed>     $values Bind values.
	 *
	 * @return self
	 */
	public function where_raw( string $sql, array $values = [] ): self {
		$this->where_clauses[] = [
			'sql'    => trim( $sql ),
			'values' => array_values( $values ),
		];
		return $this;
	}

	/**
	 * Add date range where clause.
	 *
	 * @param string $column Column name.
	 * @param int    $start_ts Start timestamp.
	 * @param int    $end_ts End timestamp.
	 * @param bool   $inclusive Inclusive range.
	 *
	 * @return self
	 */
	public function where_date_range( string $column, int $start_ts, int $end_ts, bool $inclusive = true ): self {
		$operator_start = $inclusive ? '>=' : '>';
		$operator_end   = $inclusive ? '<=' : '<';

		$this->where( $column, gmdate( 'Y-m-d H:i:s', $start_ts ), $operator_start, '%s' );
		$this->where( $column, gmdate( 'Y-m-d H:i:s', $end_ts ), $operator_end, '%s' );

		return $this;
	}

	/**
	 * Add IN clause.
	 *
	 * @param string            $column Column name.
	 * @param array<int, mixed> $values Values.
	 * @param string            $type Placeholder type.
	 *
	 * @return self
	 */
	public function where_in( string $column, array $values, string $type = '%d' ): self {
		return $this->where_internally( $column, $values, $type, false );
	}

	/**
	 * Add NOT IN clause.
	 *
	 * @param string            $column Column name.
	 * @param array<int, mixed> $values Values.
	 * @param string            $type Placeholder type.
	 *
	 * @return self
	 */
	public function where_not_in( string $column, array $values, string $type = '%d' ): self {
		return $this->where_internally( $column, $values, $type, true );
	}

	/**
	 * Add grouped OR clauses.
	 *
	 * @param callable $callback Group callback receiving temporary builder.
	 *
	 * @return self
	 */
	public function or_where_group( callable $callback ): self {
		$group = self::create();
		$callback( $group );

		if ( ! empty( $group->where_clauses ) ) {
			$this->or_groups[] = $group->where_clauses;
		}

		return $this;
	}

	/**
	 * Add GROUP BY.
	 *
	 * @param string $column Column name.
	 *
	 * @return self
	 */
	public function group_by( string $column ): self {
		$this->group_by[] = $this->sanitize_identifier( $column );
		return $this;
	}

	/**
	 * Add HAVING clause.
	 *
	 * @param string $condition Column or expression.
	 * @param mixed  $value Value.
	 * @param string $operator Operator.
	 * @param string $type Placeholder.
	 *
	 * @return self
	 */
	public function having( string $condition, $value, string $operator = '=', string $type = '%s' ): self {
		$this->having_clauses[] = [
			'sql'    => trim( $condition ) . ' ' . strtoupper( trim( $operator ) ) . ' ' . $type,
			'values' => [ $value ],
		];
		return $this;
	}

	/**
	 * Add ORDER BY.
	 *
	 * @param string $column Column name.
	 * @param string $direction ASC|DESC.
	 *
	 * @return self
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		$dir = 'DESC' === strtoupper( $direction ) ? 'DESC' : 'ASC';
		$this->order_by[] = $this->sanitize_identifier( $column ) . ' ' . $dir;
		return $this;
	}

	/**
	 * Set LIMIT.
	 *
	 * @param int $limit Limit.
	 *
	 * @return self
	 */
	public function limit( int $limit ): self {
		$this->limit_value = max( 0, $limit );
		return $this;
	}

	/**
	 * Set OFFSET.
	 *
	 * @param int $offset Offset.
	 *
	 * @return self
	 */
	public function offset( int $offset ): self {
		$this->offset_value = max( 0, $offset );
		return $this;
	}

	/**
	 * Set pagination.
	 *
	 * @param int $page Current page.
	 * @param int $per_page Per page.
	 *
	 * @return self
	 */
	public function paginate( int $page, int $per_page ): self {
		$resolved_page = max( 1, $page );
		$resolved_size = max( 1, $per_page );

		$this->limit( $resolved_size );
		$this->offset( ( $resolved_page - 1 ) * $resolved_size );

		return $this;
	}

	/**
	 * Mark query as no-cache.
	 *
	 * @return self
	 */
	public function no_cache(): self {
		$this->skip_cache = true;
		return $this;
	}

	/**
	 * Check whether query opted out of caller-level cache.
	 *
	 * @return bool
	 */
	public function is_no_cache(): bool {
		return $this->skip_cache;
	}

	/**
	 * Build SQL string without value interpolation.
	 *
	 * @return string
	 */
	public function build_sql(): string {
		list( $sql ) = $this->build_sql_with_values();
		return $sql;
	}

	/**
	 * Execute select and return result set.
	 *
	 * @param string|int $output Output format.
	 *
	 * @return array<int, mixed>|null
	 */
	public function get( $output = ARRAY_A ): ?array {
		list( $sql, $values ) = $this->build_sql_with_values();
		$prepared = $this->prepare_sql( $sql, $values );
		$result   = $this->wpdb->get_results( $prepared, $output );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Execute select and return one row.
	 *
	 * @param string|int $output Output format.
	 *
	 * @return mixed
	 */
	public function get_row( $output = ARRAY_A ) {
		list( $sql, $values ) = $this->build_sql_with_values();
		$prepared = $this->prepare_sql( $sql, $values );
		return $this->wpdb->get_row( $prepared, $output );
	}

	/**
	 * Execute select and return one value.
	 *
	 * @param int $col_offset Column offset.
	 *
	 * @return mixed
	 */
	public function get_var( int $col_offset = 0 ) {
		list( $sql, $values ) = $this->build_sql_with_values();
		$prepared = $this->prepare_sql( $sql, $values );
		return $this->wpdb->get_var( $prepared, $col_offset );
	}

	/**
	 * Execute select and return one column.
	 *
	 * @param int $col_offset Column offset.
	 *
	 * @return array<int, mixed>|null
	 */
	public function get_col( int $col_offset = 0 ): ?array {
		list( $sql, $values ) = $this->build_sql_with_values();
		$prepared = $this->prepare_sql( $sql, $values );
		$result   = $this->wpdb->get_col( $prepared, $col_offset );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Insert row.
	 *
	 * @param array<string, mixed> $data Insert data.
	 *
	 * @return int|false
	 */
	public function insert( array $data ) {
		$result = $this->wpdb->insert( $this->table, $data, $this->infer_formats( $data ) );
		if ( false === $result ) {
			return false;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update rows.
	 *
	 * @param array<string, mixed> $data Update data.
	 *
	 * @return int|false
	 */
	public function update( array $data ) {
		list( $where_sql, $where_values ) = $this->compile_where();
		if ( '' === $where_sql ) {
			return false;
		}

		$set_parts = [];
		$values    = [];
		foreach ( $data as $column => $value ) {
			$set_parts[] = $this->sanitize_identifier( (string) $column ) . ' = ' . $this->infer_format( $value );
			$values[]    = $value;
		}

		$sql      = 'UPDATE ' . $this->table . ' SET ' . implode( ', ', $set_parts ) . ' WHERE ' . $where_sql;
		$prepared = $this->prepare_sql( $sql, array_merge( $values, $where_values ) );
		$result   = $this->wpdb->query( $prepared );
		return false === $result ? false : (int) $result;
	}

	/**
	 * Delete rows.
	 *
	 * @return int|false
	 */
	public function delete() {
		list( $where_sql, $where_values ) = $this->compile_where();
		if ( '' === $where_sql ) {
			return false;
		}

		$sql      = 'DELETE FROM ' . $this->table . ' WHERE ' . $where_sql;
		$prepared = $this->prepare_sql( $sql, $where_values );
		$result   = $this->wpdb->query( $prepared );
		return false === $result ? false : (int) $result;
	}

	/**
	 * Insert or update on duplicate key.
	 *
	 * @param array<string, mixed> $insert_data Insert payload.
	 * @param array<int, string>   $update_columns Columns to update.
	 *
	 * @return bool
	 */
	public function upsert( array $insert_data, array $update_columns ): bool {
		$columns      = array_keys( $insert_data );
		$placeholders = [];
		$values       = [];

		foreach ( $insert_data as $value ) {
			$placeholders[] = $this->infer_format( $value );
			$values[]       = $value;
		}

		$escaped_columns = array_map( [ $this, 'sanitize_identifier' ], $columns );
		$updates         = [];
		foreach ( $update_columns as $column ) {
			$clean_column = $this->sanitize_identifier( $column );
			$updates[]    = $clean_column . ' = VALUES(' . $clean_column . ')';
		}

		$sql = 'INSERT INTO ' . $this->table
			. ' ( ' . implode( ', ', $escaped_columns ) . ' )'
			. ' VALUES ( ' . implode( ', ', $placeholders ) . ' )'
			. ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates );

		$prepared = $this->prepare_sql( $sql, $values );
		$result   = $this->wpdb->query( $prepared );
		return false !== $result;
	}

	/**
	 * Build SQL with binding values.
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function build_sql_with_values(): array {
		$sql = 'SELECT ' . $this->select_fields . ' FROM ' . $this->table;
		if ( ! empty( $this->joins ) ) {
			$sql .= ' ' . implode( ' ', $this->joins );
		}

		list( $where_sql, $where_values ) = $this->compile_where();
		$values = $where_values;
		if ( '' !== $where_sql ) {
			$sql .= ' WHERE ' . $where_sql;
		}

		if ( ! empty( $this->group_by ) ) {
			$sql .= ' GROUP BY ' . implode( ', ', $this->group_by );
		}

		if ( ! empty( $this->having_clauses ) ) {
			$having_sqls = [];
			foreach ( $this->having_clauses as $having ) {
				$having_sqls[] = $having['sql'];
				$values        = array_merge( $values, $having['values'] );
			}

			$sql .= ' HAVING ' . implode( ' AND ', $having_sqls );
		}

		if ( ! empty( $this->order_by ) ) {
			$sql .= ' ORDER BY ' . implode( ', ', $this->order_by );
		}

		if ( null !== $this->limit_value ) {
			$sql .= ' LIMIT ' . (int) $this->limit_value;
		}

		if ( null !== $this->offset_value ) {
			$sql .= ' OFFSET ' . (int) $this->offset_value;
		}

		return [ $sql, $values ];
	}

	/**
	 * Compile where conditions.
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function compile_where(): array {
		$clauses = [];
		$values  = [];

		foreach ( $this->where_clauses as $clause ) {
			$clauses[] = $clause['sql'];
			$values    = array_merge( $values, $clause['values'] );
		}

		foreach ( $this->or_groups as $group ) {
			$group_sql    = [];
			$group_values = [];

			foreach ( $group as $group_clause ) {
				$group_sql[]    = $group_clause['sql'];
				$group_values   = array_merge( $group_values, $group_clause['values'] );
			}

			if ( ! empty( $group_sql ) ) {
				$clauses[] = '( ' . implode( ' OR ', $group_sql ) . ' )';
				$values    = array_merge( $values, $group_values );
			}
		}

		return [ implode( ' AND ', $clauses ), $values ];
	}

	/**
	 * Prepare SQL with values when required.
	 *
	 * @param string            $sql SQL.
	 * @param array<int, mixed> $values Values.
	 *
	 * @return string
	 */
	private function prepare_sql( string $sql, array $values ): string {
		if ( empty( $values ) ) {
			return $sql;
		}

		return $this->wpdb->prepare( $sql, ...$values );
	}

	/**
	 * Infer wpdb format string.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function infer_format( $value ): string {
		if ( is_int( $value ) ) {
			return '%d';
		}

		if ( is_float( $value ) ) {
			return '%f';
		}

		return '%s';
	}

	/**
	 * Infer formats for data array.
	 *
	 * @param array<string, mixed> $data Data.
	 *
	 * @return array<int, string>
	 */
	private function infer_formats( array $data ): array {
		$formats = [];
		foreach ( $data as $value ) {
			$formats[] = $this->infer_format( $value );
		}
		return $formats;
	}

	/**
	 * Add IN/NOT IN clause.
	 *
	 * @param string            $column Column.
	 * @param array<int, mixed> $values Values.
	 * @param string            $type Placeholder.
	 * @param bool              $negate Negate.
	 *
	 * @return self
	 */
	private function where_internally( string $column, array $values, string $type, bool $negate ): self {
		$values = array_values( $values );
		if ( empty( $values ) ) {
			return $this;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $values ), $type ) );
		$operator     = $negate ? 'NOT IN' : 'IN';

		$this->where_clauses[] = [
			'sql'    => $this->sanitize_identifier( $column ) . ' ' . $operator . ' ( ' . $placeholders . ' )',
			'values' => $values,
		];

		return $this;
	}

	/**
	 * Prefix non-qualified table names.
	 *
	 * @param string $table Table.
	 *
	 * @return string
	 */
	private function resolve_table_name( string $table ): string {
		$table = trim( $table );

		if ( str_contains( $table, ' ' ) || str_contains( $table, '(' ) || str_contains( $table, ')' ) ) {
			return $table;
		}

		if ( str_starts_with( $table, $this->wpdb->prefix ) ) {
			return $table;
		}

		return $this->wpdb->prefix . ltrim( $table, '_' );
	}

	/**
	 * Basic identifier sanitization.
	 *
	 * @param string $identifier Identifier.
	 *
	 * @return string
	 */
	private function sanitize_identifier( string $identifier ): string {
		$identifier = trim( $identifier );

		if ( '*' === $identifier || preg_match( '/[^A-Za-z0-9_.,\s]/', $identifier ) ) {
			return $identifier;
		}

		$segments = array_map(
			static function ( string $segment ): string {
				$segment = trim( $segment );
				if ( '' === $segment ) {
					return $segment;
				}

				if ( '*' === $segment ) {
					return $segment;
				}

				return '`' . str_replace( '`', '', $segment ) . '`';
			},
			explode( '.', $identifier )
		);

		return implode( '.', $segments );
	}
}


