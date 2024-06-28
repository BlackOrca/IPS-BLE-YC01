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

		const RedValue = 0xF44336;
		const YellowValue = 0xF68B38;
		const GreenValue = 0x189B49;

		// const BATT_0 = 1950;
		const BATT_0 = 1700;
		//const BATT_100 = 3190;
		const BATT_100 = 3090;

		const Battery = "Battery";
		const EC = "EC";
		const TDS = "TDS";
		const PH = "PH";
		const ORP = "ORP";
		const Temperature = "Temperature";
		const Status = "Status";
		const Chlorine = "Chlorine";
		const Active = "Active";
		const DataTimestamp = "DataTimestamp";

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString("TasmotaDeviceName", "");
			$this->RegisterPropertyString("MAC", "");
			$this->RegisterPropertyInteger("RequestInterval", 30);
			$this->RegisterPropertyBoolean("Active", true);
			
			$this->RegisterTimer('RequestTimer', 0, 'BLEYC_RequestData($_IPS[\'TARGET\']);');
			
			$this->RegisterVariableFloat(self::Temperature, $this->Translate(self::Temperature), "~Temperature", 10);

			$this->RegisterPH(20);
			$this->RegisterChlorine(30);
			$this->RegisterORP(40);
			$this->RegisterTDS(50);
			$this->RegisterEC(60);
			
			$this->RegisterVariableInteger(self::DataTimestamp, $this->Translate(self::DataTimestamp), "~UnixTimestamp", 90);
			$this->RegisterVariableInteger(self::Battery, $this->Translate(self::Battery), "~Battery.100", 100);		
			$this->RegisterVariableBoolean(self::Status, self::Status, "~Alert", 110);			
			$this->RegisterVariableBoolean(self::Active, $this->Translate(self::Active), "~Switch", 120);

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

			if(!$this->ReadPropertyBoolean('Active'))
			{
				$this->SetStatus(104);
				$this->SetValue(self::Active, false);			
				return;
			}
			
			$this->SetValue(self::Active, true);	
			$this->ConnectParent(self::MqttParent);
			
			$filterResult = preg_quote('"Topic":"' . self::ResponseTopic . '/' . $this->ReadPropertyString('TasmotaDeviceName') . '/' . self::BleResultPostfix);	
			$this->SendDebug('ReceiveDataFilter', '.*' . $filterResult . '.*', 0);
			$this->SetReceiveDataFilter('.*' . $filterResult . '.*');

			if ($this->HasActiveParent() && IPS_GetKernelRunlevel() == KR_READY) {
				$this->RequestData($_IPS['TARGET']);
			}		
			
			$interval = $this->ReadPropertyInteger('RequestInterval') * 1000 * 60;
			$this->SetTimerInterval('RequestTimer', $interval);
			$this->SendDebug('RequestTimer', 'Interval: ' . $interval . ' ms', 0);
			if($this->GetValue(self::DataTimestamp) != 0)
			{
				$this->SetValue(self::DataTimestamp, 0);
			}

			$this->SetStatus(102);
		}

		public function ReceiveData($JSONString)
		{		
			if(empty($this->ReadPropertyString('TasmotaDeviceName')) || empty($this->ReadPropertyString('MAC')))
			{
				$this->SendDebug("BLEYC01", "TasmotaDeviceName oder MAC Adresse nicht gesetzt", 0);
				$this->SetValue(self::Active, false);
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
				$this->SendDebug('Payload', 'No DONEREAD found', 0);
				$this->SetValue(self::Status, true);
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

			$productCode = $decodedData[2];
			//$battery = $this->decode_position($decodedData, 15)/45;
			$battery = round(100 * ($this->decode_position($decodedData, 15) - self::BATT_0) / (self::BATT_100 - self::BATT_0));
			$battery = min(max(0, $battery), 100);
			$ec = $this->decode_position($decodedData, 5);
			$tds = $this->decode_position($decodedData, 7);
			$ph = $this->decode_position($decodedData, 3) / 100.0;
			$orp = $this->decode_position($decodedData, 9);
			//$orp = $this->decode_position($decodedData, 20);
			$temperature = $this->decode_position($decodedData, 13) / 10.0;

			$cloro = $this->decode_position($decodedData, 11);			
			if ($cloro < 0) 
			{
				$cloro = 0;
			} 
			else if($cloro > 6000)
			{
				$cloro = 0;
			} 
			else
			{
				$cloro = $cloro / 10.0;
			}

			$this->SetValue(self::Battery, $battery);
			$this->SetValue(self::EC, $ec);
			$this->SetValue(self::TDS, $tds);
			$this->SetValue(self::PH, $ph);
			$this->SetValue(self::ORP, $orp);
			$this->SetValue(self::Temperature, $temperature);
			$this->SetValue(self::Status, false);
			$this->SetValue(self::Chlorine, $cloro);
			$this->SetValue(self::DataTimestamp, time());

			$this->SendDebug('ParsePayloadAndApplyData', "Finish.", 0);
		}		

		public function RequestData()
		{
			if(!$this->ReadPropertyBoolean('Active'))
			{
				$this->SendDebug('RequestData', 'Instance is inactive', 0);
				return;
			}

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

		function reverse_bytes(array $bytes) 
		{
			return ($bytes[0] << 8) + $bytes[1];
		}
		
		function decode_position(array $decodedData, int $idx) 
		{
			return $this->reverse_bytes(array_slice($decodedData, $idx, 2));
		}

		function RegisterPH(int $position)
		{
			if(IPS_VariableProfileExists('BLEYC01.PH'))
				IPS_DeleteVariableProfile('BLEYC01.PH');

			IPS_CreateVariableProfile('BLEYC01.PH', 2);
			IPS_SetVariableProfileIcon('BLEYC01.PH', 'ErlenmeyerFlask');
			IPS_SetVariableProfileText('BLEYC01.PH', '', ' pH');
			IPS_SetVariableProfileValues('BLEYC01.PH', 0, 14, 0.1);
			IPS_SetVariableProfileDigits('BLEYC01.PH', 1);
			IPS_SetVariableProfileAssociation('BLEYC01.PH', 0, '', '', self::RedValue);
			IPS_SetVariableProfileAssociation('BLEYC01.PH', 6.5, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.PH', 7.0, '', '', self::GreenValue);
			IPS_SetVariableProfileAssociation('BLEYC01.PH', 7.5, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.PH', 8, '', '', self::RedValue);
		
			$this->RegisterVariableFloat(self::PH, $this->Translate(self::PH), "BLEYC01.PH", $position);
		}

		function RegisterChlorine(int $position)
		{
			if(IPS_VariableProfileExists('BLEYC01.Chlorine'))
				IPS_DeleteVariableProfile('BLEYC01.Chlorine');

			IPS_CreateVariableProfile('BLEYC01.Chlorine', 2);
			IPS_SetVariableProfileIcon('BLEYC01.Chlorine', 'ErlenmeyerFlask');
			IPS_SetVariableProfileText('BLEYC01.Chlorine', '', ' mg/l');
			IPS_SetVariableProfileValues('BLEYC01.Chlorine', 0, 10, 0.1);
			IPS_SetVariableProfileDigits('BLEYC01.Chlorine', 1);
			IPS_SetVariableProfileAssociation('BLEYC01.Chlorine', 0, '', '', self::RedValue);
			IPS_SetVariableProfileAssociation('BLEYC01.Chlorine', 0.5, '', '', self::GreenValue);
			IPS_SetVariableProfileAssociation('BLEYC01.Chlorine', 1.1, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.Chlorine', 2.1, '', '', self::RedValue);
		
			$this->RegisterVariableFloat(self::Chlorine, $this->Translate(self::Chlorine), "BLEYC01.Chlorine", $position);
		}

		function RegisterTDS(int $position)
		{
			if(IPS_VariableProfileExists('BLEYC01.TDS'))
				IPS_DeleteVariableProfile('BLEYC01.TDS');

			IPS_CreateVariableProfile('BLEYC01.TDS', 1);
			IPS_SetVariableProfileIcon('BLEYC01.TDS', 'Snow');
			IPS_SetVariableProfileText('BLEYC01.TDS', '', ' ppm');
			IPS_SetVariableProfileValues('BLEYC01.TDS', 0, 2000, 1);
			IPS_SetVariableProfileDigits('BLEYC01.TDS', 0);

			IPS_SetVariableProfileAssociation('BLEYC01.TDS', 0, '', '', self::RedValue);
			IPS_SetVariableProfileAssociation('BLEYC01.TDS', 40, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.TDS', 80, '', '', self::GreenValue);
			IPS_SetVariableProfileAssociation('BLEYC01.TDS', 121, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.TDS', 200, '', '', self::RedValue);
					
			$this->RegisterVariableInteger(self::TDS, "TDS", "BLEYC01.TDS", $position);
		}

		function RegisterEC(int $position)
		{
			if(IPS_VariableProfileExists('BLEYC01.EC'))
				IPS_DeleteVariableProfile('BLEYC01.EC');

			IPS_CreateVariableProfile('BLEYC01.EC', 1);
			IPS_SetVariableProfileIcon('BLEYC01.EC', '');
			IPS_SetVariableProfileText('BLEYC01.EC', '', ' µS/cm');
			IPS_SetVariableProfileValues('BLEYC01.EC', 0, 2000, 1);
			IPS_SetVariableProfileDigits('BLEYC01.EC', 0);

			// IPS_SetVariableProfileAssociation('BLEYC01.EC', 0, '', '', self::RedValue);
			// IPS_SetVariableProfileAssociation('BLEYC01.EC', 3, '', '', self::GreeValue);
			// IPS_SetVariableProfileAssociation('BLEYC01.EC', 16, '', '', self::YellowValue);
			// IPS_SetVariableProfileAssociation('BLEYC01.EC', 30, '', '', self::RedValue);
		
			$this->RegisterVariableInteger(self::EC, "EC", "BLEYC01.EC", $position);
		}

		function RegisterORP(int $position)
		{
			if (IPS_VariableProfileExists('BLEYC01.ORP')) 
				IPS_DeleteVariableProfile('BLEYC01.ORP');			

			IPS_CreateVariableProfile('BLEYC01.ORP', 2);
			IPS_SetVariableProfileIcon('BLEYC01.ORP', 'Electricity');
			IPS_SetVariableProfileText('BLEYC01.ORP', '', ' mV');
			IPS_SetVariableProfileValues('BLEYC01.ORP', -1000, 1000, 1);
			IPS_SetVariableProfileDigits('BLEYC01.ORP', 0);

			IPS_SetVariableProfileAssociation('BLEYC01.ORP', -1000, '', '', self::RedValue);
			IPS_SetVariableProfileAssociation('BLEYC01.ORP', 399, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.ORP', 600, '', '', self::GreenValue);
			IPS_SetVariableProfileAssociation('BLEYC01.ORP', 801, '', '', self::YellowValue);
			IPS_SetVariableProfileAssociation('BLEYC01.ORP', 1000, '', '', self::RedValue);

			$this->RegisterVariableFloat(self::ORP, "ORP", "BLEYC01.ORP", $position);
		}
	}