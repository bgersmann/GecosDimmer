# gecosDimmAktor

Steuert einen Dimmausgang eines GeCoS_PWM16Out über einen oder mehrere Taster.

### Funktionsumfang

* Kurzer Tastendruck: Ein/Aus.
* Langer Tastendruck: Hoch-/Runterdimmen, die Richtung wechselt nach jedem Dimmvorgang.
* Logarithmische Kennlinie: Helligkeit 0–100 % wird auf den PWM-Wert 0–4095 umgerechnet.
* Nachtwert: Ist die Nacht-Variable `false`, wird beim Einschalten der Nachtwert genutzt.
* Die Dimm-Parameter kommen aus einem Profil der Profilverwaltung (gecosDimmProfile) oder aus individuellen Einstellungen.

### Konfiguration

Name                       | Beschreibung
-------------------------- | ------------------
Variable Dimmaktor         | `Output_Int_X..` des GeCoS_PWM16Out
Variable On/Off            | `Output_Bln_X..` des GeCoS_PWM16Out
Variable Nacht             | Optional, `false` = Nacht
Taster                     | Boolean-Variablen der Taster
Dimm-Profil                | Profil aus gecosDimmProfile oder „Individuell“
Individuelle Einstellungen | Nur bei „Individuell“: Drück-Dauer, Minimum, Schritt-Dauer, Nachtwert

Instanzen aus Version 1.x behalten ihre Einstellungen als „Individuell“.

### Statusvariablen

Name       | Typ     | Beschreibung
---------- | ------- | ------------
Helligkeit | Integer | Helligkeit 0–100 %

### PHP-Befehlsreferenz

`array GDA_GetSettings(int $InstanzID);`  
Liefert die aktuell wirksamen Dimm-Einstellungen.
