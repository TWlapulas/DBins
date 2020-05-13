<?php
class Select {
		private $path;
		private $rows;
		private $head;
		private $keys;

		function __construct($path, $rows) {
				$this->path = $path;
				$this->rows = $rows;
				if ($this->rows) {  // 有資料
						$this->head = array_keys($this->rows[0]);
						$this->keys = array_column($this->rows, $this->head[0]);
				}
		}

		function count() { return sizeof($this->rows); }

		function column($item) {
				if (in_array($item, $this->head))
						return array_column($this->rows, $item);
				else
						return array();
		}
	
		function get($length=0) {
				if ($length == 0) return $this->rows;
				else return array_slice($this->rows, 0, $length);		
		}

		function show() {
				include str_replace(basename($this->path), 'showme.php', $this->path);
		}

		function find($key, $val, $cap=1) {  // 找出 key 為 val 的資料，最多 cap 筆
				$collect = array();
				if ($column = array_column($this->rows, $key)) {
						if ($cap <= 0) $cap = sizeof($column);
						foreach ($column as $idx => $col_val) {
								if (sizeof($collect) < $cap) {
										if ($col_val == $val) array_push($collect, $idx);
								} else break;
						}
				} 
				return $this->co_values($this->rows, $collect);
		}

		function sort($column, $ascend=true) {
				$target = array_column($this->rows, $column);  
				if ($ascend) asort($target, SORT_NATURAL);
				else arsort($target, SORT_NATURAL);
				$this->rows = $this->co_values($this->rows, array_keys($target));
				// keys always follow rows
				$this->keys = array_column($this->rows, $this->head[0]);
		}

		function reverse() {
				$this->rows = array_reverse($this->rows, false);
				$this->keys = array_reverse($this->keys, false);
		}

		private function co_values($master, $request) {  // corresponding values
				$values = array();
				foreach ($request as $key)
						array_push($values, $master[$key]);
				return $values;
		}
}


class Form {
		public  $Select;
		private $path;  // path/of/database/form
		private $rows;
		private $head;
		private $keys;  // key values
		private $pswd;  // password
		private $mobo;  // modifiable
		private $changed;  // 用來判斷資料是否改變

		function __construct($path, $rows, $pswd) {  
    		$this->path = $path;
				$this->rows = $rows;
				$this->pswd = $pswd;
				if ($this->rows) {	// 有資料
						$this->changed = false;
						$this->head = array_keys($this->rows[0]);
						$this->keys = array_column($this->rows, $this->head[0]);
						$this->mobo = array_slice($this->head, 1);
				} else {
						$this->changed = true;
						$this->keys = array();
				}
  	}

		function append($row) {  
		// The first item is the key value
				if (!$this->isassociative($row))
						throw new Exception("row must be an associative array");
		//  --- push the row ---
				if (isset($this->head)) {
						if ($this->head !== array_keys($row))  
								throw new Exception("Content and order of headers must match");
						if (in_array($row[$this->head[0]], $this->keys)) 
								throw new Exception("Key conflict");
				} else {
						$this->head = array_keys($row);
						$this->mobo = array_slice($this->head, 1);
				}	
				array_push($this->rows, $row);
				array_push($this->keys, $row[$this->head[0]]);
				$this->changed = true;
				unset($this->Select);
		}

		function delete($key) {
				if (false === $idx = array_search($key, $this->keys)) {
						return false;
				} else {
						unset($this->rows[$idx]);
						unset($this->keys[$idx]);
						$this->rows = array_values($this->rows);  // reindex
						$this->keys = array_values($this->keys);
				}
				$this->changed = true;
				unset($this->Select);
		}

		function modify($key, $assoArray) {
				$project = array_keys($assoArray);
				$this->joint($project, $this->mobo);
				if (!array_diff($project, $this->mobo)) {
						$original = $this->row($key);
						foreach ($assoArray as $k => $v) $original[$k] = $v;
						$this->delete($key);
						$this->append($original);
				} else throw new Exception("Modify failed: illegal action exists");
				$this->changed = true;
				unset($this->Select);
		}

