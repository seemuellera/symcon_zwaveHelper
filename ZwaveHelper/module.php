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
		$this->RegisterPropertyInteger("OptimizationInterval", 600);
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
		
		$this->RegisterVariableString("DeviceConfiguration","Device Configuration","~HTMLBox");
		$this->RegisterVariableString("DeviceAssociations","Device Associations","~HTMLBox");
		
		// Default Actions
		// $this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'ZWHELPER_RefreshInformation($_IPS[\'TARGET\']);');
		$this->RegisterTimer("OptimizeBadClient", 0 , 'ZWHELPER_OptimizeBadClient($_IPS[\'TARGET\']);');
		$this->RegisterTimer("OptimizeBadClientRunTimer", 0 , 'ZWHELPER_OptimizeBadClient($_IPS[\'TARGET\']);');
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
	}
	
	public function RequestAction($Ident, $Value) {
	
		/*
		switch ($Ident) {
		
			
			case "Status":
				// Default Action for Status Variable
				
				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
			
		}
		*/
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
		
		foreach ($allZwaveDeviceAssociations as $currentDeviceAssociations) {
		
			foreach ($currentDeviceAssociations['associationGroups'] as $groupNumber => $targetNodeIds) {

				foreach ($targetNodeIds as $targetNodeId) {
			
					$htmlOutput .= '<tr>';
					$htmlOutput .= "<td>" . $currentDeviceAssociations['instanceName'] . "</td>";
					$htmlOutput .= "<td>" . $currentDeviceAssociations['instanceId'] . "</td>";
					$htmlOutput .= "<td>" . $currentDeviceAssociations['nodeId'] . "</td>";			
					$htmlOutput .= "<td>" . $groupNumber . "</td>";
					$htmlOutput .= "<td>" . IPS_GetName($this->GetInstanceId($targetNodeId)) . "</td>";
					$htmlOutput .= "<td>" . $targetNodeId . "</td>";
					$htmlOutput .= '<tr>';
				}
			}
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
	
	public function OptimizeBadClient() {
		
		$instanceId = 0;
		
		$this->LogMessage("Sender: " . $_IPS['Sender']);
		
		// Fix this with the right sender
		/*
		if (GetValue($this->GetIDForIdent('OptimizeBadClientSwitch'))) {
			
			$this->LogMessage("Another optimization is already in progress. Aborting");
			return;
		}
		*/
		
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
				
			return;
		}
		
		$lastRun = GetValue($this->GetIDForIdent('OptimizeBadClientRun'));
		$currentRun = $lastRun + 1;
		$this->LogMessage("Starting Optimization for instance $instanceId / " . IPS_GetName($instanceId) . " / Z-Wave Node ID: " . $this->GetZwaveNodeId($instanceId) . " / run $currentRun of " . $this->ReadPropertyInteger('OptimizationTotalRuns'));
		
		// Activating the timer if this is the first run:
		if ($currentRun == 1) {
			
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

			$productName = "FGD-212";
		}

		if ( ($manufacturerId == "010F") && ($productTypeId == "0602") && ($productId == "1001") ) {

			$productName = "FGWPE new";
		}

		if ( ($manufacturerId == "010F") && ($productTypeId == "0600") && ($productId == "1000") ) {

			$productName = "FGWPE old";
		}

		if ( ($manufacturerId == "019A") && ($productTypeId == "0003") && ($productId == "0003") ) {

			$productName = "Strips Door / Window";
		}

		if ( ($manufacturerId == "0086") && ($productTypeId == "0003") && ($productId == "0074") ) {

			$productName = "Nano Dimmer with energy metering";
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
}
?>
