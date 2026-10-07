# gecosDimmProfile

Globale Profilverwaltung für alle gecosDimmAktor-Instanzen. Es wird nur eine Instanz benötigt (bei mehreren nutzen die Aktoren die erste).

Jedes Profil hat eine eindeutige ID (wird automatisch vergeben), einen Namen sowie Drück-Dauer, Schritt-Dauer, Minimum und Nachtwert.
Änderungen wirken sofort auf alle Aktoren mit diesem Profil. Unter „Aktionen“ ist zu sehen, welcher Aktor welches Profil nutzt.

### PHP-Befehlsreferenz

`array GDP_GetProfiles(int $InstanzID);`  
Liefert alle Profile.

`bool GDP_AssignProfile(int $InstanzID, int $ActorID, int $ProfileID);`  
Ordnet einem DimmAktor ein Profil zu (0 = individuell).
