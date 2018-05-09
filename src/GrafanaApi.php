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

		$api = new RestClient([
				'base_url' => $this->baseUrl,
				'headers' => ['Authorization' => 'Bearer '.$this->authToken],
		]);
		
		$json = '
			{
				"dashboard": {
				"id": "'.$dashboardId.'",
				"uid": null,
				"title": "'.$title.'",
				"tags": [ "test" ],
				"timezone": "browser",
				"schemaVersion": 16,
				"version": 0
			},
			"folderId": 0,
			"overwrite": true
			}';

		$this->logger->debug(print_r($json, true));
		
		$result = $api->post("/api/dashboards/db", $json);
		$this->logger->debug(print_r($result, true));
		$this->logger->debug(print_r($result->decode_response(), true));

		if($result->info->http_code == 200) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	
	
	
}

