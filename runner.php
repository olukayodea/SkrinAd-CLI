<?php	
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

	include_once("constants.php");	
    include_once("config.php");
    
    $database = new database;
    $db = $database->connect();
    
    class running extends database {
		protected $ageRange = array("0-10", "11-17", "18-35", "36-65", "65+");
		protected $gender = array("Male", "Female");

		protected $importHeader = array("`advert`", "`user_id`", "`service_request`", "`impression`", "`impression_time`", "`click`", "`click_time`", "`ageRange`", "`gender`", "`synctime`");
		protected $spoofHeader = array("`user`", "`ref`", "`url`");

		protected $list = array();
		protected $spinList = array();

		function getRunning() {

		}

		function adjuster( $array = [] ) {
			$today = date("D");

			if ( is_array( $array ) && count( $array )) {
				$searchList = implode( ",", $array );

				$bulkData = $this->query("SELECT * FROM `advert` WHERE `status` = 'active' AND `ref` IN (" . $searchList . ")", false, "list" );

				if (count( $bulkData ) > 0) {
					foreach( $bulkData as $data ) {
						$avail_date = explode("_", $data['avail_date']);
						if (array_search($today, $avail_date) !== false ) {	
							$total = $this->total($data['ref']);
							$used_imp = $data['used_imp'];

							if ($used_imp < $total) {
								if ((int) $this->dailCap($data['ref']) < ((int) $data['daily_cap']*0.75)) {
									for ($l = 0; $l < rand(10,20); $l++) {
										$randomUser = $this->lists("users", false, 1, "RAND", "ASC", "`ageRange` = '".$this->ageRange[array_rand($this->ageRange)]."' AND `gender` = '".$this->gender[array_rand($this->gender)]."'", "getRow");
										if ($randomUser) {
											$click = rand(0, 1);
	
											$list = "(".$data['ref'];
											$list .= ", ";
											$list .= $randomUser['ref'];
											$list .= ", ";
											$list .= intval($this->getService($randomUser['ref']));
											$list .= ", 1, '";
											$list .= time()-(60*60);
											$list .= "', ";
											$list .= $click;
											$list .= ", '";
											$list .= time()-(60*30);
											$list .= "', '";
											$list .= $randomUser['ageRange'];
											$list .= "', '";
											$list .= $randomUser['gender'];
											$list .= "', '";
											$list .= time()."')";
	
											$this->list[] = $list;
							
											if ($click == 1) {
												$spin = "(".$randomUser['ref'];
												$spin .= ", ";
												$spin .= $data['ref'];
												$spin .= ", '";
												$spin .= $data['url']."')";
												
												$this->spinList[] = $spin;
											}
										}
									}

								}
							}
						}
					}

					if (count($this->list) > 0) {
						$this->postUpdate();
						$this->spin();
						$this->impresionRefreshOne($array);
					}
				}
				return false;
			}

			return false;
		}
		
		function impresionRefreshOne($array) {
			global $db;
			
			$time = time();
			
			foreach( $array as $id) {
				$used_imp = $this->impressionGet($id);

				if ($used_imp >= $this->total($id)) {

					$this->updateOne("advert", "status", "complete", $id);
				}
				
				try {
					$db->query("UPDATE `advert` SET `used_imp` = '".$used_imp."', `sync_time` = '".$time."' WHERE ref = '".$id."'");
				} catch(PDOException $ex) {
					echo "An Error occured! ".$ex->getMessage(); 
				}
			}
		}

		function dailCap($advert) {
			$from = mktime(0, 0, 0, date("n"), date('j'), date("Y"));
			$to = mktime(23, 59, 59, date("n"), date('j'), date("Y"));
            
            return $this->query("SELECT COUNT(`ref`) FROM `advert_stat` WHERE `advert` = ".$advert." AND `synctime` BETWEEN ".$from." AND ".$to, false, "getCol");
		}
		
		function impressionGet($id) {
			return $this->countAll($id, "advert");
		}
        
		function spin() {
			if  (count($this->spinList) >  0) {
				$query = "INSERT INTO `spoof_log` (";
				$query .= implode(",", $this->spoofHeader);
				$query .= ") VALUES ";
				$query .= implode(",", $this->spinList);
				
				$this->query($query);
			}
        }
        
		function listOne($id, $tag='ref') {
            return $this->getOne("advert", $id, $tag);
        }

		function getSingle($name, $tag="title", $ref="ref") {
            return $this->getOneField("advert", $name, $ref, $tag);
        }
        
		function total($id) {
			$impression = $this->getSingle($id, "impression");
			$impression_added = $this->getSingle($id, "impression_added");

			return floor($impression+$impression_added);
		}
        	
		function countAll($id, $tag, $tag2=false, $id2=false, $tag3=false, $id3=false, $order='ref') {
			$token = array(':id' => $id);
			if ($tag2 != false) {
				$sqlTag = " AND `".$tag2."` = :id2";
				$token[':id2'] = $id2;
			} else {
				$sqlTag = "";
			}
			if ($tag3 != false) {
				$sqlTag .= " AND `".$tag3."` = :id3";
				$token[':id3'] = $id3;
			} else {
				$sqlTag .= "";
			}
			
			global $db;
			try {
				$sql = $db->prepare("SELECT COUNT(`ref`) FROM `advert_stat` WHERE `".$tag."` = :id".$sqlTag." ORDER BY `".$order."` ASC");
								
				$sql->execute($token);
			} catch(PDOException $ex) {
				echo "An Error occured! ".$ex->getMessage(); 
			}
			
			$row = $sql->fetchColumn();
			return $row;
		}

		function postUpdate() {
			$query = "INSERT INTO `advert_stat` (";
			$query .= implode(",", $this->importHeader);
			$query .= ") VALUES ";
			$query .= implode(",", $this->list);
			
			return $this->query($query);
        }
        
		function adCount($id) {
            return $this->query("SELECT COUNT(`ref`) FROM `advert_stat` WHERE `advert` = :id", array(":id"=>$id), "getCol");
        }

		function getService() {
            return $this->query("SELECT `id` FROM `service_request` ORDER BY RAND(),`create_time` DESC LIMIT 1", false, "getCol");
		}
    }
    
    $running = new running;

	if ($argData == 'dev') {
		$running->adjuster([118]);
	} else {
		$running->adjuster( [119, 121, 122, 123, 124, 125, 127, 129] );
	}
	echo "\n";
?>