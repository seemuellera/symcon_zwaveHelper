<?php

// GUID to identify the Z-Wave devices
define("ZWAVE_DEVICE_GUID","{101352E1-88C7-4F16-998B-E20D50779AF6}");

// colors
define("COLOR_OK","green");
define("COLOR_WARN","orange");
define("COLOR_CRIT","red");

// Klassendefinition
class ZwaveHelper extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {

		// Diese Zeile nicht löschen
        parent::__construct($InstanceID);
 
        // Selbsterstellter Code
    }
 
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
            
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","ZwaveHelper");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("WarningThreshold",10);
		$this->RegisterPropertyInteger("CriticalThreshold",25);
		$this->RegisterPropertyInteger("OptimizationInterval", 0);
		$this->RegisterPropertyInteger("OptimizationTotalRuns", 4);
		$this->RegisterPropertyInteger("OptimizationRuntime", 60);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		
		// Variables
		$this->RegisterVariableString("DeviceHealth","Device Health","~HTMLBox");
		$this->RegisterVariableInteger("DeviceHealthOk","Devices in State Healthy");
		$this->RegisterVariableInteger("DeviceHealthWarn","Devices in State Warning");
		$this->RegisterVariableInteger("DeviceHealthCrit","Healthy in State Critical");
		$this->RegisterVariableBoolean("OptimizeBadClientSwitch","Optimize bad client","~Switch");
		$this->RegisterVariableInteger("OptimizeBadClientInstanceId","Optimize bad client instance id");
		$this->RegisterVariableInteger("OptimizeBadClientRun","Optimize bad client run");
		$this->RegisterVariableString("LastOptimization","Last Device Optimization");
		$this->RegisterVariableString("DesiredFirmwareVersions","Desired Firmware Versions");
		
		$this->RegisterVariableString("DeviceConfiguration","Device Configuration","~HTMLBox");
		$this->RegisterVariableString("DeviceAssociations","Device Associations","~HTMLBox");
		$this->RegisterVariableString("DeviceRouting","Device Routing","~HTMLBox");
		$this->RegisterVariableString("HopCount","Routing Hop Count");
		
		// Default Actions
		$this->EnableAction("OptimizeBadClientSwitch");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'ZWHELPER_RefreshInformation($_IPS[\'TARGET\']);');
		$this->RegisterTimer("OptimizeBadClient", 0 , 'ZWHELPER_OptimizeBadClient($_IPS[\'TARGET\']);');
		$this->RegisterTimer("OptimizeBadClientRunTimer", 0 , 'ZWHELPER_OptimizeBadClient($_IPS[\'TARGET\']);');
		
		// Initialize the empty Json
		if (! GetValue($this->GetIDForIdent('LastOptimization'))) {
			
			SetValue($this->GetIDForIdent('LastOptimization'), "[]");
			$this->LogMessage("The LastOptimization JSON was empty. Intializing it with an empty array.","DEBUG");
		}
		
		if (! GetValue($this->GetIDForIdent('DesiredFirmwareVersions'))) {
			
			SetValue($this->GetIDForIdent('DesiredFirmwareVersions'), "[]");
			$this->LogMessage("The DesiredFirmwareVersions JSON was empty. Intializing it with an empty array.","DEBUG");
		}
		
		// Reset the status trackers if the module was updated during an execution
		if (GetValue($this->GetIDForIdent('OptimizeBadClientSwitch'))) {
			
			$this->LogMessage("It seems the device was updated during a running optimization. Cancelling the optimization.","DEBUG");
			SetValue($this->GetIDForIdent('OptimizeBadClientInstanceId'), NULL);
			SetValue($this->GetIDForIdent('OptimizeBadClientRun'), 0);
			SetValue($this->GetIDForIdent('OptimizeBadClientSwitch'), false);
			$this->SetTimerInterval("OptimizeBadClientRunTimer", 0);
		}
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {

		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		$newOptimizationInterval = $this->ReadPropertyInteger("OptimizationInterval") * 1000;
		$this->SetTimerInterval("OptimizeBadClient", $newOptimizationInterval);

       	// Diese Zeile nicht löschen
       	parent::ApplyChanges();
    }


	public function GetConfigurationForm() {

        	
		// Initialize the form
		$form = Array(
            	"elements" => Array(),
				"actions" => Array()
        	);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "WarningThreshold", "caption" => "Failed Packets - Warning Threshold");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "CriticalThreshold", "caption" => "Failed Packets - Critical Threshold");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "OptimizationInterval", "caption" => "Optimization Interval");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "OptimizationTotalRuns", "caption" => "Optimization runs");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "OptimizationRuntime", "caption" => "run time per optimization");
	
		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'ZWHELPER_RefreshInformation($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Optimize bad clients", "onClick" => 'ZWHELPER_OptimizeBadClient($id);');
		
		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		$this->LogMessage("ZWHELPER - Refresh in progress");
		
		$this->RefreshDeviceHealth();
		$this->RefreshDeviceConfiguration();
		$this->RefreshDeviceAssociations();
		$this->RefreshDeviceRouting();
	}
	
	public function RequestAction($Ident, $Value) {
	
		switch ($Ident) {
				
			case "OptimizeBadClientSwitch":
				// Default Action for Status Variable
				// Optimize a bad client when turning on:
				if ($Value) {
				
					$this->OptimizeBadClient();
				}
				else {
					
					$this->LogMessage("Ignoring turning off request as it will turn off as soon as the optimization finishes","DEBUG");
				}
				
				// Neuen Wert in die Statusvariable schreiben
				// SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
			
		}
	}
	
	public function RefreshDeviceHealth() {
		
		$devicesHealthy = 0;
		$devicesWarning = 0;
		$devicesCritical = 0;
		
		$htmlOutput = '';
		
		$htmlOutput .= '<table border="1px">';
		
		// Headings
		$htmlOutput .= '<thead>';
		$htmlOutput .= '<tr>';
		$htmlOutput .= '<th>Instance Name</th>';
		$htmlOutput .= '<th>Instance ID</th>';
		$htmlOutput .= '<th>Z-Wave Node ID</th>';
		$htmlOutput .= '<th>Status</th>';
		$htmlOutput .= '<th>Packets Sent</th>';
		$htmlOutput .= '<th>Packets Received</th>';
		$htmlOutput .= '<th>Packets Failed</th>';
		$htmlOutput .= '<th>Packets Failed Ratio</th>';
		$htmlOutput .= '<th>Last Optimization</th>';
		$htmlOutput .= '<th>Optimization Queued</th>';
		$htmlOutput .= '<th>BatteryDevice</th>';
		$htmlOutput .= '<th>Queue Length</th>';
		$htmlOutput .= '</tr>';
		$htmlOutput .= '</thead>';

		// Content
		$htmlOutput .= '<tbody>';
		
		$allZwaveDevices = $this->GetAllDevices();
		$allZwaveDevicesHealth = Array();
		
		foreach ($allZwaveDevices as $currentDevice) {
			
			$currentDeviceHealth = $this->GetDeviceHealth($currentDevice);
			
			if (count($currentDeviceHealth) > 0) {
				
				$allZwaveDevicesHealth[] = $currentDeviceHealth;
			}
		}
		
		array_multisort(array_column($allZwaveDevicesHealth, "packetsFailed"), SORT_DESC, $allZwaveDevicesHealth );
		
		foreach ($allZwaveDevicesHealth as $currentDeviceHealth) {
		
			$htmlOutput .= '<tr>';
			$htmlOutput .= "<td>" . $currentDeviceHealth['instanceName'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceHealth['instanceId'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceHealth['nodeId'] . "</td>";
			if ($currentDeviceHealth['nodeFailed'] == 1) {
				
				$htmlOutput .= '<td bgcolor="' . COLOR_CRIT . '">Failed</td>';
			}
			else {
				
				$htmlOutput .= '<td bgcolor="' . COLOR_OK . '">OK</td>';
			}
			$htmlOutput .= "<td>" . $currentDeviceHealth['packetsSent'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceHealth['packetsReceived'] . "</td>";
			if ($currentDeviceHealth['packetsFailed'] < $this->ReadPropertyInteger('WarningThreshold') ) {
			
				$htmlOutput .= '<td bgcolor="' . COLOR_OK . '">' . $currentDeviceHealth['packetsFailed'] . '</td>';
			}
			else {
				
				if ($currentDeviceHealth['packetsFailed'] >= $this->ReadPropertyInteger('CriticalThreshold') ) {
					
					$htmlOutput .= '<td bgcolor="' . COLOR_CRIT . '">' . $currentDeviceHealth['packetsFailed'] . '</td>';
				}
				else {
					
					$htmlOutput .= '<td bgcolor="' . COLOR_WARN . '">' . $currentDeviceHealth['packetsFailed'] . '</td>';
				}
			}
			$htmlOutput .= "<td>" . $currentDeviceHealth['packetsErrorRate'] . "%</td>";
			if ($currentDeviceHealth['lastOptimization'] == 0) {
				
					$htmlOutput .= '<td bgcolor="' . COLOR_WARN . '">never</td>';
			}
			else {
				
				$htmlOutput .= '<td bgcolor="' . COLOR_OK . '">' . strftime("%Y-%m-%d %H:%M:%S",$currentDeviceHealth['lastOptimization']) . '</td>';
			}
			if ($currentDeviceHealth['wakeupQueueOptimization'] == 1) {
				
				$htmlOutput .= "<td>Queued</td>";
			}
			else {
				
				$htmlOutput .= "<td>&nbsp;</td>";
			}
			if ($currentDeviceHealth['batteryDevice'] == 1) {
				
				$htmlOutput .= "<td>Battery Device</td>";
			}
			else {
				
				$htmlOutput .= "<td>&nbsp;</td>";
			}
			if ($currentDeviceHealth['wakeupQueueLength'] >= 0) {
				
				$htmlOutput .= "<td>" . $currentDeviceHealth['wakeupQueueLength'] . "</td>";
			}
			else {
				
				$htmlOutput .= "<td>&nbsp;</td>";
			}
			$htmlOutput .= '</tr>';
			
			// Stats
			if ($currentDeviceHealth['nodeFailed'] == 1) {
				
				$devicesCritical++;
			}
			else {
				
				if ($currentDeviceHealth['packetsFailed'] < $this->ReadPropertyInteger('WarningThreshold') ) {
					
					$devicesHealthy++;
				}
				else {
					
					if ($currentDeviceHealth['packetsFailed'] >= $this->ReadPropertyInteger('CriticalThreshold') ) {
						
						$devicesCritical++;
					}
					else {
						
						$devicesWarning++;
					}
				}
			}
		}
		
		$htmlOutput .= '</tbody>';
		
		$htmlOutput .= '</table>';
		
		// Save the result to a variable
		SetValue($this->GetIDForIdent('DeviceHealth'), $htmlOutput);
		
		SetValue($this->GetIDForIdent('DeviceHealthOk'), $devicesHealthy);
		SetValue($this->GetIDForIdent('DeviceHealthWarn'), $devicesWarning);
		SetValue($this->GetIDForIdent('DeviceHealthCrit'), $devicesCritical);
		
		return true;
	}
	
	public function RefreshDeviceRouting() {
				
		$htmlOutput = '';
		
		$htmlOutput .= '<table border="1px">';
		
		// Headings
		$htmlOutput .= '<thead>';
		$htmlOutput .= '<tr>';
		$htmlOutput .= '<th>Instance Name</th>';
		$htmlOutput .= '<th>Instance ID</th>';
		$htmlOutput .= '<th>Z-Wave Node ID</th>';
		$htmlOutput .= '<th>Direct Route to Controller</th>';
		$htmlOutput .= '<th>Number of neighbours</th>';
		$htmlOutput .= '<th>Number of failed packets</th>';
		$htmlOutput .= '<th>Error rate</th>';
		$htmlOutput .= '</tr>';
		$htmlOutput .= '</thead>';

		// Content
		$htmlOutput .= '<tbody>';
		
		$allZwaveDevices = $this->GetAllDevices();
		$allZwaveDeviceRoutings = Array();
		
		foreach ($allZwaveDevices as $currentDevice) {
			
			$currentDeviceRouting = $this->GetDeviceRouting($currentDevice);
			
			if (count($currentDeviceRouting) > 0) {
				
				$allZwaveDeviceRoutings[] = $currentDeviceRouting;
			}
		}
		
		
		
		array_multisort(array_column($allZwaveDeviceRoutings, "direct"), SORT_DESC, $allZwaveDeviceRoutings );
		
		foreach ($allZwaveDeviceRoutings as $currentDeviceRouting) {
		
			$htmlOutput .= '<tr>';
			$htmlOutput .= "<td>" . $currentDeviceRouting['instanceName'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceRouting['instanceId'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceRouting['nodeId'] . "</td>";
			if ($currentDeviceRouting['direct'] == 1) {
				
				$htmlOutput .= "<td>DIRECT</td>";
			}
			else {
				
				$htmlOutput .= "<td>&nbsp;</td>";
			}
			$htmlOutput .= "<td>" . $currentDeviceRouting['count'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceRouting['packetsFailed'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceRouting['packetsErrorRate'] . "</td>";
			$htmlOutput .= '<tr>';
		}
		
		$htmlOutput .= '</tbody>';
		
		$htmlOutput .= '</table>';
		
		// Save the result to a variable
		SetValue($this->GetIDForIdent('DeviceRouting'), $htmlOutput);
		
		return true;
	}
	
	public function RefreshDeviceConfiguration() {
				
		$htmlOutput = '';
		
		$htmlOutput .= '<table border="1px">';
		
		// Headings
		$htmlOutput .= '<thead>';
		$htmlOutput .= '<tr>';
		$htmlOutput .= '<th>Instance Name</th>';
		$htmlOutput .= '<th>Instance ID</th>';
		$htmlOutput .= '<th>Z-Wave Node ID</th>';
		$htmlOutput .= '<th>Classes</th>';
		$htmlOutput .= '<th>Secure Classes</th>';
		$htmlOutput .= '<th>Manufacturer</th>';
		$htmlOutput .= '<th>Product</th>';
		$htmlOutput .= '<th>Application Version</th>';
		$htmlOutput .= '<th>Serial Number</th>';
		$htmlOutput .= '<th>Battery Device</th>';
		$htmlOutput .= '</tr>';
		$htmlOutput .= '</thead>';

		// Content
		$htmlOutput .= '<tbody>';
		
		$allZwaveDevices = $this->GetAllDevices();
		$allZwaveDeviceConfigurations = Array();
		
		foreach ($allZwaveDevices as $currentDevice) {
			
			$currentDeviceConfiguration = $this->GetDeviceConfiguration($currentDevice);
			
			if (count($currentDeviceConfiguration) > 0) {
				
				$allZwaveDeviceConfigurations[] = $currentDeviceConfiguration;
			}
		}
		
		array_multisort(array_column($allZwaveDeviceConfigurations, "nodeId"), SORT_ASC, $allZwaveDeviceConfigurations );
		
		foreach ($allZwaveDeviceConfigurations as $currentDeviceConfiguration) {
		
			$htmlOutput .= '<tr>';
			$htmlOutput .= "<td>" . $currentDeviceConfiguration['instanceName'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceConfiguration['instanceId'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceConfiguration['nodeId'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceConfiguration['nodeClassCount'] . "</td>";
			$htmlOutput .= "<td>" . $currentDeviceConfiguration['nodeSecureClassCount'] . "</td>";
			$htmlOutput .= "<td>" . $this->LookupManufacturerId($currentDeviceConfiguration['manufacturerId']) . "</td>";
			$htmlOutput .= "<td>" . $this->LookupProductId($currentDeviceConfiguration['manufacturerId'], $currentDeviceConfiguration['productType'], $currentDeviceConfiguration['productId']) . "</td>";
			$desiredFirmwareVersion = $this->GetDesiredFirmwareVersion($currentDeviceConfiguration['manufacturerId'], $currentDeviceConfiguration['productType'], $currentDeviceConfiguration['productId']);
			if ($desiredFirmwareVersion == -1) {
				
				$htmlOutput .= "<td>" . $currentDeviceConfiguration['applicationVersion'] . "</td>";
			}
			else {
				
				if ($desiredFirmwareVersion == $currentDeviceConfiguration['applicationVersion']) {
					
					$htmlOutput .= '<td bgcolor="' . COLOR_OK . '">' . $currentDeviceConfiguration['applicationVersion'] . "</td>";
				}
				else {
					
					$htmlOutput .= '<td bgcolor="' . COLOR_WARN . '">' . $currentDeviceConfiguration['applicationVersion'] . "</td>";
				}
			}
			$htmlOutput .= "<td>" . $currentDeviceConfiguration['serialNumber'] . "</td>";
			if ($currentDeviceConfiguration['batteryDevice'] == 1) {
				
				$htmlOutput .= "<td>Battery Device</td>";
			}
			else {
				
				$htmlOutput .= "<td>&nbsp;</td>";
			}
			$htmlOutput .= '<tr>';
		}
		
		$htmlOutput .= '</tbody>';
		
		$htmlOutput .= '</table>';
		
		// Save the result to a variable
		SetValue($this->GetIDForIdent('DeviceConfiguration'), $htmlOutput);
		
		return true;
	}

	public function RefreshDeviceAssociations() {
				
		$htmlOutput = '';
		
		$htmlOutput .= '<table border="1px">';
		
		// Headings
		$htmlOutput .= '<thead>';
		$htmlOutput .= '<tr>';
		$htmlOutput .= '<th>Instance Name</th>';
		$htmlOutput .= '<th>Instance ID</th>';
		$htmlOutput .= '<th>Z-Wave Node ID</th>';
		$htmlOutput .= '<th>Group</th>';
		$htmlOutput .= '<th>Target Node Name</th>';
		$htmlOutput .= '<th>Target Node ID</th>';
		$htmlOutput .= '</tr>';
		$htmlOutput .= '</thead>';

		// Content
		$htmlOutput .= '<tbody>';
		
		$allZwaveDevices = $this->GetAllDevices();
		$allZwaveDeviceAssociations = Array();
	
		foreach ($allZwaveDevices as $currentDevice) {
			
			$currentDeviceAssociations = $this->GetDeviceAssociations($currentDevice);
			
			if (count($currentDeviceAssociations) > 0) {
				
				if(array_key_exists('associationGroups', $currentDeviceAssociations) ) {
				
					$allZwaveDeviceAssociations[] = $currentDeviceAssociations;
				}
			}
		}
		
		array_multisort(array_column($allZwaveDeviceAssociations, "nodeId"), SORT_ASC, $allZwaveDeviceAssociations );
		
		$i = 1;
		
		foreach ($allZwaveDeviceAssociations as $currentDeviceAssociations) {
			
			if ( ($i % 2) == 1 ) {
				
				$color = '#333333';
			}
			else {
				
				$color = '#555555';
			}
		
			foreach ($currentDeviceAssociations['associationGroups'] as $groupNumber => $targetNodeIds) {

				foreach ($targetNodeIds as $targetNodeId) {
			
					$htmlOutput .= '<tr bgcolor="' . $color . '">';
					$htmlOutput .= "<td>" . $currentDeviceAssociations['instanceName'] . "</td>";
					$htmlOutput .= "<td>" . $currentDeviceAssociations['instanceId'] . "</td>";
					$htmlOutput .= "<td>" . $currentDeviceAssociations['nodeId'] . "</td>";			
					$htmlOutput .= "<td>" . $groupNumber . "</td>";
					$htmlOutput .= "<td>" . IPS_GetName($this->GetInstanceId($targetNodeId)) . "</td>";
					$htmlOutput .= "<td>" . $targetNodeId . "</td>";
					$htmlOutput .= '<tr>';
				}
			}
			
			$i++;
		}
		
		$htmlOutput .= '</tbody>';
		
		$htmlOutput .= '</table>';
		
		// Save the result to a variable
		SetValue($this->GetIDForIdent('DeviceAssociations'), $htmlOutput);
		
		return true;
	}
	
	public function GetAllDevices() {
		
		$allZwaveDevices = IPS_GetInstanceListByModuleId(ZWAVE_DEVICE_GUID);
		
		return $allZwaveDevices;
	}
	
	public function GetDeviceHealth(int $instanceId) {
		
		$result = Array();
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return $result;
		}
		
		// IPS Information
		$result['instanceId'] = $instanceId;
		$result['instanceName'] = IPS_GetName($instanceId);
		
		// Instance configuration
		$result['nodeId'] = $this->GetZwaveNodeId($instanceId);
		
		// Z-Wave Information
		$zwaveInformationJson = ZW_GetInformation($instanceId);
		$zwaveInformation = json_decode($zwaveInformationJson);
		
		if (isset($zwaveInformation->NodeFailed) ) {
			
			if ($zwaveInformation->NodeFailed) {
				
				$result['nodeFailed'] = 1;
			}
			else {
				
				$result['nodeFailed'] = 0;
			}
		}
		
		if (isset($zwaveInformation->NodePacketSend) ) {
			
			$result['packetsSent'] = $zwaveInformation->NodePacketSend;
		}
		
		if (isset($zwaveInformation->NodePacketReceived) ) {
			
			$result['packetsReceived'] = $zwaveInformation->NodePacketReceived;
		}

		if (isset($zwaveInformation->NodePacketFailed) ) {
			
			$result['packetsFailed'] = $zwaveInformation->NodePacketFailed;
		}
		
		if (isset($zwaveInformation->NodePacketSend) && isset($zwaveInformation->NodePacketReceived) && isset($zwaveInformation->NodePacketFailed) ) {
			
			$packetsTotal = intval($zwaveInformation->NodePacketSend) + intval($zwaveInformation->NodePacketReceived) + intval($zwaveInformation->NodePacketFailed);
			
			if ($packetsTotal > 0) {
			
				$errorRate = intval($zwaveInformation->NodePacketFailed) / $packetsTotal * 100;
				$result['packetsErrorRate'] = round($errorRate,2);
			}
			else {
				
				$result['packetsErrorRate'] = 0;
			}
		}
		
		// Optimization information
		$result['lastOptimization'] = $this->GetLastOptimization($instanceId);
		
		if ($this->isBatteryDevice($instanceId)) {
			
			$result['batteryDevice'] = 1;
		}
		else {
			
			$result['batteryDevice'] = 0;
		}
		
		// Wakeup Queue Information
		if ($this->isBatteryDevice($instanceId)) {
		
			$wakeupQueue = ZW_GetWakeUpQueue($instanceId);
		
			$result['wakeupQueueLength'] = count($wakeupQueue);
			
			if (in_array('Optimize', $wakeupQueue)) {
				
				$result['wakeupQueueOptimization'] = 1;
			}
			else {
				
				$result['wakeupQueueOptimization'] = 0;
			}
		}
		else {
			
			$result['wakeupQueueLength'] = -1;
			$result['wakeupQueueOptimization'] = -1;
		}
		
		return $result;
	}
	
	public function GetDeviceRouting(int $instanceId) {
		
		$result = Array();
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return $result;
		}
		
		// IPS Information
		$result['instanceId'] = $instanceId;
		$result['instanceName'] = IPS_GetName($instanceId);
		
		// Instance configuration
		$result['nodeId'] = $this->GetZwaveNodeId($instanceId);
		
		// Routing information
		$routingList = ZW_RequestRoutingList($instanceId, false, false);
		
		if (in_array(1, $routingList) ) {
			
			$result['direct'] = 1;
			$result['hops'] = 0;
		}
		else {
			
			$result['direct'] = 0;
		}
		
		$result['count'] = count($routingList);
		
		// Z-Wave Information
		$zwaveInformationJson = ZW_GetInformation($instanceId);
		$zwaveInformation = json_decode($zwaveInformationJson);
		
		if (isset($zwaveInformation->NodePacketFailed) ) {
			
			$result['packetsFailed'] = $zwaveInformation->NodePacketFailed;
		}
		
		if (isset($zwaveInformation->NodePacketSend) && isset($zwaveInformation->NodePacketReceived) && isset($zwaveInformation->NodePacketFailed) ) {
			
			$packetsTotal = intval($zwaveInformation->NodePacketSend) + intval($zwaveInformation->NodePacketReceived) + intval($zwaveInformation->NodePacketFailed);
			
			if ($packetsTotal > 0 ) {
				
				$errorRate = intval($zwaveInformation->NodePacketFailed) / $packetsTotal * 100;
				$result['packetsErrorRate'] = round($errorRate,2);
			}
			else {
				
				$result['packetsErrorRate'] = 0;
			}
		}
		
		return $result;
	}
	
	public function GetDeviceConfiguration(int $instanceId) {
		
		$result = Array();
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return $result;
		}
		
		// IPS Information
		$result['instanceId'] = $instanceId;
		$result['instanceName'] = IPS_GetName($instanceId);
		
		// Instance configuration
		$result['nodeId'] = $this->GetZwaveNodeId($instanceId);
		
		// Z-Wave Information
		$zwaveInformationJson = ZW_GetInformation($instanceId);
		$zwaveInformation = json_decode($zwaveInformationJson);
		
		// Z-Wave classes
		$nodeClassCount = 0;
		if (isset($zwaveInformation->NodeClasses) ) {
			
			preg_match_all('/(\d+)/', $zwaveInformation->NodeClasses, $matches);
			$nodeClassCount += count($matches[1]);
			
		}
		if (isset($zwaveInformation->NodeControlClasses) ) {
			
			preg_match_all('/(\d+)/', $zwaveInformation->NodeControlClasses, $matches);
			$nodeClassCount += count($matches[1]);
			
		}
		$result['nodeClassCount'] = $nodeClassCount;
		
		$nodeSecureClassCount = 0;
		if (isset($zwaveInformation->NodeSecureClasses) ) {
			
			preg_match_all('/(\d+)/', $zwaveInformation->NodeSecureClasses, $matches);
			$nodeSecureClassCount += count($matches[1]);
			
		}
		if (isset($zwaveInformation->NodeSecureControlClasses) ) {
			
			preg_match_all('/(\d+)/', $zwaveInformation->NodeSecureControlClasses, $matches);
			$nodeSecureClassCount += count($matches[1]);
			
		}
		$result['nodeSecureClassCount'] = $nodeSecureClassCount;
		
		if (isset($zwaveInformation->ManufacturerID) ) {
			
			$result['manufacturerId'] = $zwaveInformation->ManufacturerID;
		}
		
		if (isset($zwaveInformation->ProductType) ) {
			
			$result['productType'] = $zwaveInformation->ProductType;
		}
		
		if (isset($zwaveInformation->ProductID) ) {
			
			$result['productId'] = $zwaveInformation->ProductID;
		}
		
		if (isset($zwaveInformation->VersionApplication) ) {
			
			$result['applicationVersion'] = $zwaveInformation->VersionApplication;
		}

		if (isset($zwaveInformation->SerialNumber) ) {
			
			$result['serialNumber'] = $zwaveInformation->SerialNumber;
		}
		
		if ($this->isBatteryDevice($instanceId)) {
			
			$result['batteryDevice'] = 1;
		}
		else {
			
			$result['batteryDevice'] = 0;
		}
		
		return $result;
	}
	
	public function GetDeviceAssociations(int $instanceId) {
		
		$result = Array();
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return $result;
		}
		
		// IPS Information
		$result['instanceId'] = $instanceId;
		$result['instanceName'] = IPS_GetName($instanceId);
		
		// Instance configuration
		$result['nodeId'] = $this->GetZwaveNodeId($instanceId);
		
		// Z-Wave Information
		$zwaveInformationJson = ZW_GetInformation($instanceId);
		$zwaveInformation = json_decode($zwaveInformationJson);
		
		if (isset($zwaveInformation->MultiChannelAssociationGroups) ) {
			
			$group = 1;
			$multiChannelAssociationGroups = json_decode($zwaveInformation->MultiChannelAssociationGroups);
			
			foreach ($multiChannelAssociationGroups as $currentGroup) {
				
				$targetNodes = Array();
				
				foreach ($currentGroup->Nodes as $currentNode) {
					
					// Skip associations to only the main controller
					if ($currentNode != 1) {
						
						$targetNodes[] = $currentNode;
					}
				}
				
				if (count($targetNodes) > 0) {
					
					$result['associationGroups'][$group] = $targetNodes;
				}
				
				$group++;
			}
		}
		
		return $result;
	}
	
	public function IsDeviceHealthy(int $instanceId) {
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return false;
		}
		
		$zwaveInformationJson = ZW_GetInformation($instanceId);
		$zwaveInformation = json_decode($zwaveInformationJson);
	
		if (isset($zwaveInformation->NodeFailed) ) {
			
			if ($zwaveInformation->NodeFailed) {
				
				return false;
			}
			else {
				
				return true;
			}
		}
		else {
			
			return false;
		}
	}
	
	public function isBatteryDevice(int $instanceId) {
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return false;
		}
		
		$zwaveInformationJson = ZW_GetInformation($instanceId);
		$zwaveInformation = json_decode($zwaveInformationJson);
	
		if (isset($zwaveInformation->WakeUpInterval) ) {
			
			if ($zwaveInformation->WakeUpInterval != -1) {
				
				// Check if the device is  a battery device from the class configuration
				$classList = json_decode($zwaveInformation->NodeClasses, true);
				
				if (in_array(hexdec(80), $classList)) {
					
						return true;
				}
				else {
					
					return false;
				}
			}
			else {
				
				return false;
			}
		}
		else {
			
			return false;
		}
	}
	
	public function GetDevicesWithFailedPackets(int $failedPacketThreshold) {
		
		$allZwaveDevices = $this->GetAllDevices();
		$devicesWithFailedPackets = Array();
		
		foreach ($allZwaveDevices as $currentDevice) {
			
			$currentDeviceHealth = $this->GetDeviceHealth($currentDevice);
			
			if (count($currentDeviceHealth) > 0) {
				
				if ($currentDeviceHealth['packetsFailed'] >= $failedPacketThreshold) {
					
					$devicesWithFailedPackets[] = $currentDeviceHealth;
				}
			}
		}
		
		array_multisort(array_column($devicesWithFailedPackets, "packetsFailed"), SORT_DESC, $devicesWithFailedPackets);
		
		$devicesSorted = Array();
		
		foreach($devicesWithFailedPackets as $currentDevice) {
			
			$devicesSorted[] = $currentDevice['instanceId'];
		}
		
		return $devicesSorted;
	}
	
	public function OptimizeBadClientEx(int $instanceId) {
		
		// Check if the submitted instance ID is a Z-wave device
		$allZwaveDevices = $this->GetAllDevices();
		
		if(! in_array($instanceId, $allZwaveDevices)) {
			
			$this->LogMessage("The provided instance ID is not a Z-Wave device","ERROR");
			return false;
		}
		
		if (GetValue($this->GetIDForIdent('OptimizeBadClientSwitch'))) {
			
			$this->LogMessage("Another optimization is already in progress. Aborting","ERROR");
			return false;
		}
		
		SetValue($this->GetIDForIdent('OptimizeBadClientInstanceId'), $instanceId);
		$this->OptimizeBadClient();
	}
	
	public function OptimizeBadClient() {
		
		$instanceId = 0;
		
		if ( (GetValue($this->GetIDForIdent('OptimizeBadClientSwitch'))) && ($_IPS['SENDER'] != "TimerEvent") ) {
			
			$this->LogMessage("Another optimization is already in progress. Aborting");
			return;
		}
		
		if (GetValue($this->GetIDForIdent('OptimizeBadClientInstanceId')) != 0) {
			
			$instanceId = GetValue($this->GetIDForIdent('OptimizeBadClientInstanceId'));
		}
		
		if ($instanceId == 0) {
			
			// No specific instance was handed over, so we fetch a bad client from the list
			$badClients = $this->GetDevicesWithFailedPackets($this->ReadPropertyInteger("CriticalThreshold") );
			
			if (count($badClients) == 0) {
				
				$this->LogMessage("No bad clients found.","DEBUG");
				return;
			}
			else {
				
				$this->LogMessage("Bad clients found: " . count($badClients) . " / Optimizing the worst one: " . $badClients[0], "DEBUG");
				$instanceId = $badClients[0];
			}
		}
		
		if (GetValue($this->GetIDForIdent('OptimizeBadClientRun')) >= $this->ReadPropertyInteger('OptimizationTotalRuns') ) {
			
			$this->LogMessage("Optimization for instance $instanceId / " . IPS_GetName($instanceId) . " / Z-Wave Node ID: " . $this->GetZwaveNodeId($instanceId) . " is complete");
			SetValue($this->GetIDForIdent('OptimizeBadClientInstanceId'), NULL);
			SetValue($this->GetIDForIdent('OptimizeBadClientRun'), 0);
			SetValue($this->GetIDForIdent('OptimizeBadClientSwitch'), false);
			$this->SetTimerInterval("OptimizeBadClientRunTimer", 0);
			ZW_ResetStatistics($instanceId);
			$this->SetLastDeviceOptimization($instanceId);
				
			return;
		}
		
		$lastRun = GetValue($this->GetIDForIdent('OptimizeBadClientRun'));
		$currentRun = $lastRun + 1;
		$this->LogMessage("Starting Optimization for instance $instanceId / " . IPS_GetName($instanceId) . " / Z-Wave Node ID: " . $this->GetZwaveNodeId($instanceId) . " / run $currentRun of " . $this->ReadPropertyInteger('OptimizationTotalRuns'));
		
		// Activating the timer if this is the first run:
		if ($currentRun == 1) {
			
			// Don't do additional runs if the device is a battery device
			if ($this->isBatteryDevice($instanceId)) {
				
				// Try to find out if another optimization is already queued
				$wakeupQueue = ZW_GetWakeUpQueue($instanceId);
			
				if (in_array('Optimize', $wakeupQueue)) {
				
					$this->LogMessage("The device is a battery device. A second optimization will not be added to the list. No further runs will be executed.");					
				}
				else {
				
					$this->LogMessage("The device is a battery device. The optimization will be added to the queue. No further runs will be executed.");					
					ZW_ResetStatistics($instanceId);
				}
				
				SetValue($this->GetIDForIdent('OptimizeBadClientInstanceId'), NULL);
				SetValue($this->GetIDForIdent('OptimizeBadClientRun'), 0);
				SetValue($this->GetIDForIdent('OptimizeBadClientSwitch'), false);
				$this->SetTimerInterval("OptimizeBadClientRunTimer", 0);
				
				$this->SetLastDeviceOptimization($instanceId);
				
				return;
			}
			
			SetValue($this->GetIDForIdent('OptimizeBadClientSwitch'), true);
			$newInterval = $this->ReadPropertyInteger("OptimizationRuntime") * 1000;
			$this->SetTimerInterval("OptimizeBadClientRunTimer", $newInterval);
			SetValue($this->GetIDForIdent('OptimizeBadClientInstanceId'), $instanceId);
		}

		ZW_Optimize($instanceId);
		
		SetValue($this->GetIDForIdent('OptimizeBadClientRun'), $currentRun);
	}
	
	protected function GetZwaveNodeId($instanceId) {
		
		$nodeId = IPS_GetProperty($instanceId, 'NodeID');

		return $nodeId;
	}
	
	protected function LookupManufacturerId($manufacturerId) {
		
		$manufacturerName = $manufacturerId;

		switch($manufacturerId) {

			case "010F":
				$manufacturerName = "Fibaro";
				break;
			case "0086":
				$manufacturerName = "AEON Labs";
				break;
			case "013C":
				$manufacturerName = "Philio";
				break;
			case "0258":
				$manufacturerName = "Shenzen neo";
				break;
			case "019A":
				$manufacturerName = "Sensative";
				break;
		}

		return $manufacturerName;
	}
	
	public function GetInstanceId(int $nodeId) {
		
		$allZwaveDevices = $this->GetAllDevices();
		
		$allNodeIds = Array();
		
		foreach ($allZwaveDevices as $currentDevice) {
			
			$currentNodeId = $this->GetZwaveNodeId($currentDevice);
			$allNodeIds[$currentNodeId] = $currentDevice;
		}
		
		if (array_key_exists($nodeId, $allNodeIds) ) {
			
			return $allNodeIds[$nodeId];
		}
		else {
			
			return false;
		}
	}
	
	protected function LookupProductId($manufacturerId, $productTypeId, $productId) {
		
		$productName = $productTypeId . " " . $productId;

		if ( ($manufacturerId == "010F") && ($productTypeId == "0102") && ($productId == "1000") ) {

			$productName = "Dimmer 2";
		}

		if ( ($manufacturerId == "010F") && ($productTypeId == "0602") && ($productId == "1001") ) {

			$productName = "FGWPE new";
		}

		if ( ($manufacturerId == "010F") && ($productTypeId == "0600") && ($productId == "1000") ) {

			$productName = "FGWPE old";
		}
		
		if ( ($manufacturerId == "010F") && ($productTypeId == "0603") && ($productId == "1003") ) {

			$productName = "FGWPE/F-102 ZW5";
		}

		if ( ($manufacturerId == "019A") && ($productTypeId == "0003") && ($productId == "0003") ) {

			$productName = "Strips Door / Window";
		}

		if ( ($manufacturerId == "0086") && ($productTypeId == "0003") && ($productId == "0074") ) {

			$productName = "Nano Dimmer with energy metering";
		}
		
		if ( ($manufacturerId == "0086") && ($productTypeId == "0002") && ($productId == "0070") ) {

			$productName = "Door / Windows Sensor 6";
		}
		
		if ( ($manufacturerId == "0086") && ($productTypeId == "0002") && ($productId == "0064") ) {

			$productName = "MultiSensor 6";
		}
		
		if ( ($manufacturerId == "0086") && ($productTypeId == "0003") && ($productId == "0013") ) {

			$productName = "Micro Smart Dimmer (2nd edition)";
		}


		return $productName;
	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		
		IPS_LogMessage($this->ReadPropertyString('Sender'), $messageComplete);
	}
	
	protected function SetLastDeviceOptimization(int $instanceId) {
		
		$allDevices = json_decode(GetValue($this->GetIDForIdent('LastOptimization')), true);
		
		$allDevices[$instanceId] = time();
		
		SetValue($this->GetIDForIdent('LastOptimization'), json_encode($allDevices));
		
		return true;
	}
	
	protected function GetLastOptimization(int $instanceId) {
		
		$allDevices = json_decode(GetValue($this->GetIDForIdent('LastOptimization')), true);
		
		if (array_key_exists($instanceId, $allDevices)) {
			
			return $allDevices[$instanceId];
		}
		else {
			
			return 0;
		}
	}
	
	protected function GetDesiredFirmwareVersion($manufacturerId, $productTypeId, $productId) {
		
		$desiredFirmwareVersions = json_decode(GetValue($this->GetIDForIdent('DesiredFirmwareVersions')), true);
		
		$result = -1;
		
		if (! (array_key_exists($manufacturerId, $desiredFirmwareVersions))) {
			
			return $result;
		}
		
		if (! (array_key_exists($productTypeId, $desiredFirmwareVersions[$manufacturerId]))) {
			
			return $result;
		}
		
		if (! (array_key_exists($productId, $desiredFirmwareVersions[$manufacturerId][$productTypeId]))) {
			
			return $result;
		}
		
		return $desiredFirmwareVersions[$manufacturerId][$productTypeId][$productId];
	}
	
	public function SetDesiredFirmwareVersion(string $manufacturerId, string $productTypeId, string $productId, string $desiredFirmwareVersion) {
	
		$desiredFirmwareVersions = json_decode(GetValue($this->GetIDForIdent('DesiredFirmwareVersions')), true);
		
		$desiredFirmwareVersions[$manufacturerId][$productTypeId][$productId] = $desiredFirmwareVersion;
		
		SetValue($this->GetIDForIdent('DesiredFirmwareVersions'), json_encode($desiredFirmwareVersions));
		
		return true;
	}
}
?>
