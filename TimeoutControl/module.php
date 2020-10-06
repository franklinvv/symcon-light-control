<?php

	require_once(__DIR__ . "/../libs/LightControlModule.php");
	class TimeoutControl extends LightControlModule {

		var $timerName = "MotionTimeout";
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyInteger("OffTimeout", 60);
			$this->RegisterPropertyString("TriggeringVariables", 0);

			$this->RegisterTimer($this->timerName, 0, "TC_turnOff(\$_IPS['TARGET']);");
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$motionSensorSources = $this->getTriggeringVariables();
			foreach($motionSensorSources as $motionSensorSource) {
				$this->RegisterMessage($motionSensorSource->VariableID, VM_UPDATE);
			}
		}

		public function MessageSink($timestamp, $senderId, $message, $data) {
			if(!$this->isModuleActive()) {
				return;
			}

			$isMotionActive = false;
			if($this->isSenderValid($senderId)) {
				$isMotionActive = $this->isMotionActive();
			} else {
				return;
			}
			

			$offTimeout = $this->ReadPropertyInteger("OffTimeout");

			$instances = $this->getRegisteredInstances();

			if($isMotionActive) {
				$this->SetTimerInterval($this->timerName, 0);
				foreach($instances as $instance) {
					$this->switchLight($instance->InstanceID, $instance->DimLevelHigh, true, 0);
				}
			} else {
				if($this->GetTimerInterval($this->timerName) == 0) {
					$this->SetTimerInterval($this->timerName, $offTimeout*1000);
					$this->dimLights();
				} else {
					turnOff();
				}
			}
		}

		private function isMotionActive() {
			$triggeringVariables = $this->getTriggeringVariables();
			$isMotionActive = false;
			foreach($triggeringVariables as $triggeringVariable) {
				if(GetValueBoolean($triggeringVariable->VariableID)) {
					$isMotionActive = true;
				}
			}
			return $isMotionActive;
		}

		private function isSenderValid($senderId) {
			$triggeringVariables = $this->getTriggeringVariables();
			$isMotionActive = false;
			$isSenderValid = false;
			foreach($triggeringVariables as $triggeringVariable) {
				if($senderId == $triggeringVariable->VariableID) {
					$isSenderValid = true;
				}
			}

			if(!$isSenderValid) {
				$this->UnregisterMessage($senderId, VM_UPDATE);
			}

			return $isSenderValid;
		}

		protected function getTriggeringVariables() {
			$variablesJson = $this->ReadPropertyString("TriggeringVariables");
			$result = json_decode($variablesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		function turnOn() {
			$instances = $this->getRegisteredInstances();
			foreach($instances as $instance) {
				$this->switchLight($instance->InstanceID, $instance->DimLevelHigh, true, 0);
			}
		}

		function dimLights() {
			$offTimeout = $this->ReadPropertyInteger("OffTimeout");
			$instances = $this->getRegisteredInstances();
			foreach($instances as $instance) {
				$this->switchLight($instance->InstanceID, $instance->DimLevelLow, true, round($offTimeout/2));
			}
		}

		function turnOff() {
			if($this->isMotionActive()) {
				IPS_LogMessage("TimeoutControl", "Not turning off");
				return;
			}
			$instances = $this->getRegisteredInstances();
			foreach($instances as $instance) {
				$this->switchLight($instance->InstanceID, 0, false, 0);
			}
			$this->SetTimerInterval($this->timerName, 0);
		}
	}