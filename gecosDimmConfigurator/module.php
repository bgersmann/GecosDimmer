<?php

declare(strict_types=1);
	class gecosDimmConfigurator extends IPSModuleStrict
	{
		private const PWM_MODULE_ID = '{E6CD7AEF-064A-42EF-A5CD-B81453DA762C}';
		private const ACTOR_MODULE_ID = '{AE282F87-51CE-1A17-A984-8B532E718003}';
		private const PROFILE_MODULE_ID = '{70331590-8CF9-4AE1-AAF9-547A344C4D6C}';
		private const CHANNELS = 16;

		public function Create(): void
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyInteger('DefaultProfileID', 1);
			$this->RegisterPropertyBoolean('AutoPlace', true);
		}

		public function ApplyChanges(): void
		{
			//Never delete this line!
			parent::ApplyChanges();
			$this->SetStatus(IS_ACTIVE);
		}

		public function GetConfigurationForm(): string
		{
			$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
			$profiles = $this->GetProfileNames();

			$options = [['caption' => 'Individuell (eigene Einstellungen)', 'value' => 0]];
			foreach ($profiles as $id => $name) {
				if ($id > 0) {
					$options[] = ['caption' => sprintf('%s (ID %d)', $name, $id), 'value' => $id];
				}
			}

			foreach ($form['elements'] as &$element) {
				if (($element['name'] ?? '') == 'DefaultProfileID') {
					$element['options'] = $options;
				}
			}
			unset($element);
			foreach ($form['actions'] as &$action) {
				if (($action['name'] ?? '') == 'Dimmers') {
					$action['values'] = $this->GetConfiguratorValues($profiles);
				}
			}
			unset($action);

			return json_encode($form);
		}

		private function GetConfiguratorValues(array $profiles): array
		{
			$defaultProfileID = $this->ReadPropertyInteger('DefaultProfileID');
			$autoPlace = $this->ReadPropertyBoolean('AutoPlace');

			// Vorhandene DimmAktoren nach Dimmer-Variable indizieren
			$actors = [];
			foreach (IPS_GetInstanceListByModuleID(self::ACTOR_MODULE_ID) as $actorID) {
				$actors[$actorID] = (int) IPS_GetProperty($actorID, 'IDDimmer');
			}

			$values = [];
			foreach (IPS_GetInstanceListByModuleID(self::PWM_MODULE_ID) as $pwmID) {
				$values[] = [
					'id'       => $pwmID,
					'expanded' => true,
					'name'     => IPS_GetName($pwmID),
					'channel'  => '',
					'address'  => IPS_GetLocation($pwmID),
					'profile'  => ''
				];
				for ($channel = 0; $channel < self::CHANNELS; $channel++) {
					$idDimmer = @IPS_GetObjectIDByIdent('Output_Int_X' . $channel, $pwmID);
					$idOnOff = @IPS_GetObjectIDByIdent('Output_Bln_X' . $channel, $pwmID);
					if ($idDimmer === false || $idOnOff === false) {
						continue;
					}
					$name = IPS_GetName($idOnOff);
					$row = [
						'id'         => $pwmID * 100 + $channel + 1,
						'parent'     => $pwmID,
						'name'       => $name,
						'channel'    => 'X' . $channel,
						'address'    => sprintf('Dimmer %d / On-Off %d', $idDimmer, $idOnOff),
						'profile'    => '',
						'instanceID' => 0
					];
					$actorID = array_search($idDimmer, $actors, true);
					if ($actorID !== false) {
						$row['instanceID'] = $actorID;
						$profileID = (int) IPS_GetProperty($actorID, 'ProfileID');
						$row['profile'] = $profiles[$profileID] ?? "Profil $profileID (fehlt)";
						unset($actors[$actorID]);
					}
					$row['create'] = [
						'moduleID'      => self::ACTOR_MODULE_ID,
						'name'          => $autoPlace ? 'Dimmer' : 'Dimmer ' . $name,
						'configuration' => [
							'IDDimmer'  => $idDimmer,
							'IDOnOff'   => $idOnOff,
							'ProfileID' => $defaultProfileID,
							'AutoPlace' => $autoPlace
						]
					];
					$values[] = $row;
				}
			}

			// DimmAktoren ohne passenden PWM-Ausgang
			if (count($actors) > 0) {
				$values[] = [
					'id'       => 1,
					'expanded' => true,
					'name'     => 'DimmAktoren ohne GeCoS PWM-Ausgang',
					'channel'  => '',
					'address'  => '',
					'profile'  => ''
				];
				foreach ($actors as $actorID => $idDimmer) {
					$profileID = (int) IPS_GetProperty($actorID, 'ProfileID');
					$values[] = [
						'id'         => 2 + count($values),
						'parent'     => 1,
						'name'       => IPS_GetLocation($actorID),
						'channel'    => '',
						'address'    => sprintf('Dimmer %d', $idDimmer),
						'profile'    => $profiles[$profileID] ?? "Profil $profileID (fehlt)",
						'instanceID' => $actorID
					];
				}
			}
			return $values;
		}

		private function GetProfileNames(): array
		{
			$names = [0 => 'Individuell'];
			$ids = IPS_GetInstanceListByModuleID(self::PROFILE_MODULE_ID);
			if (count($ids) > 0) {
				foreach (json_decode(IPS_GetProperty($ids[0], 'Profiles'), true) ?: [] as $profile) {
					$id = (int) ($profile['ID'] ?? 0);
					if ($id > 0) {
						$names[$id] = (string) ($profile['Name'] ?? "Profil $id");
					}
				}
			}
			return $names;
		}
	}
