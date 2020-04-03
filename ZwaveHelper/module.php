<?php

define("ZWAVE_DEVICE_GUID","{101352E1-88C7-4F16-998B-E20D50779AF6}");

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
		
		$htmlOutput = '';
		
		$htmlOutput .= '<table border="1px">';
		
		// Headings
		$htmlOutput .= '<thead>';
		$htmlOutput .= '<tr>';
		$htmlOutput .= '<th>Instance Name</th>';
		$htmlOutput .= '<th>Instance ID</th>';
		$htmlOutput .= '</tr>';
		$htmlOutput .= '</thead>';

		// Content
		$htmlOutput .= '<tbody>';
		
		$allZwaveDevices = $this->GetAllDevices();
		
		foreach ($allZwaveDevices as $currentDevice) {
		
			$htmlOutput .= '<tr>';
			$htmlOutput .= "<td>" . IPS_GetName($currentDevice) . "</td>";
			$htmlOutput .= "<td>" . $currentDevice . "</td>";
			$htmlOutput .= '</tr>';
		}
		
		$htmlOutput .= '</tbody>';
		
		$htmlOutput .= '</table>';
		
		// Save the result to a variable
		SetValue($this->GetIDForIdent('DeviceHealth'), $htmlOutput);
		return true;
	}
	
	public function GetAllDevices() {
		
		$allZwaveDevices = IPS_GetInstanceListByModuleId(ZWAVE_DEVICE_GUID);
		
		return $allZwaveDevices;
	}
}
?>
