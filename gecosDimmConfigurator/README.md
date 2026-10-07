# gecosDimmConfigurator

Listet alle GeCoS_PWM16Out-Instanzen mit ihren 16 Dimmausgängen. Für jeden Ausgang kann ein gecosDimmAktor erstellt werden,
Dimmer- und On/Off-Variable werden dabei automatisch mit `Output_Int_X..` und `Output_Bln_X..` belegt.

Einstellungen:

* __Dimm-Profil__: Profil, das neue Aktoren erhalten.
* __Unter On/Off-Variable ablegen__: Neue Aktoren werden versteckt unter der Schaltvariable des Ausgangs abgelegt.

Bereits vorhandene Aktoren werden über die Dimmer-Variable erkannt. Aktoren ohne passenden Ausgang erscheinen in einem eigenen Zweig.
Die Taster müssen anschließend im jeweiligen Aktor eingetragen werden.
