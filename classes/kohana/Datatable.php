<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Class DataTable
  * Compatible with DataTables 1.10.x
*
* @author Original Author Robin <contact@robin-d.fr>
* @link http://www.robin-d.fr/DataTablesPHP/
* @link https://github.com/RobinDev/DataTablesPHP
* @since File available since Release 2014.05.01
* @rewrite by andmetoo
 */
class Kohana_Datatable {

    protected $columns = array(), $unsetColumns=array();

    private $db;
    /**
     * @var DataTable
     */
    protected static $instance = null;
    /**
     * $_POST OR GET REQUEST
     * @var array
     */
    private $request;

    /**
     * Current table for use
     * @var string
     */
    private $table;

    /**
     * Array of additional conditions
     * @var array
     */
    private $conditions = array();

    /**
     * Correspond to the column options for DataTables javascript initialization (see the doc : DataTables > Refererences > Column)
     * /!/ Tmp //Comment => Will be deleted
     */
    protected static $columnParams = array(
        'name',			// Did we really care about the name ?  It's fort API
        'data',			// data source string|object ... object pour Sort !! http://datatables.net/examples/ajax/orthogonal-data.html http://datatables.net/reference/option/columns.data
        // if data === null = pas de donnée à afficher
        'title',
        'defaultContent',
        'width',
        'visible',
        'type',			// numeric|date
        'searchable',
        'render',
        'orderable',
        'orderDataType',	// We don't care because the search is did by SQL not JS ?!
        'orderData',		// Pour trier une colonne en fonction d'autres colonnes
        'orderSequence',	//
        'createdCell',		// Callback pour personnaliser la cellule en fonction de la donnée
        'contentPadding',
        'className',		 // Alias of class ????!!!!! => A vérifier
        'cellType'			 // To set TH
    );

    /**
     * 
     * @param string $tableName
     * @return Datatable
     */
    public static function instance($tableName = 'datatable') {
        $cls = get_called_class();
        if(!isset(Datatable::$instance[$tableName]))
            Datatable::$instance[$tableName] = new $cls($tableName);
        return Datatable::$instance[$tableName];
    }

    /**
     * @param $tableName
     */
    function __construct($tableName) {
        $this->table = $tableName;
        $this->db = Database::instance();
    }

