<?php

namespace SensoricNet;

use RestClient;

class GrafanaApi {
	
	private $baseUrl;
	private $authToken;
	
	function __construct() {
		global $conf;
		$this->baseUrl = $conf['grafana_base_url'];
		$this->authToken = $conf['grafana_auth_token'];
		global $logger;
		$this->logger = $logger;
	}

	/**
	 * Vytvori dashboard v Grafane
	 * 
	 * @param string $dashboardId
	 * @param string $title
	 * @param array $tags
	 */
	public function createDashboard(string $dashboardId, string $title, array $tags=NULL) {

// 		$api = new RestClient([
// 				'base_url' => $this->baseUrl,
// 				'headers' => ['Authorization' => 'Bearer '.$this->authToken],
// 		]);
		
// 		$json = '
// 			{
// 				"dashboard": {
// 				"id": "'.$dashboardId.'",
// 				"uid": null,
// 				"title": "'.$title.'",
// 				"tags": [ "test" ],
// 				"timezone": "browser",
// 				"schemaVersion": 16,
// 				"version": 0
// 			},
// 			"folderId": 0,
// 			"overwrite": true
// 			}';

		$json = file_get_contents(__DIR__ .'/grafana_dashboard.json');
		$json_dashboard_object = json_decode ($json);
		$json_dashboard_object->id=$dashboardId;
		$json_dashboard_object->uid=NULL;
		$json_dashboard_object->title=$title;
		$json_dashboard_object->tags=[ "test", 'sensoricnet' ];

		foreach ($json_dashboard_object->panels as $key=>$value) {
			$value->targets[0]->tags[0]->value=$dashboardId;
		}
		
		
		$json_object = new \stdClass();
		$json_object->dashboard=$json_dashboard_object;
		$json_object->folderId=0;
		$json_object->overwrite=true;
		
		$this->logger->debug(print_r($json_object, true));
		
		$ch = curl_init ($this->baseUrl.'/api/dashboards/db' );
		
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, json_encode($json_object));
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
// 		if ($conf['api_validate_ssl_cert'] === false) curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
				'Authorization: Bearer '.$this->authToken,
				'Content-Type: application/json'
		) );
		//	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		
		$result = curl_exec ( $ch );
		if ($result === false) {
			$this->logger->error ("Curl call failed. Error was " . curl_error ( $ch ) );
		} else {
			$http_code = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
			$this->logger->info ("Curl call was successful, return code is $http_code" );
		}
		curl_close ( $ch );
		
// 		$result = $api->post("/api/dashboards/db", (string) $json);
// 		$this->logger->debug(print_r($result, true));
// 		$this->logger->debug(print_r($result->decode_response(), true));

		if($http_code == 200) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	
	
	
}
