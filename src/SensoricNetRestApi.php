<?php

namespace SensoricNet;

use Mosquitto;
use PDO;

class SensoricNetRestApi {

	public $username;

	private $conf;
	private $logger;
	private $db;
	private $mqtt;

	const VERSION = '1';
	
	function __construct() {
		global $conf;
		$this->conf = $conf;
		global $logger;
		$this->logger = $logger;
		global $db;
		$this->db = $db;
	}
	
	/**
	 * Check user login
	 */
	public function authorize() {

		$this->logger->debug("api autorizace. session je: ".$_SESSION["api_loged"]);

// 		foreach (getallheaders() as $name => $value) {
// 			$this->logger->debug ("headers $name: $value");
// 		}
		
		foreach ($_SERVER as $name => $value) {
			$this->logger->debug("server $name: $value");
		}
		
		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];
		
		if (! empty ( $username ) && ! empty ( $password )) {
			//over uzivatele
			$auth = new Auth;
			$auth->login();
			if ($auth->doLogin($username, $password)) {
				
				$this->logger->debug("authorize");
				
				$this->logger->debug("mqtt init");
				
				$this->mqtt = new Mosquitto\Client();
				$this->mqtt->connect($this->conf['mqtt_host'], $this->conf['mqtt_port'], $this->conf['mqtt_qos']);
				//		$client->subscribe('/#', 1);
				
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * vrátí verzi API
	 *
	 * @url GET /version
	 */
	public function version() {
		$this->logger->debug("API: URL: ".$_SERVER['REQUEST_URI']);
		$this->logger->debug("API: Zobrazena API version stranka");
		
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
		
		$this->logger->debug("API: URL: ".$_SERVER['REQUEST_URI']);
		$this->logger->info ("API: Uzivatel ".$this->username." se odhlasil z API");
		header ("Location: ". str_replace ('http://', 'http://logout:true@', $this->conf['base_url']."api/"));
	}
	
	
	/**
	 * Vrátí všechny sensory přihlášeného uživatele, TODO
	 *
	 * @url GET /sensors
	 */
	public function getSensors() {
		$this->logger->debug("API: URL: ".$_SERVER['REQUEST_URI']);
		
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

		$this->logger->debug("API: URL: ".$_SERVER['REQUEST_URI']);
		$this->logger->debug("API: ttn: ".print_r($data, true));

		// test jestli je data objekt? TODO
		
		try {

			$this->logger->debug("Vytvarim transakci");
			$this->db->beginTransaction();

			$valueId = array();
			$measurementId = array();
			
			// projdi vsechny namerene hodnot v packetu (payload_fields object)
			foreach($data->payload_fields as $field_name=>$field_value) {
				
				if (!is_object($field_value)) {
					$this->logger->debug("field name: $field_name, field value: $field_value");
				}
				
				if ($field_name == 'gps_7') {
					// gps souradnice budeme zpracovavat specialne
					$this->sensorPositionUpdate($data->dev_id, $field_value);
				} else {
					// vloz normalni hodnotu
					// poskladej sensorId
					$sensorId = $data->app_id.':'.$data->dev_id.':'.$field_name;
					
					// na zaklade merene hodnoty zjisti string nebo float hodnotu
					$valueString = getValueString($field_name, $field_value);
					$valueFloat = getValueFloat($field_name, $field_value);
					
					$this->logger->debug("valueString: $valueString, valueFloat: $valueFloat");
					
					// TODO, kdy a jak posilat data dal po mqtt, tady to urcite neni vhodne...
	
					$mqtt_topic = $this->conf['mqtt_basic_topic'].'/'.$data->dev_id.'/'.$field_name;
					$mqtt_value = $valueFloat;
					$this->logger->debug("mqtt topic $mqtt_topic -> $valueFloat");
	
					$this->mqtt->loop();
					$mid = $this->mqtt->publish($mqtt_topic, $mqtt_value, 1, 0);
					$this->logger->debug ("Sent message ID: {$mid}");
					$this->mqtt->loop();
					
					
					// vloz namerenou hodnotu
					$query = $this->db->prepare ('
						INSERT INTO `values` (`id`, `timestamp`, `sensorId`, `valueString`, `valueFloat`) 
						VALUES (NULL, :metadataTime, :sensorId, :valueString, :valueFloat)
					');
					
					$query->bindParam ( ':metadataTime', $data->metadata->time, PDO::PARAM_STR);
					$query->bindParam ( ':sensorId', $sensorId );
					$query->bindParam ( ':valueString', $valueString );
					$query->bindParam ( ':valueFloat', $valueFloat );
		
					$this->logger->debug ("metadata_time: ".$data->metadata->time.", sensorId: $sensorId, valueString: $valueString, valueFloat: $valueFloat");
					
					if ($query->execute()) {
						$this->logger->debug ("Vlozeni value probehlo ok");
					} else {
						$this->logger->error ("Vlozeni value probehlo s chybou ".print_r($query->errorInfo(), true));
					}
					
					// napln pole idcek vlozenych hodnot
					$valueId[] = $this->db->lastInsertId();
				}
			}
			
			
			// vloz vsechna mereni (gw) ze kterych tyto hodnoty prisla
			foreach($data->metadata->gateways as $key=>$gateway) {
				
				//test jestli uz gw existuje v db, jestli ne, tak vytvorit!
				if ($this->gwExists($gateway)) {
					// jen updatni metadata
					$this->gwUpdate($gateway);
				} else {
					// nova gw
					$this->gwInsert($gateway);
				}
				
				$query_measurement = $this->db->prepare ('
				INSERT INTO `measurement` (`id`, `gatewayId`, `time`, `channel`, `rssi`, `snr`, `frequency`,
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
				
				$this->logger->debug ("gatewayId: ".$gateway->gtw_id.", time: ".$gateway->time.", channel: ".$gateway->channel.", rssi: ".$gateway->rssi.", snr: ".$gateway->snr.", frequency: ".$data->metadata->frequency.", modulation: ".$data->metadata->modulation.", dataRate: ".$data->metadata->data_rate.", codingRate: ".$data->metadata->coding_rate);

				if ($query_measurement->execute()) {
					$this->logger->debug ("Vlozeni measurementu probehlo ok");
				} else {
					$this->logger->error("Vlozeni measurementu probehlo s chybou ".print_r($query_measurement->errorInfo(), true));
				}
				
				// napln pole idcek vlozenych mereni (gw)
				$lastid = $this->db->lastInsertId();
				$this->logger->debug ("Posledni measurement id bylo $lastid");
				
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
					
					$this->logger->debug ("values_id: $values_id, measurement_id: $measurement_id");
					
					$query_valuesHasMeasurement->execute ();
				}
			}
			
			// konec transakce
			$this->db->commit();
			
			$this->logger->debug ("Transakce byla commitnuta");
			
			// TODO, v pripade uspesneho ulozeni do db dores mqtt
			
		} catch (\PDOException $e) {
			$this->db->rollBack();
			$this->logger->error ("Chyba pri transakci vkladani mereni ".$e->getMessage());
			//exit (); // asi teda nema cenu pokracovat? TODO
		}
	}

	/**
	 * Vrati vsechny sensenet cidla (a jejich senzory?)
	 *
	 * @url GET /sensoricnet/sensors
	 */
	public function get_sensoricnet_sensor_all() {
		
		$this->logger->debug ("API: URL: ".$_SERVER['REQUEST_URI']);
		
		// zjisti senzory senzor
		$query = $this->db->prepare ('
			SELECT `id`, `appId`, `devId`, `fieldId`, `unitType`, `unitName`, `description` FROM `sensors` WHERE 1 ORDER BY `devId`
		');
		$query->execute ();
		
		if ($result = $query->fetchAll ( PDO::FETCH_ASSOC)) {
			$this->logger->debug ("Dotaz na senzory probehlo ok");
			return $result;
		} else {
			$this->logger->error ("Dotaz na senzory probehl s chybou ".print_r($query->errorInfo(), true));
			return array('status' => 'error', 'description' => $query->errorInfo()[2]);
		}
	}

	
	/**
	 * Vlozi do db nove sensenet cidlo (sedm senzoru)
	 *
	 * @url PUT /sensoricnet/sensors/$devId
	 */
	public function create_sensoricnet_sensor($devId) {

		$this->logger->debug ("API: URL: ".$_SERVER['REQUEST_URI']);
		$this->logger->debug ("API: ttn: devId: $devId");

		// sensoricnet sensors definition
		$sensors[1]=array('fieldId' => 'temperature_1', 'unitType' => '1', 'unitName' => '°C', 'description' => 'Teplota');
		$sensors[2]=array('fieldId' => 'barometric_pressure_2', 'unitType' => '3', 'unitName' => 'hPa', 'description' => 'Tlak');
		$sensors[3]=array('fieldId' => 'relative_humidity_3', 'unitType' => '2', 'unitName' => '%', 'description' => 'Relativní vlhkost');
		$sensors[4]=array('fieldId' => 'analog_in_4', 'unitType' => '5', 'unitName' => 'ppm', 'description' => 'Prach 1um');
		$sensors[5]=array('fieldId' => 'analog_in_5', 'unitType' => '5', 'unitName' => 'ppm', 'description' => 'Prach 2,5um');
		$sensors[6]=array('fieldId' => 'analog_in_6', 'unitType' => '5', 'unitName' => 'ppm', 'description' => 'Prach 10um');
		$sensors[7]=array('fieldId' => 'gps_7', 'unitType' => '4', 'unitName' => 'GPS', 'description' => 'GPS');

		// TODO, je to treba resit jako transakci? imho asi ani ne
// 		try {
// 			$this->logger->debug("Vytvarim transakci");
// 			$this->db->beginTransaction();
		
			foreach ($sensors as $key=>$sensor) {
					// vloz senzor
				$query = $this->db->prepare ('
					INSERT INTO `sensors` (`id`, `appId`, `devId`, `fieldId`, `unitType`, `unitName`, `description`)
					VALUES(:id, :appId, :devId, :fieldId, :unitType, :unitName, :description)
				');
				
				$id = $this->conf['app_id'].':'.$devId.':'.$sensor['fieldId'];
				
				$query->bindParam ( ':id', $id );
				$query->bindParam ( ':appId', $this->conf['app_id'] );
				$query->bindParam ( ':devId', $devId );
				$query->bindParam ( ':fieldId', $sensor['fieldId'] );
				$query->bindParam ( ':unitType', $sensor['unitType'] );
				$query->bindParam ( ':unitName', $sensor['unitName'] );
				$query->bindParam ( ':description', $sensor['description'] );
	
				$this->logger->debug ("inserting sensor ".$this->conf['app_id'].':'.$devId.':'.$fieldId);
				
				if ($query->execute()) {
					$this->logger->debug ("Vlozeni senzoru $id probehlo ok");
				} else {
					$this->logger->error ("Vlozeni senzoru $id probehlo s chybou ".print_r($query->errorInfo(), true));
				}
			}
			
// 			// konec transakce
// 			$this->db->commit();
			
// 			$this->logger->debug ("Transakce byla commitnuta");
			return array('status' => 'ok');
			
// 		} catch (\PDOException $e) {
// 			$this->db->rollBack();
// 			$this->logger->error ("Chyba pri transakci vkladani senzoru ".$e->getMessage());
// 			return array('status' => 'error', 'description' => $e->getMessage());
// 		}
	}
	
	
// 	20180430 22:43:17 DEBUG:                     [0] => stdClass Object
// 	20180430 22:43:17 DEBUG:                         (
// 	20180430 22:43:17 DEBUG:                             [gtw_id] => eui-b827ebfffe41b87b
// 	20180430 22:43:17 DEBUG:                             [timestamp] => 3062930067
// 	20180430 22:43:17 DEBUG:                             [time] => 2018-04-30T20:43:17.287742Z
// 	20180430 22:43:17 DEBUG:                             [channel] => 1
// 	20180430 22:43:17 DEBUG:                             [rssi] => -107
// 	20180430 22:43:17 DEBUG:                             [snr] => 8.2
// 	20180430 22:43:17 DEBUG:                             [rf_chain] => 1
// 	20180430 22:43:17 DEBUG:                             [latitude] => 49.93057
// 	20180430 22:43:17 DEBUG:                             [longitude] => 17.893877
// 	20180430 22:43:17 DEBUG:                             [altitude] => 320
// 	20180430 22:43:17 DEBUG:                             [location_source] => registry
// 	20180430 22:43:17 DEBUG:                         )
	
	/**
	 * Testuje, jestli je tato gw uz v databazi
	 * 
	 * @param object $gateway
	 */
	private function gwExists(object $gateway):bool {
		$query = $this->db->prepare ('
			SELECT `id` FROM `gateways` WHERE `id` = :gtw_id
		');
		$query->bindParam ( ':gtw_id', $gateway->gtw_id );
		$query->execute ();
		
		if ($result = $query->fetchAll ( PDO::FETCH_ASSOC)) {
			return TRUE;
		} else
			return FALSE;
	}
	
	
	/**
	 * Vlozi gw do databaze
	 * 
	 * @param object $gateway
	 */
	private function gwInsert(object $gateway) {
		$query = $this->db->prepare ('
			INSERT INTO `gateways` (`id`, `type`, `description`, `lastLatitude`, `lastLongitude`, `lastAltitude`, `lastSeen`) 
				VALUES (:gtw_id, :type, NULL, :latitude, :longitude, :altitude, NOW())
		');
		$query->bindParam ( ':gtw_id', $gateway->gtw_id );
		$query->bindParam ( ':type', $gateway->gtw_id == 'Vodafone_NBIot' ? 'nbiot' : 'lora' );
		$query->bindParam ( ':latitude', $gateway->latitude );
		$query->bindParam ( ':longitude', $gateway->longitude );
		$query->bindParam ( ':altitude', $gateway->altitude );
		$query->execute ();

		$this->logger->debug ("inserting gateway ".$gateway->gtw_id);
		
		if ($query->execute()) {
			$this->logger->debug ("Vlozeni gateway ".$gateway->gtw_id." probehlo ok");
		} else {
			$this->logger->error ("Vlozeni gateway ".$gateway->gtw_id." probehlo s chybou ".print_r($query->errorInfo(), true));
		}
	}

	/**
	 * Updatne metadata o gw
	 *
	 * @param object $gateway
	 */
	private function gwUpdate(object $gateway) {
		$query = $this->db->prepare ('
			UPDATE `gateways` SET `lastLatitude` = :latitude, `lastLongitude` = :longitude, `lastAltitude` = :altitude, `lastSeen` = NOW() 
			WHERE `gateways`.`id` = :gtw_id
		');
		$query->bindParam ( ':gtw_id', $gateway->gtw_id );
		$query->bindParam ( ':latitude', $gateway->latitude );
		$query->bindParam ( ':longitude', $gateway->longitude );
		$query->bindParam ( ':altitude', $gateway->altitude );
		$query->execute ();
		
		$this->logger->debug ("updating gateway ".$gateway->gtw_id);
		
		if ($query->execute()) {
			$this->logger->debug ("Update gateway ".$gateway->gtw_id." probehlo ok");
		} else {
			$this->logger->error ("Update gateway ".$gateway->gtw_id." probehlo s chybou ".print_r($query->errorInfo(), true));
		}
	}

	/**
	 * Updatne polohu senzoru
	 * 
	 * @param string $dev_id
	 * @param object $gps_object
	 */
	private function sensorPositionUpdate(string $dev_id, object $gps_object) {
		// pokud se poloha zmenila (TODO), vloz novou polohu do db
		$query = $this->db->prepare ('
			INSERT INTO `sensorsPositions` (`id`, `sensorDevId`, `time`, `latitude`, `longitude`, `altitude`, `changed`) 
				VALUES (NULL, :dev_id, NOW(), :latitude, :longitude, :altitude, NULL)
		');
		$query->bindParam ( ':gtw_id', $dev_id );
		$query->bindParam ( ':latitude', $gps_object->latitude );
		$query->bindParam ( ':longitude', $gps_object->longitude );
		$query->bindParam ( ':altitude', $gps_object->altitude );
		$query->execute ();
		
		$this->logger->debug ("Vkladam polohu sensoru $dev_id: ".$gps_object);
		
		if ($query->execute()) {
			$this->logger->debug ("Vlozeni polohy senzoru $dev_id probehlo ok");
		} else {
			$this->logger->error ("Vlozeni polohy senzoru $dev_id probehlo s chybou ".print_r($query->errorInfo(), true));
		}
	}
	
}	
