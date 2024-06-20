<?php

declare(strict_types=1);
	class BLEYC01 extends IPSModule
	{
		const MqttParent = "{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}";
		const ModulToMqtt = "{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}";
		const MqttToModul = "{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}";
		const CommandTopic = "cmnd";
		const RequestCommand = "BLEOp";
		const ResponseTopic = "tele";
		const ResultPostfix = "RESULT";
		const BleResultPostfix = "BLE";

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString("TasmotaDeviceName", "");
			$this->RegisterPropertyString("MAC", "");
			$this->RegisterPropertyInteger("RequestInterval", 30);
			$this->RegisterTimer('RequestTimer', 0, 'BLEYC01_RequestData($_IPS[\'TARGET\']);');

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
			$this->SetTimerInterval('RequestTimer', $this->ReadPropertyInteger('RequestInterval') * 1000 * 60);

			//$filterResult = preg_quote('"Topic":"' . self::ResponseTopic . '/' . $this->ReadPropertyString('TasmotaDeviceName') . '/' . self::ResultPostfix);
			//$filterBle = preg_quote('"Topic":"' . self::ResponseTopic . '/' . $this->ReadPropertyString('TasmotaDeviceName') . '/' . self::BleResultPostfix);
			
			$filter = '.*(' . $this->ReadPropertyString('TasmotaDeviceName') . '|' . $this->ReadPropertyString('MAC') ').*';
			$this->SendDebug('ReceiveDataFilter', $filter, 0);
        	$this->SetReceiveDataFilter($filter);

			if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
				$this->RequestData($_IPS['TARGET']);
			}		
			
			$this->SetStatus(102);
		}

		public function ReceiveData($JSONString)
		{
			$this->SendDebug('ReceiveData', $JSONString, 0);

			if(empty($this->ReadPropertyString('TasmotaDeviceName')) || empty($this->ReadPropertyString('MAC')))
			{
				$this->SendDebug("BLEYC01", "TasmotaDeviceName oder MAC Adresse nicht gesetzt", 0);
				return;
			}

			// Empfangene Daten vom Gateway/Splitter
			$data = json_decode($JSONString);

			if (IPS_GetKernelDate() > 1670886000) 
			{
				$data['Payload'] = utf8_decode($data['Payload']);
			}
			
			$this->SendDebug('Topic', $data['Topic'], 0);
            $this->SendDebug('Payload', $data['Payload'], 0);

			$payload = json_decode($data['Payload'], true);
			$this->SendDebug('Payload decoded', $payload, 0);

			return "OK von " . $this->InstanceID;
		}

		public function RequestData()
		{
			if(empty($this->ReadPropertyString('TasmotaDeviceName')) || empty($this->ReadPropertyString('MAC')))
			{
				$this->SendDebug("BLEYC01", "TasmotaDeviceName oder MAC Adresse nicht gesetzt", 0);
				return;
			}

			$mac = $this->ReadPropertyString('MAC');
			if(strlen($mac) == 17)
			{
				$mac = str_replace(':', '', $mac);
			}

			$this->SendDebug('MAC for Payload should be only Hex Values with the length of 12', $mac, 0);

			$topic = self::CommandTopic . '/' . $this->ReadPropertyString('TasmotaDeviceName') . '/' . self::RequestCommand;
			$payload = "m:" . $this->ReadPropertyString('MAC') . " s:FF01 c:FF02 r go";

			$this->SendDebug('Topic', $topic, 0);
			$this->SendDebug('Payload', $payload, 0);

			$data['DataID'] = self::ModulToMqtt;
			$data['PacketType'] = 3;
			$data['QualityOfService'] = 0;
			$data['Retain'] = false;
			$data['Topic'] = $topic;
			$data['Payload'] = $payload;
			$dataJSON = json_encode($data, JSON_UNESCAPED_SLASHES);
			$this->SendDataToParent($dataJSON);
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