    /**
     * @param $column
     * @return $this
     */
    function setUnsetColumn($column) {
        $this->unsetColumns[] = $column;
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    function setUnsetColumns($columns) {
        foreach($columns as $c)
            $this->unsetColumns[] = $c;
        return $this;
    }


    /*** Server Side ***/
    /**
     * @param $table
     * @return $this
     */
    function setFrom($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * Join data from other tables
     *
     * @param string $table .
     * @param array $on Must contains two elements like key:sql_table => value:sql_column
     * @param string $join .
     * @return $this
     */
    function setJoin($table, $on, $join = 'LEFT JOIN') {
        $on2 = array_keys($on);
        $this->join[] = $join.' `'.$table.'` ON `'.key($on).'`.`'.current($on).'` = `'.next($on2).'`.`'.next($on).'`';
        //$this->join[] = $join.' `'.$table.'` ON `'.$table.'`.`'.$on[$table].'` = `'.$this->table.'`.`'.$on[$this->table].'`';
        return $this;
    }

    /**
     * @param $columns
     */
    function setColumns($columns)
    {
        $this->columns = $columns;
    }

    /**
     * Set additional conditions
     * @param string $column    column
     * @param string $condition condition example '=' , '<' , '>' 
     * @param string $value     Value for condition
     */
    public function setCondition($column,$condition,$value)
    {
        $this->conditions[]=array(
            'column'    =>  $column,
            'condition' =>  $condition,
            'value'     =>  $value
        );
    }

    /**
     * Create the data output array for the DataTables rows
     *
     * @param array $data Data from the SQL get
     * @return array Formatted data in a row based format
     */
    function data_output($data) {
        $out = array();

        for ( $i=0, $ien=count($data) ; $i<$ien ; ++$i ) {
            $row = array();

            for ( $j=0, $jen=count($this->columns) ; $j<$jen ; ++$j ) {
                $column = $this->columns[$j];
                // Compatibility with the json .
                // if preg_match('#\.#', $column['data']) explode ('.', $colum['data'])...
                    if(isset($column['data']) && !empty($column['data']))
                    {
                        //if alias we need output column with alias name
                        $columnname = isset($column['alias'])?'alias':'data';
                        $row[ isset($column[$columnname])?$column[$columnname]:$j ] = $data[$i][$column[$columnname]];
                    }
                    else
                        $row[ $j ] = '';

            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Send the json encoded result for DataTables.js
     *
     * @param $request
     * @return string json output
     */
    function sendData($request) {
        $query = $this->generateSQLRequest($request);
        $db = Database::instance();
        $data = $db->query(Database::SELECT,$query['data'])->as_array();
        $recordsFiltered = $db->query(Database::SELECT,$query['recordsFiltered'])->get('count');
        $recordsTotal = $db->query(Database::SELECT,$query['recordsTotal'])->get('count');
        $toJson = array(
            'draw' => intval($this->request['draw']),
            'recordsTotal' => intval($recordsTotal),
            'recordsFiltered' => intval($recordsFiltered),
            'data' => $this->data_output($data) );
        exit(json_encode($toJson));
    }

    /**
     * Generate the SQL queries (optimized for MariaDB/MySQL) to execute.
     *
     * @param  array $dtRequest Request send by DataTables.js ($_GET or $_POST)
     * @return array SQL queries to execute (keys: data, recordsFiltered, recordsTotal)
     */
    function generateSQLRequest($dtRequest) {
        $this->request = $dtRequest;

        $limit = $this->limit();
        $order = $this->order();
        $where = $this->filter();
        $columns = array();
        foreach($this->columns as $c) {
            if(isset($c['data']) && !empty($c['data']))
                $columns[] = self::toSQLColumn($c);
        }

        foreach($this->unsetColumns as $c)
            $columns[] = self::toSQLColumn($c);

        $join = isset($this->join) ? ' '.implode(' ', $this->join) : '';
        $strandWhere ='';
        $countofConditions = 1;
        foreach($this->conditions as $condition)
        {
            if(!empty($where))
            {
                $strandWhere = $strandWhere.' AND '.$condition['column'].$condition['condition'].'"'.$condition['value'].'"';
            }
            else
            {
                if($countofConditions == 1)
                {
                    $strandWhere = $strandWhere.' WHERE '.$condition['column'].$condition['condition'].'"'.$condition['value'].'"';
                }
                else
                {
                    $strandWhere = $strandWhere.' AND '.$condition['column'].$condition['condition'].'"'.$condition['value'].'"';
                }

            }
            $countofConditions++;
        }


        return array(
            'data' 			  => 'SELECT SQL_CALC_FOUND_ROWS '.implode(',',$columns).' FROM '.$this->table.$join.' '.$where.' '.$strandWhere.' '.$order.' '.$limit.';',
            'recordsFiltered' => 'SELECT FOUND_ROWS() count;',
            'recordsTotal'	  => 'SELECT COUNT(*) count FROM '.$this->table.';'
        );
    }

    /**
     * Paging : Construct the LIMIT clause for server-side processing SQL query
     *
     * @return string SQL limit clause
     */
    function limit() {
        if (isset($this->request['start']) && $this->request['length'] != -1) {
            return 'LIMIT '.intval($this->request['start']).','.intval($this->request['length']);
        }
    }

    /**
     * Ordering : Construct the ORDER BY clause for server-side processing SQL query
     *
     * @return string SQL order by clause
     */
    function order() {
        $order = '';
        if (isset($this->request['order']) && count($this->request['order'])) {
            $orderBy = array();
            for ($i=0,$ien=count($this->request['order']);$i<=$ien;$i++) {
                $columnIdx = intval($this->request['order'][$i]['column']);
                $column = $this->columns[$columnIdx];

                $orderBy[] = self::toSQLColumn($column).' '.($this->request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC');
            }
            $order = 'ORDER BY '.implode(', ', $orderBy);
        }
        return $order;
    }

    /**
     * Searching/Filtering : Construct the WHERE clause for server-side processing SQL query.
     */
    function filter() {
        $globalSearch = array();
        $columnSearch = array();

        // Global Search
        $count = 0;
        if ( isset($this->request['search']) && !empty($this->request['search']['value'])) {
            foreach($this->request['columns'] as $column)
            {
                if ( $column['searchable'] == "true")
                {

                    if(isset($this->columns[$count]['alias']))
                    {
                        $column['data'] = $this->columns[$count]['data'];
                        $column['table'] = $this->columns[$count]['table'];
                    }
                    $globalSearch[] = self::toSQLColumn($column).' LIKE '.self::quote($this->request['search']['value'], '%');
                }
                $count++;
            }
        }

        // Combine the filters into a single string
        $where = '';

        if ( count( $globalSearch ) ) {
            $where = '('.implode(' OR ', $globalSearch).')';
        }

        if ( count( $columnSearch ) ) {
            $where = $where === '' ? implode(' AND ', $columnSearch) : $where .' AND '. implode(' AND ', $columnSearch);
        }

        return $where = $where !== '' ? 'WHERE '.$where : '';
    }

    /**
     * Quote and Protect string for sql query
     *
     * @param string $str String to quote
     * @param string $encapsuler Encapsulate the string without being protected (for Like % eg)
     * @return string
     */
    static function quote($str, $encapsuler = '') {
        if(empty($encapsuler) && (is_int($str) || ctype_digit($str)))
            return $str;
        return '\''.$encapsuler.addcslashes($str, '%_\'').$encapsuler.'\'';
    }

    /**
     * return coumn as sql
     * @param  string  $column    
     * @param  boolean $onlyAlias
     * @return string
     */
    function toSQLColumn($column, $onlyAlias = false) {
        $columnTable = isset($column['table'])?$column['table']:$this->table;
        if(!isset($column['data']))
            self::sendFatal('Houston, we have a problem with one of your column : can\'t draw it SQL name because it don\'t have data or sql_name define.');
        return $onlyAlias && isset($column['alias']) ? $column['alias'] :
            '`'.$columnTable.'`.'
            .'`'.$column['data'].'`'
            .(!$onlyAlias && isset($column['alias']) ? ' AS '.$column['alias'] : '');
    }

    static function fromSQLColumn($column) {
        return isset($column['alias']) ? $column['alias'] : (isset($column['sql_name']) ? $column['sql_name'] : $column['data']);
    }

    /**
     * Throw a fatal error.
     *
     * This writes out an error message in a JSON string which DataTables will
     * see and show to the user in the browser.
     *
     * @param string $error Message to send to the client
     */
    static function sendFatal($error) {
        exit(json_encode( array( "error" => $error ) ));
    }

}
