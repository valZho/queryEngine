<?php

// Build, run, and parse MySQL queries with ease!
class QueryEngine {

	private $MYSQL_HOST = 'localhost';
	private $MYSQL_USERNAME = 'username';
	private $MYSQL_PASSWORD = 'password';
	private $MYSQL_DATABASE = 'database_name';

	public $dbh;
	public $pdo;

	private $SELECT = [];
	private $FROM = '';
	private $FROM_ALIAS = '';
	private $JOIN = [];
	private $SET = [];
	private $MULTI_COLS = [];
	private $MULTI = [];
	private $WHERE = '';
	private $SEG_START = true;
	private $GROUPBY = [];
	private $ORDERBY = [];
	private $LIMIT = null;

	private $MODE = 'select';

	public $PARAMS = [];

	public $UPDATED = ['UNIX_TIMESTAMP(updated)', 'updated'];


	function __construct($table = null, $alias = null, $mode = null, $connection = null) {
		if ($connection != null) {
			$this->
		}
		$this->from($table, $alias);
		$this->mode($mode);
		return $this;
	}

	public function setDatabase($db = 'rbdb') {
		$this->MYSQL_DATABASE = $db;
	}

	// RESET ALL
	public function reset($table = null, $alias = null, $mode = null) {
		if (!empty($table)) {
			$this->from($table, $alias);
		}
		if (isset($mode)) {
			$this->mode($mode);
		}
		$this->SELECT = [];
		$this->JOIN = [];
		$this->WHERE = '';
		$this->SET = [];
		$this->MULTI_COLS = [];
		$this->MULTI = [];
		$this->SEG_START = true;
		$this->GROUPBY = [];
		$this->ORDERBY = [];
		$this->LIMIT = null;
		$this->pdo = null;
		$this->PARAMS = [];
		return $this;
	}

	// SET MODE
	public function mode($mode = 'select') {
		if (in_array($mode, ['select','insert','multiInsert','update','delete'])) {
			$this->MODE = $mode;
		}
		return $this;
	}

	// FROM
	private function tableString($table, $alias) {
		return $table . (isset($alias) ? ' `'.$alias.'`' : '');
	}

	public function from($table, $alias) {
		$this->FROM = $this->tableString($table, $alias);
		if (!empty($alias)) {
			$this->UPDATED[0] = 'UNIX_TIMESTAMP('.$alias.'.updated)';
		}
		return $this;
	}

	// JOINS
	private function createJoin($type, $table, $alias, $on) {
		$this->JOIN[] = $type.' JOIN '.$this->tableString($table, $alias).' ON '.$on.' ';
		return $this;
	}

	public function join($table, $alias, $on) {
		$this->createJoin('', $table, $alias, $on);
		return $this;
	}

	public function leftJoin($table, $alias, $on) {
		$this->createJoin('LEFT', $table, $alias, $on);
		return $this;
	}

	public function rightJoin($table, $alias, $on) {
		$this->createJoin('RIGHT', $table, $alias, $on);
		return $this;
	}

	public function innerJoin($table, $alias, $on) {
		$this->createJoin('INNER', $table, $alias, $on);
		return $this;
	}

	public function outerJoin($table, $alias, $on) {
		$this->createJoin('OUTER', $table, $alias, $on);
		return $this;
	}

	// SELECT
	public function select($fields) {
		// the fields input should look like this: [$field, [$field], [$field, $alias], ...]
		foreach ($fields as $field) {
			if (is_string($field)) {
				$this->SELECT[] = $field;
			} else if (is_array($field)) {
				$this->SELECT[] = $field[0] . ($field[1] ? ' AS "'.$field[1].'"' : '');
			}
		}

		return $this;
	}

	// WHERE
	public function where($where = '', $param=null, $type = 'AND') {
		$this->WHERE.= (!$this->SEG_START ? ' '.$type.' ' : '') . $where;
		$this->SEG_START = false;
		if (isset($param)) { $this->params($param); }
		return $this;
	}

	public function and($where = '', $param) {
		$this->where($where, $param);
		return $this;
	}

	public function or($where = '', $param) {
		$this->where($where, $param, 'OR');
		return $this;
	}

	public function segment($start = null, $and = true) {
		$pre = !empty($this->WHERE) ? ($and ? ' AND ' : ' OR ') : ' ';
		$this->WHERE.= ($start!==null) ? ($start ? $pre.'(' : ')') : '';
		$this->SEG_START = $start ? true : false;
		return $this;
	}

