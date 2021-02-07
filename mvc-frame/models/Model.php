<?php
/*
*  Model Class for simplifying MySQL data management
*  Intended to be extended for meaningful tables and a full Model of data.
*/


namespace models;
use \PDO as PDO;

class Model {

	/* --------------------
	*  DATABASE CREDENTIALS
	*  --------------------    */
	private $db_host = \CONFIG::DATABASE_HOST;
	private $db_name = \CONFIG::DATABASE_NAME;
	private $db_username = \CONFIG::DATABASE_USERNAME;
	private $db_password = \CONFIG::DATABASE_PASSWORD;

	/*
	* If debug is on then Exceptions thrown will include all query options and info.
	*/
	public static $debug = \CONFIG::DEBUG;

	/*
	* Staticly track the number of SQL queries made, can be accessed for debugging or info.
	*/
	public static $sql_queries = 0;

	/*
	* Object PDO. Stores the PDO object with persistent connection to DB.
	*/
	protected static $global_connection;

	/*
	* stores a copy of the DB connection for this instance of Model
	*/
	private $db = null;

	/*
	* If false, all options will be cleared after execution of query.
	* If true, settings will be preserved for further query executions.
	* E.g where fields, limit etc, will be re-used.
	*/
	private $preserve = false;


	/*
	* Storage of all different SQL parts before execution of query.
	* Parts can be set without table defined, but query will not execute.
	*/
	protected $table = null;
	private $fields = [];
	private $where = [];
	private $order_by;
	private $order_dir;
	private $limit = [];
	private $group_field;
	private $joins = [];
	private $join_fields = [];
	private $vars = [];
	private $is_count = false;

	//for debugging purposes mostly.
	protected $last_results;
	protected $last_result_count;
	protected $last_query;
	protected $last_statement;






	/*
	* Construct will start a persistent connection the first time a model is used.
	* All subsequent models will use the same PDO object and connection.
	* Typically constructed with specified DB table.
	*/
	function __construct($table = null) {

		//look for a connection. or start one.
		//this allows us not to worry about connecting.
		//first instance will always connect.
		if( !isset(self::$global_connection) ) {
			$this->connect();
		}

		$this->db = self::$global_connection;

		if($table) {
			$this->set_table($table);
		}
	}

	/*
	*  Set the preserve feature on or off on the fly.
	*  Preserving settings allows to re-use where/limit/order etc criteria
	*/
	public function preserve_settings($preserve = true) {
		$this->preserve = boolval($preserve);

		return $this;
	}

	/*
	* Manually set the table, or change to new table.
	*/
	public function set_table($table) {
		$this->table = $table;

		return $this;
	}

	/*
	* Bool, if the table represented by this model exists.
	*/
	public function table_exists() {
		if($this->table && $this->db) {
			try{
				$result = $this->db->query('SELECT 1 FROM ' . $this->table . ' LIMIT 1');
			}
			catch(\Throwable $e) {
				return false;
			}

			return $result !== false;
		}

		return false;
	}

	/*
	* Execute any query optionally giving sql vars, or using the vars set in the model.
	*/
	public function raw_query($sql, $vars = null) {
		if(!$vars) {
			$vars = $this->vars;
		}

		return $this->query($sql, $vars);
	}

	/*
	* Build a select query based on the options set.
	*/
	public function get_results() {
		$this->is_count = false;
		$this->run_select_query();

		return $this->last_results;
	}

	/*
	* More aesthetically pleasing; $users->get()
	*/
	public function get() {
		return $this->get_results();
	}

	/*
	* Return the count figure of matching results.
	*/
	public function get_count() {
		$this->is_count = true;
		$this->run_select_query();

		return $this->last_result_count;
	}

	/*
	* Return the first matching row of result set.
	*/
	public function get_first() {
		return $this->set_limit(0, 1)->get_results()[0] ?? null;
	}

	/*
	* Insert a row into the table.
	* $values should be an assoc array of fields => values.
	* $type allows for different insert types.
	*/
	public function insert($values, $type = 'INSERT') {
		$insert = "$type INTO " . $this->table;
		$fields = implode(', ', array_keys($values));
		$vars = [];

		//create an array of variables for our values.
		foreach($values as $field => $value) {
			$vars[ $this->safe_var_name($field) ] = $value;
		}

		$variables = implode(', ', array_keys($vars));

		//finally lets run our insert query.
		$this->query("$insert ($fields) VALUES ($variables)", $vars);

		return intval( $this->db->lastInsertId() );
	}

	public function replace($values) {
		return $this->insert($values, 'REPLACE');
	}

