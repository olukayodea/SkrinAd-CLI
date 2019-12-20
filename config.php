<?php
	class database {
		/**
		 * @param object	$db			the datavase object
		 * @param string	$table		the name of the table that is querried
		 * 
		 */

        var $db;
        var $table;
        var $data = array();
        var $where = array();

        var $prepare = array();
        var $query;

		function connect() {
			$db = new PDO('mysql:host='.servername.';dbname='.dbname.';charset=utf8', dbusername, dbpassword, 
			array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			return $db;
        }

		/**
		 * @param string	$table		name of table to be inserted into
		 * @param array		$data		the array containing the key value pair corresponding to the coloum and corresponing data to be inserted, updated or replaced
		 * @param array		$where		the array containing the key value pair corresponding to the coloum and corresponing data in the WHERE clause
		 * @param array		$replace	an array containing the value key pair of the data to be replaced if exisit
		 * 
		 */
        public function insert($table, $data) {
            $queryLine1 = "";
            $queryLine2 = "";

            foreach  ($data as $key => $value) {
                $queryLine1 .= "`".$key."`,";
                $queryLine2 .= ":".$key.",";

                $prepare[":".$key] = $value;
            }

            $queryLine1 = trim($queryLine1, ",");
            $queryLine2 = trim($queryLine2, ",");

            $query = "INSERT INTO `".$table."` (".$queryLine1.") VALUES (".$queryLine2.")";
            return $this->run($query, $prepare, "insert");
        }

		/**
		 * @param string	$table		name of table to be inserted into
		 * @param array		$header		the array containing the table coloums
		 * @param array		$list		the array containing the data to dump into the database
		 */
        public function multiInsert($table, array $header, array $list) {
            if (count($list) > 0) {
                $query = "INSERT INTO `".$table."` (";
                $query .= implode(",", $header);
                $query .= ") VALUES ";
                $query .= implode(",", $list);
                
                return $this->run($query);
            } else {
                return false;
            }
        }

		/**  
		* @param string $table		name of table to be inserted into
        * @param array $data		an array containing the value key pair of the data to be inserted
        * @param array $replace		an array containing the value key pair of the data to be replaced if exisit
        */
        public function replace($table, $data, $replace) {
            $queryLine1 = "";
            $queryLine2 = "";
            $queryLine_r = "";

            foreach  ($data as $key => $value) {
                $queryLine1 .= "`".$key."`,";
                $queryLine2 .= ":".$key.",";

                $prepare[":".$key] = $value;
            }
            foreach  ($replace as $r_key) {
                $queryLine_r .= "`".$r_key."` = :".$r_key.",";

            }

            $queryLine1 = trim($queryLine1, ",");
            $queryLine2 = trim($queryLine2, ",");
            $queryLine_r = trim($queryLine_r, ",");

            $query = "INSERT INTO `".$table."` (".$queryLine1.") VALUES (".$queryLine2.")
            ON DUPLICATE KEY UPDATE ".$queryLine_r;

            return $this->run($query, $prepare, "replace");
        }

        /**
		 * Get a row of items in a table based on the search criteria
		 * @param string $table	name of table to fetch from
		 * @param string $tag	the col to fetch from
		 * @param string $id	the row to fetch from
        */
        public function getOne($table, $id, $tag='ref') {
            $query = "SELECT * FROM `".$table."` WHERE `".$tag."` = :".$tag." LIMIT 1";
            $return[":".$tag] = $id;
            return $this->run($query, $return, "getRow");
        }
		
        /**  Get a row of items in a table based on the search criteria
        *   @param string $table	name of table to fetch from
        *   @param string $tag		the col to fetch from
        *   @param string $id		the row to fetch from
        *   @param string $ref		the row reference to return
        *   @param array $where		the WHERE clause where included
        */
		public function getOneField($table, $id, $tag, $ref) {
            $query = "SELECT `".$ref."` FROM `".$table."` WHERE `".$tag."` = :".$tag." LIMIT 1";
            $return[":".$tag] = $id;
            return $this->run($query, $return, "getCol");
        }
        
        /**
        *   @param string $table	name of table to fetch from
        *   @param integer $start	the first row to return
        *   @param integer $limit	the number of returned row
        *   @param string $order	the sort row
        *   @param string $dir		the ORDER direction
        *   @param string $where	the WHERE clause where included
        *   @param string $type		the type of results to be returned
        */
        public function lists($table, $start=false, $limit=false, $order='ref', $dir='ASC', $where=false, $type="list") {
            $endTag = "";
            if ($where != false ) {
                $endTag .= " WHERE ".$where;
            }
            if ($order == "RAND") {
                $endTag .=" ORDER BY RAND()";
            } else {
                $endTag .=" ORDER BY `".$order."` ".$dir;
            }
            if (($start != false ) AND ($limit != false )) {
                $endTag .= " LIMIT ".$start.", ".$limit;
            } else if ($limit != false ) {
                $endTag .= " LIMIT ".$limit;
            } else {
                $endTag .= "";
            }

            $query = "SELECT * FROM `".$table."`".$endTag;
            return $this->run($query, false, $type);
        }

        /**
        * @param string $table	name of table to fetch from
        * @param string $id		value of the first coloum to compare
        * @param string $id2	value of the second coloum to compare
        * @param string $id3	value of the third coloum to compare
        * @param string $tag	the first coloum to compare
        * @param string $tag2	the second coloum to compare
        * @param string $tag3	the third coloum to compare
        * @param string $logic	the LOGICAL operation to perform on the coloums
        * @param string $order	the sort row
        * @param string $dir	the ORDER direction
        * @param integer $start	the first row to return
        * @param integer $limit	the number of returned row
        * @param string $type	the type of results to be returned
        */
        public function sortAll($table, $id, $tag, $tag2=false, $id2=false, $tag3=false, $id3=false, $order='ref', $dir="ASC", $logic="AND", $start=false, $limit=false, $type="list") {
			$prepare = array(':'.$tag => $id);
			if ($tag2 != false) {
				$sqlTag = " ".$logic." `".$tag2."` = :".$tag2;
				$prepare[':'.$tag2] = $id2;
			} else {
				$sqlTag = "";
			}
			if ($tag3 != false) {
				$sqlTag .= " ".$logic." `".$tag3."` = :".$tag3;
				$prepare[':'.$tag3] = $id3;
			} else {
				$sqlTag .= "";
            }
            
            if (($start != false ) AND ($limit != false )) {
                $endTag = " LIMIT ".$start.", ".$limit;
            } else if ($limit != false ) {
                $endTag = " LIMIT ".$limit;
            } else {
                $endTag = "";
            }
            $query = "SELECT * FROM `".$table."` WHERE `".$tag."` = :".$tag.$sqlTag." ORDER BY `".$order."` ".$dir.$endTag;
            return $this->run($query, $prepare, $type);
        }
		
		/**
        * @param string $table		name of table to fetch from
        * @param array $data		key value pair in an array to update 
        * @param array $where		key value pair in an array for WHERE clause. For OR operation of the same coloun, seperate each values with a corma eg WHERE `ref` = 1 OR `ref` = 2 will be $where['ref'] = "1,2";
        * @param string $logic		single LOGICAL operator to use in where clause of multiple keys
        * @param string $multiple	replace the where clasue with string
        */
        public function update($table, $data, $where=false, $logic=false, $multiple=false) {
            $queryLine = "";
            $whereLine = "";

            foreach  ($data as $key => $value) {
                $queryLine .= "`".$key."`= :".$key.",";

                $prepare[":".$key] = $value;
            }
            if ((count($where) > 1) AND ($logic == false)) {
                exit("You must pass a logic operator of AND or OR on the logic if multiple = false");
            }

            if ($multiple == false) {
                foreach ($where as $w_key => $w_value) {
                    $checkOR = explode(",", $w_value);
                    if (count($checkOR) > 1) {
                        $whereLine .= "(";
                        for ($i = 0; $i < count($checkOR); $i++) {
                            $whereLine .= "`".$w_key."`= :w_".$i."_".$w_key." OR ";
                            $prepare[":w_".$i."_".$w_key] = $checkOR[$i];
                        }
                        $whereLine = trim($whereLine, "OR ");
                        $whereLine .= ") ".$logic;
                    } else {
                        $whereLine .= "`".$w_key."`= :w_".$w_key." ".$logic;

                        $prepare[":w_".$w_key] = $w_value;
                    }
                }
                $queryLine = trim($queryLine, ",");
                $whereLine = trim($whereLine, $logic);
            } else {
                $whereLine = $multiple;
            }
            
            $query = "UPDATE `".$table."` SET ".$queryLine." WHERE ".$whereLine;

            return $this->run($query, $prepare);
        }
        
        /*  $table   =   name of table to be update
        *   $tag     =   coloun to update
        *   $value   =   value to update in the colounm
        *   $ref     =   row to update
        *   $id      =   unique colounm in row to update
        */
        public function updateOne($table, $tag, $value, $id, $ref="ref", $extra="") {
            if ($extra != "") {
                $extra .= ",".$extra;
            }
            $query = "UPDATE `".$table."` SET  `".$tag."` = :".$tag.$extra." WHERE `".$ref."`=:w_".$ref;
            $prepare[":".$tag] = $value;
            $prepare[":w_".$ref] = $id;

            return $this->run($query, $prepare);
        }

        public function delete($table, $id, $ref="ref") {
            $prepare[':'.$ref] = $id;
            $query = "DELETE FROM `".$table."` WHERE `".$ref."` = :".$ref;
           
            return $this->run($query, $prepare);
        }

        /*  run direct SQL queries in the database
        *   queries either pepared or raw
        *   $prepare: if query is prepared, array with the prepared values
        */
        public function query($query, $prepare=false, $type=false) {
            return $this->run($query, $prepare, $type);
        }

        /*  che k if a particular field has a particular distinct data
        *   $table   =   name of table to be checked
        *   $key     =   coloun to check for data from
        *   $value   =   value to check against the key
        */
        public function checkExixst($table, $key, $value, $return="count") {
            $query = "SELECT `ref` FROM `".$table."` WHERE `".$key."` = :".$key;
            $prepare[":".$key] = $value;
            if ($return == "col") {
                return $this->run($query, $prepare, "getCol");
            } else {
                return $this->run($query, $prepare, "count");
            }
        }

        /*  runs the SQL query with a prepare array for the variables
        *   Type    =   false
                    =   insert: when the query is an insert satatement
                    =   replace: when the query is an insert statement with a replace duplicate statement
                    =   list: get a list of associated array for all the rows
                    =   getRow: get one row
                    =   getCol: get one col
                    =   count: get row count
            search: the binded search word
        */
        public function run($query, $prepare=false, $type=false, $search=false) {
            global $db;
            try {
                if ($prepare != false) {
                    if ($search != false) {
                        $sql = $db->prepare($query);

                        foreach ($prepare as $key => $value) {
                            if ($key == ":".$search) {
                                $sql->bindValue($key, "%".$value."%");
                            } else {
                                $sql->bindValue($key, $value);
                            }
                        }
                        $sql->execute();
                    } else {
                        $sql = $db->prepare($query);
                        $sql->execute($prepare);
                    }
                } else {
                    $sql = $db->query($query);
                }
			} catch(PDOException $ex) {
                exit( "An Error occured! ".$ex->getMessage() );
            }
            if ($sql) {
                if ($type == "replace") {
                    return ($db->lastInsertId('ref') > 0 ? $db->lastInsertId('ref') : $prepare[":ref"]);
                } else if ($type == "insert") {
                    return $db->lastInsertId('ref');
                } else if ($type == "list") {
                    return $sql->fetchAll(PDO::FETCH_ASSOC);
                } else if ($type == "getRow") {
                    return $sql->fetch(PDO::FETCH_ASSOC);
                } else if ($type == "getCol") {
                    return $sql->fetchColumn();
                } else if ($type == "count") {
                    return $sql->rowCount();
                } else {
                    return true;
                }
            } else {
                return false;
            }
        }
    }
?>