	// SET (UPDATE, INSERT)
	public function set($fields = []) {
		// fields should be: ["field1", ["field2", "value"], ... ]
		// if value is supplied separately, it will be parameterized
		foreach ($fields as $field) {
			if (!is_array($field)) {
				$this->SET[] = $field;
			} else {
				$this->SET[] = $field[0];
				$this->params($field[1]);
			}
		}
		return $this;
	}

	// MULTI COLUMNS
	public function multiCols($cols) {
		// should be an array of strings (column names)
		// e.g., $qe->multi(['col1','col2','col3']);
		foreach ($cols as $col) {
			$this->MULTI_COLS[] = $col;
		}

		return $this;
	}

	// MULTI DATA
	public function multi($values) {
		// pass in the actual values as an array of values
		// multi will handle the parameterization
		// e.g., $qe->multi(['value1', 1, false]);
		$placeholders = [];
		foreach ($values as $value) {
			$this->PARAMS[] = $value;
			$placeholders[] = '?';
		}
		$this->MULTI[] = '('.implode(', ', $placeholders).')';
		return $this;
	}

	// GROUP BY
	public function group($groupbys) {
		if (is_string($groupbys)) {
			$this->GROUPBY[] = $groupbys;
		} else {
			foreach ($groupbys as $groupby) {
				$this->GROUPBY[] = $groupby;
			}
		}
		return $this;
	}

	// ORDER BY
	public function order($orderbys, $reset = false) {
		if ($reset) { $this->ORDERBY = []; }
		if (is_string($orderbys)) {
			$this->ORDERBY[] = $orderbys;
		} else {
			foreach ($orderbys as $orderby) {
				$this->ORDERBY[] = $orderby;
			}
		}
		return $this;
	}

	// LIMIT
	public function limit($limit) {
		$this->LIMIT = $limit;
		return $this;
	}

	// PARAMETERS
	public function params($params = []) {
		if (!is_array($params)) {
			$this->PARAMS[] = $params;
		} else {
			foreach ($params as $param) {
				$this->PARAMS[] = $param;
			}
		}
		return $this;
	}

	public function resetParams() {
		$this->PARAMS = [];
		return $this;
	}

	// UPDATED (common search)
	public function updated($from = null) {
		if ($from) { $this->reset($from); }
		return $this->select([$this->UPDATED])->order('updated DESC')->limit(1)->getCol(0);
	}

	// LAST INSERT ID (common search)
	public function lastInsertId() {
		$this->execute('SELECT LAST_INSERT_ID()');
		return $this->pdo->fetchColumn(0);
	}

	// QUERY STRING LIBRARY
	private function qStr($type) {
		switch ($type) {
			case 'select'  : return 'SELECT ' . (count($this->SELECT) > 0 ? implode(', ', $this->SELECT) : '*') . ' FROM '.$this->FROM.' ';
			case 'insert'  : return 'INSERT INTO '.$this->FROM.' ';
			case 'update'  : return 'UPDATE '.$this->FROM.' ';
			case 'delete'  : return 'DELETE FROM '.$this->FROM.' ';
			case 'multiSet': return '('.implode(', ', $this->MULTI_COLS).') VALUES '.implode(', ', $this->MULTI).' ';
			case 'set'     : return 'SET '.implode(', ', $this->SET).' ';
			case 'join'    : return count($this->JOIN)>0 ? implode(' ', $this->JOIN).' ' : '';
			case 'where'   : return !empty($this->WHERE) ? 'WHERE '.$this->WHERE.' ' : '';
			case 'group'   : return count($this->GROUPBY)>0 ? 'GROUP BY '.implode(', ', $this->GROUPBY).' ' : '';
			case 'order'   : return count($this->ORDERBY)>0 ? 'ORDER BY '.implode(', ', $this->ORDERBY).' ' : '';
			case 'limit'   : return !empty($this->LIMIT) ? 'LIMIT '.$this->LIMIT.' ' : '';
		}
	}

	// BUILD THE QUERY STRING
	public function query() {
		switch ($this->MODE) {
			case 'select':
				$out = $this->qStr('select');
				$out.= $this->qStr('join');
				$out.= $this->qStr('where');
				$out.= $this->qStr('group');
				$out.= $this->qStr('order');
				$out.= $this->qStr('limit');
				break;

			case 'insert':
				$out = $this->qStr('insert');
				$out.= $this->qStr('set');
				break;

			case 'multiInsert':
				$out = $this->qStr('insert');
				$out.= $this->qStr('multiSet');
				break;

			case 'update':
				$out = $this->qStr('update');
				$out.= $this->qStr('join');
				$out.= $this->qStr('set');
				$out.= $this->qStr('where');
				$out.= $this->qStr('group');
				$out.= $this->qStr('limit');
				break;

			case 'delete':
				$out = $this->qStr('delete');
				$out.= $this->qStr('where');
				$out.= $this->qStr('order');
				$out.= $this->qStr('limit');
				break;
		}

		return $out;
	}