		function select_all() {
				$args = func_get_args();   // 取得此函數的所有參數 
				if ($args) {
						$rows = $this->rows;
    				foreach ($args as $arg) {
								if ($rows) {
										$desc = $this->parse($arg);
										$rows = $this->select($rows, $desc);
								} else break;
						}
						$this->Select = new Select($this->path, $rows);
				} else
						$this->Select = new Select($this->path, $this->rows);
		}

		function select_any() {
				$args = func_get_args();   // 取得此函數的所有參數 
				if ($args) {
						$selected_rowsets = array();  
						if ($this->rows) 
								foreach ($args as $arg) {
										$desc = $this->parse($arg);
										array_push($selected_rowsets, $this->select($this->rows, $desc));
								} 
						$selected_rows = array();
						foreach ($selected_rowsets as $rowset) 
								$this->joint($selected_rows, $rowset);
						$this->Select = new Select($this->path, $selected_rows);
				} else
						$this->Select = new Select($this->path, $this->rows);
		}

		function get() {return $this->rows;}

		function show() {
				include str_replace(basename($this->path), 'showme.php', $this->path);
		}

		function path() {return $this->path;}

		private function row($key) {
				if (false === $idx = array_search($key, $this->keys)) 
						throw new Exception("no such key");
				else 
						return $this->rows[$idx];
		}

		private function select($obj, $condition) {
				$column = array_column($obj, $condition['col']);
				$val = $condition['val'];
				$match = array();
				switch ($condition['cmp']) {
    				case ">" :
								foreach ($column as $idx => $col_val) 
										if ($col_val >  $val) array_push($match, $obj[$idx]);
								break;
    				case ">=":
								foreach ($column as $idx => $col_val) 
										if ($col_val >= $val) array_push($match, $obj[$idx]);
								break;
    				case "=" :
								foreach ($column as $idx => $col_val) 
										if ($col_val == $val) array_push($match, $obj[$idx]);
								break;
						case "<=":
								foreach ($column as $idx => $col_val) 
										if ($col_val <= $val) array_push($match, $obj[$idx]);
								break;
						case "<" :
								foreach ($column as $idx => $col_val) 
										if ($col_val <  $val) array_push($match, $obj[$idx]);
								break;
    				default  :
								throw new Exception("Syntax error");
				}
				return $match;
		}

		private function parse($str) {
				$keys = array('col', 'cmp', 'val');
				$vals = array();
				foreach (explode(' ', $str) as $val) 
						if ($val !== '') array_push($vals, $val);
				return array_combine($keys, $vals);
		}

		private function isassociative($ary) {
		// Checking for sequential keys of array ary
				if(array_keys($ary) !== range(0, count($ary) - 1)) 
						return true; 
				else
						return false; 
		}

		private function encrypt($plaintext, $key) {
				$cipher = "aes-128-gcm";
				if (in_array($cipher, openssl_get_cipher_methods())) {
    				$ivlen = openssl_cipher_iv_length($cipher);
    				$iv = openssl_random_pseudo_bytes($ivlen);
						$ciphertext = openssl_encrypt($plaintext, $cipher, $key, $options=0, $iv, $tag);
						$final = $iv.'::'.$ciphertext.'::'.$tag;
						$parse = explode('::', $final, 3);
						$check = openssl_decrypt($parse[1], $cipher, $key, $options=0, $parse[0], $parse[2]);
						if ($check == $plaintext) return $final;
				}  
				return false;
		}

		private function joint(&$ary1, $ary2) {
				foreach ($ary2 as $item) 
						if (!in_array($item, $ary1, true)) 
								array_push($ary1, $item);
		}

