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

		const Battery = "Battery";
		const EC = "EC";
		const TDS = "TDS";
		const PH = "PH";
		const ORP = "ORP";
		const Temperature = "Temperature";

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString("TasmotaDeviceName", "");
			$this->RegisterPropertyString("MAC", "");
			$this->RegisterPropertyInteger("RequestInterval", 30);
			
			$this->RegisterTimer('RequestTimer', 0, 'BLEYC_RequestData($_IPS[\'TARGET\']);');
			
			$this->RegisterVariableInteger(self::Battery, $this->Translate(self::Battery), "~Battery.100", 100);
			$this->RegisterVariableInteger(self::EC, "EC", "", 40);
			$this->RegisterVariableInteger(self::TDS, "TDS", "", 50);
			$this->RegisterVariableFloat(self::PH, "PH", "~Liquid.pH.F", 20);
			$this->RegisterVariableFloat(self::ORP, "ORP", "~Volt", 60);
			$this->RegisterVariableFloat(self::Temperature, $this->Translate(self::Temperature), "~Temperature", 10);

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
			
			$filterResult = preg_quote('"Topic":"' . self::ResponseTopic . '/' . $this->ReadPropertyString('TasmotaDeviceName') . '/' . self::BleResultPostfix);	
			$this->SendDebug('ReceiveDataFilter', '.*' . $filterResult . '.*', 0);
			$this->SetReceiveDataFilter('.*' . $filterResult . '.*');

			if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
				$this->RequestData($_IPS['TARGET']);
			}		
			
			$interval = $this->ReadPropertyInteger('RequestInterval') * 1000 * 60;
			$this->SetTimerInterval('RequestTimer', $interval);
			$this->SendDebug('RequestTimer', 'Interval: ' . $interval . ' ms', 0);
			$this->SetStatus(102);
		}

		public function ReceiveData($JSONString)
		{		
			if(empty($this->ReadPropertyString('TasmotaDeviceName')) || empty($this->ReadPropertyString('MAC')))
			{
				$this->SendDebug("BLEYC01", "TasmotaDeviceName oder MAC Adresse nicht gesetzt", 0);
				return;
			}

			$this->SendDebug('ReceiveData', $JSONString, 0);

			$data = json_decode($JSONString, true);
			if(!array_key_exists('Payload', $data))
			{
				$this->SendDebug('Payload', 'No Payload found', 0);
				return;
			}
	
			$this->SendDebug('DataPayload', $data['Payload'], 0);
	
			$payload = json_decode($data['Payload'], true);
			if($payload == null)
			{
				$this->SendDebug('Payload', 'No Payload found', 0);
				return;
			}

			if(!array_key_exists('BLEOperation', $payload))
			{
				$this->SendDebug('Payload', 'No BLEOperation found', 0);
				return;
			}

			if(!array_key_exists('MAC', $payload['BLEOperation']))
			{
				$this->SendDebug('Payload', 'No MAC found', 0);
				return;
			}

			$mac = $this->ReadPropertyString('MAC');
			if(strlen($mac) == 17)
			{
				$mac = str_replace(':', '', $mac);
			}
			
			if($payload['BLEOperation']['MAC'] != $mac)
			{
				return "OK not for me!";
			}		

			//state: DONEREAD
			//FFA1FE5AFEBFFFFFFF57FFFEFF57F799FBE82FFE03FFFEFFFFFFFF5740
			if(!array_key_exists('state', $payload['BLEOperation']))
			{
				$this->SendDebug('Payload', 'No state found', 0);
				return;
			}

			$this->SendDebug('Payload', 'State: ' . $payload['BLEOperation']['state'], 0);
			if($payload['BLEOperation']['state'] != 'DONEREAD')
			{
				//$this->SendDebug('Payload', 'No DONEREAD found', 0);
				//$this->RequestData($_IPS['TARGET']);
				return;
			}

			if(!array_key_exists('read', $payload['BLEOperation']))
			{
				$this->SendDebug('Payload', 'No read found', 0);
				return;
			}

			$this->SendDebug('Payload', 'Read: ' . $payload['BLEOperation']['read'], 0);
			$this->ParsePayloadAndApplyData($payload['BLEOperation']['read']);

			return "OK von " . $this->InstanceID;
		}

		public function ParsePayloadAndApplyData(string $payload)
		{
			$this->SendDebug('ParsePayloadAndApplyData', $payload, 0);

			$decodedData = $this->decode($payload);

			if($decodedData == null)
			{
				$this->SendDebug('Parsing Error', 'Data can´t parsed Successful!', 0);
				return;
			}

			$this->SendDebug('ParsePayloadAndApplyData', 'Data Decoded.', 0);
			return;

			$productCode = $decodedData[2];
			$battery = round(100 * ($this->decode_position($decodedData, 15) - BATT_0) / (BATT_100 - BATT_0));
			$battery = min(max(0, $battery), 100);
			$ec = $this->decode_position($decodedData, 5);
			$tds = $this->decode_position($decodedData, 7);
			$ph = $this->decode_position($decodedData, 3) / 100.0;
			$orp = $this->decode_position($decodedData, 9) / 1000.0;
			$temperature = $this->decode_position($decodedData, 13) / 10.0;
			//$cloro = decode_position($decodedData, 11);
			// if ($cloro < 0) {
			// 	$cloro = 0;
			// } else {
			// 	$cloro = $cloro / 10.0;
			// }
			//$salt = $ec * 0.55;

			$this->SetValue(self::Battery, $battery);
			$this->SetValue(self::EC, $ec);
			$this->SetValue(self::TDS, $tds);
			$this->SetValue(self::PH, $ph);
			$this->SetValue(self::ORP, $orp);
			$this->SetValue(self::Temperature, $temperature);

			$this->SendDebug('ParsePayloadAndApplyData', "Finish.", 0);
		}		

		public function RequestData()
		{
			if(empty($this->ReadPropertyString('TasmotaDeviceName')) || empty($this->ReadPropertyString('MAC')))
			{
				$this->SendDebug("BLEYC01", "TasmotaDeviceName oder MAC Adresse nicht gesetzt", 0);
				return;
			}

			$this->SendDebug('RequestData', 'Send Request to Tasmota', 0);
	
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

			$this->SendDebug('RequestData', "Send data to Parent...", 0);
			$this->SendDataToParent($dataJSON);
		}

		function decode(string $byte_frame) : array
		{
			$packData = hex2bin($byte_frame);
			$frame_array = unpack('C*', $packData);
			$frame_array = array_values($frame_array);
			$size = count($frame_array);

			for ($i = $size - 1; $i > 0; $i--) {
				$tmp = $frame_array[$i];
				$hibit1 = ($tmp & 0x55) << 1;
				$lobit1 = ($tmp & 0xAA) >> 1;
				$tmp = $frame_array[$i - 1];
				$hibit = ($tmp & 0x55) << 1;
				$lobit = ($tmp & 0xAA) >> 1;

				$frame_array[$i] = 0xFF - ($hibit1 | $lobit);
				$frame_array[$i - 1] = 0xFF - ($hibit | $lobit1);
			}
				return $frame_array;
		}

		function reverse_bytes(int $bytes) 
		{
			return ($bytes[0] << 8) + $bytes[1];
		}
		
		function decode_position(array $decodedData, int $idx) 
		{
			return reverse_bytes(array_slice($decodedData, $idx, 2));
		}
	}