<?php

declare(strict_types=1);
	class gecosDimmProfile extends IPSModuleStrict
	{
		private const ACTOR_MODULE_ID = '{AE282F87-51CE-1A17-A984-8B532E718003}';

		public function Create(): void
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyString('Profiles', json_encode([
				['ID' => 1, 'Name' => 'Standard', 'DimmerDrDauer' => 250, 'DimmSchrittDauer' => 75, 'DimmerMin' => 5, 'NachtWert' => 30]
			]));
		}

		public function ApplyChanges(): void
		{
			//Never delete this line!
			parent::ApplyChanges();

			// Neue Profile (ID 0) und doppelte IDs bekommen eine eindeutige ID
			$profiles = json_decode($this->ReadPropertyString('Profiles'), true) ?: [];
			$maxID = 0;
			foreach ($profiles as $profile) {
				$maxID = max($maxID, (int) ($profile['ID'] ?? 0));
			}
			$used = [];
			$changed = false;
			foreach ($profiles as &$profile) {
				$id = (int) ($profile['ID'] ?? 0);
				if ($id <= 0 || isset($used[$id])) {
					$id = ++$maxID;
					$profile['ID'] = $id;
					$changed = true;
				}
				$used[$id] = true;
			}
			unset($profile);

			if ($changed) {
				IPS_SetProperty($this->InstanceID, 'Profiles', json_encode($profiles));
				IPS_ApplyChanges($this->InstanceID);
				return;
			}

			if (count(IPS_GetInstanceListByModuleID($this->GetModuleID())) > 1) {
				$this->SetStatus(201);
			} else {
				$this->SetStatus(IS_ACTIVE);
			}
			$this->SetSummary(sprintf('%d Profile', count($profiles)));

			// DimmAktoren mit Profil aktualisieren (Status/Zusammenfassung)
			if (IPS_GetKernelRunlevel() == KR_READY) {
				foreach (IPS_GetInstanceListByModuleID(self::ACTOR_MODULE_ID) as $actorID) {
					if (IPS_GetProperty($actorID, 'ProfileID') > 0) {
						IPS_ApplyChanges($actorID);
					}
				}
			}
		}

		public function GetConfigurationForm(): string
		{
			$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

			$names = [0 => 'Individuell'];
			foreach (json_decode($this->ReadPropertyString('Profiles'), true) ?: [] as $profile) {
				$names[(int) $profile['ID']] = $profile['Name'];
			}
			$usage = [];
			foreach (IPS_GetInstanceListByModuleID(self::ACTOR_MODULE_ID) as $actorID) {
				$profileID = (int) IPS_GetProperty($actorID, 'ProfileID');
				$usage[] = [
					'InstanceID' => $actorID,
					'Location'   => IPS_GetLocation($actorID),
					'Profile'    => $names[$profileID] ?? "Profil $profileID (fehlt)"
				];
			}
			foreach ($form['actions'] as &$action) {
				if (($action['name'] ?? '') == 'Usage') {
					$action['values'] = $usage;
				}
			}
			return json_encode($form);
		}

		/**
		 * Liefert alle Profile als Array.
		 */
		public function GetProfiles(): array
		{
			return json_decode($this->ReadPropertyString('Profiles'), true) ?: [];
		}

		/**
		 * Ordnet einem DimmAktor ein Profil zu.
		 */
		public function AssignProfile(int $ActorID, int $ProfileID): bool
		{
			if (!IPS_InstanceExists($ActorID) || IPS_GetInstance($ActorID)['ModuleInfo']['ModuleID'] != self::ACTOR_MODULE_ID) {
				return false;
			}
			IPS_SetProperty($ActorID, 'ProfileID', $ProfileID);
			IPS_ApplyChanges($ActorID);
			return true;
		}

		private function GetModuleID(): string
		{
			return IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID'];
		}
	}