	/*
	* Update the rows that match the set options.
	* $values should be an assoc array of fields and values.
	*/
	public function update($values) {
		$query = 'UPDATE ' . $this->table . ' SET';

		//create the variables.
		$single = 0;

		foreach($values as $field => $value) {

			//add a comma if there's multiple
			$query .= (($single++ > 0) ? ', ' : ' ');

			if( $value === '++' || $value === '--' ) {
				$query .= "$field = $field {$value[0]} 1";
			}
			else {
				$varname = $this->safe_var_name($field);
				$this->vars[$varname] = $value;

				 $query .= "$field = $varname";
			}
		}

		//then add the where clause.
		$query .= $this->build_where_clause();

		$stmt = $this->query($query, $this->vars);
		$this->last_result_count = $stmt->rowCount();

		if(!$this->preserve) {
			$this->clear();
		}

		return $this;
	}

	/*
	* Delete all matching rows.
	*/
	public function delete() {
		$query = 'DELETE FROM ' . $this->table . $this->build_where_clause();

		$stmt = $this->query($query, $this->vars);
		$this->last_result_count = $stmt->rowCount();
		$this->clear();

		return $this;
	}


	/* ----------------
	*  All set function
	*  Used for setting the options before executing a query.
	*  ----------------
	*/

	/*
	*  Set which fields to retreive from table.
	*  If not set then '*' will be used.
	*/
	public function set_fields($fields) {
		if(is_string($fields)) {
			$fields = explode(' ', $fields);
		}

		foreach($fields as $field) {
			if($field !== '*') {
				$this->fields[] = $field;
			}
		}

		return $this;
	}

	/*
	*  Set WHERE clauses for the query.
	*  AND will be used for joining by default and assumed to be '='
	*  Other functions for more specific WHERE clauses.
	*/
	public function set_where($field, $value = null, $and_or = 'AND', $operator = '=') {
		if(is_array($field)) {
			foreach($field as $f => $v) {
				$this->set_where($f, $v);
			}

			return $this;
		}

		if($value === null) {
			return $this;
		}

		//set_var_name will make the field name into a usable variable name.
		$varname = $this->safe_var_name($field);
		$this->vars[$varname] = $value;
		$this->where[] = "$and_or $field $operator $varname";

		return $this;
	}

	/*
	* Shortcut functions for specific WHERE types.
	*/
	public function set_and_where($field, $value, $operator = '=') {
		return $this->set_where($field, $value, 'AND', $operator);
	}

	/*
	* Using an OR WHERE will be for that WHERE only. By default WHERE clauses are joined by AND
	*/
	public function set_or_where($field, $value, $operator = '=') {
		return $this->set_where($field, $value, 'OR', $operator);
	}

	/*
	* Set a WHERE clause with an IN() value-set.
	* $field is the field and values is an array of values for the IN-set
	*/
	public function set_in_where($field, $values) {
		if(is_array($values) && !empty($values)) {
			$keys = [];

			//create vars for the IN-set
			//'InValue' will be made unique each use.
			foreach($values as $value) {
				$key = $this->safe_var_name('InValue');
				$this->vars[$key] = $value;
				$keys[] = $key;
			}

			$this->set_custom_where("$field IN(" . implode(',', $keys) . ')');
		}

		return $this;
	}

	/*
	* Commonly used WHERE clause for a search type feature.
	*/
	public function set_search($field, $value, $and_or = 'AND') {
		return $this->set_where($field, "%$value%", $and_or, 'LIKE');
	}

	/*
	* Set a completely custom WHERE clause. If no AND/OR detected AND is assumed as per.
	*/
	public function set_custom_where($where_claus) {
		if( !preg_match('/^(AND|OR)/', $where_claus) ) {
			$where_claus = "AND $where_claus";
		}

		$this->where[] = $where_claus;

		return $this;
	}

	/*
	*  Add a variable to be used in an SQL query.
	*/
	public function set_value($key, $value) {
		$key = $this->safe_var_name($key);
		$this->vars[$key] = $value;

		return $this;
	}

	/* Alias of above.
	*/
	public function set_var($key, $value) {
		return $this->set_value($key, $value);
	}

	/*
	* Set the order of the SQL query. The ORDER BY and the direction.
	*/
	public function set_order($order_by, $order_dir = 'ASC') {
		if(is_array($order_by)) {
			$order_dir = $order_by[1];
			$order_by = $order_by[0];
		}

		$this->order_by = $order_by;

		//ensures safe values.
		$directions = [
			'a' => 'ASC', 'A' => 'ASC', 'asc' => 'ASC', 'ASC' => 'ASC',
			'd'	=> 'DESC', 'D' => 'DESC', 'desc' => 'DESC', 'DESC' => 'DESC'
		];

		if(in_array($order_dir, array_keys($directions))) {
			$this->order_dir = $directions[$order_dir];
		}

		return $this;
	}

