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
			$this->RegisterPropertyString("Variables", "");

			$this->RegisterVariableFloat("CurrentDimValue", "Current dim value", "", 0);

			$var = IPS_GetObjectIDByIdent("CurrentDimValue", $this->InstanceID);
			if($var) {
				$this->SetValue("CurrentDimValue", 0.5);
			}

			$this->RegisterVariableBoolean("CurrentSwitchValue", "Current switch value", "~Switch", true);

			$var = IPS_GetObjectIDByIdent("CurrentSwitchValue", $this->InstanceID);
			if($var) {
				$this->SetValue("CurrentSwitchValue", true);
			}
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

			$dimValue = $this->calculateDimValue($illumination);
			$this->SetValue("CurrentDimValue", $dimValue);

			$switchValue = $this->calculateSwitchValue($illumination);
			$this->SetValue("CurrentSwitchValue", $switchValue);

			$integerValue = $dimValue * 255;
			$floatValue = $dimValue;
			$boolValue = $switchValue;

			$variables = $this->getRegisteredVariables();
			IPS_LogMessage("LightControl", print_r($variables, true));
			foreach($variables as $variable) {
				$variable = IPS_GetVariable($variable->VariableID);
				switch($variable["VariableType"]) {
					case 0:
						RequestAction($variable["VariableID"], $boolValue);
					break;
					case 1:
						RequestAction($variable["VariableID"], $integerValue);
					break;
					default:
					case 2:
						RequestAction($variable["VariableID"], $floatValue);
					break;
				}
			}
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
		}

		private function getRegisteredVariables() {
			$variablesJson = $this->ReadPropertyString("Variables");
			$result = json_decode($variablesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		private function calculateDimValue($illuminationValue) {
			$bottomDimValue = $this->ReadPropertyInteger("BottomDimValue");
			$topDimValue = $this->ReadPropertyInteger("TopDimValue");
			// $bottomSwitchValue = $this->ReadPropertyInteger("BottomSwitchValue");
			// $topSwitchValue = $this->ReadPropertyInteger("TopSwitchValue");

			$delta = $topDimValue - $bottomDimValue;

			$dimValue = 1 - round(($illuminationValue - $bottomDimValue) / $delta, 1);
			return min(1.0, max(0.0, $dimValue));
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