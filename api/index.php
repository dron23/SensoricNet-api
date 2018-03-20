<?php

require __DIR__ . '/../vendor/autoload.php';	// composer autoloader

require __DIR__ . "/../config.php"; //setup, promenne
require __DIR__ . "/../functions.php";
require __DIR__ . "/../initpage_inc.php"; //inicializace, db...


class SensoricNetRestApi {

	public $username;
	var $conf;
	var $db;

	const VERSION = '1';

	/**
	 * Check user login
	 */
	public function authorize() {

// pro ukazku neresim autentizaci, jinak TODO

//		logit ("debug", "api autorizace. session je: ".$_SESSION["api_loged"]);

// 		foreach (getallheaders() as $name => $value) {
// 			logit ("debug", "headers $name: $value");
// 		}
		
// 		foreach ($_SERVER as $name => $value) {
// 			logit ("debug", "server $name: $value");
// 		}
		
//		$username = $_SERVER['PHP_AUTH_USER'];
//		$password = $_SERVER['PHP_AUTH_PW'];
		
//		if (! empty ( $username ) && ! empty ( $password )) {
//			//over uzivatele
		
		global $conf;
		$this->conf = $conf;
		global $db;
		$this->db = $db;
		
		logit ("debug", "authorize");

		return true;
	}

