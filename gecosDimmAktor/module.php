<?php

declare(strict_types=1);
	class gecosDimmAktor extends IPSModuleStrict
	{
		private const PROFILE_MODULE_ID = '{70331590-8CF9-4AE1-AAF9-547A344C4D6C}';

		// Standardwerte, falls kein Profil gefunden wird
		private const DEFAULT_SETTINGS = [
			'DimmerDrDauer'    => 250,
			'DimmSchrittDauer' => 75,
			'DimmerMin'        => 5,
			'NachtWert'        => 30
		];

		public function Create(): void
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyInteger( 'IDDimmer', 0 );
			$this->RegisterPropertyInteger( 'IDOnOff', 0 );
			$this->RegisterPropertyInteger( 'NachtAktiv', 0 );
			$this->RegisterPropertyString( 'InputTriggers', '[]' );
			// 0 = individuelle Einstellungen (unten), >0 = Profil aus der Profilverwaltung
			$this->RegisterPropertyInteger( 'ProfileID', 0 );
			// Individuelle Einstellungen
			$this->RegisterPropertyInteger( 'DimmerDrDauer', 250 );
			$this->RegisterPropertyInteger( 'DimmSchrittDauer', 75 );
			$this->RegisterPropertyInteger( 'DimmerMin', 5 );
			$this->RegisterPropertyInteger( 'NachtWert', 30 );
			// Wird vom Konfigurator gesetzt: Instanz unter die Schaltvariable verschieben
			$this->RegisterPropertyBoolean( 'AutoPlace', false );
			$this->RegisterAttributeBoolean("DimmRichtung", true);
		}

		public function Destroy(): void
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges(): void
		{
			//Never delete this line!
			parent::ApplyChanges();

			//Alte Registrierungen entfernen
			foreach ($this->GetMessageList() as $senderID => $messages) {
				foreach ($messages as $message) {
					$this->UnregisterMessage($senderID, $message);
				}
			}
			foreach ($this->GetReferenceList() as $referenceID) {
				$this->UnregisterReference($referenceID);
			}

			$vpos = 100;
			$this->MaintainVariable( 'Intensity', $this->Translate( 'Intensity' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER, 'MAX'=>100,'MIN'=>0,'STEP_SIZE'=>1,'USAGE_TYPE'=> 2, 'SUFFIX'=> ' %' , 'ICON'=> 'lightbulb-exclamation-on'], $vpos++, true );
			$this->MaintainAction("Intensity", true);

			$idDimmer = $this->ReadPropertyInteger('IDDimmer');
			$idOnOff = $this->ReadPropertyInteger('IDOnOff');
			$idNacht = $this->ReadPropertyInteger('NachtAktiv');
			foreach ([$idDimmer, $idOnOff, $idNacht] as $id) {
				if ($id > 0 && IPS_VariableExists($id)) {
					$this->RegisterReference($id);
				}
			}

			$inputTriggerOkCount = 0;
			foreach ($this->GetInputTriggerIDs() as $triggerID) {
				$this->RegisterMessage($triggerID, VM_UPDATE);
				$this->RegisterReference($triggerID);
				$inputTriggerOkCount++;
			}

			$profileInstanceID = $this->GetProfileInstanceID();
			if ($this->ReadPropertyInteger('ProfileID') > 0 && $profileInstanceID > 0) {
				$this->RegisterReference($profileInstanceID);
			}

			// Vom Konfigurator angelegte Instanz unter die Schaltvariable verschieben
			if ($this->ReadPropertyBoolean('AutoPlace') && IPS_GetParent($this->InstanceID) == 0 && $idOnOff > 0 && IPS_VariableExists($idOnOff)) {
				IPS_SetParent($this->InstanceID, $idOnOff);
				IPS_SetHidden($this->InstanceID, true);
			}

			if (!IPS_VariableExists($idDimmer) || !IPS_VariableExists($idOnOff)) {
				$status = 201;
			} elseif ($this->ReadPropertyInteger('ProfileID') > 0 && $this->GetProfile($this->ReadPropertyInteger('ProfileID')) === null) {
				$status = 202;
			} elseif ($inputTriggerOkCount == 0) {
				//Ohne Taster kann die Instanz nicht arbeiten
				$status = IS_INACTIVE;
			} else {
				$status = IS_ACTIVE;
			}

			$this->SetStatus($status);
			$this->SetSummary($this->GetProfileName());
		}

		public function GetConfigurationForm(): string
		{
			$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
			$profileID = $this->ReadPropertyInteger('ProfileID');

			$options = [['caption' => 'Individuell (eigene Einstellungen)', 'value' => 0]];
			foreach ($this->GetProfiles() as $profile) {
				$options[] = ['caption' => sprintf('%s (ID %d)', $profile['Name'], $profile['ID']), 'value' => (int) $profile['ID']];
			}
			if ($profileID > 0 && $this->GetProfile($profileID) === null) {
				$options[] = ['caption' => sprintf('Profil %d (nicht gefunden)', $profileID), 'value' => $profileID];
			}

			$this->PatchFormElements($form['elements'], function (array &$element) use ($options, $profileID) {
				switch ($element['name'] ?? '') {
					case 'ProfileID':
						$element['options'] = $options;
						break;
					case 'IndividualSettings':
						$element['visible'] = ($profileID == 0);
						break;
					case 'ProfileInfo':
						$element['caption'] = $this->GetProfileInfo($profileID);
						$element['visible'] = ($profileID > 0);
						break;
				}
			});

			return json_encode($form);
		}

		public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
		{
			if ($Message == VM_UPDATE && in_array($SenderID, $this->GetInputTriggerIDs())) {
				$this->SendDebug("gecosDimmAktor", "MessageSink: VM_UPDATE for SenderID: $SenderID Message: $Message", 0);
				if (GetValueBoolean($SenderID)) {
					$this->Dimmen($this->ReadPropertyInteger('IDOnOff'), $this->GetIDForIdent("Intensity"), $SenderID);
				}
			}
		}

		public function Dimmen(int $IDOnOff, int $IDIntensity, int $IDinputTrigger): void
		{
			$settings = $this->GetSettings();
			if (GetValueBoolean($IDOnOff)) {
				$this->SendDebug("gecosDimmAktor", "Dimmen: Dimmer is currently ON, waiting...", 0);
				IPS_Sleep($settings['DimmerDrDauer']); //Lampe ist an, warten bis lange gedrückt
				if (!GetValueBoolean($IDinputTrigger)) {
					RequestAction($IDOnOff, false); // Dimmer ausschalten
				} else {
					//Ab hier dimmen
					$this->DimmLoop($IDIntensity, $IDinputTrigger, GetValueInteger($IDIntensity), $settings);
				}
			} else {
				$this->SendDebug("gecosDimmAktor", "Dimmen: Dimmer is currently OFF, turning it ON", 0);
				$intensity = max(GetValueInteger($IDIntensity), $settings['DimmerMin']);
				$idNacht = $this->ReadPropertyInteger('NachtAktiv');
				if ($idNacht > 0 && IPS_VariableExists($idNacht) && !GetValueBoolean($idNacht)) {
					$intensity = $settings['NachtWert'];
					$this->WriteAttributeBoolean("DimmRichtung", false); // Richtung auf hoch setzen
				}
				RequestAction($IDIntensity, $intensity); // Dimmer setzen
				RequestAction($IDOnOff, true); // Dimmer einschalten
				$this->SendDebug("gecosDimmAktor", "Dimmen: New intensity is $intensity", 0);
				IPS_Sleep($settings['DimmerDrDauer']); // Warten bis lange gedrückt, starte dimmen
				if (GetValueBoolean($IDOnOff)) {
					$this->DimmLoop($IDIntensity, $IDinputTrigger, $intensity, $settings);
				}
			}
		}

		public function RequestAction(string $Ident, mixed $Value): void
		{
			switch($Ident) {
				case "Intensity":
					$Value = min(100, max(0, (int) $Value));
					// Logarithmische Kennlinie: 0-100 % -> 0-4095 PWM
					$a = 100;
					$b = 4096;
					$o = 350; //Offset
					$T = (($a+$o) * log10(2)) / (log10($b));
					$y = (int) round(pow(2, (($Value+$o) / $T)) - 1);
					RequestAction($this->ReadPropertyInteger('IDDimmer'), $y);
					$this->SendDebug("gecosDimmAktor", "RequestAction: Intensity $Value % -> PWM $y", 0);
					//Neuen Wert in die Statusvariable schreiben
					$this->SetValue($Ident, $Value);
					break;
				case "UpdateProfileSelection":
					$profileID = (int) $Value;
					$this->UpdateFormField('IndividualSettings', 'visible', $profileID == 0);
					$this->UpdateFormField('ProfileInfo', 'visible', $profileID > 0);
					$this->UpdateFormField('ProfileInfo', 'caption', $this->GetProfileInfo($profileID));
					break;
				default:
					throw new Exception("Invalid Ident");
			}
		}

		/**
		 * Liefert die aktiven Dimm-Einstellungen (aus Profil oder individuell).
		 */
		public function GetSettings(): array
		{
			$profileID = $this->ReadPropertyInteger('ProfileID');
			if ($profileID == 0) {
				$settings = [];
				foreach (array_keys(self::DEFAULT_SETTINGS) as $key) {
					$settings[$key] = $this->ReadPropertyInteger($key);
				}
				return $settings;
			}
			$profile = $this->GetProfile($profileID);
			if ($profile === null) {
				$this->SendDebug("gecosDimmAktor", "Profil $profileID nicht gefunden, nutze Standardwerte", 0);
				return self::DEFAULT_SETTINGS;
			}
			$settings = [];
			foreach (self::DEFAULT_SETTINGS as $key => $default) {
				$settings[$key] = (int) ($profile[$key] ?? $default);
			}
			return $settings;
		}

		private function DimmLoop(int $IDIntensity, int $IDinputTrigger, int $intensity, array $settings): void
		{
			$gedimmt = false;
			$hoch = !$this->ReadAttributeBoolean("DimmRichtung");
			while (GetValueBoolean($IDinputTrigger)) {
				$intensity = min(100, max($settings['DimmerMin'], $intensity + ($hoch ? 1 : -1)));
				$this->SendDebug("gecosDimmAktor", "Dimmen: Dimming " . ($hoch ? 'up' : 'down') . ", intensity: $intensity", 0);
				RequestAction($IDIntensity, $intensity);
				$gedimmt = true;
				if (($hoch && $intensity >= 100) || (!$hoch && $intensity <= $settings['DimmerMin'])) {
					break;
				}
				IPS_Sleep($settings['DimmSchrittDauer']); // Warten bis zum nächsten Schritt
			}
			if ($gedimmt) {
				$this->WriteAttributeBoolean("DimmRichtung", !$this->ReadAttributeBoolean("DimmRichtung"));
			}
		}

		private function GetInputTriggerIDs(): array
		{
			$ids = [];
			foreach (json_decode($this->ReadPropertyString('InputTriggers'), true) ?: [] as $inputTrigger) {
				$triggerID = (int) ($inputTrigger['VariableID'] ?? 0);
				if ($triggerID > 0 && IPS_VariableExists($triggerID)) {
					$ids[] = $triggerID;
				}
			}
			return $ids;
		}

		private function GetProfileInstanceID(): int
		{
			$ids = IPS_GetInstanceListByModuleID(self::PROFILE_MODULE_ID);
			return count($ids) > 0 ? $ids[0] : 0;
		}

		private function GetProfiles(): array
		{
			$instanceID = $this->GetProfileInstanceID();
			if ($instanceID == 0) {
				return [];
			}
			return json_decode(IPS_GetProperty($instanceID, 'Profiles'), true) ?: [];
		}

		private function GetProfile(int $profileID): ?array
		{
			foreach ($this->GetProfiles() as $profile) {
				if ((int) $profile['ID'] == $profileID) {
					return $profile;
				}
			}
			return null;
		}

		private function GetProfileName(): string
		{
			$profileID = $this->ReadPropertyInteger('ProfileID');
			if ($profileID == 0) {
				return 'Individuell';
			}
			$profile = $this->GetProfile($profileID);
			return $profile === null ? "Profil $profileID fehlt" : $profile['Name'];
		}

		private function GetProfileInfo(int $profileID): string
		{
			if ($profileID == 0) {
				return '';
			}
			if ($this->GetProfileInstanceID() == 0) {
				return 'Keine Profilverwaltung (gecosDimmProfile) gefunden - es werden Standardwerte genutzt.';
			}
			$profile = $this->GetProfile($profileID);
			if ($profile === null) {
				return "Profil $profileID wurde nicht gefunden - es werden Standardwerte genutzt.";
			}
			return sprintf('Drück-Dauer: %d ms | Schritt-Dauer: %d ms | Minimum: %d %% | Nachtwert: %d %%  (Pflege in der Profilverwaltung)',
				$profile['DimmerDrDauer'], $profile['DimmSchrittDauer'], $profile['DimmerMin'], $profile['NachtWert']);
		}

		private function PatchFormElements(array &$elements, callable $patch): void
		{
			foreach ($elements as &$element) {
				$patch($element);
				if (isset($element['items']) && is_array($element['items'])) {
					$this->PatchFormElements($element['items'], $patch);
				}
			}
		}
	}
