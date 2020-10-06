<?php

	require_once(__DIR__ . "/../libs/LightControlModule.php");
	class DimControl extends LightControlModule {

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->createVariableProfiles();

			$this->RegisterPropertyInteger("BottomDimValue", 0);
			$this->RegisterPropertyInteger("TopDimValue", 0);
			$this->RegisterPropertyInteger("BottomSwitchValue", 0);
			$this->RegisterPropertyInteger("TopSwitchValue", 0);
			$this->RegisterPropertyInteger("IlluminationValue", 0);
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

		public function applyLightState(int $illumination) {
			if(!$this->isModuleActive()) {
				return;
			}

			$transitionTime = $this->ReadPropertyInteger("DelayValue");

			$dimValue = $this->calculateDimValue($illumination);
			$this->SetValue("CurrentDimValue", $dimValue);

			$switchValue = $this->calculateSwitchValue($illumination);
			$this->SetValue("CurrentSwitchValue", $switchValue);

			$instances = $this->getRegisteredInstances();

			foreach($instances as $instance) {
				$this->switchLight($instance->InstanceID, $dimValue, $switchValue, $transitionTime);
			}
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
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