	/**
	 * vrátí verzi API
	 *
	 * @url GET /version
	 */
	public function version() {
		logit ("debug", "API: URL: ".$_SERVER['REQUEST_URI']);
		logit ("debug", "API: Zobrazena API version stranka");
		
		$query = $this->db->prepare ('SELECT value FROM settings WHERE property = "dbVersion"');
		$query->execute ();
		
		if ($result = $query->fetch ( PDO::FETCH_ASSOC)) {
			$db = $result['value'];
		} else {
			$db="0";
		}
		
		return array(
				'web' => $this->conf['version'], 'api' => self::VERSION, 'db' => $db
		);
	}
	
	
	/**
	 * Log out a user
	 *
	 * @url GET /logout
	 */
	public function logout() {
		// pro ukazku neresim autentizaci, jinak TODO
		
		logit ("debug", "API: URL: ".$_SERVER['REQUEST_URI']);
		logit ('info', "API: Uzivatel ".$this->username." se odhlasil z API");
		header ("Location: ". str_replace ('http://', 'http://logout:true@', $this->conf['base_url']."api/"));
	}
	
	
	/**
	 * Vrátí všechny sensory přihlášeného uživatele, TODO
	 *
	 * @url GET /sensors
	 */
	public function getSensors() {
		logit ("debug", "API: URL: ".$_SERVER['REQUEST_URI']);
		
		$query = $this->db->prepare ('
			SELECT * FROM `Sensors`
		');
		$query->execute ();
	
		if ($result = $query->fetchAll ( PDO::FETCH_ASSOC)) {
			return $result;
		} else
			return false;
	}
	

	/**
	 * Vlozi update z ttn
	 *
	 * @url POST /ttn
	 */
	public function ttn_data_insert($data) {
		logit ("debug", "API: URL: ".$_SERVER['REQUEST_URI']);
		logit ("debug", "API: ttn: ".print_r($data, true));

		// test lestli je data objekt? TODO
		
		try {

			logit ("debug", "Vytvarim transakci");
			$this->db->beginTransaction();

			$valueId = array();
			$measurementId = array();
			
			// projdi vsechny namerene hodnot v packetu (payload_fields object)
			foreach($data->payload_fields as $field_name=>$field_value) {
				
				if (!is_object($field_value)) {
					logit ("debug", "field name: $field_name, field value: $field_value");
				}
				
				// poskladej sensorId
				$sensorId = $data->app_id.':'.$data->dev_id.':'.$field_name;
				
				// na zaklade merene hodnoty zjisti string nebo float hodnotu
				$valueString = getValueString($field_name, $field_value);
				$valueFloat = getValueFloat($field_name, $field_value);
				
				logit ("debug", "valueString: $valueString, valueFloat: $valueFloat");
				
				// TODO, kdy a jak posilat data dal po mqtt, tady to urcite neni vhodne...
				
				$mqqt = new Mosquitto\Client;
				$mqqt->onConnect(function() use ($mqqt) {
					$mqqt->publish($conf['mqtt_basic_topic'].'/'.$data->dev_id.'/'.$field_name, $valueFloat, 0);
					$mqqt->disconnect();
				});
				
				$mqqt->connect($conf['mqtt_host']);
				$mqqt->loopForever();
				
				// vloz namerenou hodnotu
				$query = $this->db->prepare ('
					INSERT INTO `values` (`id`, `timestamp`, `sensorId`, `valueString`, `valueFloat`) 
					VALUES (NULL, :metadataTime, :sensorId, :valueString, :valueFloat)
				');
				
				$query->bindParam ( ':metadataTime', $data->metadata->time, PDO::PARAM_STR);
				$query->bindParam ( ':sensorId', $sensorId );
				$query->bindParam ( ':valueString', $valueString );
				$query->bindParam ( ':valueFloat', $valueFloat );
	
				logit ("debug", "metadata_time: ".$data->metadata->time.", sensorId: $sensorId, valueString: $valueString, valueFloat: $valueFloat");
				
				if ($query->execute()) {
					logit ("debug", "Vlozeni value probehlo ok");
				} else {
					logit ("error", "Vlozeni value probehlo s chybou ".print_r($query->errorInfo(), true));
				}
				
				// napln pole idcek vlozenych hodnot
				$valueId[] = $this->db->lastInsertId();
			}
			
			
			// vloz vsechna mereni (gw) ze kterych tyto hodnoty prisla
			foreach($data->metadata->gateways as $key=>$gateway) {
				
				$query_measurement = $this->db->prepare ('
				INSERT INTO `measurement` (	`id`, `gatewayId`, `time`, `channel`, `rssi`, `snr`, `frequency`,
											`modulation`, `dataRate`, `codingRate`)
				VALUES (NULL, :gatewayId, :time, :channel, :rssi, :snr, :frequency,
						:modulation, :dataRate, :codingRate)
				');
				
				$query_measurement->bindParam (':gatewayId', $gateway->gtw_id);
				$query_measurement->bindParam (':time', $gateway->time );
				$query_measurement->bindParam (':channel', $gateway->channel );
				$query_measurement->bindParam (':rssi', $gateway->rssi );
				$query_measurement->bindParam (':snr', $gateway->snr );
				$query_measurement->bindParam (':frequency', $data->metadata->frequency );
				$query_measurement->bindParam (':modulation', $data->metadata->modulation );
				$query_measurement->bindParam (':dataRate', $data->metadata->data_rate );
				$query_measurement->bindParam (':codingRate', $data->metadata->coding_rate );
				
				logit ("debug", "gatewayId: ".$gateway->gtw_id.", time: ".$gateway->time.", channel: ".$gateway->channel.", rssi: ".$gateway->rssi.", snr: ".$gateway->snr.", frequency: ".$data->metadata->frequency.", modulation: ".$data->metadata->modulation.", dataRate: ".$data->metadata->data_rate.", codingRate: ".$data->metadata->coding_rate);

				if ($query_measurement->execute()) {
					logit ("debug", "Vlozeni measurementu probehlo ok");
				} else {
					logit ("error", "Vlozeni measurementu probehlo s chybou ".print_r($query_measurement->errorInfo(), true));
				}
				
				// napln pole idcek vlozenych mereni (gw)
				$lastid = $this->db->lastInsertId();
				logit ("debug", "Posledni measurement id bylo $lastid");
				
				$measurementId[] = $lastid;
			}

			// vloz propojeni mereni a hodnot (n:m relationship)
			foreach($valueId as $values_id) {
				foreach($measurementId as $measurement_id) {
					$query_valuesHasMeasurement = $this->db->prepare ('
						INSERT INTO `valuesHasMeasurement` (`values_id`, `measurement_id`) 
						VALUES (:values_id, :measurement_id)
					');
					
					$query_valuesHasMeasurement->bindParam ( ':values_id', $values_id);
					$query_valuesHasMeasurement->bindParam ( ':measurement_id', $measurement_id );
					
					logit ("debug", "values_id: $values_id, measurement_id: $measurement_id");
					
					$query_valuesHasMeasurement->execute ();
				}
			}
			
			// konec transakce
			$this->db->commit();
			
			logit ("info", "Transakce byla commitnuta");
			
			// TODO, v pripade uspesneho ulozeni do db dores mqtt
			
		} catch (PDOException $e) {
			$this->db->rollBack();
			logit ("error", "Chyba pri transakci vkladani mereni ".$e->getMessage());
			//exit (); // asi teda nema cenu pokracovat? TODO
		}
	}
}	

$mode = 'debug'; // 'debug' or 'production'
//$mode = 'production';

$server = new \Jacwright\RestServer\RestServer ($mode);
$server->addClass ( 'SensoricNetRestApi' );

$server->handle ();

