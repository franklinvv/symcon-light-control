<?php
	class TimeoutControl extends IPSModule {

		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyString("Instances", "");
			$this->RegisterPropertyString("EnablingVariables", "");
			$this->RegisterPropertyInteger("MotionSensorValueID", 0);
			$this->RegisterPropertyInteger("DimValueWhenMotionActive", 100);
			$this->RegisterPropertyInteger("DimValueWhenMotionInactive", 10);
			$this->RegisterPropertyInteger("OffTimeout", 60);
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
		}

	}