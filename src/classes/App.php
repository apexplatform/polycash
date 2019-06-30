<?php
class App {
	public $dbh;
	
	public function __construct($skip_select_db) {
		$conn = new PDO("mysql:host=".AppSettings::getParam('mysql_server').";charset=utf8", AppSettings::getParam('mysql_user'), AppSettings::getParam('mysql_password')) or die("Error, failed to connect to the database.");
		$this->dbh = $conn;
		
		if (!$skip_select_db) {
			$this->select_db(AppSettings::getParam('mysql_database'));
		}
	}
	
	public function select_db($db_name) {
		$this->dbh->query("USE ".$db_name.";") or die("Error accessing the '".$db_name."' database, please visit <a href=\"/install.php?key=\">install.php</a>.");
		$this->dbh->query("SET sql_mode='';");
		$this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	
	public function quote_escape($string) {
		return $this->dbh->quote($string);
	}
	
	public function last_insert_id() {
		return $this->dbh->lastInsertId();
	}
	
	public function run_query($query, $params=[]) {
		$statement = $this->dbh->prepare($query);
		
		if ($statement) {
			$statement->execute($params);
			
			return $statement;
		}
		else {
			throw new Exception("Failed to prepare a query");
		}
	}
	
	public function run_limited_query($query, $params) {
		$this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		$result = $this->run_query($query, $params);
		$this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		return $result;
	}
	
	public function log_then_die($message) {
		$this->log_message($message);
		throw new Exception($message);
	}
	
	public function log_message($message) {
		$this->run_query("INSERT INTO log_messages SET message=:message;", ['message' => $message]);
	}
	
	public function utf8_clean($str) {
		return iconv('UTF-8', 'UTF-8//IGNORE', $str);
	}

	public function min_excluding_false($some_array) {
		$min_value = false;
		for ($i=0; $i<count($some_array); $i++) {
			if ((string)$some_array[$i] !== "") {
				if ($min_value === false) $min_value = $some_array[$i];
				else $min_value = min($min_value, $some_array[$i]);
			}
		}
		return $min_value;
	}
	
	public function make_alphanumeric($string, $extrachars) {
		$allowed_chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ".$extrachars;
		$new_string = "";
		
		for ($i=0; $i<strlen($string); $i++) {
			if (is_numeric(strpos($allowed_chars, $string[$i])))
				$new_string .= $string[$i];
		}
		return $new_string;
	}

	public function random_string($length) {
		$characters = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
		$bits_per_char = ceil(log(strlen($characters), 2));
		$hex_chars_per_char = ceil($bits_per_char/4);
		$hex_chars_needed = $length*$hex_chars_per_char;
		$rand_data = bin2hex(openssl_random_pseudo_bytes(ceil($hex_chars_needed/2), $crypto_strong));
		if(!$crypto_strong) $this->log_then_die("An insecure random string of length ".$length." was generated.");
		
		$string = "";
		for ($i=0; $i<$length; $i++) {
			$hex_chars = substr($rand_data, $i*$hex_chars_per_char, $hex_chars_per_char);
			$rand_num = hexdec($hex_chars);
			$rand_index = $rand_num%strlen($characters);
			$string .= $characters[$rand_index];
		}
		return $string;
	}
	
	public function random_hex_string($length) {
		$characters = "0123456789abcdef";
		$bits_per_char = ceil(log(strlen($characters), 2));
		$hex_chars_per_char = ceil($bits_per_char/4);
		$hex_chars_needed = $length*$hex_chars_per_char;
		$rand_data = bin2hex(openssl_random_pseudo_bytes(ceil($hex_chars_needed/2), $crypto_strong));
		if(!$crypto_strong) $this->log_then_die("An insecure random string of length ".$length." was generated.");
		
		$string = "";
		for ($i=0; $i<$length; $i++) {
			$hex_chars = substr($rand_data, $i*$hex_chars_per_char, $hex_chars_per_char);
			$rand_num = hexdec($hex_chars);
			$rand_index = $rand_num%strlen($characters);
			$string .= $characters[$rand_index];
		}
		return $string;
	}
	
	public function random_number($length) {
		$characters = "0123456789";
		$bits_per_char = ceil(log(strlen($characters), 2));
		$hex_chars_per_char = ceil($bits_per_char/4);
		$hex_chars_needed = $length*$hex_chars_per_char;
		$rand_data = bin2hex(openssl_random_pseudo_bytes(ceil($hex_chars_needed/2), $crypto_strong));
		if(!$crypto_strong) $this->log_then_die("An insecure random string of length ".$length." was generated.");
		
		$string = "";
		for ($i=0; $i<$length; $i++) {
			$hex_chars = substr($rand_data, $i*$hex_chars_per_char, $hex_chars_per_char);
			$rand_num = hexdec($hex_chars);
			$rand_index = $rand_num%strlen($characters);
			$string .= $characters[$rand_index];
		}
		return $string;
	}
	
	public function normalize_username($username) {
		return $this->make_alphanumeric(strip_tags($username), "$-()/!.,:;#@");
	}
	
	public function normalize_password($password, $salt) {
		return hash("sha256", $salt.$password);
	}
	
	public function strong_strip_tags($string) {
		return htmlspecialchars(strip_tags($string));
	}
	
	public function recaptcha_check_answer($recaptcha_privatekey, $ip_address, $g_recaptcha_response) {
		$response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$recaptcha_privatekey."&response=".$g_recaptcha_response."&remoteip=".$ip_address), true);
		if ($response['success'] == false) return false;
		else return true;
	}
	
	public function fetch_game_by_id($game_id) {
		return $this->run_query("SELECT * FROM games WHERE game_id=:game_id;", ['game_id'=>$game_id])->fetch();
	}
	
	public function fetch_game_by_identifier($url_identifier) {
		return $this->run_query("SELECT * FROM games WHERE url_identifier=:url_identifier;", ['url_identifier'=>$url_identifier])->fetch();
	}
	
	public function fetch_transaction_by_id($transaction_id) {
		return $this->run_query("SELECT * FROM transactions WHERE transaction_id=:transaction_id;", ['transaction_id'=>$transaction_id])->fetch();
	}
	
	public function update_schema() {
		$migrations_path = AppSettings::srcPath()."/sql";
		
		$keep_looping = true;
		
		try {
			$migration_id = ((int)$this->get_site_constant("last_migration_id"))+1;
		}
		catch (Exception $e) {
			$keep_looping = false;
		}
		
		if ($keep_looping) {
			while ($keep_looping) {
				$fname = $migrations_path."/".$migration_id.".sql";
				if (is_file($fname)) {
					$cmd = $this->mysql_binary_location()." -u ".AppSettings::getParam('mysql_user')." -h ".AppSettings::getParam('mysql_server');
					if (AppSettings::getParam('mysql_password')) $cmd .= " -p'".AppSettings::getParam('mysql_password')."'";
					$cmd .= " ".AppSettings::getParam('mysql_database')." < ".$fname;
					exec($cmd);
					$migration_id++;
				}
				else {
					$keep_looping = false;
					$migration_id--;
				}
			}
			$this->set_site_constant("last_migration_id", $migration_id);
		}
	}
	
	public function safe_merge_argv_to_request(&$argv, &$allowed_params) {
		if ($argv && AppSettings::runningFromCommandline()) {
			$arg_i = 0;
			foreach ($argv as $arg) {
				if ($arg_i > 0) {
					$arg_parts = explode("=", $arg);
					if(count($arg_parts) == 2 && in_array($arg_parts[0], $allowed_params)) {
						$_REQUEST[$arg_parts[0]] = $arg_parts[1];
					}
				}
				$arg_i++;
			}
		}
	}
	
	public function mysql_binary_location() {
		if (!empty(AppSettings::getParam('mysql_binary_location'))) return AppSettings::getParam('mysql_binary_location');
		else {
			$var = $this->run_query("SHOW VARIABLES LIKE 'basedir';")->fetch();
			$var_val = str_replace("\\", "/", $var['Value']);
			if (!in_array($var_val[strlen($var_val)-1], ['/', '\\'])) $var_val .= "/";
			if (PHP_OS == "WINNT") return $var_val."bin/mysql.exe";
			else return $var_val."bin/mysql";
		}
	}
	
	public function php_binary_location() {
		if (!empty(AppSettings::getParam('php_binary_location'))) return AppSettings::getParam('php_binary_location');
		else if (PHP_OS == "WINNT") return str_replace("\\", "/", dirname(ini_get('extension_dir')))."/php.exe";
		else return PHP_BINDIR ."/php";
	}
	
	public function start_regular_background_processes() {
		$html = "";
		$process_count = 0;
		
		$pipe_config = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];
		$pipes = [];
		
		$last_script_run_time = (int) $this->get_site_constant("last_script_run_time");
		
		$script_path_name = AppSettings::srcPath();
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_blocks.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) $process_count++;
		else $html .= "Failed to start a process for loading blocks.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_games.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) $process_count++;
		else $html .= "Failed to start a process for loading blocks.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/minutely_main.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$main_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($main_process)) $process_count++;
		else $html .= "Failed to start the main process.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/minutely_check_payments.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$payments_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($payments_process)) $process_count++;
		else $html .= "Failed to start a process for processing payments.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/fetch_currency_prices.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$currency_prices_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($currency_prices_process)) $process_count++;
		else $html .= "Failed to start a process for updating currency prices.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_cached_urls.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$cached_url_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($cached_url_process)) $process_count++;
		else $html .= "Failed to start a process for loading cached urls.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/ensure_user_addresses.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$ensure_addresses_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($ensure_addresses_process)) $process_count++;
		else $html .= "Failed to start a process for ensuring user addresses.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/set_cached_game_definition_hashes.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$ensure_addresses_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($ensure_addresses_process)) $process_count++;
		else $html .= "Failed to start a process for caching game definitions.<br/>\n";
		
		$html .= "Started ".$process_count." background processes.<br/>\n";
		return $html;
	}
	
	public function generate_games($default_blockchain_id) {
		$game_types = $this->run_query("SELECT * FROM game_types ORDER BY game_type_id ASC;");
		while ($game_type = $game_types->fetch(PDO::FETCH_ASSOC)) {
			$this->generate_games_by_type($game_type, $default_blockchain_id);
		}
	}
	
	public function generate_games_by_type($game_type, $default_blockchain_id) {
		$num_running_games = $this->run_query("SELECT * FROM games WHERE game_type_id=:game_type_id AND game_status IN('editable','published','running');", ['game_type_id'=>$game_type['game_type_id']])->rowCount();
		$needed_games = $game_type['target_open_games'] - $num_running_games;
		
		for ($i=0; $i<$needed_games; $i++) {
			$new_game = $this->generate_game_by_type($game_type, $default_blockchain_id);
		}
	}
	
	public function generate_game_by_type($game_type, $default_blockchain_id) {
		$blockchain = new Blockchain($this, $default_blockchain_id);
		
		$skip_game_type_vars = ['name','url_identifier','target_open_games','default_game_winning_inflation','default_logo_image_id','identifier_case_sensitive'];
		
		$series_index = (int)($this->run_query("SELECT MAX(game_series_index) FROM games WHERE game_type_id=:game_type_id;", ['game_type_id'=>$game_type['game_type_id']])->fetch()['MAX(game_series_index)']+1);
		
		$game_name = $game_type['name'];
		if ($game_type['event_rule'] == "entity_type_option_group") $game_name .= $series_index;
		
		$new_game_params = [
			'game_series_index' => $series_index,
			'name' => $game_name,
			'url_identifier' => $this->game_url_identifier($game_name),
			'game_winning_inflation' => $game_type['default_game_winning_inflation'],
			'logo_image_id' => $game_type['default_logo_image_id']
		];
		
		foreach ($game_type AS $var => $val) {
			if (!in_array($var, $skip_game_type_vars)) {
				if (!empty($val)) {
					$new_game_params[$var] = $val;
				}
			}
		}
		
		return Game::create_game($blockchain, $new_game_params);
	}
	
	public function get_redirect_url($url) {
		$url = strip_tags($url);
		
		$redirect_url = $this->run_query("SELECT * FROM redirect_urls WHERE url=:url;", ['url'=>$url])->fetch();
		
		if (!$redirect_url) {
			$this->run_query("INSERT INTO redirect_urls SET redirect_key=:redirect_key, url=:url, time_created=:time_created;", [
				'redirect_key' => $this->random_string(24),
				'url' => $url,
				'time_created' => time()
			]);
			$redirect_url_id = $this->last_insert_id();
			
			$redirect_url = $this->run_query("SELECT * FROM redirect_urls WHERE redirect_url_id=:redirect_url_id;", ['redirect_url_id'=>$redirect_url_id])->fetch();
		}
		return $redirect_url;
	}

	public function get_redirect_by_key($redirect_key) {
		return $this->run_query("SELECT * FROM redirect_urls WHERE redirect_key=:redirect_key;", ['redirect_key'=>$redirect_key])->fetch();
	}
	
	public function mail_async($email, $from_name, $from, $subject, $message, $bcc, $cc, $delivery_key) {
		if (empty($delivery_key)) $delivery_key = $this->random_string(16);
		
		$this->run_query("INSERT INTO async_email_deliveries SET to_email=:to_email, from_name=:from_name, from_email=:from_email, subject=:subject, message=:message, bcc=:bcc, cc=:cc, delivery_key=:delivery_key, time_created=:time_created;", [
			'to_email' => $email,
			'from_name' => $from_name,
			'from_email' => $from,
			'subject' => $subject,
			'message' => $message,
			'bcc' => $bcc,
			'cc' => $cc,
			'delivery_key' => $delivery_key,
			'time_created' => time()
		]);
		$delivery_id = $this->last_insert_id();
		
		$command = $this->php_binary_location()." ".AppSettings::srcPath()."/scripts/async_email_deliver.php delivery_id=".$delivery_id." > /dev/null 2>/dev/null &";
		exec($command);
		
		/*$curl_url = AppSettings::getParam('base_url')."/scripts/async_email_deliver.php?delivery_id=".$delivery_id;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $curl_url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);*/

		return $delivery_id;
	}
	
	public function get_site_constant($constant_name) {
		$constant = $this->run_query("SELECT * FROM site_constants WHERE constant_name=:constant_name;", ['constant_name'=>$constant_name])->fetch();
		if ($constant) return $constant['constant_value'];
		else return "";
	}

	public function set_site_constant($constant_name, $constant_value) {
		try {
			$constant = $this->run_query("SELECT * FROM site_constants WHERE constant_name=:constant_name;", ['constant_name'=>$constant_name])->fetch();
			$run_query = true;
		}
		catch (Exception $e) {
			// site_constants table does not exist yet.
			$run_query = false;
		}
		
		if ($run_query) {
			if ($constant) {
				$this->run_query("UPDATE site_constants SET constant_value=:constant_value WHERE constant_id=:constant_id;", [
					'constant_value' => $constant_value,
					'constant_id' => $constant['constant_id']
				]);
			}
			else {
				$this->run_query("INSERT INTO site_constants SET constant_name=:constant_name, constant_value=:constant_value;", [
					'constant_name'=>$constant_name,
					'constant_value' => $constant_value
				]);
			}
		}
	}
	
	public function to_significant_digits($number, $significant_digits) {
		if ($number === 0) return 0;
		$number_digits = floor(log10($number));
		$returnval = (pow(10, $number_digits - $significant_digits + 1)) * floor($number/(pow(10, $number_digits - $significant_digits + 1)));
		return $returnval;
	}

	public function format_bignum($number) {
		if ($number >= 0) $sign = "";
		else $sign = "-";
		
		$number = abs($number);
		if ($number > 1) $number = $this->to_significant_digits($number, 5);
		
		if ($number >= pow(10, 9)) {
			return $sign.($number/pow(10, 9))."B";
		}
		else if ($number >= pow(10, 6)) {
			return $sign.($number/pow(10, 6))."M";
		}
		else if ($number > pow(10, 5)) {
			return $sign.($number/pow(10, 3))."k";
		}
		else return $sign.rtrim(rtrim(number_format(sprintf('%.8F', $number), 8), '0'), ".");
	}
	
	public function round_to($number, $min_decimals, $target_sigfigs, $format_string) {
		$decimals = $target_sigfigs-1-floor(log10($number));
		if ($min_decimals !== false) $decimals = max($min_decimals, $decimals);
		if ($format_string) return @number_format($number, $decimals);
		else return round($number, $decimals);
	}
	
	public function format_percentage($number) {
		if ($number >= 50) $min_decimals = 0;
		else $min_decimals = 2;

		$max_decimals = 20;
		
		$decimal_places = $min_decimals;
		$keep_looping = true;
		do {
			$pow10 = pow(10, $decimal_places);
			if ((string)($number*$pow10) == (string)(round($number*$pow10))) $keep_looping = false;
			else $decimal_places++;
		}
		while ($keep_looping && $decimal_places < $max_decimals);
		
		return number_format($number, $decimal_places);
	}
	
	public function to_ranktext($rank) {
		return $rank.date("S", strtotime("1/".$rank."/".date("Y")));
	}
	
	public function cancel_transaction($transaction_id, $affected_input_ids, $created_input_ids) {
		$this->run_query("DELETE FROM transactions WHERE transaction_id=:transaction_id;", ['transaction_id'=>$transaction_id]);
		
		if (count($affected_input_ids) > 0) {
			$this->run_query("UPDATE transaction_ios SET spend_status='unspent', spend_transaction_id=NULL, spend_block_id=NULL WHERE io_id IN (".implode(",", array_map('intval', $affected_input_ids)).");");
		}
		
		if ($created_input_ids && count($created_input_ids) > 0) {
			$this->run_query("DELETE FROM transaction_ios WHERE io_id IN (".implode(",", array_map('intval', $created_input_ids)).");");
		}
	}

	public function transaction_coins_in($transaction_id) {
		$coins_in = $this->run_query("SELECT SUM(amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.spend_transaction_id=:transaction_id;", ['transaction_id'=>$transaction_id])->fetch(PDO::FETCH_NUM);
		if ($coins_in[0] > 0) return $coins_in[0];
		else return 0;
	}

	public function transaction_coins_out($transaction_id) {
		$coins_out = $this->run_query("SELECT SUM(amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id;", ['transaction_id'=>$transaction_id])->fetch(PDO::FETCH_NUM);
		if ($coins_out[0] > 0) return $coins_out[0];
		else return 0;
	}

	public function output_message($status_code, $message, $dump_object=false) {
		if (empty($dump_object)) $dump_object = ["status_code"=>$status_code, "message"=>$message];
		else {
			$dump_object['status_code'] = $status_code;
			$dump_object['message'] = $message;
		}
		echo json_encode($dump_object);
	}
	
	public function try_apply_invite_key($user_id, $invite_key, &$invite_game, &$user_game) {
		$reload_page = false;
		$invitation = $this->run_query("SELECT * FROM game_invitations WHERE invitation_key=:invitation_key;", ['invitation_key'=>$invite_key])->fetch();
		
		if ($invitation) {
			if ($invitation['used'] == 0 && $invitation['used_user_id'] == "" && $invitation['used_time'] == 0) {
				$db_game = $this->fetch_game_by_id($invitation['game_id']);
				
				if ($db_game) {
					$update_invitation_params = [
						'user_id' => $user_id,
						'used_time' => time(),
						'invitation_id' => $invitation['invitation_id']
					];
					$update_invitation_q = "UPDATE game_invitations SET used_user_id=:user_id, used_time=:used_time, used=1";
					if (AppSettings::getParam('pageview_tracking_enabled')) {
						$update_invitation_q .= ", used_ip=:used_ip";
						$update_invitation_params['used_ip'] = $_SERVER['REMOTE_ADDR'];
					}
					$update_invitation_q .= " WHERE invitation_id=:invitation_id;";
					$this->run_query($update_invitation_q);
					
					$user = new User($this, $user_id);
					$blockchain = new Blockchain($this, $db_game['blockchain_id']);
					$invite_game = new Game($blockchain, $invitation['game_id']);
					
					$user_game = $user->ensure_user_in_game($invite_game, false);
					
					return true;
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function send_apply_invitation(&$db_user, &$invitation) {
		$invite_game = false;
		$user_game = false;
		$this->try_apply_invite_key($db_user['user_id'], $invitation['invitation_key'], $invite_game, $user_game);
		$invite_game->give_faucet_to_user($user_game);
	}
	
	public function format_seconds($seconds) {
		$seconds = intval($seconds);
		$weeks = floor($seconds/(3600*24*7));
		$days = floor($seconds/(3600*24));
		$hours = floor($seconds / 3600);
		$minutes = floor($seconds / 60);
		
		if ($weeks > 0) {
			if ($weeks == 1) $str = $weeks." week";
			else $str = $weeks." weeks";
			$days = $days - 7*$weeks;
			if ($days != 1) $str .= " and ".$days." days";
			else $str .= " and ".$days." day";
			return $str;
		}
		else if ($days > 1) {
			return $days." days";
		}
		else if ($hours > 0) {
			$str = "";
			if ($hours != 1) $str .= $hours." hours";
			else $str .= $hours." hour";
			$remainder_min = round(($seconds - (3600*$hours))/60);
			if ($remainder_min > 0 && $hours < 3) {
				$str .= " and ".$remainder_min." ";
				if ($remainder_min == '1') $str .= "minute";
				else $str .= "minutes";
			}
			return $str;
		}
		else if ($minutes > 0) {
			$remainder_sec = $seconds-$minutes*60;
			$str = "";
			if ($minutes != 1) $str .= $minutes." minutes";
			else $str .= $minutes." minute";
			if ($remainder_sec > 0 && $minutes < 10) $str .= " and ".$remainder_sec." seconds";
			return $str;
		}
		else {
			if ($seconds != 1) return $seconds." seconds";
			else return $seconds." second";
		}
	}
	
	public function game_url_identifier($game_name) {
		$url_identifier = "";
		$append_index = 0;
		$keeplooping = true;
		
		do {
			if ($append_index > 0) $append = "(".$append_index.")";
			else $append = "";
			$url_identifier = $this->normalize_uri_part($game_name.$append);
			$conflicting_game = $this->fetch_game_by_identifier($url_identifier);
			if (!$conflicting_game) $keeplooping = false;
			else $append_index++;
		}
		while ($keeplooping);
		
		return $url_identifier;
	}
	
	public function normalize_uri_part($uri_part) {
		return $this->make_alphanumeric(str_replace(" ", "-", strtolower($uri_part)), "-().");
	}
	
	public function prepend_a_or_an($word) {
		$firstletter = strtolower($word[0]);
		if (strpos('aeiou', $firstletter)) return "an ".$word;
		else return "a ".$word;
	}
	
	public function friendly_intval($val) {
		if ($val > 0) return $val;
		else return 0;
	}
	
	public function fetch_game_from_url() {
		$login_url_parts = explode("/", rtrim(ltrim($_SERVER['REQUEST_URI'], "/"), "/"));
		
		if (in_array($login_url_parts[0], ["wallet", "manage"]) && count($login_url_parts) > 1) {
			return $this->fetch_game_by_identifier($login_url_parts[1]);
		}
		else return false;
	}
	
	public function exchange_rate_between_currencies($numerator_currency_id, $denominator_currency_id, $ref_time, $ref_currency_id) {
		$price_time = time();
		
		if ($numerator_currency_id == $ref_currency_id) {
			$rate_ref_per_numerator = 1;
		}
		else {
			$rate_ref_per_numerator_record = $this->currency_price_at_time($numerator_currency_id, $ref_currency_id, $ref_time);
			$rate_ref_per_numerator = $rate_ref_per_numerator_record['price'];
			$price_time = min($price_time, $rate_ref_per_numerator_record['time_added']);
		}
		
		if ($denominator_currency_id == $ref_currency_id) {
			$rate_ref_per_denominator = 1;
		}
		else {
			$rate_ref_per_denominator_record = $this->currency_price_at_time($denominator_currency_id, $ref_currency_id, $ref_time);
			$rate_ref_per_denominator = $rate_ref_per_denominator_record['price'];
			$price_time = min($price_time, $rate_ref_per_denominator_record['time_added']);
		}
		
		return [
			'exchange_rate' => $rate_ref_per_denominator/$rate_ref_per_numerator,
			'time' => $price_time
		];
	}
	
	public function currency_price_at_time($currency_id, $ref_currency_id, $ref_time) {
		return $this->run_query("SELECT * FROM currency_prices WHERE currency_id=:currency_id AND reference_currency_id=:reference_currency_id AND time_added <= :ref_time ORDER BY time_added DESC LIMIT 1;", [
			'currency_id' => $currency_id,
			'reference_currency_id' => $ref_currency_id,
			'ref_time' => $ref_time
		])->fetch();
	}
	
	public function currency_price_after_time($currency_id, $ref_currency_id, $ref_time, $not_after_time) {
		return $this->run_query("SELECT * FROM currency_prices WHERE currency_id=:currency_id AND reference_currency_id=:reference_currency_id AND time_added >= :ref_time AND time_added<=:not_after_time ORDER BY time_added ASC LIMIT 1;", [
			'currency_id' => $currency_id,
			'reference_currency_id' => $ref_currency_id,
			'ref_time' => $ref_time,
			'not_after_time' => $not_after_time
		])->fetch();
	}
	
	public function latest_currency_price($currency_id) {
		return $this->run_query("SELECT * FROM currency_prices WHERE currency_id=:currency_id AND reference_currency_id=:reference_currency_id ORDER BY price_id DESC LIMIT 1;", [
			'currency_id' => $currency_id,
			'reference_currency_id' => $this->get_site_constant('reference_currency_id')
		])->fetch();
	}
	
	public function get_currency_by_abbreviation($currency_abbreviation) {
		return $this->run_query("SELECT * FROM currencies WHERE abbreviation=:abbreviation;", ['abbreviation'=>strtoupper($currency_abbreviation)])->fetch();
	}
	
	public function get_reference_currency() {
		return $this->fetch_currency_by_id($this->get_site_constant('reference_currency_id'));
	}
	
	public function set_reference_currency($reference_currency_id) {
		$has_ref_price = $this->run_query("SELECT * FROM currency_prices WHERE currency_id=:currency_id AND reference_currency_id=:currency_id;", ['currency_id' => $reference_currency_id])->rowCount() > 0;
		if (!$has_ref_price) {
			$app->run_query("INSERT INTO currency_prices SET currency_id=:currency_id, reference_currency_id=:currency_id, price=1, time_added=:time_added;", [
				'currency_id' => $reference_currency_id,
				'time_added' => time()
			]);
		}
	}
	
	public function update_all_currency_prices() {
		$reference_currency_id = $this->get_site_constant('reference_currency_id');
		$currency_urls = $this->run_query("SELECT * FROM currencies c JOIN oracle_urls o ON c.oracle_url_id=o.oracle_url_id WHERE c.currency_id != :currency_id GROUP BY o.oracle_url_id;", ['currency_id'=>$reference_currency_id]);
		
		while ($currency_url = $currency_urls->fetch()) {
			$api_response_raw = file_get_contents($currency_url['url']);
			echo "(".strlen($api_response_raw).") ".$currency_url['url']."<br/>\n";
			
			$currencies_by_url = $this->run_query("SELECT * FROM currencies WHERE oracle_url_id=:oracle_url_id;", ['oracle_url_id'=>$currency_url['oracle_url_id']]);
			
			while ($currency = $currencies_by_url->fetch()) {
				if ($currency_url['format_id'] == 2) {
					$api_response = json_decode($api_response_raw);
					$price = $api_response->USD->bid;
				}
				else if ($currency_url['format_id'] == 1) {
					$api_response = json_decode($api_response_raw);
					if (!empty($api_response->rates)) {
						$api_rates = (array) $api_response->rates;
						$price = 1/($api_rates[$currency['abbreviation']]);
					}
				}
				else if ($currency_url['format_id'] == 3) {
					$html_data = $this->first_snippet_between($api_response_raw, '<div id="currency-exchange-rates"', '></div>');
					$price = 1/((float)$this->first_snippet_between($html_data, 'data-btc="', '"'));
				}
				
				if ($price > 0) {
					$this->run_query("INSERT INTO currency_prices SET currency_id=:currency_id, reference_currency_id=:reference_currency_id, price=:price, time_added=:time_added;", [
						'currency_id' => $currency['currency_id'],
						'reference_currency_id' => $reference_currency_id,
						'price' => $price,
						'time_added' => time()
					]);
				}
			}
		}
	}
	
	public function update_currency_price($currency_id) {
		$currency = $this->fetch_currency_by_id($currency_id);

		if ($currency) {
			if ($currency['abbreviation'] == "BTC") {
				$reference_currency = $this->get_reference_currency();
				
				$api_url = "https://api.bitcoinaverage.com/ticker/global/all";
				$api_response_raw = file_get_contents($api_url);
				$api_response = json_decode($api_response_raw);
				
				$price = $api_response->$reference_currency['abbreviation']->bid;

				if ($price > 0) {
					$this->run_query("INSERT INTO currency_prices SET currency_id=:currency_id, reference_currency_id=:reference_currency_id, price=:price, time_added=:time_added;", [
						'currency_id' => $currency_id,
						'reference_currency_id' => $reference_currency['currency_id'],
						'price' => $price,
						'time_added' => time()
					]);
					$currency_price_id = $this->last_insert_id();

					return $this->run_query("SELECT * FROM currency_prices WHERE price_id=:price_id;", ['price_id'=>$currency_price_id])->fetch();
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function currency_conversion_rate($numerator_currency_id, $denominator_currency_id) {
		if ($numerator_currency_id == $denominator_currency_id) {
			$returnvals['conversion_rate'] = 1;
		}
		else {
			$latest_numerator_rate = $this->latest_currency_price($numerator_currency_id);
			$latest_denominator_rate = $this->latest_currency_price($denominator_currency_id);

			$returnvals['numerator_price_id'] = $latest_numerator_rate['price_id'];
			$returnvals['denominator_price_id'] = $latest_denominator_rate['price_id'];
			$returnvals['conversion_rate'] = round(pow(10,8)*$latest_denominator_rate['price']/$latest_numerator_rate['price'])/pow(10,8);
		}
		return $returnvals;
	}
	
	public function historical_currency_conversion_rate($numerator_price_id, $denominator_price_id) {
		$numerator_rate = $this->run_query("SELECT * FROM currency_prices WHERE price_id=:price_id;", ['price_id'=>$numerator_price_id])->fetch();

		$denominator_rate = $this->run_query("SELECT * FROM currency_prices WHERE price_id=:price_id;", ['price_id'=>$denominator_price_id])->fetch();
		
		return round(pow(10,8)*$denominator_rate['price']/$numerator_rate['price'])/pow(10,8);
	}
	
	public function new_currency_invoice(&$account, $pay_currency_id, $pay_amount, &$user, &$user_game, $invoice_type) {
		$address_key = $this->new_normal_address_key($account['currency_id'], $account);
		
		$new_invoice_params = [
			'current_time' => time(),
			'pay_currency_id' => $pay_currency_id,
			'expire_time' => time()+AppSettings::getParam('invoice_expiration_seconds'),
			'user_game_id' => $user_game['user_game_id'],
			'invoice_type' => $invoice_type,
			'invoice_key_string' => $this->random_string(32),
			'pay_amount' => $pay_amount
		];
		$new_invoice_q = "INSERT INTO currency_invoices SET time_created=:current_time, pay_currency_id=:pay_currency_id";
		if ($address_key) {
			$new_invoice_q .= ", address_id=:address_id";
			$new_invoice_params['address_id'] = $address_key['address_id'];
		}
		$new_invoice_q .= ", expire_time=:expire_time, user_game_id=:user_game_id, invoice_type=:invoice_type, status='unpaid', invoice_key_string=:invoice_key_string, pay_amount=:pay_amount;";
		$this->run_query($new_invoice_q, $new_invoice_params);
		$invoice_id = $this->last_insert_id();
		
		return $this->fetch_currency_invoice_by_id($invoice_id);
	}
	
	public function new_normal_address_key($currency_id, &$account) {
		do {
			$address_key = $this->new_address_key($currency_id, $account);
		}
		while ($address_key['is_separator_address'] == 1 || $address_key['is_destroy_address'] == 1);
		
		return $address_key;
	}
	
	public function new_address_key($currency_id, &$account) {
		$reject_destroy_addresses = true;
		
		$currency = $this->fetch_currency_by_id($currency_id);
		
		if ($currency['blockchain_id'] > 0) {
			$blockchain = new Blockchain($this, $currency['blockchain_id']);
			
			if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
				if (empty($blockchain->db_blockchain['rpc_username']) || empty($blockchain->db_blockchain['rpc_password'])) $save_method = "skip";
				else {
					$blockchain->load_coin_rpc();
					
					if ($blockchain->coin_rpc) {
						try {
							$address_text = $blockchain->coin_rpc->getnewaddress("", "legacy");
							$save_method = "wallet.dat";
						}
						catch (Exception $e) {
							$save_method = "skip";
						}
					}
					else $save_method = "skip";
				}
			}
			else {
				$address_text = $this->random_string(34);
				$save_method = "fake";
			}
			
			if ($save_method == "skip") return false;
			else {
				$db_address = $blockchain->create_or_fetch_address($address_text, true, false, false, true, false);
				
				if ($reject_destroy_addresses && $db_address['is_destroy_address'] == 1) return $this->new_address_key($currency_id, $account, $reject_destroy_addresses);
				else {
					if ($account) {
						$this->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
							'user_id' => $account['user_id'],
							'address_id' => $db_address['address_id']
						]);
						$this->run_query("UPDATE transaction_ios SET user_id=:user_id WHERE address_id=:address_id;", [
							'user_id' => $account['user_id'],
							'address_id' => $db_address['address_id']
						]);
					}
					
					$address_key = $this->run_query("SELECT * FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id WHERE ak.address_id=:address_id;", ['address_id'=>$db_address['address_id']])->fetch();
					
					if ($address_key) {
						if ($account) {
							$this->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
								'account_id' => $account['account_id'],
								'address_key_id' => $address_key['address_key_id']
							]);
							
							$address_key['account_id'] = $account['account_id'];
						}
					}
					else {
						$new_key_params = [
							'currency_id' => $blockchain->currency_id(),
							'address_id' => $db_address['address_id'],
							'save_method' => $save_method,
							'pub_key' => $address_text
						];
						$new_key_q = "INSERT INTO address_keys SET currency_id=:currency_id, address_id=:address_id, save_method=:save_method, pub_key=:pub_key";
						if (!empty($keySet['privWIF'])) {
							$new_key_q .= ", priv_key=:priv_key";
							$new_key_params['priv_key'] = $keySet['privWIF'];
						}
						if (!empty($account)) {
							$new_key_q .= ", account_id=:account_id";
							$new_key_params['account_id'] = $account['account_id'];
						}
						$new_key_q .= ";";
						$this->run_query($new_key_q, $new_key_params);
						$address_key_id = $this->last_insert_id();
						
						$address_key = $this->run_query("SELECT * FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id WHERE ak.address_key_id=:address_key_id;", [
							'address_key_id' => $address_key_id
						])->fetch();
					}
					
					return $address_key;
				}
			}
		}
		else return false;
	}
	
	public function decimal_to_float($number) {
		if (strpos($number, ".") === false) return $number;
		else return rtrim(rtrim($number, '0'), '.');
	}
	
	public function display_games($category_id, $game_id, $user=false) {
		echo '<div class="paragraph">';
		$display_games_params = [];
		$display_games_q = "SELECT g.*, c.short_name AS currency_short_name FROM games g LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.featured=1 AND (g.game_status='published' OR g.game_status='running')";
		if (!empty($category_id)) {
			$display_games_q .= " AND g.category_id=:category_id";
			$display_games_params['category_id'] = $category_id;
		}
		if (!empty($game_id)) {
			$display_games_q .= " AND g.game_id=:game_id";
			$display_games_params['game_id'] = $game_id;
		}
		$display_games_q .= " ORDER BY g.featured_score DESC, g.game_id DESC;";
		$display_games = $this->run_query($display_games_q, $display_games_params);
		
		if ($display_games->rowCount() > 0) {
			$cell_width = 12;
			
			$counter = 0;
			echo '<div class="row">';
			
			while ($db_game = $display_games->fetch()) {
				$blockchain = new Blockchain($this, $db_game['blockchain_id']);
				$featured_game = new Game($blockchain, $db_game['game_id']);
				$last_block_id = $blockchain->last_block_id();
				$mining_block_id = $last_block_id+1;
				$db_last_block = $blockchain->fetch_block_by_id($last_block_id);
				$current_round_id = $featured_game->block_to_round($mining_block_id);
				
				$filter_arr = false;
				$event_ids = "";
				list($new_event_js, $new_event_html) = $featured_game->new_event_js($counter, $user, $filter_arr, $event_ids, true);
				?>
				<script type="text/javascript">
				games.push(new Game(<?php
					echo $db_game['game_id'];
					echo ', '.$featured_game->last_block_id();
					echo ', false';
					echo ', ""';
					echo ', "'.$db_game['payout_weight'].'"';
					echo ', '.$db_game['round_length'];
					echo ', 0';
					echo ', "'.$db_game['url_identifier'].'"';
					echo ', "'.$db_game['coin_name'].'"';
					echo ', "'.$db_game['coin_name_plural'].'"';
					echo ', "'.$blockchain->db_blockchain['coin_name'].'"';
					echo ', "'.$blockchain->db_blockchain['coin_name_plural'].'"';
					echo ', "home", "'.$event_ids.'"';
					echo ', "'.$featured_game->logo_image_url().'"';
					echo ', "'.$featured_game->vote_effectiveness_function().'"';
					echo ', "'.$featured_game->effectiveness_param1().'"';
					echo ', "'.$featured_game->blockchain->db_blockchain['seconds_per_block'].'"';
					echo ', "'.$featured_game->db_game['inflation'].'"';
					echo ', "'.$featured_game->db_game['exponential_inflation_rate'].'"';
					echo ', "'.$db_last_block['time_mined'].'"';
					echo ', "'.$featured_game->db_game['decimal_places'].'"';
					echo ', "'.$featured_game->blockchain->db_blockchain['decimal_places'].'"';
					echo ', "'.$db_game['view_mode'].'"';
					echo ', 0';
					echo ', false';
					echo ', "'.$featured_game->db_game['default_betting_mode'].'"';
					echo ', false';
				?>));
				</script>
				<?php
				echo '<div class="col-md-'.$cell_width.'">';
				echo '<center><h1 style="display: inline-block">'.$featured_game->db_game['name'].'</h1>';
				if ($featured_game->db_game['short_description'] != "") echo "<p>".$featured_game->db_game['short_description']."</p>";
				
				$ref_user_game = false;
				$faucet_io = $featured_game->check_faucet($ref_user_game);
				
				echo '<p><a href="/'.$featured_game->db_game['url_identifier'].'/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; ';
				if ($faucet_io) echo 'Join now & receive '.$this->format_bignum($faucet_io['colored_amount_sum']/pow(10,$featured_game->db_game['decimal_places'])).' '.$featured_game->db_game['coin_name_plural'];
				else echo 'Play Now';
				echo "</a>";
				echo ' <a href="/explorer/games/'.$featured_game->db_game['url_identifier'].'/events/" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> &nbsp; '.ucwords($featured_game->db_game['event_type_name']).' Results</a>';
				echo "</p>\n";
				
				if ($featured_game->db_game['module'] == "CoinBattles") {
					$featured_game->load_current_events();
					$event = $featured_game->current_events[0];
					list($html, $js) = $featured_game->module->currency_chart($featured_game, $event->db_event['event_starting_block'], false);
					echo '<div style="margin-bottom: 15px;" id="game'.$counter.'_chart_html">'.$html."</div>\n";
					echo '<div id="game'.$counter.'_chart_js"><script type="text/javascript">'.$js.'</script></div>'."\n";
				}
				
				echo '<div id="game'.$counter.'_events" class="game_events game_events_short">'.$new_event_html.'</div>'."\n";
				echo '<script type="text/javascript" id="game'.$counter.'_new_event_js">'."\n";
				echo $new_event_js;
				echo '</script>';
				
				echo "<br/>\n";
				echo '<a href="/'.$featured_game->db_game['url_identifier'].'/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; ';
				if ($faucet_io) echo 'Join now & receive '.$this->format_bignum($faucet_io['colored_amount_sum']/pow(10,$featured_game->db_game['decimal_places'])).' '.$featured_game->db_game['coin_name_plural'];
				else echo 'Play Now';
				echo '</a>';
				echo ' <a href="/explorer/games/'.$featured_game->db_game['url_identifier'].'/events/" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> &nbsp; '.ucwords($featured_game->db_game['event_type_name']).' Results</a>';
				echo "</center><br/>\n";
				
				if ($counter%(12/$cell_width) == 1) echo '</div><div class="row">';
				$counter++;
				echo "</div>\n";
			}
			echo "</div>\n";
		}
		else {
			echo "No public games are running right now.<br/>\n";
		}
		echo "</div>\n";
	}
	
	public function refresh_utxo_user_ids($only_unspent_utxos) {
		$update_user_id_q = "UPDATE transaction_ios io JOIN addresses a ON io.address_id=a.address_id SET io.user_id=a.user_id";
		if ($only_unspent_utxos) $update_user_id_q .= " WHERE io.spend_status='unspent'";
		$update_user_id_q .= ";";
		$this->run_query($update_user_id_q);
	}
	
	public function fetch_image_by_id($image_id) {
		return $this->run_query("SELECT * FROM images WHERE image_id=:image_id;", ['image_id'=>$image_id])->fetch();
	}
	
	public function image_url(&$db_image) {
		$url = '/images/custom/'.$db_image['image_id'];
		if ($db_image['access_key'] != "") $url .= '_'.$db_image['access_key'];
		$url .= '.'.$db_image['extension'];
		return $url;
	}
	
	public function image_identifier(&$raw_image) {
		return hash("sha256", $raw_image);
	}
	
	public function add_image(&$raw_image, $extension, $access_key, &$error_message) {
		$db_image = false;
		$image_identifier = $this->image_identifier($raw_image);
		$existing_r = $this->run_query("SELECT * FROM images WHERE image_identifier=:image_identifier;", ['image_identifier'=>$image_identifier]);
		
		if ($existing_r->rowCount() > 0) {
			$error_message = "This image already exists.";
			$db_image = $existing_r->fetch();
		}
		else {
			if (in_array($extension, ['jpg','jpeg','png','gif','tif','tiff','bmp','webp'])) {
				$new_image_params = [
					'image_identifier' => $image_identifier,
					'extension' => $extension
				];
				$new_image_q = "INSERT INTO images SET image_identifier=:image_identifier, extension=:extension";
				if (!empty($access_key)) {
					$new_image_q .= ", access_key=:access_key";
					$new_image_params['access_key'] = $access_key;
				}
				$new_image_q .= ";";
				$this->run_query($new_image_q, $new_image_params);
				$image_id = $this->last_insert_id();
				
				$db_image = $this->fetch_image_by_id($image_id);
				$image_fname = AppSettings::publicPath().$this->image_url($db_image);
				
				if ($fh = fopen($image_fname, 'w')) {
					fwrite($fh, $raw_image);
					fclose($fh);
					
					$image_info = getimagesize($image_fname);
					
					if (!empty($image_info[0]) && !empty($image_info[1])) {
						$this->run_query("UPDATE images SET width=:width, height=:height WHERE image_id=:image_id;", [
							'width' => $image_info[0],
							'height' => $image_info[1],
							'image_id' => $db_image['image_id']
						]);
						$db_image['height'] = $image_info[0];
						$db_image['width'] = $image_info[1];
					}
				}
				else {
					$db_image = false;
					$this->run_query("DELETE FROM images WHERE image_id=:image_id;", ['image_id'=>$image_id]);
					$error_message = 'Failed to write '.$image_fname;
				}
			}
			else $error_message = "That image file type is not supported.";
		}
		
		return $db_image;
	}
	
	public function delete_unconfirmable_transactions() {
		$start_time = microtime(true);
		$unconfirmed_tx_r = $this->run_query("SELECT * FROM transactions t JOIN blockchains b ON t.blockchain_id=b.blockchain_id WHERE b.online=1 AND t.block_id IS NULL AND t.transaction_desc='transaction' ORDER BY t.blockchain_id ASC;");
		$game_id = false;
		$delete_count = 0;
		
		while ($unconfirmed_tx = $unconfirmed_tx_r->fetch()) {
			$blockchain = new Blockchain($this, $unconfirmed_tx['blockchain_id']);
			
			$coins_in = $this->transaction_coins_in($unconfirmed_tx['transaction_id']);
			$coins_out = $this->transaction_coins_out($unconfirmed_tx['transaction_id']);
			
			if ($coins_in == 0 || $coins_out > $coins_in) {
				$success = $blockchain->delete_transaction($unconfirmed_tx);

				if ($success) $delete_count++;
			}
		}
		return "Took ".(microtime(true)-$start_time)." sec to delete $delete_count unconfirmable transactions.";
	}
	
	public function game_info_table(&$db_game) {
		$html = '<div class="game_info_table">';
		
		$blocks_per_hour = 3600/$db_game['seconds_per_block'];
		$round_reward = ($db_game['pos_reward']+$db_game['pow_reward']*$db_game['round_length'])/pow(10,$db_game['decimal_places']);
		$seconds_per_round = $db_game['seconds_per_block']*$db_game['round_length'];
		
		$invite_currency = false;
		if ($db_game['invite_currency'] > 0) {
			$invite_currency = $this->fetch_currency_by_id($db_game['invite_currency']);
		}
		
		if ($db_game['game_id'] > 0) {
			$html .= '<div class="row"><div class="col-sm-5">Game title:</div><div class="col-sm-7">'.$db_game['name']."</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Blockchain:</div><div class="col-sm-7">';
		if ($db_game['blockchain_id'] > 0) {
			$db_blockchain = $this->fetch_blockchain_by_id($db_game['blockchain_id']);
			$html .= '<a href="/explorer/blockchains/'.$db_blockchain['url_identifier'].'/blocks/">'.$db_blockchain['blockchain_name'].'</a>';
		}
		else $html .= "None";
		$html .= "</div></div>\n";
		
		if ($db_game['game_id'] > 0) {
			$blockchain = new Blockchain($this, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$html .= '<div class="row"><div class="col-sm-5">Game definition:</div><div class="col-sm-7"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/definition/?definition_mode=actual">'.$this->shorten_game_def_hash($game->db_game['cached_definition_hash']).'</a></div></div>';
		}
		
		if ($db_game['final_round'] > 0) {
			$html .= '<div class="row"><div class="col-sm-5">Length of game:</div><div class="col-sm-7">';
			$html .= $db_game['final_round']." rounds (".$this->format_seconds($seconds_per_round*$db_game['final_round']).")";
			$html .= "</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Starts on block:</div><div class="col-sm-7"><a href="/explorer/games/'.$db_game['url_identifier'].'/blocks/'.$db_game['game_starting_block'].'">'.$db_game['game_starting_block']."</a></div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Escrow address:</div><div class="col-sm-7">';
		if ($db_game['escrow_address'] == "") $html .= "None";
		else $html .= '<a href="/explorer/games/'.$db_game['url_identifier'].'/addresses/'.$db_game['escrow_address'].'">'.$db_game['escrow_address'].'</a>';
		$html .= "</div></div>\n";
		
		$genesis_amount_disp = $this->format_bignum($db_game['genesis_amount']/pow(10,$db_game['decimal_places']));
		$html .= '<div class="row"><div class="col-sm-5">Genesis transaction:</div><div class="col-sm-7">';
		$html .= '<a href="/explorer/games/'.$db_game['url_identifier'].'/transactions/'.$db_game['genesis_tx_hash'].'">';
		$html .= $genesis_amount_disp.' ';
		if ($genesis_amount_disp == "1") $html .= $db_game['coin_name'];
		else $html .= $db_game['coin_name_plural'];
		$html .= '</a>';
		$html .= "</div></div>\n";
		
		if ($db_game['game_id'] > 0) {
			$last_block_id = $game->blockchain->last_block_id();
			$current_round = $game->block_to_round($last_block_id+1);
			$coins_per_vote = $this->coins_per_vote($game->db_game);
			
			$game_pending_bets = $game->pending_bets(true);
			list($vote_supply, $vote_supply_value) = $game->vote_supply($last_block_id, $current_round, $coins_per_vote, true);
			$coins_in_existence = $game->coins_in_existence(false, true);
			
			$circulation_amount_disp = $this->format_bignum($coins_in_existence/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">'.ucwords($game->db_game['coin_name_plural']).' in circulation:</div><div class="col-sm-7">';
			$html .= $circulation_amount_disp.' ';
			if ($circulation_amount_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			$pending_bets_disp = $this->format_bignum($game_pending_bets/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">Pending bets:</div><div class="col-sm-7">';
			$html .= $pending_bets_disp.' ';
			if ($pending_bets_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			$supply_disp = $this->format_bignum($vote_supply_value/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">Unrealized '.$game->db_game['coin_name_plural'].':</div><div class="col-sm-7">';
			$html .= $supply_disp.' ';
			if ($supply_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			$unrealized_supply_disp = $this->format_bignum(($coins_in_existence+$vote_supply_value+$game_pending_bets)/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">Total supply:</div><div class="col-sm-7">';
			$html .= $unrealized_supply_disp.' ';
			if ($unrealized_supply_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			if (!in_array($db_game['buyin_policy'], ["none","for_sale",""])) {
				$escrow_amount_disp = $this->format_bignum($game->escrow_value($last_block_id)/pow(10,$db_game['decimal_places']));
				$html .= '<div class="row"><div class="col-sm-5">'.ucwords($game->blockchain->db_blockchain['coin_name_plural']).' in escrow:</div><div class="col-sm-7">';
				$html .= $escrow_amount_disp.' ';
				if ($escrow_amount_disp == "1") $html .= $game->blockchain->db_blockchain['coin_name'];
				else $html .= $game->blockchain->db_blockchain['coin_name_plural'];
				$html .= "</div></div>\n";
				
				$exchange_rate_disp = $this->format_bignum($coins_in_existence/$game->escrow_value($last_block_id));
				$html .= '<div class="row"><div class="col-sm-5">Current exchange rate:</div><div class="col-sm-7">';
				$html .= $exchange_rate_disp.' ';
				if ($exchange_rate_disp == "1") $html .= $db_game['coin_name'];
				else $html .= $db_game['coin_name_plural'];
				$html .= ' per '.$game->blockchain->db_blockchain['coin_name'];
				$html .= "</div></div>\n";
			}
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Buy-in policy:</div><div class="col-sm-7">';
		if ($db_game['buyin_policy'] == "unlimited") $html .= "Unlimited";
		else if ($db_game['buyin_policy'] == "none") $html .= "Not allowed";
		else if ($db_game['buyin_policy'] == "per_user_cap") $html .= "Up to ".$this->format_bignum($db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per player";
		else if ($db_game['buyin_policy'] == "game_cap") $html .= $this->format_bignum($db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s available";
		else if ($db_game['buyin_policy'] == "game_and_user_cap") $html .= $this->format_bignum($db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per person until ".$this->format_bignum($db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s are reached";
		else if ($db_game['buyin_policy'] == "for_sale") $html .= "For sale by node operators";
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Inflation:</div><div class="col-sm-7">';	
		if ($db_game['inflation'] == "linear") $html .= "Linear (".$this->format_bignum($round_reward)." coins per round)";
		else if ($db_game['inflation'] == "fixed_exponential") $html .= "Fixed Exponential (".(100*$db_game['exponential_inflation_rate'])."% per round)";
		else {
			if ($db_game['exponential_inflation_rate'] == 0) {
				$html .= "None, fixed supply";
			}
			else {
				$html .= "Exponential (".(100*$db_game['exponential_inflation_rate'])."% per round)<br/>";
				$html .= $this->format_bignum($this->votes_per_coin($db_game))." ".str_replace("_", " ", $db_game['payout_weight'])."s per ".$db_game['coin_name'];
			}
		}
		$html .= "</div></div>\n";
		
		$total_inflation_pct = $this->game_final_inflation_pct($db_game);
		if ($total_inflation_pct) {
			$html .= '<div class="row"><div class="col-sm-5">Potential inflation:</div><div class="col-sm-7">'.number_format($total_inflation_pct)."%</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Blocks per round:</div><div class="col-sm-7">'.$db_game['round_length']."</div></div>\n";
		
		$average_seconds_per_block = $blockchain->seconds_per_block('average');
		$html .= '<div class="row"><div class="col-sm-5">Block time:</div><div class="col-sm-7">'.$this->format_seconds($db_game['seconds_per_block']);
		if ($blockchain && $average_seconds_per_block != $db_game['seconds_per_block']) $html .= " to ".$this->format_seconds($average_seconds_per_block);
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Time per round:</div><div class="col-sm-7">'.$this->format_seconds($db_game['round_length']*$db_game['seconds_per_block']);
		if ($blockchain && $average_seconds_per_block != $db_game['seconds_per_block']) $html .= " to ".$this->format_seconds(round($db_game['round_length']*$average_seconds_per_block/60)*60);
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Betting fees:</div><div class="col-sm-7">';
		$html .= $this->format_percentage((1-$db_game['max_payout_rate'])*100)."%";
		if ($db_game['min_payout_rate'] != $db_game['max_payout_rate']) $html .= " to ".$this->format_percentage((1-$db_game['min_payout_rate'])*100)."%";
		$html .= "</div></div>\n";
		
		if ($db_game['maturity'] != 0) {
			$html .= '<div class="row"><div class="col-sm-5">Transaction maturity:</div><div class="col-sm-7">'.$db_game['maturity']." block";
			if ($db_game['maturity'] != 1) $html .= "s";
			$html .= "</div></div>\n";
		}
		
		if ($game) {
			$escrow_r = $this->run_query("SELECT * FROM game_escrow_amounts esa JOIN currencies c ON esa.currency_id=c.currency_id WHERE esa.game_id=:game_id ORDER BY c.short_name_plural ASC;", ['game_id'=>$game->db_game['game_id']]);
			
			if ($escrow_r->rowCount() > 0) {
				$html .= '<div class="row"><div class="col-sm-5">Backed by:</div><div class="col-sm-7">';
				while ($escrow_amount = $escrow_r->fetch()) {
					$html .= $this->format_bignum($escrow_amount['amount'])." ".$escrow_amount['short_name_plural']."<br/>\n";
				}
				$html .= "</div></div>\n";
			}
		}
		
		$html .= "</div>\n";
		
		return $html;
	}
	
	public function fetch_game_definition(&$game, $definition_mode, $show_internal_params) {
		// $definition_mode is "defined" or "actual"
		$game_definition = [];
		$game_definition['blockchain_identifier'] = $game->blockchain->db_blockchain['url_identifier'];
		
		if ($game->db_game['option_group_id'] > 0) {
			$db_group = $this->fetch_group_by_id($game->db_game['option_group_id']);
			
			$game_definition['option_group'] = $db_group['description'];
		}
		else $game_definition['option_group'] = null;
		
		$verbatim_vars = $this->game_definition_verbatim_vars();
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($game->db_game[$var_name] == "0" || $game->db_game[$var_name] > 0) $var_val = (int) $game->db_game[$var_name];
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $game->db_game[$var_name];
			else if ($var_type == "bool") {
				if ($game->db_game[$var_name]) $var_val = true;
				else $var_val = false;
			}
			else $var_val = $game->db_game[$var_name];
			
			$game_definition[$var_name] = $var_val;
		}
		
		$escrow_amounts = [];
		
		if ($definition_mode == "actual") {
			$escrow_amounts_q = "SELECT * FROM game_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id=:game_id ORDER BY c.short_name_plural ASC;";
		}
		else if ($definition_mode == "defined") {
			$escrow_amounts_q = "SELECT * FROM game_defined_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id=:game_id ORDER BY c.short_name_plural ASC;";
		}
		
		$db_escrow_amounts = $this->run_query($escrow_amounts_q, ['game_id'=>$game->db_game['game_id']]);
		
		while ($escrow_amount = $db_escrow_amounts->fetch()) {
			$escrow_amounts[$escrow_amount['short_name_plural']] = (float) $escrow_amount['amount'];
		}
		
		$game_definition['escrow_amounts'] = $escrow_amounts;
		
		$event_verbatim_vars = $this->event_verbatim_vars();
		$events_obj = [];
		
		if ($definition_mode == "defined") {
			$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM game_defined_events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC;";
		}
		else {
			$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC;";
		}
		$db_events = $this->run_query($events_q, ['game_id'=>$game->db_game['game_id']]);
		
		$i=0;
		while ($db_event = $db_events->fetch()) {
			$temp_event = [];
			
			for ($j=0; $j<count($event_verbatim_vars); $j++) {
				$var_type = $event_verbatim_vars[$j][0];
				$var_name = $event_verbatim_vars[$j][1];
				
				if ($db_event['payout_rule'] != "linear" && in_array($var_name, ['track_max_price','track_min_price','track_payout_price','track_name_short'])) {}
				else {
					$var_val = $db_event[$var_name];
					
					if ($var_type == "int" && $var_val != "") $var_val = (int) $var_val;
					else if ($var_type == "float" && $var_val != "") $var_val = (float) $var_val;
					
					$temp_event[$var_name] = $var_val;
				}
			}
			
			if (!empty($db_event['sport_name'])) $temp_event['sport'] = $db_event['sport_name'];
			if (!empty($db_event['league_name'])) $temp_event['league'] = $db_event['league_name'];
			if (!empty($db_event['external_identifier']) && $show_internal_params) $temp_event['external_identifier'] = $db_event['external_identifier'];
			
			if ($definition_mode == "defined") {
				$db_options = $this->fetch_game_defined_options($game->db_game['game_id'], $db_event['event_index'], false, false);
			}
			else {
				$db_options = $this->fetch_options_by_event($db_event['event_id']);
			}
			
			$j = 0;
			while ($option = $db_options->fetch()) {
				$possible_outcome = ["title"=>$option['name']];
				if ($show_internal_params) {
					if (!empty($option['target_probability'])) $possible_outcome['target_probability'] = $option['target_probability'];
					if (!empty($option['entity_id'])) $possible_outcome['entity_id'] = $option['entity_id'];
				}
				$temp_event['possible_outcomes'][$j] = $possible_outcome;
				$j++;
			}
			$events_obj[$i] = $temp_event;
			$i++;
		}
		$game_definition['events'] = $events_obj;
		
		return $game_definition;
	}
	
	public function shorten_game_def_hash($hash) {
		return substr($hash, 0, 16);
	}
	
	public function game_def_to_hash(&$game_def_str) {
		return hash("sha256", $game_def_str);
	}
	
	public function game_def_to_text(&$game_def) {
		return json_encode($game_def, JSON_PRETTY_PRINT);
	}
	
	public function game_final_inflation_pct(&$db_game) {
		if ($db_game['final_round'] > 0) {
			if ($db_game['inflation'] == "fixed_exponential" || $db_game['inflation'] == "exponential") {
				$inflation_factor = pow(1+$db_game['exponential_inflation_rate'], $db_game['final_round']);
			}
			else {
				if ($db_game['start_condition'] == "players_joined") {
					$db_game['initial_coins'] = $db_game['genesis_amount'];
					$final_coins = $this->ideal_coins_in_existence_after_round($db_game, $db_game['final_round']);
					$inflation_factor = $final_coins/$db_game['initial_coins'];
				}
				else return false;
			}
			$inflation_pct = round(($inflation_factor-1)*100);
			return $inflation_pct;
		}
		else return false;
	}
	
	public function ideal_coins_in_existence_after_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "linear") return $db_game['genesis_amount'] + $round_id*($db_game['pos_reward'] + $db_game['round_length']*$db_game['pow_reward']);
		else if ($db_game['inflation'] == "fixed_exponential") return floor($db_game['genesis_amount'] * pow(1 + $db_game['exponential_inflation_rate'], $round_id));
	}
	
	public function coins_created_in_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "exponential") {
			$blockchain = new Blockchain($this, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			$coi_block = ($round_id-1)*$game->db_game['round_length'];
			$coins_in_existence = $game->coins_in_existence($coi_block, false);
			return $coins_in_existence*$game->db_game['exponential_inflation_rate'];
		}
		else {
			$thisround_coins = $this->ideal_coins_in_existence_after_round($db_game, $round_id);
			$prevround_coins = $this->ideal_coins_in_existence_after_round($db_game, $round_id-1);
			if (is_nan($thisround_coins) || is_nan($prevround_coins) || is_infinite($thisround_coins) || is_infinite($prevround_coins)) return 0;
			else return $thisround_coins - $prevround_coins;
		}
	}

	public function pow_reward_in_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "linear") return $db_game['pow_reward'];
		else if ($db_game['inflation'] == "fixed_exponential" || $db_game['inflation'] == "exponential") {
			$round_coins_created = $this->coins_created_in_round($db_game, $round_id);
			$round_pow_coins = floor($db_game['exponential_inflation_minershare']*$round_coins_created);
			return floor($round_pow_coins/$db_game['round_length']);
		}
		else return 0;
	}

	public function pos_reward_in_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "linear") return $db_game['pos_reward'];
		else if ($db_game['inflation'] == "fixed_exponential") {
			if ($round_id > 1 || empty($db_game['game_id'])) {
				$round_coins_created = $this->coins_created_in_round($db_game, $round_id);
			}
			else {
				$blockchain = new Blockchain($this, $db_game['blockchain_id']);
				$game = new Game($blockchain, $db_game['game_id']);
				$round_coins_created = $game->coins_in_existence(false, true)*$db_game['exponential_inflation_rate'];
			}
			return floor((1-$db_game['exponential_inflation_minershare'])*$round_coins_created);
		}
		else {
			$info = $this->run_query("SELECT SUM(:payout_weight_score), SUM(:unconfirmed_payout_weight_score) FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id=:game_id;", [
				'payout_weight_score' => $db_game['payout_weight']."_score",
				'unconfirmed_payout_weight_score' => "unconfirmed_".$db_game['payout_weight']."_score",
				'game_id' => $db_game['game_id']
			])->fetch();
			$score = $info['SUM('.$db_game['payout_weight'].'_score)']+$info['SUM(unconfirmed_'.$db_game['payout_weight'].'_score)'];
			
			return $score/$this->votes_per_coin($db_game);
		}
	}
	
	public function votes_per_coin($db_game) {
		if ($db_game['inflation'] == "exponential") {
			if ($db_game['exponential_inflation_rate'] == 0) return 0;
			else {
				if ($db_game['payout_weight'] == "coin_round") $votes_per_coin = 1/$db_game['exponential_inflation_rate'];
				else $votes_per_coin = $db_game['round_length']/$db_game['exponential_inflation_rate'];
				return $votes_per_coin;
			}
		}
		else return 0;
	}
	
	public function coins_per_vote($db_game) {
		if ($db_game['inflation'] == "exponential") {
			if ($db_game['payout_weight'] == "coin_round") $coins_per_vote = $db_game['exponential_inflation_rate'];
			else $coins_per_vote = $db_game['exponential_inflation_rate']/$db_game['round_length'];
			return $coins_per_vote;
		}
		else return 0;
	}
	
	public function fetch_currency_by_id($currency_id) {
		return $this->run_query("SELECT * FROM currencies WHERE currency_id=:currency_id;", ['currency_id'=>$currency_id])->fetch();
	}
	
	public function fetch_external_address_by_id($external_address_id) {
		return $this->run_query("SELECT * FROM external_addresses WHERE address_id=:address_id;", ['address_id'=>$external_address_id])->fetch();
	}
	
	public function fetch_currency_invoice_by_id($currency_invoice_id) {
		return $this->run_query("SELECT * FROM currency_invoices WHERE invoice_id=:invoice_id;", ['invoice_id'=>$currency_invoice_id])->fetch();
	}
	
	public function lock_process($lock_name) {
		$this->set_site_constant($lock_name, getmypid());
	}
	
	public function unlock_process($lock_name) {
		if ($this->get_site_constant($lock_name) == getmypid()) {
			$this->set_site_constant($lock_name, '0');
		}
	}
	
	public function check_process_running($lock_name) {
		if (AppSettings::getParam('process_lock_method') == "db") {
			$process_running = (int) $this->get_site_constant($lock_name);
			
			if ($process_running > 0) {
				if (PHP_OS == "WINNT") {
					$pid_cmd = 'tasklist /fi "PID eq '.$process_running.'" /NH';
					$pid_response = exec($pid_cmd);
					$pid_no_match_str = "INFO: No tasks";
					
					if (substr($pid_response, 0, strlen($pid_no_match_str)) == $pid_no_match_str) {
						$this->set_site_constant($lock_name, 0);
						return 0;
					}
					else return $process_running;
				}
				else {
					$cmd = "ps -p ".$process_running."|wc -l";
					$cmd_result_lines = (int) exec($cmd);
					if ($cmd_result_lines > 1) return $process_running;
					else {
						$this->set_site_constant($lock_name, 0);
						return 0;
					}
				}
			}
			else return 0;
		}
		else {
			$cmd = "ps aux|grep \"".realpath(dirname($_SERVER["SCRIPT_FILENAME"]))."/".basename($_SERVER["SCRIPT_FILENAME"])."\"|grep -v grep|wc -l";
			$running = (int) (trim(exec($cmd))-1);
			if ($running < 0) $running = 0;
			else if ($running > 1) $running = 1;
			$num_running = $running;
			$this->log_message("$num_running $cmd");
			
			$cmd = "ps aux|grep \"".basename($_SERVER["SCRIPT_FILENAME"])."\"|grep -v grep|wc -l";
			$running = (int) (trim(exec($cmd))-1);
			if ($running < 0) $running = 0;
			else if ($running > 1) $running = 1;
			$num_running += $running;
			$this->log_message("$num_running $cmd");
			
			if ($num_running > 0) return 1;
			else return 0;
		}
	}
	
	public function voting_character_definitions() {
		if (AppSettings::getParam('identifier_case_sensitive') == 1) {
			$voting_characters = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
			$firstchar_divisions = [26,16,8,4,2,1];
		}
		else {
			$voting_characters = "123456789abcdefghijklmnopqrstuvwxyz";
			$firstchar_divisions = [19,8,4,2,1];
		}
		$range_max = -1;
		for ($i=0; $i<count($firstchar_divisions); $i++) {
			$num_this_length = $firstchar_divisions[$i]*pow(strlen($voting_characters), $i);
			$length_to_range[$i+1] = [$range_max+1, $range_max+$num_this_length];
			$range_max = $range_max+$num_this_length;
		}
		$returnvals['voting_characters'] = $voting_characters;
		$returnvals['firstchar_divisions'] = $firstchar_divisions;
		$returnvals['length_to_range'] = $length_to_range;
		return $returnvals;
	}
	
	public function vote_identifier_to_option_index($vote_identifier) {
		$defs = $this->voting_character_definitions();
		$firstchar_divisions = $defs['firstchar_divisions'];
		$voting_characters = $defs['voting_characters'];
		$length_to_range = $defs['length_to_range'];
		
		$firstchar = $vote_identifier[0];
		$firstchar_index = strpos($voting_characters, $firstchar);
		$firstchar_offset = 0;
		
		$range = $length_to_range[strlen($vote_identifier)];
		if ($range) {
			if (strlen($vote_identifier) == 1) {
				$firstchar_range_offset = 0;
				$firstchar_char_offset = 0;
			}
			else {
				$firstchar_range_offset = $length_to_range[strlen($vote_identifier)-1][1]+1;
				$firstchar_char_offset = 0;
				for ($i=0; $i<strlen($vote_identifier)-1; $i++) {
					$firstchar_char_offset += $firstchar_divisions[$i];
				}
			}
			$firstchar_index_within_range = $firstchar_index-$firstchar_char_offset;
			$option_id = $firstchar_range_offset+$firstchar_index_within_range*pow(strlen($voting_characters), strlen($vote_identifier)-1);
			
			for ($i=1; $i<strlen($vote_identifier); $i++) {
				$char = $vote_identifier[$i];
				$char_id = strpos($voting_characters, $char);
				$option_id += $char_id*pow(strlen($voting_characters), strlen($vote_identifier)-$i-1);
			}
			return $option_id;
		}
		else return false;
	}
	
	public function option_index_to_vote_identifier($option_index) {
		$defs = $this->voting_character_definitions();
		$firstchar_divisions = $defs['firstchar_divisions'];
		$voting_characters = $defs['voting_characters'];
		$length_to_range = $defs['length_to_range'];
		$firstchar_offset = 0;
		
		foreach ($length_to_range as $length => $range) {
			if ($option_index >= $range[0] && $option_index <= $range[1]) {
				$num_firstchars = $firstchar_divisions[$length-1];
				$index_within_range = $option_index-$range[0];
				$chars = "";
				$current_num = $index_within_range;
				$modulus = strlen($voting_characters);
				for ($i=0; $i<$length-1; $i++) {
					$remainder = $current_num%$modulus;
					$current_num = floor($current_num/$modulus);
					$chars .= $voting_characters[$remainder];
				}
				$firstchar_index = $firstchar_offset+$current_num;
				$chars .= $voting_characters[(int)$firstchar_index];
			}
			$firstchar_offset += $firstchar_divisions[$length-1];
		}
		
		return strrev($chars);
	}

	public function addr_text_to_vote_identifier($addr_text) {
		$defs = $this->voting_character_definitions();
		$firstchar_divisions = $defs['firstchar_divisions'];
		$voting_characters = $defs['voting_characters'];
		$length_to_range = $defs['length_to_range'];
		
		if (AppSettings::getParam('identifier_case_sensitive') == 0) $addr_text = strtolower($addr_text);
		
		$firstchar_pos = AppSettings::getParam('identifier_first_char');
		if (empty($firstchar_pos) || $firstchar_pos != (int) $firstchar_pos) die("Error: site constant 'identifier_first_char' must be defined.\n");
		
		$firstchar = $addr_text[$firstchar_pos];
		$firstchar_index = strpos($voting_characters, $firstchar);
		$firstchar_offset = 0;
		
		foreach ($length_to_range as $length => $range) {
			$firstchar_begin_index = $firstchar_offset;
			$firstchar_end_index = $firstchar_begin_index+$firstchar_divisions[$length-1]-1;
			if ($firstchar_index >= $firstchar_begin_index && $firstchar_index <= $firstchar_end_index) {
				return substr($addr_text, $firstchar_pos, $length);
			}
			$firstchar_offset = $firstchar_end_index+1;
		}
		return substr($addr_text, $firstchar_pos, 1);
	}
	
	public function fetch_account_by_id($account_id) {
		return $this->run_query("SELECT * FROM currency_accounts WHERE account_id=:account_id;", ['account_id'=>$account_id])->fetch();
	}
	
	public function event_verbatim_vars() {
		return [
			['int', 'event_index', true],
			['int', 'next_event_index', true],
			['int', 'event_starting_block', true],
			['int', 'event_final_block', true],
			['int', 'event_payout_block', true],
			['string', 'payout_rule', true],
			['float', 'payout_rate', true],
			['float', 'track_max_price', true],
			['float', 'track_min_price', true],
			['float', 'track_payout_price', true],
			['string', 'track_name_short', true],
			['string', 'event_starting_time', true],
			['string', 'event_final_time', true],
			['string', 'event_payout_time', true],
			['string', 'event_name', false],
			['string', 'option_block_rule', false],
			['string', 'option_name', false],
			['string', 'option_name_plural', false],
			['int', 'outcome_index', true]
		];
	}
	
	public function game_definition_verbatim_vars() {
		return [
			['float', 'protocol_version', true],
			['string', 'name', false],
			['string', 'url_identifier', false],
			['string', 'module', true],
			['int', 'category_id', false],
			['int', 'decimal_places', true],
			['bool', 'finite_events', true],
			['string', 'event_type_name', true],
			['string', 'event_type_name_plural', true],
			['string', 'event_rule', true],
			['string', 'event_winning_rule', true],
			['int', 'event_entity_type_id', true],
			['int', 'events_per_round', true],
			['string', 'inflation', true],
			['float', 'exponential_inflation_rate', true],
			['int', 'pos_reward', true],
			['int', 'round_length', true],
			['int', 'maturity', true],
			['string', 'payout_weight', true],
			['int', 'final_round', true],
			['string', 'buyin_policy', true],
			['float', 'game_buyin_cap', true],
			['string', 'sellout_policy', true],
			['int', 'sellout_confirmations', true],
			['string', 'coin_name', true],
			['string', 'coin_name_plural', true],
			['string', 'coin_abbreviation', true],
			['string', 'escrow_address', true],
			['string', 'genesis_tx_hash', true],
			['int', 'genesis_amount', true],
			['int', 'game_starting_block', true],
			['string', 'game_winning_rule', true],
			['string', 'game_winning_field', true],
			['float', 'game_winning_inflation', true],
			['float', 'default_payout_rate', true],
			['string', 'default_vote_effectiveness_function', true],
			['string', 'default_effectiveness_param1', true],
			['float', 'default_max_voting_fraction', true],
			['int', 'default_option_max_width', false],
			['int', 'default_payout_block_delay', true],
			['string', 'view_mode', true]
		];
	}
	
	public function blockchain_verbatim_vars() {
		return [
			['string', 'blockchain_name'],
			['string', 'url_identifier'],
			['string', 'coin_name'],
			['string', 'coin_name_plural'],
			['int', 'seconds_per_block'],
			['int', 'decimal_places'],
			['int', 'initial_pow_reward']
		];
	}
	
	public function fetch_blockchain_definition(&$blockchain) {
		$verbatim_vars = $this->blockchain_verbatim_vars();
		$blockchain_definition = [];
		
		if (in_array($blockchain->db_blockchain['p2p_mode'], array("web_api", "none"))) {
			if ($blockchain->db_blockchain['p2p_mode'] == "none") {
				$peer = $this->get_peer_by_server_name(AppSettings::getParam('base_url'), true);
			}
			else {
				$peer = $this->fetch_peer_by_id($blockchain->db_blockchain['authoritative_peer_id']);
			}
			$blockchain_definition['peer'] = $peer['base_url'];
		}
		else $blockchain_definition['peer'] = "none";
		
		if (in_array($blockchain->db_blockchain['p2p_mode'], array("none","web_api"))) {
			$blockchain_definition['p2p_mode'] = "web";
		}
		else $blockchain_definition['p2p_mode'] = "rpc";
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($blockchain->db_blockchain[$var_name] == "0" || $blockchain->db_blockchain[$var_name] > 0) $var_val = (int) $blockchain->db_blockchain[$var_name];
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $blockchain->db_blockchain[$var_name];
			else $var_val = $blockchain->db_blockchain[$var_name];
			
			$blockchain_definition[$var_name] = $var_val;
		}
		return $blockchain_definition;
	}
	
	public function migrate_game_definitions($game, $initial_game_def_hash, $new_game_def_hash) {
		$log_message = "";
		$initial_game_def = $this->get_game_definition_by_hash($initial_game_def_hash);
		
		if ($initial_game_def) {
			$initial_game_obj = get_object_vars(json_decode($initial_game_def));
			
			$new_game_def = $this->get_game_definition_by_hash($new_game_def_hash);
			
			if ($new_game_def) {
				$new_game_obj = get_object_vars(json_decode($new_game_def));
				
				$min_starting_block = min($initial_game_obj['game_starting_block'], $new_game_obj['game_starting_block']);
				
				$verbatim_vars = $this->game_definition_verbatim_vars();
				$reset_block = false;
				$reset_event_index = false;
				
				$sports_entity_type = $this->check_set_entity_type("sports");
				$leagues_entity_type = $this->check_set_entity_type("leagues");
				$general_entity_type = $this->check_set_entity_type("general entity");
				
				// Check if any base params are different. If so, reset from game starting block
				for ($i=0; $i<count($verbatim_vars); $i++) {
					$var = $verbatim_vars[$i];
					if ($var[2] == true) {
						if ((string)$initial_game_obj[$var[1]] != (string)$new_game_obj[$var[1]]) {
							$reset_block = $min_starting_block;
							
							$this->run_query("UPDATE games SET ".$var[1]."=:".$var[1]." WHERE game_id=:game_id;", [
								$var[1] => $new_game_obj[$var[1]],
								'game_id' => $game->db_game['game_id']
							]);
						}
					}
				}
				
				$this->run_query("DELETE FROM game_escrow_amounts WHERE game_id=:game_id;", ['game_id'=>$game->db_game['game_id']]);
				
				if (!empty($new_game_obj['escrow_amounts'])) {
					foreach ($new_game_obj['escrow_amounts'] as $currency_identifier => $amount) {
						$escrow_currency = $this->run_query("SELECT * FROM currencies WHERE short_name_plural=:currency_identifier;", [
							'currency_identifier' => $currency_identifier
						])->fetch();
						
						if ($escrow_currency) {
							$this->run_query("INSERT INTO game_escrow_amounts SET game_id=:game_id, currency_id=:currency_id, amount=:amount;", [
								'game_id' => $game->db_game['game_id'],
								'currency_id' => $escrow_currency['currency_id'],
								'amount' => $amount
							]);
						}
					}
				}
				
				$event_verbatim_vars = $this->event_verbatim_vars();
				
				$num_initial_events = 0;
				if (!empty($initial_game_obj['events'])) $num_initial_events = count($initial_game_obj['events']);
				$num_new_events = 0;
				if (!empty($new_game_obj['events'])) $num_new_events = count($new_game_obj['events']);
				
				$matched_events = min($num_initial_events, $num_new_events);
				
				for ($i=0; $i<$matched_events; $i++) {
					$initial_event_text = $this->game_def_to_text($initial_game_obj['events'][$i]);
					
					if ($this->game_def_to_text($new_game_obj['events'][$i]) != $initial_event_text) {
						$reset_block = $this->min_excluding_false(array($reset_block, $initial_game_obj['events'][$i]->event_starting_block, $new_game_obj['events'][$i]->event_starting_block));
						
						if ($reset_event_index === false) $reset_event_index = $new_game_obj['events'][$i]->event_index;
					}
				}
				
				$set_events_from = $this->min_excluding_false(array($reset_event_index, $matched_events+1));
				
				if ($set_events_from !== false) {
					$log_message .= "Resetting events from #".$set_events_from."\n";
					$game->reset_events_from_index($set_events_from);
				}
				
				if ($num_new_events+1 > $set_events_from) {
					if (!is_numeric($reset_block)) $reset_block = $new_game_obj['events'][$set_events_from-1]->event_starting_block;
					
					for ($i=$set_events_from; $i<count($new_game_obj['events'])+1; $i++) {
						if (!empty($new_game_obj['events'][$i-1])) {
							$gde = get_object_vars($new_game_obj['events'][$i-1]);
							$this->check_set_gde($game, $gde, $event_verbatim_vars, $sports_entity_type['entity_type_id'], $leagues_entity_type['entity_type_id'], $general_entity_type['entity_type_id']);
						}
					}
				}
				
				if (is_numeric($reset_block)) {
					$log_message .= "Resetting blocks from #".$reset_block."\n";
					$game->reset_blocks_from_block($reset_block);
				}
				else $log_message .= "Failed to determine a reset block.\n";
				
				$game->update_db_game();
			}
			else $log_message .= "No match for new game def: ".$new_game_def_hash."\n";
		}
		else $log_message .= "No match for initial game def: ".$initial_game_def_hash."\n";
		
		return $log_message;
	}
	
	public function check_set_gde(&$game, &$gde, &$event_verbatim_vars, $sport_entity_type_id, $league_entity_type_id, $general_entity_type_id) {
		$db_gde = $this->fetch_game_defined_event_by_index($game->db_game['game_id'], $gde['event_index']);
		
		$gde_params = [];
		if ($db_gde) $gde_q = "UPDATE game_defined_events SET ";
		else {
			$gde_q = "INSERT INTO game_defined_events SET game_id=:game_id, ";
			$gde_params['game_id'] = $game->db_game['game_id'];
		}
		
		if (!empty($gde['sport'])) {
			$sport_entity = $this->check_set_entity($sport_entity_type_id, $gde['sport']);
			$gde_q .= "sport_entity_id=:sport_entity_id, ";
			$gde_params['sport_entity_id'] = $sport_entity['entity_id'];
		}
		else $gde_q .= "sport_entity_id=NULL, ";
		
		if (!empty($gde['league'])) {
			$league_entity = $this->check_set_entity($league_entity_type_id, $gde['league']);
			$gde_q .= "league_entity_id=:league_entity_id, ";
			$gde_params['league_entity_id'] = $league_entity['entity_id'];
		}
		else $gde_q .= "league_entity_id=NULL, ";
		
		if (!empty($gde['external_identifier'])) {
			$gde_q .= "external_identifier=:external_identifier, ";
			$gde_params['external_identifier'] = $gde['external_identifier'];
		}
		else $gde_q .= "external_identifier=NULL, ";
		
		$track_entity_id = null;
		if (!empty($gde['track_entity_id'])) $track_entity_id = $gde['track_entity_id'];
		else if (!empty($gde['track_name_short'])) {
			$track_currency = $this->get_currency_by_abbreviation($gde['track_name_short']);
			if ($track_currency) $track_entity = $this->check_set_entity($general_entity_type_id, $track_currency['name']);
			else $track_entity = $this->check_set_entity($general_entity_type_id, $gde['track_name_short']);
			$track_entity_id = $track_entity['entity_id'];
		}
		
		$gde_q .= "track_entity_id=:track_entity_id, ";
		$gde_params['track_entity_id'] = $track_entity_id;
		
		for ($j=0; $j<count($event_verbatim_vars); $j++) {
			$var_type = $event_verbatim_vars[$j][0];
			if (isset($gde[$event_verbatim_vars[$j][1]])) $var_val = (string) $gde[$event_verbatim_vars[$j][1]];
			else $var_val = "";
			
			if ($var_val === "" || strtolower($var_val) == "null") $escaped_var_val = null;
			else $escaped_var_val = $var_val;
			
			$gde_params[$event_verbatim_vars[$j][1]] = $escaped_var_val;
			$gde_q .= $event_verbatim_vars[$j][1]."=:".$event_verbatim_vars[$j][1].", ";
		}
		
		$gde_q = substr($gde_q, 0, strlen($gde_q)-2);
		if ($db_gde) {
			$gde_q .= " WHERE game_defined_event_id=:game_defined_event_id";
			$gde_params['game_defined_event_id'] = $db_gde['game_defined_event_id'];
		}
		$this->run_query($gde_q, $gde_params);
		
		$delete_params = [
			'game_id' => $game->db_game['game_id'],
			'event_index' => $gde['event_index'],
		];
		$delete_q = "DELETE FROM game_defined_options WHERE game_id=:game_id AND event_index=:event_index";
		if (!empty($gde['possible_outcomes'])) {
			$delete_q .= " AND option_index > :option_index";
			$delete_params['option_index'] = count($gde['possible_outcomes']);
		}
		$delete_q .= ";";
		$this->run_query($delete_q, $delete_params);
		
		if (!empty($gde['possible_outcomes'])) {
			$existing_gdos = $this->fetch_game_defined_options($game->db_game['game_id'], $gde['event_index'], false, false);
			
			for ($k=0; $k<count($gde['possible_outcomes']); $k++) {
				$existing_gdo = $existing_gdos->fetch();
				
				if (is_object($gde['possible_outcomes'][$k])) $possible_outcome = get_object_vars($gde['possible_outcomes'][$k]);
				else $possible_outcome = $gde['possible_outcomes'][$k];
				
				$gdo_params = [
					'name' => $possible_outcome['title']
				];
				if ($existing_gdo) $gdo_q = "UPDATE game_defined_options SET ";
				else {
					$gdo_q = "INSERT INTO game_defined_options SET game_id=:game_id, event_index=:event_index, option_index=:option_index, ";
					$gdo_params['game_id'] = $game->db_game['game_id'];
					$gdo_params['event_index'] = $gde['event_index'];
					$gdo_params['option_index'] = $k;
				}
				$gdo_q .= "name=:name";
				
				if (!empty($possible_outcome['target_probability'])) {
					$gdo_q .= ", target_probability=:target_probability";
					$gdo_params['target_probability'] = $possible_outcome['target_probability'];
				}
				else $gdo_q .= ", target_probability=NULL";
				
				if (empty($possible_outcome['entity_id'])) {
					if ($track_entity_id) $possible_outcome['entity_id'] = $track_entity_id;
					else {
						$gdo_entity = $this->check_set_entity($general_entity_type_id, $possible_outcome['title']);
						$possible_outcome['entity_id'] = $gdo_entity['entity_id'];
					}
				}
				$gdo_q .= ", entity_id=:entity_id";
				$gdo_params['entity_id'] = $possible_outcome['entity_id'];
				
				if ($existing_gdo) {
					$gdo_q .= " WHERE game_defined_option_id=:game_defined_option_id";
					$gdo_params['game_defined_option_id'] = $existing_gdo['game_defined_option_id'];
				}
				$gdo_q .= ";";
				$this->run_query($gdo_q, $gdo_params);
			}
		}
	}
	
	public function get_game_definition_by_hash($game_def_hash) {
		$db_game_def = $this->run_query("SELECT * FROM game_definitions WHERE definition_hash=:definition_hash;", ['definition_hash'=>$game_def_hash])->fetch();
		if ($db_game_def) return $db_game_def['definition'];
		else return false;
	}
	
	public function check_set_game_definition($game_def_hash, $game_def_str) {
		$existing_def = $this->get_game_definition_by_hash($game_def_hash);
		
		if (!$existing_def) {
			$this->run_query("INSERT INTO game_definitions SET definition_hash=:definition_hash, definition=:definition;", [
				'definition_hash' => $game_def_hash,
				'definition' => $game_def_str
			]);
		}
	}
	
	public function check_module($module_name) {
		return $this->run_query("SELECT * FROM modules WHERE module_name=:module_name;", ['module_name'=>$module_name])->fetch();
	}
	
	public function create_blockchain_from_definition(&$definition, &$thisuser, &$error_message, &$db_new_blockchain) {
		$blockchain = false;
		$blockchain_def = json_decode($definition) or die("Error: invalid JSON formatted blockchain");
		
		if (!empty($blockchain_def->url_identifier)) {
			$db_blockchain = $this->fetch_blockchain_by_identifier($blockchain_def->url_identifier);
			
			if (!$db_blockchain) {
				$p2p_mode = "web_api";
				if ($blockchain_def->p2p_mode == "rpc") $p2p_mode = "rpc";
				
				$import_params = [
					'p2p_mode' => $p2p_mode,
					'creator_id' => $thisuser->db_user['user_id']
				];
				$import_q = "INSERT INTO blockchains SET online=1, p2p_mode=:p2p_mode, creator_id=:creator_id, ";
				
				$peer = false;
				$import_params['authoritative_peer_id'] = null;
				if ($blockchain_def->peer != "none") {
					$peer = $this->get_peer_by_server_name($blockchain_def->peer, false);
					if ($peer) {
						$import_params['authoritative_peer_id'] = $peer['peer_id'];
					}
				}
				$import_q .= "authoritative_peer_id=:authoritative_peer_id, ";
				
				$verbatim_vars = $this->blockchain_verbatim_vars();
				
				for ($var_i=0; $var_i<count($verbatim_vars); $var_i++) {
					$var_type = $verbatim_vars[$var_i][0];
					$var_name = $verbatim_vars[$var_i][1];
					
					$import_q .= $var_name."=:".$var_name.", ";
					$import_params[$var_name] = $blockchain_def->$var_name;
				}
				$import_q = substr($import_q, 0, strlen($import_q)-2).";";
				
				$this->run_query($import_q, $import_params);
				$blockchain_id = $this->last_insert_id();
				
				$error_message = "Import was a success! Next please <a href=\"/scripts/sync_blockchain_initial.php?key=".AppSettings::getParam('operator_key')."&blockchain_id=".$blockchain_id."\">reset and synchronize ".$blockchain_def->blockchain_name."</a>";
			}
			else $error_message = "Error: this blockchain already exists.";
		}
		else $error_message = "Invalid url_identifier";
	}
	
	public function set_game_from_definition(&$game_definition, &$thisuser, &$error_message, &$db_game, $permission_override) {
		$game = false;
		$decode_error = false;
		
		if (is_object($game_definition)) $game_def = $game_definition;
		else {
			if ($game_def = json_decode($game_definition)) {}
			else {
				$decode_error = true;
				$error_message .= "Error: the game definition you entered could not be imported. Please make sure to enter properly formatted JSON.\n";
			}
		}
		
		if (!$decode_error) {
			$module_ok = true;
			if (!empty($game_def->module)) {
				if (!$this->check_module($game_def->module)) $module_ok = false;
			}
			
			if ($module_ok) {
				if (!empty($game_def->blockchain_identifier)) {
					$new_private_blockchain = false;
					
					if ($game_def->blockchain_identifier == "private") {
						$new_private_blockchain = true;
						$chain_id = $this->random_string(6);
						$decimal_places = 8;
						$url_identifier = "private-chain-".$chain_id;
						$chain_pow_reward = 25*pow(10,$decimal_places);
						
						$this->run_query("INSERT INTO blockchains SET online=1, p2p_mode='none', blockchain_name=:blockchain_name, url_identifier=:url_identifier, coin_name='chaincoin', coin_name_plural='chaincoins', seconds_per_block=30, decimal_places=:decimal_places, initial_pow_reward=:initial_pow_reward;", [
							'blockchain_name' => "Private Chain ".$chain_id,
							'url_identifier' => $url_identifier,
							'decimal_places' => $decimal_places,
							'initial_pow_reward' => $chain_pow_reward
						]);
						$blockchain_id = $this->last_insert_id();
						
						$new_blockchain = new Blockchain($this, $blockchain_id);
						if ($thisuser) $new_blockchain->set_blockchain_creator($thisuser);
						
						$game_def->blockchain_identifier = $url_identifier;
					}
					
					$db_blockchain = $this->fetch_blockchain_by_identifier($game_def->blockchain_identifier);
					
					if ($db_blockchain) {
						$blockchain = new Blockchain($this, $db_blockchain['blockchain_id']);
						
						$game_def->url_identifier = $this->normalize_uri_part($game_def->url_identifier);
						
						if (!empty($game_def->url_identifier)) {
							$verbatim_vars = $this->game_definition_verbatim_vars();
							
							$permission_to_change = false;
							
							$db_url_matched_game = $this->fetch_game_by_identifier($game_def->url_identifier);
							
							if ($db_url_matched_game) {
								if ($db_url_matched_game['blockchain_id'] == $blockchain->db_blockchain['blockchain_id']) {
									$url_matched_game = new Game($blockchain, $db_url_matched_game['game_id']);
									
									if ($permission_override) $permission_to_change = true;
									else {
										if ($thisuser) {
											$permission_to_change = $this->user_can_edit_game($thisuser, $url_matched_game);
											
											if (!$permission_to_change) $error_message .= "Error: you can't edit this game.\n";
										}
										else $error_message .= "Permission denied. You must be logged in.\n";
									}
									
									if ($permission_to_change) $game = $url_matched_game;
								}
								else $error_message .= "Error: invalid game.blockchain_id.\n";
							}
							else $permission_to_change = true;
							
							if ($permission_to_change) {
								if (!$game) {
									$db_group = false;
									if (!empty($game_def->option_group)) {
										$db_group = $this->fetch_group_by_description($game_def->option_group);
										
										if (!$db_group) {
											$import_error = "";
											$this->import_group_from_file($game_def->option_group, $import_error);
											
											$db_group = $this->fetch_group_by_description($game_def->option_group);
										}
									}
									
									$new_game_params = [
										'featured' => 1,
										'game_status' => 'published'
									];
									if ($thisuser) $new_game_params['creator_id'] = $thisuser->db_user['user_id'];
									if ($db_group) $new_game_params['option_group_id'] = $db_group['group_id'];
									
									for ($i=0; $i<count($verbatim_vars); $i++) {
										$var_type = $verbatim_vars[$i][0];
										$var_name = $verbatim_vars[$i][1];
										
										if ($game_def->$var_name != "") {
											$new_game_params[$var_name] = $game_def->$var_name;
										}
									}
									
									$game = Game::create_game($blockchain, $new_game_params);
									
									if (!empty($game_def->module)) {
										$this->run_query("UPDATE modules SET primary_game_id=:primary_game_id WHERE module_name=:module_name AND primary_game_id IS NULL;", [
											'primary_game_id' => $game->db_game['game_id'],
											'module_name' => $game_def->module
										]);
									}
								}
								
								if (!empty($game_def->definitive_peer)) {
									$definitive_game_peer = $game->get_game_peer_by_server_name($game_def->definitive_peer);
									
									if ($definitive_game_peer) {
										$this->run_query("UPDATE games SET definitive_game_peer_id=:definitive_game_peer_id WHERE game_id=:game_id;", [
											'definitive_game_peer_id' => $definitive_game_peer['game_peer_id'],
											'game_id' => $game->db_game['game_id']
										]);
										$game->db_game['definitive_game_peer_id'] = $definitive_game_peer['game_peer_id'];
									}
								}
								
								$this->run_query("DELETE FROM game_defined_escrow_amounts WHERE game_id=:game_id;", ['game_id'=>$game->db_game['game_id']]);
								
								if (!empty($game_def->escrow_amounts)) {
									foreach ($game_def->escrow_amounts as $currency_identifier => $amount) {
										$escrow_currency = $this->run_query("SELECT * FROM currencies WHERE short_name_plural=:currency_identifier;", [
											'currency_identifier'=>$currency_identifier
										])->fetch();
										
										if ($escrow_currency) {
											$this->run_query("INSERT INTO game_defined_escrow_amounts SET game_id=:game_id, currency_id=:currency_id, amount=:amount;", [
												'game_id' => $game->db_game['game_id'],
												'currency_id' => $escrow_currency['currency_id'],
												'amount' => $amount
											]);
										}
									}
								}
								
								$show_internal_params = false;
								$from_game_def = $this->fetch_game_definition($game, "defined", $show_internal_params);
								$from_game_def_str = $this->game_def_to_text($from_game_def);
								$from_game_def_hash = $this->game_def_to_hash($from_game_def_str);
								$this->check_set_game_definition($from_game_def_hash, $from_game_def_str);
								
								$to_game_def_str = $this->game_def_to_text($game_def);
								$to_game_def_hash = $this->game_def_to_hash($to_game_def_str);
								$this->check_set_game_definition($to_game_def_hash, $to_game_def_str);
								
								if ($from_game_def_hash != $to_game_def_hash) {
									$error_message .= $this->migrate_game_definitions($game, $from_game_def_hash, $to_game_def_hash);
								}
								else $error_message .= "Found no changes to apply.\n";
								
								$game->update_db_game();
								$db_game = $game->db_game;
							}
						}
						else $error_message .= "Error, invalid game URL identifier.\n";
					}
					else {
						if ($new_private_blockchain) {
							$this->run_query("DELETE FROM blockchains WHERE blockchain_id=:blockchain_id;", [
								'blockchain_id'=>$new_blockchain->db_blockchain['blockchain_id']
							]);
						}
						$error_message .= "Error, failed to identify the right blockchain.\n";
					}
				}
				else $error_message .= "Error, blockchain url identifier was empty.\n";
			}
			else $error_message .= "Error, invalid module.\n";
		}
		
		return $game;
	}
	
	public function check_set_option_group($description, $singular_form, $plural_form) {
		$group = $this->fetch_group_by_description($description);
		
		if ($group) return $group;
		else {
			$this->run_query("INSERT INTO option_groups SET description=:description, option_name=:option_name, option_name_plural=:option_name_plural;", [
				'description' => $description,
				'option_name' => $singular_form,
				'option_name_plural' => $plural_form
			]);
			return $this->fetch_group_by_id($this->last_insert_id());
		}
	}
	
	public function fetch_entity_by_id($entity_id) {
		return $this->run_query("SELECT * FROM entities WHERE entity_id=:entity_id;", ['entity_id'=>$entity_id])->fetch();
	}
	
	public function check_set_entity($entity_type_id, $name) {
		$existing_entity_params = [
			'entity_name' => $name
		];
		$existing_entity_q = "SELECT * FROM entities WHERE ";
		if ($entity_type_id) {
			$existing_entity_q .= "entity_type_id=:entity_type_id AND ";
			$existing_entity_params['entity_type_id'] = $entity_type_id;
		}
		$existing_entity_q .= "entity_name=:entity_name;";
		$existing_entity = $this->run_query($existing_entity_q, $existing_entity_params)->fetch();
		
		if ($existing_entity) return $existing_entity;
		else {
			$new_entity_q = "INSERT INTO entities SET entity_name=:entity_name";
			if ($entity_type_id) $new_entity_q .= ", entity_type_id=:entity_type_id";
			$new_entity_q .= ";";
			$this->run_query($new_entity_q, $existing_entity_params);
			
			return $this->fetch_entity_by_id($this->last_insert_id());
		}
	}
	
	public function fetch_entity_type_by_id($entity_type_id) {
		return $this->run_query("SELECT * FROM entity_types WHERE entity_type_id=:entity_type_id;", ['entity_type_id'=>$entity_type_id])->fetch();
	}
	
	public function check_set_entity_type($name) {
		$existing_entity_type = $this->run_query("SELECT * FROM entity_types WHERE entity_name=:entity_name;", ['entity_name'=>$name])->fetch();
		
		if ($existing_entity_type) return $existing_entity_type;
		else {
			$this->run_query("INSERT INTO entity_types SET entity_name=:entity_name;", ['entity_name'=>$name]);
			return $this->fetch_entity_type_by_id($this->last_insert_id());
		}
	}
	
	public function cached_url_info($url) {
		return $this->run_query("SELECT * FROM cached_urls WHERE url=:url;", ['url'=>$url])->fetch();
	}
	
	public function async_fetch_url($url, $require_now) {
		$cached_url = $this->cached_url_info($url);
		
		if ($cached_url) {
			if ($require_now && empty($cached_url['time_fetched'])) {
				$start_load_time = microtime(true);
				$http_response = file_get_contents($cached_url['url']) or die("Failed to fetch url: $url");
				
				$this->run_query("UPDATE cached_urls SET cached_result=:cached_result, time_fetched=:time_fetched, load_time=:load_time WHERE cached_url_id=:cached_url_id;", [
					'cached_result' => $http_response,
					'time_fetched' => time(),
					'load_time' => (microtime(true)-$start_load_time),
					'cached_url_id' => $cached_url['cached_url_id']
				]);
				
				$cached_url = $this->run_query("SELECT * FROM cached_urls WHERE cached_url_id=:cached_url_id;", ['cached_url_id'=>$cached_url['cached_url_id']])->fetch();
			}
		}
		else {
			$new_cached_url_params = [
				'url' => $url,
				'current_time' => time()
			];
			$new_cached_url_q = "INSERT INTO cached_urls SET url=:url, time_created=:current_time";
			if ($require_now) {
				$start_load_time = microtime(true);
				$http_response = file_get_contents($url) or die("Failed to fetch url: $url");
				$new_cached_url_q .= ", time_fetched=:current_time, cached_result=:cached_result, load_time=:load_time";
				$new_cached_url_params['cached_result'] = $http_response;
				$new_cached_url_params['load_time'] = microtime(true)-$start_load_time;
			}
			$new_cached_url_q .= ";";
			$this->run_query($new_cached_url_q, $new_cached_url_params);
			
			$cached_url = $this->run_query("SELECT * FROM cached_urls WHERE cached_url_id=:cached_url_id;", ['cached_url_id'=>$this->last_insert_id()])->fetch();
		}
		
		return $cached_url;
	}
	
	public function permission_to_claim_address(&$thisuser, &$address_blockchain, &$db_address) {
		if (!empty($thisuser) && $this->user_is_admin($thisuser) && empty($db_address['user_id'])) {
			if ($address_blockchain->db_blockchain['p2p_mode'] == "none") return true;
			else return false;
		}
		else return false;
	}
	
	public function give_address_to_user(&$game, &$user, $db_address) {
		if ($game) {
			$user_game = $user->ensure_user_in_game($game, false);
			
			if ($user_game) {
				$address_key = $this->run_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id=:address_id;", [
					'address_id' => $db_address['address_id']
				])->fetch();
				
				if ($address_key) {
					$this->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
						'account_id' => $user_game['account_id'],
						'address_key_id' => $address_key['address_key_id']
					]);
				}
				else {
					$this->run_query("INSERT INTO address_keys SET address_id=:address_id, account_id=:account_id, save_method='fake', pub_key=:pub_key;", [
						'address_id' => $db_address['address_id'],
						'account_id' => $user_game['account_id'],
						'pub_key' => $db_address['address']
					]);
				}
				$this->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
					'user_id' => $user->db_user['user_id'],
					'address_id' => $db_address['address_id']
				]);
				
				return true;
			}
			else return false;
		}
		else {
			$blockchain = new Blockchain($this, $db_address['primary_blockchain_id']);
			$currency_id = $blockchain->currency_id();
			
			$account = $this->user_blockchain_account($user->db_user['user_id'], $currency_id);
			
			if ($account) {
				$address_key = $this->run_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id=:address_id;", [
					'address_id' => $db_address['address_id']
				])->fetch();
				
				if ($address_key) {
					$this->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
						'account_id' => $account['account_id'],
						'address_key_id' => $address_key['address_key_id']
					]);
				}
				else {
					$this->run_query("INSERT INTO address_keys SET address_id=:address_id, account_id=:account_id, save_method='fake', pub_key=:pub_key;", [
						'address_id' => $db_address['address_id'],
						'account_id' => $account['account_id'],
						'pub_key' => $db_address['address']
					]);
				}
				
				$this->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
					'user_id' => $user->db_user['user_id'],
					'address_id' => $db_address['address_id']
				]);
				
				return true;
			}
			else return false;
		}
	}
	
	public function blockchain_ensure_currencies() {
		$problem_blockchains = $this->run_query("SELECT b.* FROM blockchains b WHERE NOT EXISTS (SELECT * FROM currencies c WHERE b.blockchain_id=c.blockchain_id);");
		
		while ($db_blockchain = $problem_blockchains->fetch()) {
			$this->run_query("INSERT INTO currencies SET blockchain_id=:blockchain_id, name=:name, short_name=:short_name, short_name_plural=:short_name_plural, abbreviation=:abbreviation;", [
				'blockchain_id' => $db_blockchain['blockchain_id'],
				'name' => $db_blockchain['blockchain_name'],
				'short_name' => $db_blockchain['coin_name'],
				'short_name_plural' => $db_blockchain['coin_name_plural'],
				'abbreviation' => $db_blockchain['coin_name_plural']
			]);
		}
	}
	
	public function user_is_admin(&$user) {
		if (!empty($user)) {
			if ($user->db_user['user_id'] == $this->get_site_constant("admin_user_id")) return true;
			else return false;
		}
		else return false;
	}
	
	public function user_can_edit_game(&$user, &$game) {
		if (!empty($user) && !empty($game->db_game['creator_id'])) {
			if ($user->db_user['user_id'] == $game->db_game['creator_id']) return true;
			else return false;
		}
		else return false;
	}
	
	public function user_blockchain_account($user_id, $currency_id) {
		return $this->run_query("SELECT * FROM currency_accounts WHERE game_id IS NULL AND user_id=:user_id AND currency_id=:currency_id;", [
			'user_id' => $user_id,
			'currency_id' => $currency_id
		])->fetch();
	}
	
	public function render_error_message(&$error_message, $error_class) {
		if ($error_class == "nostyle") return $error_message;
		else {
			$html = '
			<div class="alert alert-dismissible alert-success">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				'.$error_message.'
			</div>';
			
			return $html;
		}
	}
	
	public function get_card_denominations($currency, $fv_currency_id) {
		return $this->run_query("SELECT * FROM card_currency_denominations WHERE currency_id=:currency_id AND fv_currency_id=:fv_currency_id ORDER BY denomination ASC;", [
			'currency_id' => $currency['currency_id'],
			'fv_currency_id' => $fv_currency_id
		])->fetchAll();
	}
	
	public function calculate_cards_cost($usd_per_btc, $denomination, $purity, $how_many) {
		$error = FALSE;
		$total_usd = 0;
		
		if ($purity == "unspecified") $purity = 100;
		
		if ($currency != "btc") $currency = "usd";
		
		if ($currency == "btc") $purity = 100;
		
		if ($purity != round($purity)) $error = TRUE;
		
		if ($how_many > 0 && $how_many <= 1000 && $how_many == round($how_many)) {}
		else $error = TRUE;
		
		if ($purity >= 80 && $purity <= 100 && $purity == round($purity)) {}
		else $error = TRUE;
		
		if ($error) return FALSE;
		else {
			$cards_facevalue_usd = $how_many*$denomination['denomination'];
			if ($denomination['currency_id'] != 1) $cards_facevalue_usd = round($cards_facevalue_usd*$usd_per_btc, 2);
			
			$total_usd += $cards_facevalue_usd;
			
			$print_fees = round(0.25*$how_many, 2);
			$total_usd += $print_fees;
			
			$builtin_discount = round($cards_facevalue_usd*((100-$purity)/100), 2);
			$total_usd = $total_usd - $builtin_discount;
			
			return round($total_usd/$usd_per_btc, 5);
		}
	}
	
	public function position_by_pos($position, $side, $paper_width) {
		$num_cols = 2;
		if ($paper_width == "small") $num_cols = 1;
		
		$position = $position - 1; // use 0,1... ordering instead of 1,2...
		
		if ($paper_width == "small") {
			$left_margin = 0.2;
			$top_margin = 0.5;
			
			$card_w = 2;
			$card_h = 3.5;
		}
		else {
			$left_margin = 0.75;
			$top_margin = 0.5;
			
			$card_w = 3.5;
			$card_h = 2;
		}
		
		if ($side == "front") {
			if ($position % $num_cols == 0) $x = $left_margin;
			else $x = $left_margin + $card_w;
			
			$row = floor($position/$num_cols);
			$y = $top_margin + $row*$card_h;
		}
		else if ($side == "back") {
			if ($position % $num_cols == 0 && $paper_width != "small") $x = $left_margin + $card_w;
			else $x = $left_margin;
			
			$row = floor($position/$num_cols);
			$y = $top_margin + $row*$card_h;
		}
		
		$result[0] = $x;
		$result[1] = $y;
		return $result;
	}
	
	public function try_create_card_account($card, $thisuser, $password) {
		if ($card['status'] == "sold") {
			if (empty($thisuser)) {
				$username = $this->random_string(16);
				$user_password = $this->random_string(16);
				$verify_code = $this->random_string(32);
				$salt = $this->random_string(16);
				
				$thisuser = $this->create_new_user($verify_code, $salt, $username, $user_password);
			}
			
			$new_card_user_params = [
				'card_id' => $card['card_id'],
				'password' => $password,
				'create_time' => time()
			];
			$new_card_user_q = "INSERT INTO card_users SET card_id=:card_id, password=:password, create_time=:create_time";
			if (AppSettings::getParam('pageview_tracking_enabled')) {
				$new_card_user_q .= ", create_ip=:create_ip";
				$new_card_user_params['create_ip'] = $_SERVER['REMOTE_ADDR'];
			}
			$new_card_user_q .= ";";
			$this->run_query($new_card_user_q, $new_card_user_params);
			$card_user_id = $this->last_insert_id();
			
			$this->run_query("UPDATE cards SET user_id=:user_id, card_user_id=:card_user_id, claim_time=:claim_time WHERE card_id=:card_id;", [
				'user_id' => $thisuser->db_user['user_id'],
				'card_user_id' => $card_user_id,
				'claim_time' => time(),
				'card_id' => $card['card_id']
			]);
			
			$this->change_card_status($card, 'claimed');
			
			$session_key = $_COOKIE['my_session'];
			$expire_time = time()+3600*24;
			
			$card_session_params = [
				'card_user_id' => $card_user_id,
				'session_key' => $session_key,
				'login_time' => time(),
				'expire_time' => $expire_time
			];
			$card_session_q = "INSERT INTO card_sessions SET card_user_id=:card_user_id, session_key=:session_key, login_time=:login_time, expire_time=:expire_time";
			if (AppSettings::getParam('pageview_tracking_enabled')) {
				$card_session_q .= ", ip_address=:ip_address";
				$card_session_params['ip_address'] = $_SERVER['REMOTE_ADDR'];
			}
			$card_session_q .= ";";
			$this->run_query($card_session_q, $card_session_params);
			
			$redirect_url = false;
			$login_success = $thisuser->log_user_in($redirect_url, false);
			
			$txt = "<p>Your account has been created! ";
			$txt .= "Any time you want to access your money, please visit the link on your gift card.</p>\n";
			
			$txt .= "<a href=\"/cards/\" class=\"btn btn-default\">Go to My Account</a>";
			
			$success = TRUE;
		}
		else {
			$success = FALSE;
			$txt = "";
		}
		
		$returnvals[0] = $success;
		$returnvals[1] = $txt;
		return $returnvals;
	}
	
	public function get_card_currency_balance($card_id, $currency_id) {
		$balance = $this->run_query("SELECT * FROM card_currency_balances WHERE card_id=:card_id AND currency_id=:currency_id;", [
			'card_id' => $card_id,
			'currency_id' => $currency_id
		])->fetch();
		
		if ($balance) return $balance['balance'];
		else return 0;
	}
	
	public function get_card_currency_balances($card_id) {
		return $this->run_query("SELECT * FROM card_currency_balances b JOIN currencies c ON b.currency_id=c.currency_id WHERE b.card_id=:card_id ORDER BY b.currency_id ASC;", ['card_id'=>$card_id])->fetchAll();
	}
	
	public function set_card_currency_balances($card) {
		$balances_by_currency_id = [];
		
		$card_conversions = $this->run_query("SELECT * FROM card_conversions WHERE card_id=:card_id;", ['card_id'=>$card['card_id']]);
		
		while ($conversion = $card_conversions->fetch()) {
			if (!empty($conversion['currency1_id'])) {
				if (empty($balances_by_currency_id[$conversion['currency1_id']])) $balances_by_currency_id[$conversion['currency1_id']] = 0;
				$balances_by_currency_id[$conversion['currency1_id']] += $conversion['currency1_delta'];
			}
			if (!empty($conversion['currency2_id'])) {
				if (empty($balances_by_currency_id[$conversion['currency2_id']])) $balances_by_currency_id[$conversion['currency2_id']] = 0;
				$balances_by_currency_id[$conversion['currency2_id']] += $conversion['currency2_delta'];
			}
		}
		
		foreach ($balances_by_currency_id as $currency_id => $balance) {
			$db_balance = $this->run_query("SELECT * FROM card_currency_balances WHERE card_id=:card_id AND currency_id=:currency_id;", [
				'card_id' => $card['card_id'],
				'currency_id' => $currency_id
			])->fetch();
			
			if ($db_balance) {
				$this->run_query("UPDATE card_currency_balances SET balance=:balance WHERE balance_id=:balance_id;", [
					'balance' => $balance,
					'balance_id' => $db_balance['balance_id']
				]);
			}
			else {
				$this->run_query("INSERT INTO card_currency_balances SET card_id=:card_id, currency_id=:currency_id, balance=:balance;", [
					'card_id' => $card['card_id'],
					'currency_id' => $currency_id,
					'balance' => $balance
				]);
			}
		}
	}
	
	public function calculate_cards_networth($my_cards) {
		$networth = 0;
		$currency_prices = $this->fetch_currency_prices();
		
		foreach ($my_cards as $card) {
			$balances = $this->get_card_currency_balances($card['card_id']);
			$networth += $this->calculate_card_networth($card, $balances, $currency_prices);
		}
		
		return $networth;
	}
	
	public function calculate_card_networth($card, $balances, $currency_prices) {
		$value = 0;
		
		foreach ($balances as $balance) {
			if (!empty($currency_prices[$balance['currency_id']])) {
				$value += $balance['balance']/$currency_prices[$balance['currency_id']]['price'];
			}
		}
		
		return $value;
	}
	
	public function get_card_fees($card) {
		return $card['amount']*(100-$card['purity'])/100;
	}
	
	public function try_withdraw_mobilemoney($currency_id, $phone_number, $first_name, $last_name, $amount, &$my_cards) {
		$beyonic = new Beyonic();
		$beyonic->setApiKey(AppSettings::getParam('beyonic_api_key'));
		
		$payment = new MobilePayment($this, false);
		$payment->set_fields($my_cards[0]['card_group_id'], $currency_id, $amount, $phone_number, $first_name, $last_name);
		$payment->create();
		
		$mobilemoney_error = false;
		
		try {
			$beyonic_request = $beyonic->sendRequest('payments', 'POST', false, array(
				'phonenumber' => $phone_number,
				'payment_type' => "money",
				'first_name' => $first_name,
				'last_name' => $last_name,
				'amount' => $amount,
				'currency' => 'UGX',
				'description' => AppSettings::getParam('site_name_short')
			));
		}
		catch (Exception $e) {
			$mobilemoney_error = true;
			$error_message = "There was an error initiating the payment: ".$e->responseBody;
		}
		
		if (!$mobilemoney_error) {
			$this->run_query("UPDATE mobile_payments SET beyonic_request_id=:beyonic_request_id WHERE payment_id=:payment_id;", [
				'beyonic_request_id' => $beyonic_request->id,
				'payment_id' => $payment->db_payment['payment_id']
			]);
			
			$this->change_card_status($my_cards[0], 'redeemed');
			
			$this->run_query("UPDATE cards SET status='redeemed' WHERE card_id=:card_id;", ['card_id'=>$my_cards[0]['card_id']]);
			
			$withdrawal_params = [
				'card_id' => $my_cards[0]['card_id'],
				'currency_id' => $currency_id,
				'status_change_id' => $status_change_id,
				'withdraw_time' => time(),
				'amount' => $amount
			];
			if (AppSettings::getParam('pageview_tracking_enabled')) $withdrawal_params['ip_address'] = $_SERVER['REMOTE_ADDR'];
			else $withdrawal_params['ip_address'] = null;
			
			$this->run_query("INSERT INTO card_withdrawals SET withdraw_method='mobilemoney', card_id=:card_id, currency_id=:currency_id, status_change_id=:status_change_id, withdraw_time=:withdraw_time, amount=:amount, ip_address=:ip_address;", $withdrawal_params);
			$withdrawal_id = $this->last_insert_id();
			
			$conversion_params = [
				'card_id' => $my_cards[0]['card_id'],
				'withdrawal_id' => $withdrawal_id,
				'time_created' => time(),
				'currency1_id' => $currency_id,
				'currency1_delta' => (-1*$amount)
			];
			if (AppSettings::getParam('pageview_tracking_enabled')) $conversion_params['ip_address'] = $_SERVER['REMOTE_ADDR'];
			else $conversion_params['ip_address'] = null;
			
			$this->run_query("INSERT INTO card_conversions SET card_id=:card_id, withdrawal_id=:withdrawal_id, time_created=:time_created, ip_address=:ip_address, currency1_id=:currency1_id, currency1_delta=:currency1_delta;", $conversion_params);
			
			$this->set_card_currency_balances($my_cards[0]);
			
			$error_message = "Beyonic request was successful!";
		}
		
		return $error_message;
	}
	
	public function get_peer_by_server_name($server_name, $allow_new) {
		$server_name = trim(strtolower(strip_tags($server_name)));
		$initial_server_name = $server_name;
		if (substr($server_name, 0, 7) == "http://") $server_name = substr($server_name, 7, strlen($server_name)-7);
		if (substr($server_name, 0, 8) == "https://") $server_name = substr($server_name, 8, strlen($server_name)-8);
		if (substr($server_name, 0, 4) == "www.") $server_name = substr($server_name, 4, strlen($server_name)-4);
		if ($server_name[strlen($server_name)-1] == "/") $server_name = substr($server_name, 0, strlen($server_name)-1);
		
		$peer = $this->run_query("SELECT * FROM peers WHERE peer_identifier=:peer_identifier;", ['peer_identifier'=>$server_name])->fetch();
		
		if (!$peer && $allow_new) {
			$this->run_query("INSERT INTO peers SET peer_identifier=:peer_identifier, peer_name=:peer_name, base_url=:base_url, time_created=:time_created;", [
				'peer_identifier' => $server_name,
				'peer_name' => $server_name,
				'base_url' => $initial_server_name,
				'time_created' => time()
			]);
			$peer = $this->fetch_peer_by_id($this->last_insert_id());
		}
		
		return $peer;
	}
	
	public function change_card_status(&$db_card, $new_status) {
		$this->run_query("INSERT INTO card_status_changes SET card_id=:card_id, from_status=:from_status, to_status=:to_status, change_time=:change_time;", [
			'card_id' => $db_card['card_id'],
			'from_status' => $db_card['status'],
			'to_status' => $new_status,
			'change_time' => time()
		]);
		
		$this->run_query("UPDATE cards SET status=:status WHERE card_id=:card_id;", [
			'status' => $new_status,
			'card_id' => $db_card['card_id']
		]);
		
		$db_card['status'] = $new_status;
	}
	
	public function card_secret_to_hash($secret) {
		return hash("sha256", $secret);
	}
	
	public function create_new_user($verify_code, $salt, $username, $password) {
		$new_user_params = [
			'username' => $username,
			'password' => $this->normalize_password($password, $salt),
			'salt' => $salt,
			'login_method' => strpos($username, '@') === false ? "password" : "email",
			'time_created' => time(),
			'verify_code' => $verify_code,
			'ip_address' => AppSettings::getParam('pageview_tracking_enabled') ? $_SERVER['REMOTE_ADDR'] : null
		];
		$new_user_q = "INSERT INTO users SET username=:username, password=:password, salt=:salt";
		if (strpos($username, '@') !== false) {
			$new_user_q .= ", notification_email=:notification_email";
			$new_user_params['notification_email'] = $username;
		}
		if (AppSettings::getParam('new_games_per_user') != "unlimited" && AppSettings::getParam('new_games_per_user') > 0) {
			$new_user_q .= ", authorized_games=:authorized_games";
			$new_user_params['authorized_games'] = AppSettings::getParam('new_games_per_user');
		}
		$new_user_q .= ", login_method=:login_method, time_created=:time_created, verify_code=:verify_code, ip_address=:ip_address;";
		$this->run_query($new_user_q, $new_user_params);
		$user_id = $this->last_insert_id();
		
		$thisuser = new User($this, $user_id);
		
		if ($user_id == 1) $this->set_site_constant("admin_user_id", $user_id);
		
		return $thisuser;
	}
	
	public function fetch_currency_prices() {
		$prices = [];
		$all_currencies = $this->run_query("SELECT * FROM currencies ORDER BY currency_id ASC;");
		
		while ($db_currency = $all_currencies->fetch()) {
			$prices[$db_currency['currency_id']] = $this->latest_currency_price($db_currency['currency_id']);
		}
		
		return $prices;
	}
	
	public function account_balance($account_id) {
		return $this->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id AND io.spend_status='unspent';", ['account_id'=>$account_id])->fetch()['SUM(io.amount)'];
	}
	
	public function card_public_vars() {
		return ['peer_card_id', 'mint_time', 'amount', 'purity', 'status'];
	}
	
	public function pay_out_card(&$card, $address, $fee) {
		$db_currency = $this->fetch_currency_by_id($card['currency_id']);
		$blockchain = new Blockchain($this, $db_currency['blockchain_id']);
		
		$io_tx = $blockchain->fetch_transaction_by_hash($card['io_tx_hash']);
		
		if ($io_tx) {
			$io = $this->run_query("SELECT * FROM transaction_ios WHERE create_transaction_id=:create_transaction_id AND out_index=:out_index;", [
				'create_transaction_id' => $io_tx['transaction_id'],
				'out_index' => $card['io_out_index']
			])->fetch();
			
			if ($io) {
				$db_address = $blockchain->create_or_fetch_address($address, true, false, false, false, false);
				
				$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
				$amounts = array($io['amount']-$fee_amount);
				
				$payout_tx_error = false;
				$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, array($io['io_id']), array($db_address['address_id']), array(0), $fee_amount, $payout_tx_error);
				
				if ($transaction_id) {
					$transaction = $this->fetch_transaction_by_id($transaction_id);
					
					$this->run_query("UPDATE cards SET redemption_tx_hash=:tx_hash WHERE card_id=:card_id;", [
						'tx_hash' => $transaction['tx_hash'],
						'card_id' => $card['card_id']
					]);
					$card['redemption_tx_hash'] = $transaction['tx_hash'];
					$this->change_card_status($card, 'redeemed');
					
					return $transaction;
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function redeem_card_to_account(&$thisuser, &$card, $claim_type) {
		$message = "";
		$status_code = false;
		
		$db_account = $this->user_blockchain_account($thisuser->db_user['user_id'], $card['fv_currency_id']);
		
		if ($db_account['current_address_id'] > 0) {
			$db_address = $this->fetch_address_by_id($db_account['current_address_id']);
			
			if ($db_address) {
				$db_currency = $this->fetch_currency_by_id($db_account['currency_id']);
				
				$blockchain = new Blockchain($this, $db_currency['blockchain_id']);
				
				$this_peer = $this->get_peer_by_server_name(AppSettings::getParam('base_url'), true);
				
				$fee = 0.0001;
				$fee_amount = (int)($fee*pow(10, $blockchain->db_blockchain['decimal_places']));
				
				if ($claim_type == "to_game") $success_message = "/accounts/?action=prompt_game_buyin&account_id=".$db_account['account_id']."&amount=".($card['amount']-$fee);
				else $success_message = "/accounts/?action=view_account&account_id=".$db_account['account_id'];
				
				if ($card['peer_id'] != $this_peer['peer_id']) {
					$remote_peer = $this->fetch_peer_by_id($card['peer_id']);
					
					$remote_url = $remote_peer['base_url']."/api/card/".$card['peer_card_id']."/withdraw/?secret=".$card['secret_hash']."&fee=".$fee."&address=".$db_address['address'];
					$remote_response_raw = file_get_contents($remote_url);
					$remote_response = get_object_vars(json_decode($remote_response_raw));
					
					if ($remote_response['status_code'] == 1) {
						$status_code=1;
						$message = $success_message;
						$this->change_card_status($card, "redeemed");
						
						$this->run_query("UPDATE cards SET redemption_tx_hash=:tx_hash WHERE card_id=:card_id;", [
							'tx_hash' => $remote_response['message'],
							'card_id' => $card['card_id']
						]);
						
						$card['redemption_tx_hash'] = $remote_response['message'];
					}
					else {$status_code=12; $message = $remote_response['message'];}
				}
				else {
					$io_tx = $blockchain->fetch_transaction_by_hash($card['io_tx_hash']);
					
					if ($io_tx) {
						$io = $this->run_query("SELECT * FROM transaction_ios WHERE create_transaction_id=:transaction_id AND out_index=:out_index;", [
							'transaction_id' => $io_tx['transaction_id'],
							'out_index' => $card['io_out_index']
						])->fetch();
						
						if ($io) {
							$success_message .= "&io_id=".$io['io_id'];
							
							$redeem_tx_error = false;
							$transaction_id = $blockchain->create_transaction("transaction", array($io['amount']-$fee_amount), false, array($io['io_id']), array($db_address['address_id']), array(0), $fee_amount, $redeem_tx_error);
							
							if ($transaction_id) {
								$transaction = $this->fetch_transaction_by_id($transaction_id);
								
								$message = $success_message;
								$this->change_card_status($card, "redeemed");
								$status_code = 1;
								
								$this->run_query("UPDATE cards SET redemption_tx_hash=:tx_hash WHERE card_id=:card_id;", [
									'tx_hash' => $transaction['tx_hash'],
									'card_id' => $card['card_id']
								]);
								$card['redemption_tx_hash'] = $transaction['tx_hash'];
							}
							else {$status_code=11; $message="TX Error: ".$error_message;}
						}
						else {$status_code=10; $message="Error: card payment UTXO not found.";}
					}
					else {$status_code=9; $message="Error: card payment transaction not found.";}
				}
			}
			else {$status_code=8; $message="Error: address not found.";}
		}
		else {$status_code=7; $message="Error: this account does not have a valid address ID.";}
		
		return [$status_code, $message];
	}
	
	public function web_api_transaction_ios($transaction_id) {
		$inputs = [];
		$outputs = [];
		
		$tx_in_q = "SELECT a.address, t.tx_hash, io.out_index, io.amount, io.spend_status, io.option_index FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_transaction_id=:transaction_id;";
		$tx_in_r = $this->run_query($tx_in_q, ['transaction_id'=>$transaction_id]);
		
		while ($input = $tx_in_r->fetch(PDO::FETCH_ASSOC)) {
			array_push($inputs, $input);
		}
		
		$tx_out_q = "SELECT io.option_index, io.spend_status, io.out_index, io.amount, a.address FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id;";
		$tx_out_r = $this->run_query($tx_out_q, ['transaction_id'=>$transaction_id]);
		
		while ($output = $tx_out_r->fetch(PDO::FETCH_ASSOC)) {
			array_push($outputs, $output);
		}
		
		return [$inputs, $outputs];
	}
	
	public function curl_post_request($url, $data, $headers) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);

		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		$contents = curl_exec($ch);
		curl_close($ch);
		return $contents;
	}
	
	public function send_login_link(&$db_thisuser, &$redirect_url, $username) {
		$access_key = $this->random_string(16);
		
		$login_url = AppSettings::getParam('base_url')."/wallet/?login_key=".$access_key;
		if (!empty($redirect_url)) $login_url .= "&redirect_key=".$redirect_url['redirect_key'];
		
		$new_login_link_params = [
			'access_key' => $access_key,
			'username' => $username,
			'time_created' => time()
		];
		$new_login_link_q = "INSERT INTO user_login_links SET access_key=:access_key, username=:username";
		if (!empty($db_thisuser['user_id'])) {
			$new_login_link_q .= ", user_id=:user_id";
			$new_login_link_params['user_id'] = $db_thisuser['user_id'];
		}
		$new_login_link_q .= ", time_created=:time_created;";
		$this->run_query($new_login_link_q, $new_login_link_params);
		
		$subject = "Click here to log in to ".AppSettings::getParam('coin_brand_name');
		
		$message = "<p>Someone just tried to log in to your ".AppSettings::getParam('coin_brand_name')." account with username: <b>".$username."</b></p>\n";
		$message .= "<p>To complete the login, please follow <a href=\"".$login_url."\">this link</a>:</p>\n";
		$message .= "<p><a href=\"".$login_url."\">".$login_url."</a></p>\n";
		$message .= "<p>If you didn't try to sign in, please delete this email.</p>\n";
		
		$delivery_id = $this->mail_async($username, AppSettings::getParam('coin_brand_name'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message, "", "", "");
	}
	
	public function first_snippet_between($string, $delim1, $delim2) {
		$startpos = strpos($string, $delim1);
		if ($startpos) {
			$snippet = substr($string, $startpos+strlen($delim1), strlen($string)-$startpos-strlen($delim1));
			$endpos = strpos($snippet, $delim2);
			if ($endpos) {
				$snippet = substr($snippet, 0, $endpos);
				return $snippet;
			}
			else return false;
		}
		else return false;
	}

	public function guess_links_containing($needle, $haystack, $delimiter, $make_unique) {
		$parts = explode($needle, $haystack);
		$urls = "";
		$u = 0;
		for ($i=1; $i<count($parts); $i++) {
			$first = substr($parts[$i-1], strrpos($parts[$i-1], $delimiter)+strlen($delimiter), strlen($parts[$i-1])-strrpos($parts[$i-1], $delimiter)-strlen($delimiter));
			$rest = substr($parts[$i], 0, strpos($parts[$i], $delimiter));
			if (strlen($rest) < 255) {
				$url = trim($first.$needle.$rest);
				
				$urls[$u] = $url;
				$u++;
			}
		}
		
		if ($make_unique) return array_values(array_unique($urls));
		else return $urls;
	}
	
	public function fetch_addresses_in_account(&$account, $option_index, $quantity) {
		$addresses = $this->run_limited_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id AND a.option_index=:option_index LIMIT :quantity;", [
			'account_id' => $account['account_id'],
			'option_index' => $option_index,
			'quantity' => $quantity
		])->fetchAll();
		
		if (count($addresses) < $quantity) {
			$currency = $this->fetch_currency_by_id($account['currency_id']);
			$blockchain = new Blockchain($this, $currency['blockchain_id']);
			$addresses_needed = $quantity-count($addresses);
			
			if ($blockchain->db_blockchain['p2p_mode'] == "rpc") { 
				$this->dbh->beginTransaction();
				$add_addresses = $this->run_limited_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id=:blockchain_id AND a.option_index=:option_index AND k.account_id IS NULL AND a.address_set_id IS NULL LIMIT :addresses_needed;", [
					'blockchain_id' => $currency['blockchain_id'],
					'option_index' => $option_index,
					'addresses_needed' => $addresses_needed
				])->fetchAll();
				
				if (count($add_addresses) > 0) {
					$add_address_ids = array_column($add_addresses, 'address_id');
					$addresses = array_merge($addresses, $add_addresses);
					$addresses_needed = $quantity-count($addresses);
					
					if (!empty($account['user_id'])) {
						$this->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id IN (".implode(",", array_map('intval', $add_address_ids)).");", [
							'user_id' => $account['user_id']
						]);
					}
					
					$this->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_id IN (".implode(",", array_map('intval', $add_address_ids)).");", [
						'account_id' => $account['account_id']
					]);
				}
				$this->dbh->commit();
			}
			else {
				for ($i=0; $i<$addresses_needed; $i++) {
					array_push($addresses, $this->gen_address_by_index($blockchain, $account, false, $option_index));
				}
			}
		}
		
		return $addresses;
	}
	
	public function fetch_address_by_id($address_id) {
		return $this->run_query("SELECT * FROM addresses WHERE address_id=:address_id;", ['address_id'=>$address_id])->fetch();
	}
	
	public function fetch_address($address) {
		return $this->run_query("SELECT * FROM addresses WHERE address=:address;", ['address'=>$address])->fetch();
	}
	
	public function calculate_effectiveness_factor($vote_effectiveness_function, $effectiveness_param1, $event_starting_block, $event_final_block, $block_id) {
		if ($vote_effectiveness_function == "linear_decrease") {
			$slope = -1*$effectiveness_param1;
			$event_length_blocks = $event_final_block-$event_starting_block+1;
			$blocks_in = $block_id-$event_starting_block;
			$frac_complete = $blocks_in/$event_length_blocks;
			$effectiveness = floor(pow(10,8)*$frac_complete*$slope)/pow(10,8) + 1;
			return max(0, $effectiveness);
		}
		else return 1;
	}
	
	public function render_bet(&$bet, &$game, $coins_per_vote, $current_round, &$net_delta, &$net_stake, &$pending_stake, &$resolved_fees_paid, &$num_wins, &$num_losses, &$num_unresolved, &$num_refunded, $div_td, $last_block_id) {
		$this_bet_html = "";
		$event_total_reward = ($bet['sum_score']+$bet['sum_unconfirmed_score'])*$coins_per_vote + $bet['sum_destroy_score'] + $bet['sum_unconfirmed_destroy_score'];
		$option_effective_reward = $bet['option_effective_destroy_score']+$bet['unconfirmed_effective_destroy_score'] + ($bet['option_votes']+$bet['unconfirmed_votes'])*$coins_per_vote;
		$current_effectiveness = $this->calculate_effectiveness_factor($bet['vote_effectiveness_function'], $bet['effectiveness_param1'], $bet['event_starting_block'], $bet['event_final_block'], $last_block_id+1);
		
		$expected_payout = 0;
		$bet_fees_paid = 0;
		
		if ($bet['spend_status'] != "unconfirmed") {
			$my_inflation_stake = $bet[$game->db_game['payout_weight']."s_destroyed"]*$coins_per_vote;
			$my_effective_stake = $bet['effective_destroy_amount'] + $bet['votes']*$coins_per_vote;
			
			if ($bet['payout_rule'] == "binary" && $option_effective_reward > 0) {
				$nofees_reward = round($event_total_reward*($my_effective_stake/$option_effective_reward));
				$bet_fees_paid = round((1-$bet['payout_rate'])*$nofees_reward);
				$expected_payout = $nofees_reward-$bet_fees_paid;
				
				if ($bet['winning_option_id'] == $bet['option_id']) {
					$resolved_fees_paid += $bet_fees_paid/pow(10,$game->db_game['decimal_places']);
				}
			}
			else if ((string)$bet['track_payout_price'] != "") $expected_payout = $bet['colored_amount'];
		}
		else {
			$unconfirmed_votes = $bet['ref_'.$game->db_game['payout_weight']."s"];
			if ($current_round != $bet['ref_round_id']) $unconfirmed_votes += $bet['colored_amount']*($current_round-$bet['ref_round_id']);
			$my_inflation_stake = $unconfirmed_votes*$coins_per_vote;
			$my_effective_stake = floor(($bet['destroy_amount']+$my_inflation_stake)*$current_effectiveness);
			
			if ($bet['payout_rule'] == "binary") {
				$nofees_reward = round($event_total_reward*($my_effective_stake/$option_effective_reward));
				$bet_fees_paid = round((1-$bet['payout_rate'])*$nofees_reward);
				$expected_payout = $nofees_reward-$bet_fees_paid;
			}
		}
		$my_stake = $bet['destroy_amount'] + $my_inflation_stake;
		
		if ($my_stake > 0) {
			$payout_multiplier = $expected_payout/$my_stake;
			
			$net_stake += $my_stake/pow(10,$game->db_game['decimal_places']);
			if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "" && $bet['outcome_index'] != -1) $pending_stake += $my_stake/pow(10,$game->db_game['decimal_places']);
			
			if ($div_td == "div") $this_bet_html .= '<div class="col-sm-1 text-center">';
			else $this_bet_html .= '<td>';
			$this_bet_html .= '<a href="';
			if ($div_td == "td") $this_bet_html .= AppSettings::getParam('base_url');
			$this_bet_html .= '/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$bet['tx_hash']."/".$bet['game_out_index'].'">';
			if ($game->db_game['inflation'] == "exponential") {
				$this_bet_html .= $this->format_bignum($my_stake/pow(10,$game->db_game['decimal_places']))."&nbsp;".$game->db_game['coin_abbreviation'];
			}
			else {
				$this_bet_html .= $this->format_bignum($bet['votes']/pow(10,$game->db_game['decimal_places']))." votes";
			}
			$this_bet_html .= "</a>";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-1 text-center";
				if ($bet['spend_status'] == "unconfirmed") $this_bet_html .= " yellowtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			$this_bet_html .= $this->format_bignum($expected_payout/pow(10,$game->db_game['decimal_places']))."&nbsp;".$game->db_game['coin_abbreviation'];
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-1 text-center";
				if ($bet['spend_status'] == "unconfirmed") $this_bet_html .= " yellowtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			if ($bet['payout_rule'] == "binary") $this_bet_html .= "x".$this->round_to($payout_multiplier, 2, 4, true);
			else $this_bet_html .= "N/A";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-1";
				if ($bet['spend_status'] == "unconfirmed") $this_bet_html .= " yellowtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			$this_bet_html .= round($bet['effectiveness_factor']*100, 2)."%";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") $this_bet_html .= "<div class=\"col-sm-2 text-center\">";
			else $this_bet_html .= "<td>";
			$this_bet_html .= $bet['option_name'];
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") $this_bet_html .= "<div class=\"col-sm-3\">";
			else $this_bet_html .= "<td>";
			$this_bet_html .= "<a target=\"_blank\" href=\"";
			if ($div_td == "td") $this_bet_html .= AppSettings::getParam('base_url');
			$this_bet_html .= "/explorer/games/".$game->db_game['url_identifier']."/events/".$bet['event_index']."\">".$bet['event_name']."</a>";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			$pct_gain = false;
			
			if ($bet['outcome_index'] == -1) {
				$outcome_txt = "Refunded";
				$num_refunded++;
			}
			else if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "") {
				$outcome_txt = "Not Resolved";
				$num_unresolved++;
			}
			else {
				if ($bet['payout_rule'] == "binary") {
					if ($bet['winning_option_id'] == $bet['option_id']) {
						$outcome_txt = "Won";
						$delta = ($expected_payout - $my_stake)/pow(10,$game->db_game['decimal_places']);
						$num_wins++;
					}
					else {
						$outcome_txt = "Lost";
						$delta = (-1)*$my_stake/pow(10,$game->db_game['decimal_places']);
						$num_losses++;
					}
				}
				else {
					$delta = ($expected_payout - $my_stake)/pow(10,$game->db_game['decimal_places']);
					$pct_gain = round(100*($expected_payout/$my_stake-1), 2);
					
					if ($delta >= 0) {
						$outcome_txt = "Won";
						$num_wins++;
					}
					else {
						$outcome_txt = "Lost";
						$num_losses++;
					}
				}
				$net_delta += $delta;
			}
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-3";
				if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "") {}
				else if ($delta >= 0) $this_bet_html .= " greentext";
				else $this_bet_html .= " redtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			$this_bet_html .= $outcome_txt;
			
			if (!empty($bet['winning_option_id']) || (string)$bet['track_payout_price'] != "") {
				$this_bet_html .= " &nbsp;&nbsp; ";
				if ($delta >= 0) $this_bet_html .= "+";
				else $this_bet_html .= "-";
				$this_bet_html .= $this->format_bignum(abs($delta));
				$this_bet_html .= " ".$game->db_game['coin_abbreviation'];
				
				if ($pct_gain !== false) {
					$this_bet_html .= " &nbsp; ";
					if ($pct_gain >= 0) $this_bet_html .= "+";
					else $this_bet_html .= "-";
					$this_bet_html .= abs($pct_gain)."%";
				}
			}
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
		}
		return $this_bet_html;
	}
	
	public function bets_summary(&$game, &$net_stake, &$num_wins, &$num_losses, &$num_unresolved, &$num_refunded, &$pending_stake, &$net_delta, &$resolved_fees_paid) {
		$num_resolved = $num_wins+$num_losses+$num_refunded;
		if ($num_wins+$num_losses > 0) $win_rate = $num_wins/($num_wins+$num_losses);
		else $win_rate = 0;
		$num_bets = $num_resolved+$num_unresolved;
		
		$adjusted_net_delta = $net_delta+$resolved_fees_paid;
		
		$html = number_format($num_bets)." bets totalling <font class=\"greentext\">".$this->format_bignum($net_stake)."</font> ".$game->db_game['coin_name_plural'].".<br/>\n";
		$html .= "You've won ".number_format($num_wins)." of your ".number_format($num_wins+$num_losses)." resolved bets (".round($win_rate*100, 1)."%). ";
		if ($resolved_fees_paid > 0) $html .= "You paid <font class=\"redtext\">".$this->format_bignum($resolved_fees_paid)."</font> ".$game->db_game['coin_name_plural']." in fees and made ";
		else $html .= "You made ";
		$html .= " a net ";
		if ($adjusted_net_delta >= 0) $html .= "gain";
		else $html .= "loss";
		$html .= " of <font class=\"";
		if ($adjusted_net_delta >= 0) $html .= "greentext";
		else $html .= "redtext";
		$html .= "\">".$this->format_bignum(abs($adjusted_net_delta))."</font> ".$game->db_game['coin_name_plural']." on your bets.";
		if ($num_unresolved > 0 || $num_refunded > 0) {
			$html .= "\n<br/>";
			if ($num_refunded > 0) $html .= number_format($num_refunded)." of your bets were refunded";
			if ($num_unresolved > 0 && $num_refunded > 0) $html .= " and you have ";
			else if ($num_unresolved > 0) $html .= "You have ";
			if ($num_unresolved > 0) $html .= number_format($num_unresolved)." pending bets totalling <font class=\"greentext\">".$this->format_bignum($pending_stake)."</font> ".$game->db_game['coin_name_plural'];
			$html .= ".";
		}
		return $html;
	}
	
	public function import_group_from_file($import_group_description, &$error_message) {
		$import_group_fname = AppSettings::srcPath()."/lib/groups/".$import_group_description.".csv";
		
		if (is_file($import_group_fname)) {
			$import_group_fh = fopen($import_group_fname, 'r');
			$import_group_content = fread($import_group_fh, filesize($import_group_fname));
			fclose($import_group_fh);
			
			$general_entity_type = $this->check_set_entity_type("general entity");
			
			$csv_lines = explode("\n", $import_group_content);
			$header_vars = explode(",", trim(strtolower($csv_lines[0])));
			$name_col = array_search("entity_name", $header_vars);
			$image_col = array_search("default_image_id", $header_vars);
			$group_params = explode(",", $csv_lines[1]);
			
			$this->run_query("INSERT INTO option_groups SET option_name=:option_name, option_name_plural=:option_name_plural, description=:description;", [
				'option_name' => $group_params[0],
				'option_name_plural' => $group_params[1],
				'description' => $import_group_description
			]);
			$group_id = $this->last_insert_id();
			
			for ($csv_i=2; $csv_i<count($csv_lines); $csv_i++) {
				$csv_params = explode(",", $csv_lines[$csv_i]);
				$member_entity = $this->check_set_entity($general_entity_type['entity_type_id'], $csv_params[$name_col]);
				
				if (empty($member_entity['default_image_id']) && !empty($csv_params[$image_col])) {
					$this->run_query("UPDATE entities SET default_image_id=:default_image_id WHERE entity_id=:entity_id;", [
						'default_image_id' => $csv_params[$image_col],
						'entity_id' => $member_entity['entity_id']
					]);
				}
				$this->run_query("INSERT INTO option_group_memberships SET option_group_id=:option_group_id, entity_id=:entity_id;", [
					'option_group_id' => $group_id,
					'entity_id' => $member_entity['entity_id']
				]);
			}
		}
		else $error_message = "Failed to import group from file.. the file does not exist.\n";
	}
	
	public function flush_buffers() {
		@ob_end_flush();
		@ob_flush();
		@flush();
		@ob_start();
	}
	
	public function fetch_group_by_description($description) {
		return $this->run_query("SELECT * FROM option_groups WHERE description=:description;", ['description'=>$description])->fetch();
	}
	
	public function fetch_group_by_id($group_id) {
		return $this->run_query("SELECT * FROM option_groups WHERE group_id=:group_id;", ['group_id'=>$group_id])->fetch();
	}
	
	public function running_as_admin() {
		if (AppSettings::runningFromCommandline()) return true;
		else if (empty(AppSettings::getParam('operator_key')) || $_REQUEST['key'] == AppSettings::getParam('operator_key')) return true;
		else return false;
	}
	
	public function refresh_address_set_indices(&$address_set) {
		$info = $this->run_query("SELECT MAX(option_index) FROM addresses WHERE address_set_id=:address_set_id;", ['address_set_id'=>$address_set['address_set_id']])->fetch();
		if ($info['MAX(option_index)'] > 0) {
			$this->run_query("UPDATE address_sets SET has_option_indices_until=:has_option_indices_until WHERE address_set_id=:address_set_id;", [
				'has_option_indices_until' => $info['MAX(option_index)'],
				'address_set_id' => $address_set['address_set_id']
			]);
			$address_set['has_option_indices_until'] = $info['MAX(option_index)'];
		}
	}
	
	public function finish_address_sets(&$game, &$game_addrsets, $to_option_index) {
		$fully_successful = true;
		$account = false;
		
		for ($set_i=0; $set_i<count($game_addrsets); $set_i++) {
			if ($game_addrsets[$set_i]['has_option_indices_until'] >= $to_option_index) {}
			else {
				$this->refresh_address_set_indices($game_addrsets[$set_i]);
				
				if ((string)$game_addrsets[$set_i]['has_option_indices_until'] === "") $from_option_index = 0;
				else if ($game_addrsets[$set_i]['has_option_indices_until'] == 0) $from_option_index = 1;
				else $from_option_index = $game_addrsets[$set_i]['has_option_indices_until']+1;
				
				$has_option_indices_until = false;
				$set_successful = true;
				
				for ($option_index=$from_option_index; $option_index<=$to_option_index; $option_index++) {
					if ($game->blockchain->db_blockchain['p2p_mode'] != "rpc") {
						$this->gen_address_by_index($game->blockchain, $account, $game_addrsets[$set_i]['address_set_id'], $option_index);
						
						$has_option_indices_until = $option_index;
					}
					else {
						$address = $this->run_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id=:blockchain_id AND a.option_index=:option_index AND k.account_id IS NULL AND a.address_set_id IS NULL LIMIT 1;", [
							'blockchain_id' => $game->blockchain->db_blockchain['blockchain_id'],
							'option_index' => $option_index
						])->fetch();
						
						if ($address) {
							$this->run_query("UPDATE addresses SET address_set_id=:address_set_id WHERE address_id=:address_id;", [
								'address_set_id' => $game_addrsets[$set_i]['address_set_id'],
								'address_id' => $address['address_id']
							]);
							
							$has_option_indices_until = $option_index;
						}
						else {
							$set_successful = false;
							$set_i = count($game_addrsets);
							$option_index = $to_option_index+1;
						}
					}
				}
				
				if ($has_option_indices_until !== false) {
					$this->run_query("UPDATE address_sets SET has_option_indices_until=:has_option_indices_until WHERE address_set_id=:address_set_id;", [
						'has_option_indices_until' => $has_option_indices_until,
						'address_set_id' => $game_addrsets[$set_i]['address_set_id']
					]);
				}
				
				if (!$set_successful) $fully_successful = false;
			}
		}
		
		return $fully_successful;
	}
	
	public function apply_address_set(&$game, $account_id) {
		$address_set = $this->run_query("SELECT * FROM address_sets WHERE game_id=:game_id AND applied=0 AND has_option_indices_until IS NOT NULL ORDER BY RAND() LIMIT 1;", [
			'game_id' => $game->db_game['game_id']
		])->fetch();
		
		if ($address_set) {
			$this->refresh_address_set_indices($address_set);
			
			$this->run_query("UPDATE address_sets SET applied=1 WHERE address_set_id=:address_set_id;", ['address_set_id'=>$address_set['address_set_id']]);
			
			$this->run_query("UPDATE addresses a JOIN address_keys k ON a.address_id=k.address_id SET k.account_id=:account_id WHERE a.address_set_id=:address_set_id AND k.account_id IS NULL;", [
				'account_id' => $account_id,
				'address_set_id' => $address_set['address_set_id']
			]);
			
			$this->run_query("UPDATE currency_accounts SET has_option_indices_until=:has_option_indices_until WHERE account_id=:account_id;", [
				'has_option_indices_until' => $address_set['has_option_indices_until'],
				'account_id' => $account_id
			]);
		}
	}
	
	public function gen_address_by_index(&$blockchain, &$account, $address_set_id, $option_index) {
		if ($blockchain->db_blockchain['p2p_mode'] != "rpc") {
			$vote_identifier = $this->option_index_to_vote_identifier($option_index);
			$addr_text = "11".$vote_identifier;
			$addr_text .= $this->random_string(34-strlen($addr_text));
			
			if ($option_index == 0) $is_destroy_address=1;
			else $is_destroy_address=0;
			
			if ($option_index == 1) $is_separator_address=1;
			else $is_separator_address=0;
			
			$new_address_params = [
				'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
				'option_index' => $option_index,
				'vote_identifier' => $vote_identifier,
				'is_destroy_address' => $is_destroy_address,
				'is_separator_address' => $is_separator_address,
				'address' => $addr_text,
				'time_created' => time()
			];
			$new_address_q = "INSERT INTO addresses SET is_mine=1";
			if ($account && !empty($account['user_id'])) {
				$new_address_q .= ", user_id=:user_id";
				$new_address_params['user_id'] = $account['user_id'];
			}
			if ($address_set_id) {
				$new_address_q .= ", address_set_id=:address_set_id";
				$new_address_params['address_set_id'] = $address_set_id;
			}
			$new_address_q .= ", primary_blockchain_id=:blockchain_id, option_index=:option_index, vote_identifier=:vote_identifier, is_destroy_address=:is_destroy_address, is_separator_address=:is_separator_address, address=:address, time_created=:time_created;";
			$this->run_query($new_address_q, $new_address_params);
			$address_id = $this->last_insert_id();
			$this->flush_buffers();
			
			$new_key_params = [
				'address_id' => $address_id,
				'pub_key' => $addr_text
			];
			$new_key_q = "INSERT INTO address_keys SET address_id=:address_id";
			if ($account) {
				$new_key_q .= ", account_id=:account_id";
				$new_key_params['account_id'] = $account['account_id'];
			}
			$new_key_q .= ", save_method='fake', pub_key=:pub_key;";
			$this->run_query($new_key_q, $new_key_params);
			$address_key_id = $this->last_insert_id();
			
			return $this->run_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id=:address_id AND k.address_key_id=:address_key_id;", [
				'address_id' => $address_id,
				'address_key_id' => $address_key_id
			])->fetch();
		}
	}
	
	public function safe_fetch_url($url) {
		if (AppSettings::getParam('api_proxy_url')) $safe_url = AppSettings::getParam('api_proxy_url').urlencode($url);
		else $safe_url = str_replace('&amp;', '&', $url);
		
		$arrContextOptions = [
			"ssl" => [
				"verify_peer"=>false,
				"verify_peer_name"=>false,
			],
		];
		
		return file_get_contents($safe_url, false, stream_context_create($arrContextOptions));
	}
	
	public function set_entity_image_from_url($image_url, $entity_id, &$error_message) {
		$db_image = false;
		$image_fname_parts = explode(".", $image_url);
		$image_extension = trim($image_fname_parts[count($image_fname_parts)-1]);
		
		if ($raw_image = $this->safe_fetch_url($image_url)) {
			$access_key = $this->random_string(20);
			
			$db_image = $this->add_image($raw_image, $image_extension, $access_key, $error_message);
			
			if ($db_image) {
				$this->run_query("UPDATE entities SET default_image_id=:default_image_id WHERE entity_id=:entity_id;", [
					'default_image_id' => $db_image['image_id'],
					'entity_id' => $entity_id
				]);
				$this->run_query("UPDATE options SET image_id=:image_id WHERE entity_id=:entity_id AND image_id IS NULL;", [
					'image_id' => $db_image['image_id'],
					'entity_id' => $entity_id
				]);
				
				$error_message .= "Added image #".$db_image['image_id']." (".strlen($raw_image).")<br/>\n";
			}
			else $error_message .= "Error creating image.<br/>\n";
		}
		else $error_message .= "Failed to fetch $image_url<br/>\n";
		
		return $db_image;
	}
	
	public function change_user_game($thisuser, $game, $user_game_id) {
		if ($user_game_id == "new") {
			$select_user_game = $thisuser->ensure_user_in_game($game, true);
			$thisuser->set_selected_user_game($game, $select_user_game['user_game_id']);
		}
		else {
			$select_user_game = $this->run_query("SELECT * FROM user_games WHERE user_game_id=:user_game_id;", ['user_game_id'=>$user_game_id])->fetch();
			
			if ($select_user_game && $select_user_game['user_id'] == $thisuser->db_user['user_id'] && $select_user_game['game_id'] == $game->db_game['game_id']) {
				$thisuser->set_selected_user_game($game, $select_user_game['user_game_id']);
			}
		}
	}
	
	public function any_normal_address_in_account($account_id) {
		return $this->run_query("SELECT * FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id WHERE ak.account_id=:account_id AND a.is_destroy_address=0 AND a.is_separator_address=0 ORDER BY a.option_index ASC LIMIT 1;", [
			'account_id' => $account_id
		])->fetch();
	}
	
	public function fetch_strategy_by_id($strategy_id) {
		return $this->run_query("SELECT * FROM user_strategies WHERE strategy_id=:strategy_id;", ['strategy_id'=>$strategy_id])->fetch();
	}
	
	public function fetch_io_by_hash_out_index($blockchain_id, &$tx_hash, $out_index) {
		return $this->run_query("SELECT io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.tx_hash=:tx_hash AND t.blockchain_id=:blockchain_id AND io.out_index=:out_index;", [
			'tx_hash' => $tx_hash,
			'blockchain_id' => $blockchain_id,
			'out_index' => $out_index
		])->fetch();
	}
	
	public function spendable_ios_in_account($account_id, $game_id, $round_id, $last_block_id) {
		$spendable_io_params = [
			'account_id' => $account_id,
			'game_id' => $game_id
		];
		$spendable_io_q = "SELECT *, COUNT(*), SUM(gio.is_resolved) AS num_resolved, SUM(gio.colored_amount) AS coins";
		if ($last_block_id !== false) {
			$spendable_io_q .= ", SUM(gio.colored_amount)*(:ref_block_id-io.create_block_id) AS coin_blocks";
			$spendable_io_params['ref_block_id'] = ($last_block_id+1);
		}
		if ($round_id !== false) {
			$spendable_io_q .= ", SUM(gio.colored_amount*(:ref_round_id-gio.create_round_id)) AS coin_rounds";
			$spendable_io_params['ref_round_id'] = $round_id;
		}
		$spendable_io_q .= " FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status IN ('unspent','unconfirmed') AND k.account_id=:account_id AND gio.game_id=:game_id GROUP BY gio.io_id HAVING COUNT(*)=num_resolved ORDER BY io.io_id ASC;";
		return $this->run_query($spendable_io_q, $spendable_io_params);
	}
	
	public function fetch_blockchain_by_identifier($blockchain_identifier) {
		return $this->run_query("SELECT * FROM blockchains WHERE url_identifier=:url_identifier;", [
			'url_identifier' => $blockchain_identifier
		])->fetch();
	}
	
	public function fetch_blockchain_by_id($blockchain_id) {
		return $this->run_query("SELECT * FROM blockchains WHERE blockchain_id=:blockchain_id;", ['blockchain_id'=>$blockchain_id])->fetch();
	}
	
	public function fetch_user_game_by_api_key($api_key) {
		return $this->run_query("SELECT *, u.user_id AS user_id, g.game_id AS game_id FROM users u JOIN user_games ug ON u.user_id=ug.user_id JOIN games g ON ug.game_id=g.game_id JOIN user_strategies s ON ug.strategy_id=s.strategy_id LEFT JOIN featured_strategies fs ON s.featured_strategy_id=fs.featured_strategy_id WHERE ug.api_access_code=:api_access_code;", ['api_access_code'=>$api_key])->fetch();
	}
	
	public function fetch_io_by_id($io_id) {
		return $this->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id=:io_id;", ['io_id'=>$io_id])->fetch();
	}
	
	public function fetch_peer_by_id($peer_id) {
		return $this->run_query("SELECT * FROM peers WHERE peer_id=:peer_id;", ['peer_id'=>$peer_id])->fetch();
	}
	
	public function fetch_event_by_id($event_id) {
		return $this->run_query("SELECT * FROM events WHERE event_id=:event_id;", ['event_id'=>$event_id])->fetch();
	}
	
	public function fetch_option_by_id($option_id) {
		return $this->run_query("SELECT * FROM options op JOIN events ev ON op.event_id=ev.event_id WHERE op.option_id=:option_id;", ['option_id'=>$option_id])->fetch();
	}
	
	public function fetch_options_by_event($event_id, $require_entities=false) {
		$options_q = "SELECT * FROM options op";
		if ($require_entities) $options_q .= " LEFT JOIN entities en ON op.entity_id=en.entity_id";
		$options_q .= " WHERE op.event_id=:event_id ORDER BY op.option_index ASC;";
		return $this->run_query($options_q, ['event_id'=>$event_id]);
	}
	
	public function fetch_option_by_event_option_index($event_id, $event_option_index) {
		return $this->run_query("SELECT * FROM options WHERE event_id=:event_id AND event_option_index=:event_option_index;", [
			'event_id' => $event_id,
			'event_option_index' => $event_option_index
		])->fetch();
	}
	
	public function fetch_card_by_peer_and_id($peer_id, $card_id) {
		return $this->run_query("SELECT * FROM cards WHERE peer_card_id=:card_id AND peer_id=:peer_id;", [
			'card_id' => $card_id,
			'peer_id' => $peer_id
		])->fetch();
	}
	
	public function fetch_user_game($user_id, $game_id) {
		return $this->run_query("SELECT * FROM user_games WHERE user_id=:user_id AND game_id=:game_id ORDER BY selected DESC;", [
			'user_id' => $user_id,
			'game_id' => $game_id
		])->fetch();
	}
	
	public function fetch_user_by_id($user_id) {
		return $this->run_query("SELECT * FROM users WHERE user_id=:user_id;", ['user_id'=>$user_id])->fetch();
	}
	
	public function fetch_user_by_username($username) {
		return $this->run_query("SELECT * FROM users WHERE username=:username;", ['username'=>$username])->fetch();
	}
	
	public function fetch_recycle_ios_in_account($account_id, $quantity) {
		return $this->run_limited_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id AND a.is_destroy_address=1 AND io.spend_status='unspent' ORDER BY io.amount DESC LIMIT :quantity;", ['account_id'=>$account_id, 'quantity'=>$quantity])->fetchAll();
	}
	
	public function set_strategy_time_next_apply($strategy_id, $time_next_apply) {
		$this->run_query("UPDATE user_strategies SET time_next_apply=:time_next_apply WHERE strategy_id=:strategy_id;", [
			'time_next_apply' => $time_next_apply,
			'strategy_id' => $strategy_id
		]);
	}
	
	public function load_module_classes() {
		try {
			$all_dbs = $this->run_query("SHOW DATABASES;");
			if ($all_dbs->rowCount() > 0) {
				try {
					$all_modules = $this->run_query("SELECT * FROM modules ORDER BY module_id ASC;");
					
					while ($module = $all_modules->fetch()) {
						include(AppSettings::srcPath()."/modules/".$module['module_name']."/".$module['module_name']."GameDefinition.php");
					}
				}
				catch(Exception $ee) {}
			}
		}
		catch (Exception $e) {}
	}
	
	public function fetch_game_defined_event_by_id($game_id, $game_defined_event_id) {
		return $this->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND game_defined_event_id=:game_defined_event_id;", [
			'game_id' => $game_id,
			'game_defined_event_id' => $game_defined_event_id
		])->fetch(PDO::FETCH_ASSOC);
	}
	
	public function fetch_game_defined_event_by_index($game_id, $event_index) {
		return $this->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_index=:event_index;", [
			'game_id' => $game_id,
			'event_index' => $event_index
		])->fetch();
	}
	
	public function fetch_game_defined_options($game_id, $event_index, $event_option_index, $require_entity_type) {
		$gdo_q = "SELECT *";
		if ($require_entity_type) $gdo_q .= ", e.entity_name AS entity_name";
		$gdo_q .= " FROM game_defined_options gdo";
		if ($require_entity_type) $gdo_q .= " LEFT JOIN entities e ON gdo.entity_id=e.entity_id LEFT JOIN entity_types et ON e.entity_type_id=et.entity_type_id";
		$gdo_q .= " WHERE gdo.game_id=:game_id AND gdo.event_index=:event_index";
		$gdo_params = [
			'game_id' => $game_id,
			'event_index' => $event_index
		];
		
		if ($event_option_index !== false) {
			$gdo_q .= " AND gdo.option_index=:event_option_index";
			$gdo_params['event_option_index'] = $event_option_index;
		}
		else $gdo_q .= " ORDER BY gdo.option_index ASC";
		
		return $this->run_query($gdo_q, $gdo_params);
	}
	
	public function fetch_game_defined_option_by_id($game_id, $gdo_id) {
		return $this->run_query("SELECT * FROM game_defined_options WHERE game_id=:game_id AND game_defined_option_id=:game_defined_option_id;", [
			'game_id' => $game_id,
			'game_defined_option_id' => $gdo_id
		])->fetch();
	}
	
	public function fetch_currency_by_name($currency_name) {
		return $this->run_query("SELECT * FROM currencies WHERE name=:name;", ['name'=>$currency_name])->fetch();
	}
	
	public function fetch_running_games() {
		return $this->run_query("SELECT * FROM games WHERE game_status='running';");
	}
	
	public function fetch_account_by_user_and_address($user_id, $address_id) {
		return $this->run_query("SELECT * FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id=:address_id AND c.user_id=:user_id;", [
			'address_id' => $address_id,
			'user_id' => $user_id
		])->fetch();
	}
	
	public function create_new_account($params) {
		$params['time_created'] = time();
		
		if (!isset($params['game_id'])) $params['game_id'] = null;
		if (!isset($params['user_id'])) $params['user_id'] = null;
		if (!isset($params['is_faucet'])) $params['is_faucet'] = 0;
		if (!isset($params['is_escrow_account'])) $params['is_escrow_account'] = 0;
		if (!isset($params['is_game_sale_account'])) $params['is_game_sale_account'] = 0;
		if (!isset($params['is_blockchain_sale_account'])) $params['is_blockchain_sale_account'] = 0;
		
		$this->run_query("INSERT INTO currency_accounts SET currency_id=:currency_id, game_id=:game_id, user_id=:user_id, account_name=:account_name, is_faucet=:is_faucet, is_escrow_account=:is_escrow_account, is_game_sale_account=:is_game_sale_account, is_blockchain_sale_account=:is_blockchain_sale_account, time_created=:time_created;", $params);
		
		return $this->fetch_account_by_id($this->last_insert_id());
	}
}
?>