		function __destruct() {
				if ($this->changed) {
						$json = json_encode($this->rows, JSON_NUMERIC_CHECK);
						$ciphertext = $this->encrypt($json, $this->pswd);
						if ($ciphertext == false) {
								trigger_error('Encryption failed', E_USER_WARNING);
						} else {				
								file_put_contents($this->path, base64_encode($ciphertext));
						}
				}
				file_put_contents($this->path.'.token', '');
  	}
}


class DBins {
		public  $Form;
  	private $path;  // path/of/database
		private $pswd;  // password
		const EXTENSION = '.fom';

  	function __construct($path, $pswd) {
    		$this->path = realpath($path);
				$this->pswd = $this->hash($pswd);
				if (!file_exists("$this->path/showme.php"))
						$this->load_showme();
				if (!file_exists("$this->path/busy.html"))
						$this->load_busy();
  	}
// create a form
		function create($name) {  
				$file = "$this->path/$name".self::EXTENSION;
				if (file_exists($file)) {
						throw new Exception("File already exists");
				} else {
						$this->Form = new Form($file, array(), $this->pswd);
				}
		}
// get a form
		function get($name) {
				$file = "$this->path/$name".self::EXTENSION;
				if (!file_exists($file)) 
						throw new Exception("File does not exist");
				if (isset($this->Form))
						if ($this->Form->path() == $file) 
								return;
						else
								throw new Exception("can not get before free");
				if ($this->token_borrow($file.'.token')) {
						$ciphertext = base64_decode(file_get_contents($file));
						$json = $this->decrypt($ciphertext, $this->pswd);
						$rows = json_decode($json, true, 9, JSON_NUMERIC_CHECK);
						$this->Form = new Form($file, $rows, $this->pswd);
				} else {  // Server is busy
						$this->busy();
				}
		}
// remove a form
		function remove($name) {
				$file = "$this->path/$name".self::EXTENSION;
				if (isset($this->Form) and $this->Form->path() == $file) 
						throw new Exception("can not remove the form: Using");
				else 
						return unlink($file) and unlink($file.'.token');
		}
// unget a form
		function free() {
		//$formPath = "$this->path/$name".self::EXTENSION;
				if (isset($this->Form)) unset($this->Form);
				else;
		}

		private function decrypt($ciphertext, $key) {
				$cipher = "aes-128-gcm";
				if (in_array($cipher, openssl_get_cipher_methods())) {
						$parsing = explode('::', $ciphertext, 3);
						$iv = $parsing[0];
						$tag = $parsing[2];
    				return openssl_decrypt($parsing[1], $cipher, $key, $options=0, $iv, $tag);
				} 
				throw new Exception("Decryption failed");
		}

		private function load_showme() {
		// Initialize a file URL to the variable 
				$url = 'https://template--wkankan.repl.co/showme.php'; 
		// Use basename() function to return the base name of file 
				$file_name = "$this->path/".basename($url); 
				if (!$contents = file_get_contents($url))
						throw new Exception("download failed: showme.php");
				else
						file_put_contents($file_name, $contents);
		}

		private function load_busy() {
		// Initialize a file URL to the variable 
				$url = 'https://template--wkankan.repl.co/busy.html'; 
		// Use basename() function to return the base name of file 
				$file_name = "$this->path/".basename($url); 
				if (!$contents = file_get_contents($url))
						throw new Exception("download failed: busy.html");
				else
						file_put_contents($file_name, $contents);
		}

		private function busy() { include_once "$this->path/busy.html"; }

		private function hash($obj, $salt='Gh48yp') {
				$apply  = substr($salt, 0, 2);
				$remain = substr($salt, 2);
				if ($apply) 
						return $this->hash(crypt($obj, $apply), $remain);
				else
						return $obj;
		}

		private function token_borrow($token, $retry=10) {
				if (unlink($token))
						return true;
				else {
						if ($retry) {
								usleep(rand(500000, 3000000));
								return $this->token_borrow($token, --$retry);
						} else return false;
				}
		}

  	function __destruct() {}
}


