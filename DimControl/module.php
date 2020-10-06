<?php
	class DimControl extends IPSModule {

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->createVariableProfiles();

			$this->RegisterPropertyInteger("BottomDimValue", 0);
			$this->RegisterPropertyInteger("TopDimValue", 0);
			$this->RegisterPropertyInteger("BottomSwitchValue", 0);
			$this->RegisterPropertyInteger("TopSwitchValue", 0);
			$this->RegisterPropertyInteger("IlluminationValue", 0);
			$this->RegisterPropertyString("Instances", "");
			$this->RegisterPropertyString("EnablingVariables", "");
			$this->RegisterPropertyInteger("DelayValue", 5);
			
			$this->RegisterVariableInteger("CurrentDimValue", "Current dim value", "LC.Brightness", 0);
			$this->RegisterVariableBoolean("CurrentSwitchValue", "Current switch value", "~Switch", false);
		}

		public function ApplyChanges() {
			//Never delete this line!
			parent::ApplyChanges();

			$illuminationValueSource = $this->ReadPropertyInteger("IlluminationValue");
			if($illuminationValueSource) {
				$this->RegisterMessage($illuminationValueSource, VM_UPDATE);

				$illumination = GetValueInteger($illuminationValueSource);
				$this->applyLightState($illumination);
			}
		}

		private function createVariableProfiles() {
			$profileName = "LC.Brightness";
			IPS_DeleteVariableProfile($profileName);

			IPS_CreateVariableProfile($profileName, 1);
			IPS_SetVariableProfileText($profileName, "", "%");
			IPS_SetVariableProfileIcon($profileName,  "Intensity");
		}

		public function MessageSink($timestamp, $senderId, $message, $data) {
			$illuminationValueSource = $this->ReadPropertyInteger("IlluminationValue");
			if($senderId != $illuminationValueSource) {
				$this->UnregisterMessage($senderId, VM_UPDATE);
			}

			$illumination = GetValueInteger($illuminationValueSource);
			$this->applyLightState($illumination);
		}

		public function applyLightState($illumination) {
			$enablingVariables = $this->getEnablingVariables();
			foreach($enablingVariables as $enablingVariable) {
				if(GetValueBoolean($enablingVariable->VariableID) && $enablingVariable->Invert) {
					return;
				}

				if(!GetValueBoolean($enablingVariable->VariableID) && !$enablingVariable->Invert) {
					return;
				}
			}

			$transitionTime = $this->ReadPropertyInteger("DelayValue") * 10;

			$dimValue = $this->calculateDimValue($illumination);
			$this->SetValue("CurrentDimValue", $dimValue);

			$switchValue = $this->calculateSwitchValue($illumination);
			$this->SetValue("CurrentSwitchValue", $switchValue);

			$instances = $this->getRegisteredInstances();

			foreach($instances as $instance) {
				$instance = IPS_GetInstance($instance->InstanceID);
				switch($instance["ModuleInfo"]["ModuleName"]) {
					case "HUELight":
						$params = array(
							"BRIGHTNESS" => $dimValue*2,54,
							"STATE" => ($dimValue > 0),
							"TRANSITIONTIME" => $transitionTime
						);
						HUE_SetValues($instance["InstanceID"], $params);
						IPS_LogMessage("LightControl", $dimValue);
					break;
					case "Z2DLightSwitch":
						if(@IPS_GetObjectIDByIdent("Z2D_Brightness", $instance["InstanceID"])) {
							// we have brightness capabilities
							Z2D_DimSetEx($instance["InstanceID"], $dimValue, $transitionTime);
							IPS_LogMessage("LightControl", sprintf("Dim value: %d", $dimValue));
						} else if(@IPS_GetObjectIDByIdent("Z2D_State", $instance["InstanceID"])) {
							// we have on/off capabilities
							Z2D_SwitchMode($instance["InstanceID"], $switchValue);
						}
					break;
					case "Z-Wave Module":
						if(@IPS_GetObjectIDByIdent("IntensityVariable", $instance["InstanceID"])) {
							ZW_DimSetEx($instance["InstanceID"], $dimValue, $transitionTime);
						} else if(@IPS_GetObjectIDByIdent("StatusVariable", $instance["InstanceID"])) {
							ZW_SwitchMode($instance["InstanceID"], $switchValue);
						}
					break;
				}
			}
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
		}

		private function getEnablingVariables() {
			$variablesJson = $this->ReadPropertyString("EnablingVariables");
			$result = json_decode($variablesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		private function getRegisteredInstances() {
			$instancesJson = $this->ReadPropertyString("Instances");
			$result = json_decode($instancesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		private function calculateDimValue($illuminationValue) {
			$bottomDimValue = $this->ReadPropertyInteger("BottomDimValue");
			$topDimValue = $this->ReadPropertyInteger("TopDimValue");

			$delta = $topDimValue - $bottomDimValue;

			$dimValue = 100 - round((($illuminationValue - $bottomDimValue) / $delta) * 100);
			return min(100, max(0, $dimValue));
		}

		private function calculateSwitchValue($illuminationValue) {
			$bottomSwitchValue = $this->ReadPropertyInteger("BottomSwitchValue");
			$topSwitchValue = $this->ReadPropertyInteger("TopSwitchValue");

			$switchValue = $this->GetValue("CurrentSwitchValue");

			if($illuminationValue <= $bottomSwitchValue) {
				return true;
			}
			if($illuminationValue >= $topSwitchValue) {
				return false;
			}

			return $switchValue;
		}
	}