	/*
	* Set the limit of the SQL query.
	*/
	public function set_limit($start, $amount = null) {
		$limit = [];
		$limit[0] = $start;

		if(is_int($amount)) {
			$limit[1] = $amount;
		}

		$this->limit = $limit;
		return $this;
	}

	/*
	* Set the grouping field of the SQL query.
	*/
	public function set_group($group_field) {
		$this->group_field = $group_field;

		return $this;
	}

	/*
	* Set a join table, type of join and which fields.
	* A joined table will need a join condition to be joined.
	*/
	public function set_join_table($table, $fields = [], $type = 'LEFT') {
		//allow just specifying single field.
		if(!is_array($fields)) {
			$fields = [$fields];
		}

		if(empty($fields)) {
			$fields = ['*'];
		}

		// an alias can be defined for a table or table field.
		foreach($fields as $alias => $field) {

			//aka no alias provided just normal integer array.
			if(is_int($alias)) {
				$alias = '';
			}

			$this->join_fields[] = "$table.$field $alias";
		}

		$this->joins[$table] = array(
			'type'	=> $type
		);

		return $this;
	}

	/*
	* Set the join condition for a table already specified.
	* $left_table_value will most likely be a field from left table.
	* $right_table_value can be a right table field or literal value.
	* '=' and AND are assumed in the condition.
	*/
	public function set_join_condition($table, $left_table_value, $right_table_value, $operator = '=', $and_or = 'AND') {

		//we dont change the values into a variable here
		//as more often than not it is another field from left table.
		//placing trust in the programmer if using a literal value.

		if( array_key_exists($table, $this->joins) ) {

			if( !isset($this->joins[$table]['on']) || !is_array($this->joins[$table]['on']) ) {
				$this->joins[$table]['on'] = [];
			}

			$this->joins[$table]['on'][] = "$and_or {$this->table}.$left_table_value $operator $table.$right_table_value";
		}

		return $this;
	}

	/*
	*  Clears all the options.
	*  This is used after queries are executed unless $preserve has been set.
	*  Can optionally clear specific parts; where, fields, limit, vars, order, join
	*/
	public function clear($specfic_clear = null) {
		if($specfic_clear) {
			switch($specfic_clear) {
				case 'where' :
					$this->where = [];
					break;

				case 'fields' :
					$this->fields = [];
					break;

				case 'limit' :
					$this->limit = [];
					break;

				case 'vars' :
				case 'values' :
					$this->vars = [];
					break;

				case 'order' :
					$this->order_by = null;
					$this->order_dir = null;
					break;

				case 'join' :
					$this->joins = [];
					$this->join_fields = [];
			}
		}
		else {
			$this->fields = $this->where = $this->limit = $this->vars = $this->joins = $this->join_fields = [];
			$this->order_by = $this->order_dir = $this->page = null;
			$this->is_count = false;
		}

		return $this;
	}

	public function get_last_results() {
		return $this->last_result;
	}

	public function get_last_result_count() {
		return $this->last_result_count;
	}

	public function get_last_statement() {
		return $this->last_statement;
	}

	public function get_last_query() {
		return $this->last_query;
	}





/* PRIVATE functions.
connect()
query(sql query) - execute a query.
run_select_query() - build and execute the select query with all the settings
build_select_query() - build the sql query of all the settings.
safe_var_name(poss name) - removes any non-alpha characters and makes unique.
*/

	/*
	*  Create the PDO object and persistent connection for all models to use.
	*/
	private function connect() {
		$dsn = "mysql:host={$this->db_host};dbname={$this->db_name};charset=utf8mb4";

		$options = [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_PERSISTENT => true
		];

		try {
			self::$global_connection = new PDO($dsn, $this->db_username, $this->db_password, $options);
		}
		catch (\Throwable $e) {

			//change our message depending on debug mode.
			$message = self::$debug ? $e->getMessage() : 'Could not connect to database';

			//throw an exception to be caught by handler.
			throw new \Exception($message);
		}
	}

