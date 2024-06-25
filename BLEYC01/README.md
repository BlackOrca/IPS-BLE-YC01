# BLEYC01
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)

### 1. Funktionsumfang

* Mithilfe eines ESP32, geflasht mit Tasmota (getestet Version 14.1), als Gateway zum auslesen des BLE-YC01 Poolsensors.

### 2. Voraussetzungen

* IP-Symcon ab Version 7.0

### 3. Software-Installation

* Über den Module Store das 'BLEYC01'-Modul installieren.
* Alternativ über das Module Control folgende URL <https://github.com/BlackOrca/IPS-BLE-YC01.git> hinzufügen
* Auf dem ESP32 Tasmota installieren. Entweder über den Webinstaller von  oder das Release mit einem gängigen Tool aufspielen. Wichtig, dieses Modul wurde mit dem Tasmota32.Bluetooth 14.1 Image getestet und funktioniert hiermit. Release kann hier runtergeladen werden <https://github.com/arendst/Tasmota/releases/download/v14.1.0/tasmota32-bluetooth.bin> oder über den Webinstaller <https://tasmota.github.io/install/>
* Nachdem installieren Tasmota mit dem WiFi verbinden.
* Im Tasmota unter Configuration -> MQTT Host, Port, User und Passwort eintragen. Topic entweder belassen (tasmota_XXXXX) oder z.B. tasmota-pool eintragen.
* Wichtig: Das Modul erwartet die standard Topics. Somit Full Topic im Tasmotag nicht ändern!
* Im Tasmota unter Configuration -> BLE -> Enable Bluetooth aktivieren und speichern. Tasmota startet dann neu.
* Wenn der Pooltester in der nähe ist müsste man entweder unter Configuration -> BLE "Devices Seen" den Pooltester zu sehen sein. Erkennbar am Name BLE-YC01. Hier die MAC Adresse kopieren. Alternativ in der Tasmota Console die BLE Communication verfolgen. Da kommt dann auch immer eine Nachricht wenn der Poolsensor auf sich aufmerksam macht.
* Dann im Modul den vergebenen Topic und die MAC Adresse einfügen und speichern. Nach einem Kurzen Moment sollte etwas im Modul ankommen.
* Wenn nicht, bitte sichergehen das das Tasmota Gerät erfolgreich mit eurem MQTT Broker verbunden und der Topic vernünftig vergeben ist und der Full Topic im Tasmota %prefix%/%topic%/ ist!

* Es werden mehrere BLEYC10 über einen Tasmota unterstüzt. Die Daten werden durch die MAC Adresse richtig zugeordnet.
* mit dem Befehl ``` BLEOp M:c000000XXXXXX s:FF01 c:FF02 r go ``` (bitte die MAC des BLE benutzen) in der Console des Tasmota muss man folgendes zurückbekommen:

```
14:25:50.743 MQT: stat/tasmota-topic/RESULT = {"BLEOp":{"opid":249,"u":0}}
14:25:55.942 MQT: tele/tasmota-topic/BLE = {"BLEOperation":{"opid":"249","stat":"3","state":"DONEREAD","MAC":"C000000XXXXX","svc":"0xff01","char":"0xff02","read":"FFA1FC5AFC54FF3CFD885555AA2AF78EDBFD2FFC89FFFFFFFFFFFF7515"}} 
//Der wert hinter read ist der Payload mit den Gelesenen Daten die das Modul intepretieren wird.
```

* Die Paralele installation des Tasmota-Moduls ist möglich und es löst keine Probleme aus die bakannt wären.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'BLEYC01'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name           | Beschreibung
--------       | ------------------
MAC            | MAC Adresse des BLE-YC10 ohne Doppelpunkte oder andere Sonderzeichen.
MQTT-Name      | Name des Tasmota Gerätes welches in den MQTT Topics genutzt wird
Abfrageinterval| Intervall in Minuten in der der Poolsensor ausgelesen werden soll. Nicht zu oft, jedes Abrufen kostet Batterie.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Aktiv  | bool | Ob das Modul aktiv ist oder nicht
Status | bool | Ob der Letzte Abruf funktioniert hat
Temperatur | float | Gelesene Temperatur
PH | float | Gelesener PH Wert
EC | int | Gelesener EC Wert
TDS | int | Gelesener TDS Wert
ORP | float | Gelesener ORP Wert
Chlor | float | Gelesener Chlor Wert
Batterie | int | Gelesene Batteriespannung umgewandelt in einen % Wert

#### Profile

Name   | Typ
------ | -------
BLEYC10.EC | int
BLEYC10.TDS | int
BLEYC10.Chlorine | float
BLEYC10.ORP |float

### 6. WebFront

Anzeige der Werte.