	// DOES THE QUERY HAVE ALL THE MINIMUM PIECES?
	public function isReady() {
		if (empty($this->FROM)) { return false; }

		switch($this->MODE) {
			case 'insert':
			case 'update':
				return (count($this->SET) > 0);
			case 'multiInsert':
				return ((count($this->MULTI_COLS)>0) && (count($this->MULTI)>0));
		}

		return true;
	}

	// output the query with parameters?
	public function debug() {
		return $this->query().' ——— '.json_encode($this->PARAMS);
	}

	// QUERY ----------------------
	public function prepPDO() {
		if (!is_object($this->dbh)) {
			$this->dbh = new PDO(
				'mysql:host='.$this->MYSQL_HOST.';dbname='.$this->MYSQL_DATABASE,
				$this->MYSQL_USERNAME,
				$this->MYSQL_PASSWORD,
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
					PDO::ATTR_EMULATE_PREPARES => false,
					PDO::ATTR_STRINGIFY_FETCHES => false,
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
				)
			);
		}

		if ($this->isReady()) {
			$this->pdo = $this->dbh->prepare($this->query());
		}
		return $this;
	}

	public function execute($queryOverride = null, $paramsOverride = []) {
		if (!is_object($this->dbh)) {
			$this->dbh = new PDO(
				'mysql:host='.$this->MYSQL_HOST.';dbname='.$this->MYSQL_DATABASE.';charset=utf8',
				$this->MYSQL_USERNAME,
				$this->MYSQL_PASSWORD,
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET group_concat_max_len=4000000000",
					PDO::ATTR_EMULATE_PREPARES => false,
					PDO::ATTR_STRINGIFY_FETCHES => false,
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
				)
			);
		}

		if ($queryOverride) {
			$this->pdo = $this->dbh->prepare($queryOverride);
			$this->pdo->execute($paramsOverride);

		} else if (!is_object($this->pdo) && $this->isReady()) {
			$this->pdo = $this->dbh->prepare($this->query());
			$this->pdo->execute($this->PARAMS);

		}
		return $this;
	}

	private function runCmd($cmd = '', $fields, $key, $value) {
		// do we process this?
		if (!in_array($key, $fields)) { return $value; }

		// which command to run?
		switch ($cmd) {

			// decode a JSON string input into PHP array
			case 'decode':
				return ($value===null) ? null : json_decode($value, true);

			// decode a JSON string input into PHP object
			case 'decodeObject':
				return ($value===null) ? null : json_decode($value, false);

			// convert a value to a true boolean
			case 'boolean':
				return $value ? true : false;
		}
	}

	public function getAll($commands = [], $fetchType = 'assoc', $columnNumber = 0) {
		// command structure: ['command'=>[field1, field2, ...], ...]
		$this->execute();
		switch ($fetchType) {
			case 'col':
				$rows = $this->pdo->fetchAll(PDO::FETCH_COLUMN, $columnNumber);

				// commands can process the data before returning it
				foreach ($rows as &$col) {
					foreach ($commands as $cmd => $fields) {
						$col = $this->runCmd($cmd, ['col'], 'col', $col); // always run on single column
					}
				}
				break;

			case 'assoc':
			default:
				$rows = $this->pdo->fetchAll(PDO::FETCH_ASSOC);

				// commands can process the data before returning it
				foreach ($rows as &$row) {
					foreach($row as $key => &$value) {
						foreach ($commands as $cmd => $fields) {
							$value = $this->runCmd($cmd, $fields, $key, $value);
						}
					}
				}
		}

		return $rows;
	}

	public function getRow($commands = []) {
		$this->execute();
		$row = $this->pdo->fetch(PDO::FETCH_ASSOC);

		// commands can process the data before returning it
		if ($row) {
			foreach($row as $key => &$value) {
				if ($value) {
					foreach ($commands as $cmd => $fields) {
						$value = $this->runCmd($cmd, $fields, $key, $value);
					}
				}
			}
		}

		return $row;
	}

	public function getCol($column = 0, $commands = []) {
		$this->execute();
		$col = $this->pdo->fetchColumn($column);

		// commands can process the data before returning it
		foreach ($commands as $cmd => $fields) {
			$col = $this->runCmd($cmd, ['col'], 'col', $col); // always run on single column
		}

		return $col;
	}

}

?>
