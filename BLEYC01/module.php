<?php

declare(strict_types=1);
	class BLEYC01 extends IPSModule
	{
		const MqttParent = "{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}";
		const ModulToMqtt = "{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}";
		const MqttToModul = "{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}";

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString("TopicTasmotaDevice", "");
			$this->RegisterPropertyString("MAC", "");

			$this->ConnectParent(self::MqttParent);
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

			$this->ConnectParent(self::MqttParent);

			$filter = preg_quote('"Topic":"' . $this->ReadPropertyString('TopicTasmotaDevice') . '/' . $this->ReadPropertyString('TopicTasmotaDevice') . '"');
			$this->SetReceiveDataFilter(".*Hallo.*");
		}

		public function ReceiveData($JSONString) {

			// Empfangene Daten vom Gateway/Splitter
			$data = json_decode($JSONString);
			IPS_LogMessage("ReceiveData", utf8_decode($data->Buffer));
			
			// Sende Resultat zurück an das/den Gateway/Splitter
			return "OK von " . $this->InstanceID;
		}
	}

// TX (vom Modul zum Server)
// {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}
// {
//     "PacketType": 3, // Publish
//     "QualityOfService": 0,
//     "Retain": false,
//     "Topic": "/blub/blubber/switch",
//     "Payload": "an"
// }

// RX (vom Server zum Modul)
// {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
// {
//     "PacketType": 3, // Publish
//     "QualityOfService": 0,
//     "Retain": false,
//     "Topic": "/blub/blubber/switch",
//     "Payload": "an"
// }