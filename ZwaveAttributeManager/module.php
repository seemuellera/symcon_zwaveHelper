<?php

// GUID to identify the Z-Wave devices
define("ZWAVE_DEVICE_GUID","{101352E1-88C7-4F16-998B-E20D50779AF6}");

// colors
define("COLOR_OK","green");
define("COLOR_WARN","orange");
define("COLOR_CRIT","red");

// Klassendefinition
class ZwaveAttributeManager extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","ZwaveAttributeManager");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("FetchInterval",0);
		$this->RegisterPropertyInteger("ZwaveInstanceId",0);
		$this->RegisterPropertyInteger("ParameterId",0);
		
		// Variables
		$this->RegisterVariableInteger("TargetValue", "Target Value");
		$this->RegisterVariableInteger("CurrentValue", "Current Value");
		$this->RegisterVariableBoolean("ParameterInSync","Parameter Value in Sync","~Alert.Reversed");
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		
		// Default Actions
		$this->EnableAction("TargetValue");
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'ZWAM_RefreshInformation($_IPS[\'TARGET\']);');
		$this->RegisterTimer("FetchInformation", 0 , 'ZWAM_FetchValueFromDevice($_IPS[\'TARGET\']);');
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
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "FetchInterval", "caption" => "Fetch from Device Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "SelectInstance", "name" => "ZwaveInstanceId", "caption" => "Zwave Instance", "validModules" => Array(ZWAVE_DEVICE_GUID));
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "ParameterId", "caption" => "Parameter ID");
		
		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'ZWAM_RefreshInformation($id);');
		
		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		if (! GetValue($this->GetIDForIdent("Status")) ) {

			$this->LogMessage("Skipping Refresh because instance Status is off", "DEBUG");
			return;
		}

		$this->LogMessage("Refresh in progress","DEBUG");
		
		$this->GetCurrentValue();

		if ( GetValue($this->GetIDForIdent("CurrentValue")) == GetValue($this->GetIDForIdent("TargetValue")) ) {

			SetValue($this->GetIDForIdent("ParameterInSync"), true);
		}
		else {

			SetValue($this->GetIDForIdent("ParameterInSync"), false);
		}
	}
	
	public function RequestAction($Ident, $Value) {
	
		switch ($Ident) {
				
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			case "TargetValue":
				$this->SetTargetValue($Value);
				IPS_Sleep(500);
				$this->RefreshInformation();
				break;
			default:
				throw new Exception("Invalid Ident");
			
		}
	}
		
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}
	
	public function FetchCurrentValue() {

		if (! GetValue($this->GetIDForIdent("Status")) ) {

			$this->LogMessage("Skipping fetch current value because instance Status is off", "DEBUG");
			return;
		}

		$result = ZW_ConfigurationGetValue($this->ReadPropertyInteger("ZwaveInstanceId"), $this->ReadPropertyInteger("ParameterId"));

		if (! $result) {

			$this->LogMessage("Unable to fetch configuration value from device","WARN");
		}
	}

	public function GetCurrentValue() {

		if (! GetValue($this->GetIDForIdent("Status")) ) {

			$this->LogMessage("Skipping get current value because instance Status is off", "DEBUG");
			return;
		}

		$result = ZW_GetInformation($this->ReadPropertyInteger("ZwaveInstanceId"));

		if (! $result) {

			$this->LogMessage("Unable to get device information", "ERROR");
			return;
		}

		$resultDecoded = json_decode($result);
		$parameterJson = $resultDecoded->ConfigurationValues;
		$parameters = json_decode($parameterJson, true);

		$parameterValue = $parameters[$this->ReadPropertyInteger("ParameterId")]["Value"];

		SetValue($this->GetIDForIdent("CurrentValue"), $parameterValue);
	}

	public function SetTargetValue(int $newValue) {

		if (! GetValue($this->GetIDForIdent("Status")) ) {

			$this->LogMessage("Skipping set value because instance Status is off", "DEBUG");
			return;
		}

		SetValue($this->GetIDForIdent("TargetValue"), $newValue);

		$result = ZW_ConfigurationSetValue($this->ReadPropertyInteger("ZwaveInstanceId"), $this->ReadPropertyInteger("ParameterId"), $newValue);

		if (! $result) {

			$this->LogMessage("Unable to set configuration value", "CRIT");
		}
	}
}
?>
