<?php

	require_once(__DIR__ . "/../include/LightControlModule.php");
	class TimeoutControl extends LightControlModule {

		var $timerName = "MotionTimeout";
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyInteger("MotionSensorValueID", 0);
			$this->RegisterPropertyInteger("DimLevelWhenMotionActive", 100);
			$this->RegisterPropertyInteger("DimLevelWhenMotionInactive", 10);
			$this->RegisterPropertyInteger("OffTimeout", 60);

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

			$motionSensorSource = $this->ReadPropertyInteger("MotionSensorValueID");
			if($motionSensorSource) {
				$this->RegisterMessage($motionSensorSource, VM_UPDATE);

				// $isMotionActive = GetValueBoolean($motionSensorSource);
				// $this->applyLightState($isMotionActive);
			}
		}

		public function MessageSink($timestamp, $senderId, $message, $data) {
			if(!$this->isModuleActive()) {
				return;
			}

			$motionSensorSource = $this->ReadPropertyInteger("MotionSensorValueID");
			if($senderId != $motionSensorSource) {
				$this->UnregisterMessage($senderId, VM_UPDATE);
			}

			$offTimeout = $this->ReadPropertyInteger("OffTimeout");

			$isMotionActive = GetValueBoolean($motionSensorSource);

			$instances = $this->getRegisteredInstances();

			$dimLevelWhenMotionActive = $this->ReadPropertyInteger("DimLevelWhenMotionInactive");

			if($isMotionActive) {
				foreach($instances as $instance) {
					$this->SetTimerInterval($this->timerName, 0);
					$this->switchLight($instance->InstanceID, 100, true, round($offTimeout/2));
				}
			} else {
				if($this->GetTimerInterval() == 0) {
					$this->SetTimerInterval($this->timerName, $offTimeout*1000);
				} else {
					turnOff();
				}
			}
		}

		function dimLights() {
			$instances = $this->getRegisteredInstances();
			$dimLevelWhenMotionInactive = $this->ReadPropertyInteger("DimLevelWhenMotionActive");
			foreach($instances as $instance) {
				$this->switchLight($instance->InstanceID, $dimLevelWhenMotionInactive, true, 0);
			}
		}

		function turnOff() {
			$instances = $this->getRegisteredInstances();
			foreach($instances as $instance) {
				$this->switchLight($instance->InstanceID, 0, false, 0);
			}
			$this->SetTimerInterval($this->timerName, 0);
		}
	}