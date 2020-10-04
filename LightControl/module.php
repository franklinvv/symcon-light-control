<?php
	class LightControl extends IPSModule {

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyInteger("BottomDimValue", 0);
			$this->RegisterPropertyInteger("TopDimValue", 0);
			$this->RegisterPropertyInteger("BottomSwitchValue", 0);
			$this->RegisterPropertyInteger("TopSwitchValue", 0);
			$this->RegisterPropertyInteger("IlluminationValue", 0);
			$this->RegisterPropertyString("Instances", "");

			$this->RegisterVariableFloat("CurrentFloatValue", "Current float value", "", 0);
//			$var = IPS_GetObjectIDByIdent("CurrentFloatValue", $this->InstanceID);

			$this->RegisterVariableBoolean("CurrentSwitchValue", "Current switch value", "~Switch", true);
//			$var = IPS_GetObjectIDByIdent("CurrentSwitchValue", $this->InstanceID);

			$this->RegisterVariableInteger("CurrentIntegerValue", "Current integer value", "", 0);
//			$var = IPS_GetObjectIDByIdent("CurrentIntegerValue", $this->InstanceID);
		}

		public function ApplyChanges() {
			//Never delete this line!
			parent::ApplyChanges();

			$illuminationValueSource = $this->ReadPropertyInteger("IlluminationValue");
			if($illuminationValueSource) {
				$this->RegisterMessage($illuminationValueSource, VM_UPDATE);
			}
		}

		public function MessageSink($timestamp, $senderId, $message, $data) {
			$illuminationValueSource = $this->ReadPropertyInteger("IlluminationValue");
			if($senderId != $illuminationValueSource) {
				$this->UnregisterMessage($senderId, VM_UPDATE);
			}

			$illumination = GetValueInteger($illuminationValueSource);

			$floatValue = $this->calculateFloatValue($illumination);
			$this->SetValue("CurrentFloatValue", $floatValue);

			$boolValue = $this->calculateSwitchValue($illumination);
			$this->SetValue("CurrentSwitchValue", $boolValue);

			$integerValue = $this->calculateIntegerValue($illumination);
			$this->SetValue("CurrentIntegerValue", $integerValue);

			$instances = $this->getRegisteredInstances();

			foreach($instances as $instance) {
				$instance = IPS_GetInstance($instance->InstanceID);
				switch($instance["ModuleInfo"]["ModuleName"]) {
					case "HUELight":
						//HUE_SetBrightness($instance["InstanceID"], $integerValue);
						$params = array(
							"BRIGHTNESS" => $integerValue,
							"STATE" => ($integerValue > 0),
							"TRANSITIONTIME" => 200
						);
						HUE_SetValues($instance["InstanceID"], $params);
						IPS_LogMessage("LightControl", $integerValue);
					break;
				}
				// switch($instance["VariableType"]) {
				// 	case 0:
				// 		RequestAction($variable["VariableID"], $boolValue);
				// 	break;
				// 	case 1:
				// 		RequestAction($variable["VariableID"], $integerValue);
				// 	break;
				// 	default:
				// 	case 2:
				// 		RequestAction($variable["VariableID"], $floatValue);
				// 	break;
				// }
			}
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
		}

		private function getRegisteredInstances() {
			$instancesJson = $this->ReadPropertyString("Instances");
			$result = json_decode($instancesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		private function calculateFloatValue($illuminationValue) {
			$bottomDimValue = $this->ReadPropertyInteger("BottomDimValue");
			$topDimValue = $this->ReadPropertyInteger("TopDimValue");

			$delta = $topDimValue - $bottomDimValue;

			$dimValue = 1 - round(($illuminationValue - $bottomDimValue) / $delta, 1);
			return min(1.0, max(0.0, $dimValue));
		}

		private function calculateIntegerValue($illuminationValue) {
			$bottomDimValue = $this->ReadPropertyInteger("BottomDimValue");
			$topDimValue = $this->ReadPropertyInteger("TopDimValue");

			$delta = $topDimValue - $bottomDimValue;

			$dimValue = 254 - round((($illuminationValue - $bottomDimValue) / $delta) * 254);
			return min(254, max(0, $dimValue));
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
