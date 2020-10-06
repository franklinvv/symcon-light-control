<?php
    class LightControlModule extends IPSModule {

        public function Create() {
            parent::Create();
            $this->RegisterPropertyString("Instances", "");
			$this->RegisterPropertyString("EnablingVariables", "");
        }

        protected function getLightBrightness($instanceId) {
            $instance = IPS_GetInstance($instanceId);
            switch($instance["ModuleInfo"]["ModuleName"]) {
                case "HUELight":
                    return HUE_GetBrightness($instance["InstanceID"]);
                break;
                case "Z2DLightSwitch":
                    if($brightnessVariable = @IPS_GetObjectIDByIdent("Z2D_Brightness", $instance["InstanceID"])) {
                        // we have brightness capabilities
                        return GetValueInteger($brightnessVariable);
                    } else if($stateVariable = @IPS_GetObjectIDByIdent("Z2D_State", $instance["InstanceID"])) {
                        // we have on/off capabilities
                        return GetValueBoolean($stateVariable) ? 100 : 0;
                    }
                break;
                case "Z-Wave Module":
                    if($brightnessVariable = @IPS_GetObjectIDByIdent("IntensityVariable", $instance["InstanceID"])) {
                        return GetValueInteger($brightnessVariable);
                    } else if($stateVariable = @IPS_GetObjectIDByIdent("StatusVariable", $instance["InstanceID"])) {
                        return GetValueBoolean($stateVariable) ? 100 : 0;
                    }
                break;
            }
        }
        
        protected function switchLight($instanceId, $dimValue, $switchValue, $transitionTime) {
            $transitionTime *= 10;
            $transitionTime = min(255, $transitionTime);
            $instance = IPS_GetInstance($instanceId);
            switch($instance["ModuleInfo"]["ModuleName"]) {
                case "HUELight":
                    $params = array(
                        "BRIGHTNESS" => round($dimValue*2,54),
                        "STATE" => ($dimValue > 0),
                        "TRANSITIONTIME" => $transitionTime
                    );
                    HUE_SetValues($instance["InstanceID"], $params);
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

        protected function getEnablingVariables() {
			$variablesJson = $this->ReadPropertyString("EnablingVariables");
			$result = json_decode($variablesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
		}

		protected function getRegisteredInstances() {
			$instancesJson = $this->ReadPropertyString("Instances");
			$result = json_decode($instancesJson);
			return (json_last_error() == JSON_ERROR_NONE) ? $result : NULL;
        }
        
        protected function isModuleActive() {
            $enablingVariables = $this->getEnablingVariables();
			foreach($enablingVariables as $enablingVariable) {
				if(GetValueBoolean($enablingVariable->VariableID) && $enablingVariable->Invert) {
					return false;
				}

				if(!GetValueBoolean($enablingVariable->VariableID) && !$enablingVariable->Invert) {
					return false;
				}
            }
            return true;
        }
    }
?>