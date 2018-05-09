<?php

namespace SensoricNet;

class GrafanaApi {
	
	private $baseUrl;
	private $authToken;
	
	function __construct() {
		global $conf;
		$this->baseUrl = $conf['grafana_base_url'];
		$this->authToken = $conf['grafana_auth_token'];
	}
	
// 	{
// 		"dashboard": {
// 		"id": "test-dashboard0001",
// 		"uid": null,
// 		"title": "Testovací dashboard vytvořený přes API",
// 		"tags": [ "test" ],
// 		"timezone": "browser",
// 		"schemaVersion": 16,
// 		"version": 0
// 	},
// 	"folderId": 0,
// 	"overwrite": true
// 	}
	
	
}

