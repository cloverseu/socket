<?php
	/**
	 * 
	 */
	class WebSocket{
		private $socket;
		public $cycle = array();
		public $accept = array();
		public $isHand = array();
		public $max = 10;

	/**
	 * [__construct description]
	 * @param [type] $address [description]
	 * @param [type] $port    [description]
	 * @param [type] $max     [description]
	 */
		function __construct($address,$port){
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
	 		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1) or die("socket_option() failed");
	 		socket_bind($this->socket, $address, $port) or die("socket_bind() failed");
	 		socket_listen($this->socket, $this->max) or die("socket_listen() failed");
	 		echo "Server Started : ".date('Y-m-d H:i:s')."\n";
	 		echo "this->socket socket  : ".$this->socket."\n";
	 		echo "Listening on   : ".$address." port ".$port."\n\n";
 			while(true){
 			$this->cycle = $this->accept;
 			$this->cycle[] = $this->socket;
 			socket_select($this->cycle, $write = NULL, $except = NULL, NULL);
 				foreach ($this->cycle as $key => $value) {
 					if ($value == $this->socket){
 						$accept = socket_accept($value);
 						$this->accept[] = $accept;
 						$index = array_keys($this->accept);
 						$index = end($index);
 						$this->isHand[$index] = FALSE;
 						continue;
 					}
 					$index = array_search($value, $this->accept);
 					echo $index."\n";
 					$data = socket_recv($value, $buf, 2048, 0);
 					if (!$data) {
 						$this->close($value);
 						continue;
 					}
 					if (!$this->isHand[$index]) {
 						$this->handShake($value,$buf,$index);
 						$this->add($this);
 						continue;
 					}
 					$data = $this->encode($buf);
 					echo $data."\n";
 					$this->send($data,$this,$index);
 				}
 				sleep(1);
 			}
 		}
 		/**
 		 * [getHeader description]
 		 * @param  [type] $request [description]
 		 * @return [type]          [description]
 		 */
 		private function getHeader($request) {
	 		$url = $host = $origin = $key = null;
	 		// 获取url信息
	 		if(preg_match("/GET (.*) HTTP\/1\.1\r\n/", $request, $match)){ 
	 			$url = $match[1]; 
	 		}
	 		// 获取host地址
	 		if(preg_match("/Host: (.*)\r\n/", $request, $match)){ 
	 			$host = $match[1]; 
	 		}
	 		// websocket的源
	 		if(preg_match("/Sec-WebSocket-Origin: (.*)\r\n/", $request, $match)){ 
	 			$origin = $match[1]; 
	 		}
	 		// 获取密钥
	 		if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $request, $match)){ 
	 			$key = $match[1]; 
	 		}
	 		return array($url, $host, $origin, $key);
 		}
 		/**
 		 * [handShake description]
 		 * @param  [type] $socket [description]
 		 * @param  [type] $buffer [description]
 		 * @return [type]         [description]
 		 */
 		public function handShake($socket, $buffer,$index) {
	 		if(empty($socket) || empty($buffer)) {
	 			return false;
	 		}
	 		list($url , $host, $origin, $key) = $this->getHeader($buffer);
	 		$key .= '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'; // 密钥(可不加)
	 		$hashData = base64_encode(sha1($key, true)); // base64加密
	 		// websocket协议
	 		$upgrade  = "HTTP/1.1 101 Switching Protocols\r\n" .
	 					"Upgrade: websocket\r\n" .
	 					"Connection: Upgrade\r\n" .
	 					"Sec-WebSocket-Accept: " . $hashData . "\r\n\r\n";
	 		socket_write($socket, $upgrade, strlen($upgrade)); // 输出内容到客户端
	 		$this->isHand[$index] = TRUE;
 		}
 		/**
 		 * [unwrap description]
 		 * @param  [type] $buffer [description]
 		 * @return [type]         [description]
 		 */
 		public function encode($buffer) {
	 		$mask = array();
	 		$data = "";
	 		$msg = unpack("H*", $buffer);
	 		
	 		$head = substr($msg[1],0,2);
	 		
	 		if (hexdec($head{1}) === 8) {
	 			$data = false;
	 		} else if (hexdec($head{1}) === 1) {
	 			$mask[] = hexdec(substr($msg[1],4,2));
	 			$mask[] = hexdec(substr($msg[1],6,2));
	 			$mask[] = hexdec(substr($msg[1],8,2));
	 			$mask[] = hexdec(substr($msg[1],10,2));
	 		
	 			$s = 12;
	 			$e = strlen($msg[1])-2;
	 			$n = 0;
	 			for ($i= $s; $i<= $e; $i+= 2) {
	 				$data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
	 				$n++;
	 			}
	 		}
	 		
	 		return $data;
	 	}
	 	/**
	 	 * [wrap description]
	 	 * @param  [type] $string [description]
	 	 * @return [type]         [description]
	 	 */
	 	public function decode($string) {
	 		$frame = array();
	 		$frame[0] = "81";
	 		$len = strlen($string);
	 		$frame[1] = $len < 16 ? "0".dechex($len) : dechex($len);
	 		$frame[2] = $this->ord_hex($string);
	 		$data = implode("",$frame);
	 		return pack("H*", $data);
	 	}
	 	/**
	 	 * [ord_hex description]
	 	 * @param  [type] $data [description]
	 	 * @return [type]       [description]
	 	 */
	 	private function ord_hex($data) {
	 		$msg = "";
	 		$l = strlen($data);
	 		for ($i= 0; $i< $l; $i++) {
	 			$msg .= dechex(ord($data{$i}));
	 		}
	 		return $msg;
	 	}
	 	/**
	 	 * [send_to_all description]
	 	 * @param  [type] $data   [description]
	 	 * @param  [type] $type   [description]
	 	 * @param  [type] $client [description]
	 	 * @return [type]         [description]
	 	 */
	 	public function send_to_all($data,$type,$client){
	 		$res = array(
	 				'msg' => $data,
	 				'type' => $type,
	 			);
	 		$res = json_encode($res);
	 		$res = $this->decode($res);
	 		foreach ($client->accept as $key => $value) {
	 		socket_write($value, $res , strlen($res));
			}	 	
	 	}
	 	/**
	 	 * [send description]
	 	 * @param  [type] $data   [description]
	 	 * @param  [type] $client [description]
	 	 * @param  [type] $index  [description]
	 	 * @return [type]         [description]
	 	 */
	 	public function send($data,$client,$index){
	 		$res = array(
	 				'msg' => $data,
	 				'user' => $index,
	 			);
	 		$res = json_encode($res);
	 		$this->send_to_all($res,'msg',$client);
	 	}
	 	/**
	 	 * [add description]
	 	 * @param [type] $client [description]
	 	 */
	 	public function add($client){
	 		$num = count($client->accept);
	 		$this->send_to_all($num,'num',$client);
	 	}
	 	/**
	 	 * [close description]
	 	 * @param  [type] $client [description]
	 	 * @return [type]         [description]
	 	 */
	 	public function close($client){
	 		$index = array_search($client, $this->accept);
			socket_close($client);
			unset($this->accept[$index]);
			unset($this->isHand[$index]);
	 		$num = count($client->accept);
	 		$this->send_to_all($num,'num',$client);
	 	}
	}		
?>