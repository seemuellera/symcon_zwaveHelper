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
		
		// Variables
		$this->RegisterVariableString("DeviceHealth","Device Health","~HTMLBox");
		$this->RegisterVariableInteger("DeviceHealthOk","Devices in State Healthy");
		$this->RegisterVariableInteger("DeviceHealthWarn","Devices in State Warning");
		$this->RegisterVariableInteger("DeviceHealthCrit","Healthy in State Critical");
		
		// Default Actions
		// $this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'ZWHELPER_RefreshInformation($_IPS[\'TARGET\']);');

    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {

		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		

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
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "WarningThreshold", "caption" => "Failed Packets - Warning Threshold");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "CriticalThreshold", "caption" => "Failed Packets - Critical Threshold");

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'ZWHELPER_RefreshInformation($id);');
		
		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		IPS_LogMessage($_IPS['SELF'],"ZWHELPER - Refresh in progress");
		
		$this->RefreshDeviceHealth();
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
	
	public function GetAllDevices() {
		
		$allZwaveDevices = IPS_GetInstanceListByModuleId(ZWAVE_DEVICE_GUID);
		
		return $allZwaveDevices;
	}
	
	public function GetDeviceHealth($instanceId) {
		
		$result = Array();
		
		// Return an empty array and stop processing if the ID does not exists
		if (! IPS_InstanceExists($instanceId) ) {
		
			return $result;
		}
		
		// IPS Information
		$result['instanceId'] = $instanceId;
		$result['instanceName'] = IPS_GetName($instanceId);
		
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
	
	public function IsDeviceHealthy($instanceId) {
		
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
	
	public function GetDevicesWithFailedPackets($failedPacketThreshold) {
		
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
			
			devicesSorted[] = $currentDevice['instanceId'];
		}
		
		return $devicesSorted;
	}
}
?>
