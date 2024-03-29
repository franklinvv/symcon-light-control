<?php

	require_once(__DIR__ . "/../libs/LightControlModule.php");

	define("PROPERTY_OFF_TIMEOUT", "OffTimeout");
	define("PROPERTY_TRIGGERING_VARIABLES", "TriggeringVariables");
	define("PROPERTY_LIGHT_SENSOR_VALUES", "LightSensorValues");
	define("PROPERTY_DYNAMIC_TIMEOUT", "DynamicTimeout");
	define("ATTRIBUTE_LAST_TRIGGERED", "AttributeLastTriggered");
	class TimeoutControl extends LightControlModule {

		var $timerName = "MotionTimeout";
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyInteger(PROPERTY_OFF_TIMEOUT, 60);
			$this->RegisterPropertyString(PROPERTY_TRIGGERING_VARIABLES, 0);
			$this->RegisterPropertyString(PROPERTY_LIGHT_SENSOR_VALUES, 0);
			$this->RegisterPropertyBoolean(PROPERTY_DYNAMIC_TIMEOUT, 0);

			$this->RegisterAttributeInteger(ATTRIBUTE_LAST_TRIGGERED, 0);

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

			// unregister from all messages, so we can safely readd just the necessary ones
			foreach(array_keys($this->GetMessageList()) as $message) {
				$this->UnregisterMessage($message, VM_UPDATE);
			}

			// register for triggering variable changes
			$motionSensorSources = $this->getTriggeringVariables();
			if($motionSensorSources) {
				foreach($motionSensorSources as $motionSensorSource) {
					$this->RegisterMessage($motionSensorSource->VariableID, VM_UPDATE);
				}
			}
		}

		public function MessageSink($timestamp, $senderId, $message, $data) {
			if($message != VM_UPDATE) return;

			$object = IPS_GetObject(IPS_GetParent($senderId));
			$this->SendDebug(sprintf("%s (#%d)", $object["ObjectName"], $senderId), GetValueBoolean($senderId) ? "Active" : "Inactive", 0);

			$isMotionActive = $this->isMotionActive();


			$instances = $this->getRegisteredInstances();

			if($isMotionActive) {
				if(!$this->isModuleActive()) return;
				
				if(!$this->areLightSensorsEnabled()) {
					$this->SendDebug("Main", "Too bright to turn on lights", 0);
					return;
				}
				$this->SendDebug("Main", "Turning everything on", 0);
				if($this->ReadAttributeInteger(ATTRIBUTE_LAST_TRIGGERED) == 0) {
					$this->WriteAttributeInteger(ATTRIBUTE_LAST_TRIGGERED, time());
				}

				foreach($instances as $instance) {
					$this->switchLight($instance->InstanceID, $instance->DimLevelHigh, true, 0);
				}
				$this->SetTimerInterval($this->timerName, 0);
			} else {
				$offTimeout = $this->getTimeoutSeconds();

				if($this->GetTimerInterval($this->timerName) == 0) {
					$this->SendDebug("Main", "Starting dimming sequence", 0);
					$this->dimLights();
					$this->SetTimerInterval($this->timerName, $offTimeout*1000);
				} else {
					$this->WriteAttributeInteger(ATTRIBUTE_LAST_TRIGGERED, 0);
					$this->SendDebug("Main", "Turning everything off", 0);
					$this->turnOff();
				}
			}
		}

		private function getTimeoutSeconds() {
			if($this->ReadPropertyBoolean(PROPERTY_DYNAMIC_TIMEOUT)) {
				$maxTimeOut = $this->ReadPropertyInteger("OffTimeout");
				$timestampLastTriggered = $this->ReadAttributeInteger(ATTRIBUTE_LAST_TRIGGERED);
				$timeout = min($maxTimeOut, (time()-$timestampLastTriggered) / 2);
				$this->SendDebug("Main", sprintf("Activity lasted for %d seconds. Setting timeout to %d seconds.", time()-$timestampLastTriggered, $timeout), 0);
				return $timeout;
			} else {
				return $this->ReadPropertyInteger("OffTimeout");
			}
		}

		private function areLightSensorsEnabled() {
			$lightSensors = $this->getLightSensors();
			if($lightSensors) {
				foreach($lightSensors as $lightSensor) {
					$sensorValue = GetValueFloat($lightSensor->VariableID);
					$this->SendDebug("Main", sprintf("Variable ID: %d, sensorValue: %f, threshold: %d", $lightSensor->VariableID, $sensorValue, $lightSensor->Threshold), 0);
					if($sensorValue <= $lightSensor->Threshold) {
						return true;
					}
				}
				return false;
			} else {
				$this->SendDebug("Main", "No light sensors", 0);
				return true;
			}
		}

		private function isMotionActive() {
			$triggeringVariables = $this->getTriggeringVariables();
			$isMotionActive = false;
			foreach($triggeringVariables as $triggeringVariable) {
				$object = IPS_GetObject(IPS_GetParent($triggeringVariable->VariableID));
				if(GetValueBoolean($triggeringVariable->VariableID)) {
					$isMotionActive = true;
				}
			}
			return $isMotionActive;
		}

		protected function getTriggeringVariables() {
			$variablesJson = $this->ReadPropertyString("TriggeringVariables");
			$result = json_decode($variablesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		protected function getLightSensors() {
			$variablesJson = $this->ReadPropertyString("LightSensorValues");
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
			$offTimeout = $this->getTimeoutSeconds();
			$instances = $this->getRegisteredInstances();
			$this->SendDebug("Main", sprintf("Dimming lights over a period of %d seconds", $offTimeout));
			foreach($instances as $instance) {
				if($this->getLightBrightness($instance->InstanceID) > $instance->DimLevelLow) {
					$this->switchLight($instance->InstanceID, $instance->DimLevelLow, true, round($offTimeout/2));
				}
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
			$this->WriteAttributeInteger(ATTRIBUTE_LAST_TRIGGERED, 0);
		}
	}