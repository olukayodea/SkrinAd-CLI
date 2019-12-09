<?php	
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    // $URL = "http://127.0.0.1/skrinad/";
    // $servername = "localhost";
    // $dbusername = "root";
    // $dbpassword = "root";
    // $dbname = "linnkste_skrinad";
    $URL = "https://skrinad.me/";
    $servername = "main-db.cpwhcjg2ara2.eu-west-3.rds.amazonaws.com";
    $dbusername = "skrinad_db_user";
    $dbpassword = "n%).*6CBlBBu";
    $dbname = "skrinad_db";

	define("URL", $URL);
	define("servername",  $servername);
	define("dbusername",  $dbusername);
	define("dbpassword",  $dbpassword);
	define("dbname",  $dbname);
	
    include_once("config.php");
    
    $database = new database;
    $db = $database->connect();
    
    class running extends database {
		protected $ageRange = array("0-10", "11-17", "18-35", "36-65", "65+");
		protected $gender = array("Male", "Female");
		
		function adjust($id) {
			$data = $this->listOne($id);
			$avail_date = explode("_", $data['avail_date']);
			$today = date("D");

			$counter = 0;
			if (array_search($today, $avail_date) !== false ) {	
				$total = $this->total($data['ref']);
				$used_imp = $data['used_imp'];
				if ($data['status'] == "active") {
					if ($used_imp < $total) {
						$to = rand(30, 50);
							
						for ($i = 0; $i < $to; $i++) {
							if ($this->dailCap($data['ref']) < $data['daily_cap']) {
								$randomUser = $this->lists("users", false, 1, "RAND", "ASC", "`ageRange` = '".$this->ageRange[array_rand($this->ageRange)]."' AND `gender` = '".$this->gender[array_rand($this->gender)]."'", "getRow");

								$click = rand(0, 1);

								$array['advert'] = $data['ref'];
								$array['user_id'] = $randomUser['ref'];
								$array['impression'] = 1;
								$array['impression_time'] = time()-(60*30);
								$array['click'] = $click;
								$array['click_time'] = time();
					
								//post to us
								for ($l = 0; $l < rand(2,3); $l++) {
									$counter++;
									if ($click == 1) {
										$this->spin($randomUser['ref'], $data['ref'], $data['url']);
									}
									$this->postUpdate($array, false, false);
								}
							} else {
								break;
							}
						}
						$this->impresionRefreshOne($data['ref']);
					}
				}
			}
			
			return $counter." Processed";
		}
		
		function impresionRefreshOne($id) {
			global $db;
			
			$time = time();
			
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

		function dailCap($advert) {
			$endTime = mktime(0, 0, 0, date("n"), date('j'), date("Y"));
            
            return $this->query("SELECT COUNT(`ref`) FROM `advert_stat` WHERE `advert` = ".$advert." AND `synctime` > ".$endTime, false, "getCol");
		}
		
		
		function impressionGet($id) {
			return $this->countAll($id, "advert");
		}
        
		function spin($user, $ref, $url) {
			$database = new database;
			$database->insert("spoof_log", array("user" => $user, "ref" => $ref, "url" => $url));
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

		function postUpdate($array) {
			$advert = $array['advert'];
			$user_id = $array['user_id'];
			$impression = $array['impression'];
			$impression_time = $array['impression_time'];
			$click = $array['click'];
			$click_time = $array['click_time'];
			$service_request = $this->getService($user_id);
			$synctime = time();

            $userData = $this->getOne("users", $user_id, "ref");
			
			if ($this->adCount($advert) < $this->total($advert)) {
                $data = array(
                    'advert' => $advert, 
                    'user_id' => $user_id, 
                    'service_request' => $service_request, 
                    'impression' => $impression, 
                    'impression_time' => $impression_time, 
                    'click' => $click, 
                    'click_time' => $click_time, 
                    'ageRange' => ($userData['ageRange'] != NULL ? $userData['ageRange'] : "36-65"),
                    'gender' => ($userData['gender'] != NULL ? $userData['gender'] : "Female"),
                    'synctime' => $synctime);

					$this->insert("advert_stat", $data);

			} else {
				return false;
			}
        }
        
		function adCount($id) {
            return $this->query("SELECT COUNT(`ref`) FROM `advert_stat` WHERE `advert` = :id", array(":id"=>$id), "getCol");
        }


		function getService($id) {
            return $this->query("SELECT `id` FROM `service_request` WHERE `id` = :id ORDER BY `create_time` DESC LIMIT 1", array(":id"=>$id), "getCol");
		}
    }
    
    $running = new running;
	//echo $running->adjust(81);
    //echo $running->adjust(82);
    echo $running->adjust(81);
	echo "\n";
    echo $running->adjust(82);
	echo "\n";
    echo $running->adjust(86);
	echo "\n";
?>