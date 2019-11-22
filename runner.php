<?php	
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    // $URL = "http://127.0.0.1/skrinad/";
    // $servername = "localhost";
    // $dbusername = "root";
    // $dbpassword = "root";
    // $dbname = "linnkste_skrinad";
    $URL = "https://skrinad.me/";
    $servername = "172.31.47.34";
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
		function adjust($id) {
			$data = $this->listOne($id);
			$avail_date = explode("_", $data['avail_date']);
			$today = date("D");

			if (array_search($today, $avail_date) !== false ) {
				$total = $this->total($data['ref']);
				$used_imp = $data['used_imp'];
				$total = $this->lists("users", false, false, "ref", "ASC", false, "count");
				if ($used_imp < $total) {
					$to = rand(10, 20);
                    
                    if ($this->dailCap($data['ref']) <= $data['daily_cap']) {
						for ($i = 0; $i < $to; $i++) {

							$randomUser = rand(1, $total);

							$click = rand(0, 1);

							if ($click == 1) {
								$this->spin($randomUser, $data['ref'], $data['url']);
							}
							$array['advert'] = $data['ref'];
							$array['user_id'] = $randomUser;
							$array['impression'] = 1;
							$array['impression_time'] = time()-(60*30);
							$array['click'] = $click;
							$array['click_time'] = time();
				
							//post to us
							$this->postUpdate($array, false, false);
						}
					}
				}
			}
        }
        
		function dailCap($advert) {
			$startTime = mktime(0, 0, 0, date("n"), date('j')-1, date("Y"));
			$endTime = mktime(0, 0, 0, date("n"), date('j'), date("Y"));
            
            return $this->query("SELECT COUNT(`ref`) FROM `advert_stat` WHERE `advert` = $advert AND `synctime` BETWEEN ".$startTime." AND ".$endTime, false, "getCol");
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
    $running->adjust(81);
    $running->adjust(82);
    $running->adjust(83);
    $running->adjust(84);
?>