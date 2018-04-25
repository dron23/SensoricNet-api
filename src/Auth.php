<?php

namespace SensoricNet;

use PDO;

class Auth {
	
	// hodnoty předávané při přihlašování uživatele
	public $username;
	public $password;
	
	// hodnoty o uživateli
	public $user_id;
	public $firstname;
	public $lastname;
	
	// interní hodnoty
	private $is_logged;
	private $is_admin;
	
	public $last_message;
	private $session_login_string;
	private $ip;
	private $last_time;
	
	// nastavení délky nečinosti uľivatele před odhláąením
	public $checktimelimit;
	
	// spojení na databázi
	public $db;
	public $table;
	
	// konstruktor třídy obsahující inicializaci některých proměných
	public function login() {
		if (isset($_POST["login_name"])){
			$this->username=htmlspecialchars($_POST["login_name"]);
		}else{
			$this->username=htmlspecialchars($_SESSION["login_name"]);
		}
		
		$this->login_pw=htmlspecialchars($_POST["login_pw"]);
		
		$this->session_login_string=htmlspecialchars($_SESSION["session_login_string"]);
		
		// zabezpečení proti útokům typu SQL inject
		$this->session_login_string=$this->test_sql($this->session_login_string);
		$this->username=$this->test_sql($this->username);
		$this->login_pw=$this->test_sql($this->login_pw);
		
		global $db;
		$this->db=$db;
		$this->ipaddr=$_SERVER["REMOTE_ADDR"];
		
		// délka časového limitu v sekundách od posledního přístupu
		$this->checktimelimit=3600;
		
		$this->isLogged();
	}
	
	/**
	 * 
	 * @return boolean
	 */
	function doLogin(string $username, string $password):bool {
		if (strlen($username)>1){

			// zjisti password hash
			$query = $this->db->prepare(
					'SELECT password FROM users 
						WHERE username = :username AND status = 1');
			$query->bindParam(':username', $username);
			$query->execute();
			if ($row = $query->fetch(PDO::FETCH_ASSOC)) {

				// compare password
				if (password_verify($password, $row['password'])) {
					//heslo sedi
					
					// ok prilogovat
					$this->session_login_string=md5(uniqid(rand()));
					
					$query = $this->db->prepare(
							'UPDATE users SET session = :session, ipaddr = :ipaddr, lasttime = NOW() 
								WHERE username = :username');
					$query->bindParam(':session', $this->session_login_string);
					$query->bindParam(':ipaddr', $this->ipaddr);
					$query->bindParam(':username', $username);
					$query->execute();
					
					$_SESSION["session_login_string"]=$this->session_login_string;
					$_SESSION["login_name"]=$username;
					
					$this->load();
					$this->last_message="Uživatel byl úspěšně přihlášen.";
					return TRUE;
					
				} else {
					//heslo je spatne
					return FALSE;
				}
			} else {
				//username does not exist
				return FALSE;
			}
		}
		// spatne heslo nebo user
		$this->last_message="Chybné uživatelské jméno nebo heslo.";
		return FALSE;
	}
	
	
	/**
	 * Odhlaseni uzivatele
	 */
	function logout() {
		// znehodnot v db ulozenou session
		$query = $this->db->prepare(
				'UPDATE users SET session = :new_session 
					WHERE session = :session AND ipaddr = :ipaddr');
		$query->bindParam(':new_session', md5(uniqid(rand())));
		$query->bindParam(':session', $this->session_login_string);
		$query->bindParam(':ipaddr', $this->ipaddr);
		$query->execute();
		
		$this->session_login_string=md5(uniqid(rand()));
		$this->username=md5(uniqid(rand()));
		session_unset();
		session_destroy();
		$this->is_logged=FALSE;
		$this->is_admin=FALSE;
		$this->last_message="Uživatel odhlášen";
	}
	
	/**
	 * Testovani stavu prihlaseni
	 * 
	 * @return boolean
	 */
	function isLogged():bool {
		$query = $this->db->prepare(
				'SELECT username FROM users 
					WHERE session = :session AND username = :username AND ipaddr = :ipaddr AND lasttime >= DATE_SUB(NOW(), INTERVAL :checktimelimit SECOND)');
		$query->bindParam(':session', $this->session_login_string);
		$query->bindParam(':username', $this->username);
		$query->bindParam(':ipaddr', $this->ipaddr);
		$query->bindParam(':checktimelimit', $this->checktimelimit);
		$query->execute();
		if ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			// jestli mame vysledek, tak je user stale prihlasen
			//zapsani casu prihlaseni uzivatele
			$query = $this->db->prepare(
					'UPDATE promoters SET (lasttime = NOW()) 
						WHERE session = :session AND username = :username');
			$query->bindParam(':session', $this->session_login_string);
			$query->bindParam(':username', $this->username);
			$query->execute();
			
			$this->load();
			return TRUE;
		} else {
			$this->last_message="Uživatel odhlášen kvůli nečinosti.";
			return FALSE;
		}
	}
	
	// naplneni promennych
	private function load(){
		$this->last_message="";
		
		$query = $this->db->prepare(
				'SELECT * FROM users 
					WHERE session = :session AND username = :username AND ipaddr = :ipaddr');
		$query->bindParam(':session', $this->session_login_string);
		$query->bindParam(':username', $this->username);
		$query->bindParam(':ipaddr', $this->ipaddr);
		$query->execute();
		if ($data = $query->fetch(PDO::FETCH_ASSOC)) {
			// jestli mame vysledek, tak je user stale prihlasen
			$this->id_user=$data['id'];
			$this->firstname=$data['name'];
			$this->lastname=$data['surname'];
			$this->lasttime=$data['lasttime'];
			$this->user_level=$data[userlevel];
			$this->user_level == 255 ? $this->is_admin=TRUE : $this->is_admin=FALSE;
			$this->is_logged=TRUE;
		} else {
			$this->is_logged=FALSE;
		}
	}
	
	// zabezpečení proti útokům typu SQL inject, tohle uz imho ale resi PDO, TODO
	private function test_sql($teststring)
	{
		$teststring=strtr($teststring," ","x");
		$teststring=strtr($teststring,"+","x");
		$teststring=strtr($teststring,"--","x");
		$teststring=strtr($teststring,"&","x");
		
		return ($teststring);
	}
}