	/*
	* All queries go through this.
	* Execute query with the provided vars, optionally use the last statement from previous query.
	*/
	protected function query($query, $vars = [], $use_last_statement = false) {
		$this->last_query = $query;
		self::$sql_queries++;

		if(!$this->table || !$this->db) {
			$this->error('Attempted Query with no table or database connection.');
		}

		try {
			if ( !$vars || empty($vars) ) {
				 return $this->db->query($query);
			}

			//option to use an already prepared statement.
			if( $use_last_statement && $this->last_statement instanceof \PDOStatement) {
				$stmt = $this->last_statement;
			}
			else {
				$stmt = $this->db->prepare($query);
			}

			$stmt->execute($vars);
			$this->last_statement = $stmt;

			return $stmt;
		}
		catch(\PDOException $e) {
			$this->error($e->getMessage());
		}
	}

	/*
	* Piece together all the options into a select query.
	* Used by all public funcs that require a select query.
	*/
	private function run_select_query() {
		$results = array();
		$stmt = $this->query( $this->build_select_query() , $this->vars);

		if($this->is_count) {
			$count = intval( $stmt->fetchColumn() );
		}
		else {
			$results = $stmt->fetchAll();
			$count = count($results);
		}

		$this->last_result_count = $count;
		$this->last_results = $results;

		if(!$this->preserve) {
			$this->clear();
		}
	}

	/*
	* Build together all the options that have been set into a string SQL Query.
	*/
	private function build_select_query() {
		$query = 'SELECT ';

		//add the fields.
		if($this->is_count) {
			$query .= 'count(*)';
		}
		else {
			if(empty($this->fields)) {
				$this->fields = [$this->table .'.*'];
			}

			$query .= implode(', ', $this->fields);

			if(!empty($this->join_fields)) {
				$query .= ', ' . implode(', ', $this->join_fields);
			}
		}

		//add the table.
		$query .= ' FROM ' . $this->table;
		$query .= $this->build_joins();
		$query .= $this->build_where_clause();

		//add the ordering.
		if($this->order_by) {
			$query .= ' ORDER BY ' . $this->order_by . ' ';
			$query .= $this->order_dir ?? 'ASC';
		}

		//add the grouping.
		if($this->group_field) {
			$query .= ' GROUP BY ' . $this->group_field;
		}

		//add the limit. stored as array eg: [0, 25]
		if(!empty($this->limit)) {
			$query .= ' LIMIT ' . $this->limit[0];
			if(isset($this->limit[1])) {
				$query .= ', ' . $this->limit[1];
			}
		}

		return $query;
	}

	private function build_joins() {
		$sql =  '';

		foreach($this->joins as $table => $join) {
			$sql .= ' ' . $join['type'] . ' JOIN ' . $table . ' ON ';
			$sql .= preg_replace('/^(AND|OR|and|or)\s/', '', implode(' ', $join['on']));
		}

		return $sql;
	}

	private function build_where_clause() {
		if( !empty($this->where) ) {
			$where = preg_replace('/^(AND|OR|and|or)\s/', '', implode(' ', $this->where));
			return " WHERE $where";
		}

		return '';
	}

	/*
	* Used to make field names into a safe and unique variable name to use.
	*/
	private function safe_var_name($varname) {
		//create an sql variable
		$varname = ':' . preg_replace('/[^a-z]+/', '', $varname);
		$i = 1;

		//if weve already used this varname increment it.
		while( array_key_exists($varname . $i, $this->vars) ) {
			$i++;
		}

		//we found a safe/unique varname to use.
		return $varname . $i;
	}

	/* If modified to not be persistent then close the connection here.
	*/
	function __destruct() {
		//attempt to close the connection ourself if not persistent.
		if($this->db && !$this->db->getAttribute(PDO::ATTR_PERSISTENT)) {
			$this->last_result = null;
			$this->last_statement = null;
			self::$global_connection = null;
		}
	}

	/* Wrapper for various errors that can happen.
	*  Debug mode will output all various info stored in the model object.
	*/
	private function error($message) {

		if(self::$debug) {
			$fields = implode(', ', $this->fields);
			$wheres = implode(', ', $this->where);
			$limit = implode(', ', $this->limit);
			$joined_tables = implode(', ', array_keys($this->joins));

			$vars = '';
			foreach($this->vars as $key => $value) {
				$vars .= "$key = $value, ";
			}

			$message = "<br><br>SQL Model Error: <br>
			------------------------------------
			$message <br>
			------------------------------------ <br>
			Last Query: {$this->last_query} <br>
			Last Result Count: {$this->last_result_count}<br>
			<br>
			------------------------------------ <br>
			Table: {$this->table} <br>
			Fields: {$fields} <br>
			Where clauses: $wheres <br>
			Joined Tables: $joined_tables <br>
			Ordering: {$this->order_by} {$this->order_dir} <br>
			Limit: $limit <br>
			SQL Variables: $vars <br><br>";
		}
		else {
			$message = 'SQL Model Error';
		}

		throw new \Exception($message);
	}
}
