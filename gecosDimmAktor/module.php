<?php

declare(strict_types=1);
	class gecosDimmAktor extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyInteger( 'IDDimmer', 0 );
			$this->RegisterPropertyInteger( 'IDOnOff', 0 );
			$this->RegisterPropertyInteger( 'NachtAktiv', 0 );
			$this->RegisterPropertyString( 'InputTriggers', '[]' );
			$this->RegisterPropertyInteger( 'DimmerDrDauer', 250 );
			$this->RegisterPropertyInteger( 'DimmSchrittDauer', 75 );
			$this->RegisterPropertyInteger( 'DimmerMin', 5 );
			$this->RegisterPropertyInteger( 'NachtWert', 30 );
			$this->RegisterAttributeBoolean("DimmRichtung", true);
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

			$vpos = 100;
			$this->MaintainVariable( 'Intensity', $this->Translate( 'Intensity' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER, 'MAX'=>100,'MIN'=>0,'STEP_SIZE'=>1,'USAGE_TYPE'=> 2, 'SUFFIX'=> ' %' , 'ICON'=> 'lightbulb-exclamation-on'], $vpos++, 1 );
			$this->MaintainAction("Intensity", true);
			$inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
			$inputTriggerOkCount = 0;
			foreach ($inputTriggers as $inputTrigger) {
				$triggerID = $inputTrigger['VariableID'];
				$this->RegisterMessage($triggerID, VM_UPDATE);
				$this->RegisterReference($triggerID);
				$inputTriggerOkCount++;
			}
			
			//If we are missing triggers or outputs the instance will not work
			if ($inputTriggerOkCount == 0) {
				$status = IS_INACTIVE;
			} else {
				$status = IS_ACTIVE;
			}

			$this->SetStatus($status);
		}
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
			if ($Message == VM_UPDATE) {
				$this->SendDebug("gecosDimmAktor", "MessageSink: VM_UPDATE for SenderID: $SenderID Message: $Message", 0);
				$status=GetValueBoolean($SenderID);
				if ($status) {
					$this->Dimmen($this->ReadPropertyInteger('IDOnOff'), $this->GetIDForIdent("Intensity"), $SenderID);
				}
			}
		}
		public function Dimmen(int $IDOnOff, int $IDIntensity, int $IDinputTrigger):void {
			if  (GetValueBoolean($IDOnOff)) {
				IPS_Sleep($this->ReadPropertyInteger('DimmerDrDauer')); //Lampe ist an, warten bis lange gedrückt
				$this->SendDebug("gecosDimmAktor", "Dimmen: Dimmer is currently ON, waiting...", 0);
				if  (!GetValueBoolean($IDinputTrigger)) {
					RequestAction($IDOnOff, False); // Dimmer ausschalten
				} else {					
					//Ab hier dimmen:
					// Start the dimming process				
					$gedimmt=false;
					$intensity = GetValueInteger($IDIntensity);
					while (GetValueBoolean($IDinputTrigger)) {
						if (!$this->ReadAttributeBoolean("DimmRichtung")) {
							$this->SendDebug("gecosDimmAktor", "Dimmen: Dimming in progress, current intensity: $intensity", 0);
							// Hochdimmen
							$intensity = $intensity + 1;
							RequestAction($IDIntensity, (int)$intensity);	
							if ($intensity>100) {
								$intensity = 100;
								RequestAction($IDIntensity, (int)$intensity);
								break;
							}
						} else {
							$this->SendDebug("gecosDimmAktor", "Dimmen: Dimming in progress, current intensity: $intensity", 0);
							// Runterdimmen
							$intensity = $intensity - 1;
							RequestAction($IDIntensity, (int)$intensity);
							if ($intensity<$this->ReadPropertyInteger('DimmerMin')) {
								$intensity = $this->ReadPropertyInteger('DimmerMin');
								RequestAction($IDIntensity, (int)$intensity);							
								break;
							}
						}			
						$gedimmt=true;							
						IPS_Sleep($this->ReadPropertyInteger('DimmSchrittDauer')); // Warten bis zum nächsten Schritt
					}
					if ($gedimmt) {
						$this->WriteAttributeBoolean("DimmRichtung", !$this->ReadAttributeBoolean("DimmRichtung"));				
					}
				}
			} else {
				$this->SendDebug("gecosDimmAktor", "Dimmen: Dimmer is currently OFF, turning it ON and increasing intensity", 0);				
				$intensity = GetValueInteger($IDIntensity);
				if ($intensity < $this->ReadPropertyInteger('DimmerMin')) {
					$intensity = $this->ReadPropertyInteger('DimmerMin');
				}
				if ($this->ReadPropertyInteger('NachtAktiv') > 0 && GetValueBoolean($this->ReadPropertyInteger('NachtAktiv'))) {
					$intensity = $this->ReadPropertyInteger('NachtWert');
					$this->WriteAttributeBoolean("DimmRichtung",false); // Richtung auf hoch setzen
				}
				RequestAction($IDIntensity, $intensity); // Dimmer setzen
				RequestAction($IDOnOff, true); // Dimmer einschalten
				$this->SendDebug("gecosDimmAktor", "Dimmen: New intensity is $intensity", 0);
				IPS_Sleep($this->ReadPropertyInteger('DimmerDrDauer')); // Warten bis Lange gedrückt, starte dimmen:
				if  (GetValueBoolean($IDOnOff)) {
					// Start the dimming process				
					$gedimmt=false;
					while (GetValueBoolean($IDinputTrigger)) {
						if (!$this->ReadAttributeBoolean("DimmRichtung")) {
							// Hochdimmen
							$intensity = $intensity + 1;
							RequestAction($IDIntensity, (int)$intensity);	
							if ($intensity>100) {
								$intensity = 100;
								RequestAction($IDIntensity, (int)$intensity);
								break;
							}
						} else {
							// Runterdimmen
							$intensity = $intensity - 1;
							RequestAction($IDIntensity, (int)$intensity);
							if ($intensity<$this->ReadPropertyInteger('DimmerMin')) {							
								break;
							}
						}			
						$gedimmt=true;							
						IPS_Sleep($this->ReadPropertyInteger('DimmSchrittDauer')); // Warten bis zum nächsten Schritt
					}
					if ($gedimmt) {
						$this->WriteAttributeBoolean("DimmRichtung", !$this->ReadAttributeBoolean("DimmRichtung"));				
					}
				}				
			}			
		}
		public function RequestAction($Ident, $Value) {
$this->SendDebug("gecosDimmAktor", "Dimmen: New intensity is $Ident", 0);
			switch($Ident) {
				case "Intensity":
					//Hier würde normalerweise eine Aktion z.B. das Schalten ausgeführt werden
					//Ausgaben über 'echo' werden an die Visualisierung zurückgeleitet
					$a = 100;
					$b = 4096;
					$x =$Value;
					$o = 350; //Offset
					$T = (($a+$o) * log10(2)) / (log10($b));
					$y = pow (2, (($x+$o) / $T)) - 1;
					RequestAction($this->ReadPropertyInteger('IDDimmer'), $y);
					$this->SendDebug("gecosDimmAktor", "Dimmen: New intensity is $y", 0);
					//Neuen Wert in die Statusvariable schreiben
					SetValue($this->GetIDForIdent($Ident), $Value);					
					break;
				default:
					throw new Exception("Invalid Ident");
			}
			
		}

	}