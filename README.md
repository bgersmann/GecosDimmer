# GecosDimmer

Dimmer-Logik (Taster: kurz = Ein/Aus, lang = Dimmen) für GeCoS 16-PWM Module.
Voraussetzung: IP-Symcon ab Version 8.2 und die [GeCoS-Modules](https://github.com/bgersmann/GeCoS-Modules).

Folgende Module beinhaltet das GecosDimmer Repository:

- __gecosDimmAktor__ ([Dokumentation](gecosDimmAktor))  
	Ein Dimmausgang eines GeCoS_PWM16Out mit Taster-Steuerung, logarithmischer Helligkeitskennlinie und Nachtwert.
- __gecosDimmProfile__ ([Dokumentation](gecosDimmProfile))  
	Globale Verwaltung der Dimm-Profile (Drück-Dauer, Schritt-Dauer, Minimum, Nachtwert).
- __gecosDimmConfigurator__ ([Dokumentation](gecosDimmConfigurator))  
	Findet alle GeCoS_PWM16Out-Instanzen und legt pro Dimmausgang einen gecosDimmAktor an.

### Einrichtung

1. Eine Instanz __gecosDimmProfile__ anlegen und Profile pflegen (es reicht eine Instanz im System).
2. Eine Instanz __gecosDimmConfigurator__ anlegen, Standard-Profil wählen und übernehmen.
3. Im Konfigurator die gewünschten Ausgänge erstellen.
4. In jedem DimmAktor die Taster (Input Triggers) eintragen.
