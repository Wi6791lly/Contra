<?php

	/*
	*       dAmnPHP version 5 by photofroggy.
	*
	*       Released under a Creative Commons Attribution-Noncommercial-Share Alike 3.0 License, which allows you to copy, distribute, transmit, alter, transform,
	*       and build upon this work but not use it for commercial purposes, providing that you attribute me, photofroggy (froggywillneverdie@msn.com) for its creation.
	*
	*       This class handles dAmn sockets and reads data from a dAmn connection. Can be
	*       used with any PHP client. I guess this is almost an unintended implementation of the
	*       dAmnSock specification proposed by Zeros-Elipticus, or as close as I will willingly
	*       come to it.
	*
	*       To create a new instance of the class simply use $variable = new dAmnPHP;
	*
	*       To get a cookie you need to use $dAmn->getCookie($username, $password);
	*       and store the cookie in $dAmn->cookie;. If you don't do this then you won't
	*       be able to get connected to dAmn or any chat network.
	*
	*       To be able to actually get logged into deviantART and connected to dAmn you
	*       need to set some variables from outside the class.
	*
	*       EXAMPLE:
	*               $dAmn->Client = 'dAmnPHP/public/3';
	*               $dAmn->owner = 'photofroggy';
	*               $dAmn->trigger = '!';
	*
	*       Now when you use $dAmn->connect();, that info will be sent in the handshake!
	*
	*       Use $dAmn->read(); to read data from the socket. If packets are received
	*       then the packets are returned in an array. If nothing is really happening
	*       on the socket then false is returned.
	*/

	// Before anything happens, we need to make sure OpenSSL is loaded. If not, kill the program!
	if(!extension_loaded('OpenSSL')) {
		echo '>> WARNING: You don\'t have OpenSSL loaded!',chr(10);
		if(PHP_OS == 'WIN32' || PHP_OS == 'WINNT' || PHP_OS == 'Windows')
			echo '>> Re-read the Install PHP guide @ http://botdom.com/documentation/Install_PHP_on_Windows',chr(10),'>> ';
		if(PHP_OS == 'Linux')
			echo '>> Re-read the Install PHP guide @ http://botdom.com/documentation/Install_PHP_on_Linux',chr(10),'>> ';
		if(PHP_OS == 'Darwin')
			echo '>> Re-read the Install PHP guide @ http://botdom.com/documentation/Install_PHP_on_Mac_OS_X',chr(10),'>> ';
		for($i = 0;$i < 3; ++$i) {
			sleep(1);
			echo '.';
		}
		echo chr(10);
		sleep(1);
		exit();
	}
	// Also make sure date.timezone is set. If not, kill the program.
	if(!ini_get('date.timezone')) {
		echo '>> WARNING: You didn\'t setup php properly. (date.timezone is not set)',chr(10);
		if(PHP_OS == 'WIN32' || PHP_OS == 'WINNT' || PHP_OS == 'Windows')
			echo '>> Re-read the Install PHP guide @ http://botdom.com/documentation/Install_PHP_on_Windows#Part_2 (Do/redo step 7-9)',chr(10),'>> ';
		if(PHP_OS == 'Linux')
			echo '>> Re-read the Install PHP guide @ http://botdom.com/documentation/Install_PHP_on_Linux#PHP_configure_setup (Do/redo step 2-4)',chr(10),'>> ';
		if(PHP_OS == 'Darwin')
			echo '>> Re-read the Install PHP guide @ http://botdom.com/documentation/Install_PHP_on_Mac_OS_X#PHP_configure_setup (Do/redo step 2-4)',chr(10),'>> ';
		for($i = 0;$i < 3; ++$i) {
			sleep(1);
			echo '.';
		}
		echo chr(10);
		sleep(1);
		exit();
	}
	// This is just a constant...
	define('LBR', chr(10)); // LineBReak

class dAmnPHP {
	public $Ver = 5;
	public $server = array(
		'chat' => array(
			'host' => 'chat.deviantart.com',
			'version' => '0.3',
			'port' => 3900,
		),
		'login' => array(
			'transport' => 'ssl://',
			'host' => 'www.deviantart.com',
			'file' => '/users/login',
			'port' => 443,
		),
	);
	public $Client = 'dAmnPHP';
	public $Agent = 'dAmnPHP/5';
	public $owner = 'photofroggy';
	public $trigger = '!';
	public $socket = Null;
	public $cookie = Null;
	public $connecting = Null;
	public $login = Null;
	public $connected = Null;
	public $close = Null;
	public $buffer = Null;
	public $chat = array();
	public $disconnects = 0;
	static $tablumps = array(                       // Regex stuff for removing tablumps.
		'a1' => array(
			"&b\t",  "&/b\t",    "&i\t",    "&/i\t", "&u\t",   "&/u\t", "&s\t",   "&/s\t",    "&sup\t",    "&/sup\t", "&sub\t", "&/sub\t", "&code\t", "&/code\t",
			"&br\t", "&ul\t",    "&/ul\t",  "&ol\t", "&/ol\t", "&li\t", "&/li\t", "&bcode\t", "&/bcode\t",
			"&/a\t", "&/acro\t", "&/abbr\t", "&p\t", "&/p\t"
		),
		'a2' => array(
			"<b>",  "</b>",       "<i>",     "</i>", "<u>",   "</u>", "<s>",   "</s>",    "<sup>",    "</sup>", "<sub>", "</sub>", "<code>", "</code>",
			"\n",   "<ul>",       "</ul>",   "<ol>", "</ol>", "<li>", "</li>", "<bcode>", "</bcode>",
			"</a>", "</acronym>", "</abbr>", "<p>",  "</p>\n"
		),
		'b1' => array(
			"/&emote\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t/",
			"/&a\t([^\t]+)\t([^\t]*)\t/",
			"/&link\t([^\t]+)\t&\t/",
			"/&link\t([^\t]+)\t([^\t]+)\t&\t/",
			"/&dev\t[^\t]\t([^\t]+)\t/",
			"/&avatar\t(.*?)\t(.*?)\t/",
			"/&thumb\t([0-9]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t/",
			"/&img\t([^\t]+)\t([^\t]*)\t([^\t]*)\t/",
			"/&iframe\t([^\t]+)\t([0-9%]*)\t([0-9%]*)\t&\/iframe\t/",
			"/&acro\t([^\t]+)\t/",
			"/&abbr\t([^\t]+)\t/"
		),
		'b2' => array(
			"\\1",
			"<a href=\"\\1\" title=\"\\2\">",
			"\\1",
			"\\1 (\\2)",
			":dev\\1:",
			":icon\\1:",
			":thumb\\1:",
			"<img src=\"\\1\" alt=\"\\2\" title=\"\\3\" />",
			"<iframe src=\"\\1\" width=\"\\2\" height=\"\\3\" />",
			"<acronym title=\"\\1\">",
			"<abbr title=\"\\1\">"
		),
	);

	function Time($ts=false) { return date('H:i:s', ($ts===false?time():$ts)); }
	function Clock($ts=false) {     return '['.$this->Time($ts).']'; }
	function Message($str = '', $ts = false) { echo $this->Clock($ts),' '.$str,chr(10); }
	function Notice($str = '', $ts = false)  { $this->Message('** '.$str,$ts); }
	function Warning($str = '', $ts = false) { $this->Message('>> '.$str,$ts); }
	function getCookie($username, $pass) {
		// Method to get the cookie! Yeah! :D
		// Our first job is to open an SSL connection with our host.
		$socket = fsockopen(
			$this->server['login']['transport'].$this->server['login']['host'],
			$this->server['login']['port']
		);
		// If we didn't manage that, we need to exit!
		if($socket === false) {
		return array(
			'status' => 2,
			'error' => 'Could not open an internet connection');
		}
		// Fill up the form payload
		$POST = '&username='.urlencode($username);
		$POST.= '&password='.urlencode($pass);
		$POST.= '&remember_me=1';
		// And now we send our header and post data and retrieve the response.
		$response = $this->send_headers(
			$socket,
			$this->server['login']['host'],
			$this->server['login']['file'],
			'http://www.deviantart.com/users/rockedout',
			$POST
		);

		// Now that we have our data, we can close the socket.
		fclose ($socket);
		// And now we do the normal stuff, like checking if the response was empty or not.
		if(empty($response))
		return array(
			'status' => 3,
			'error' => 'No response returned from the server'
		);
		if(stripos($response, 'set-cookie') === false)
		return array(
			'status' => 4,
			'error' => 'No cookie returned'
		);
		// Grab the cookies from the header
		$response=explode("\r\n", $response);
		$cookie_jar = array();
		foreach ($response as $line)
		{
			if(strpos($line, 'Location: ') !== false)
				if($line == 'Location: http://www.deviantart.com/users/wrong-password')
				return array(
					'status' => 6,
					'error' => 'Wrong password returned'
				);
				if (strpos($line, 'Set-Cookie:')!== false)
					$cookie_jar[] = substr($line, 12, strpos($line, '; ')-12);
		}
		// Using these cookies, we're gonna go to chat.deviantart.com and get
		// our authtoken from the dAmn client.
		if (($socket = @fsockopen('ssl://www.deviantart.com', 443)) == false)
			return array(
			'status' => 2,
			'error' => 'Could not open an internet connection');

		$response = $this->send_headers(
			$socket,
			'chat.deviantart.com',
			'/chat/Botdom',
			'http://chat.deviantart.com',
			null,
			$cookie_jar
		);

		// Now search for the authtoken in the response
		$cookie = null;
		if(($pos = strpos($response, 'dAmn_Login( ')) !== false)
		{
			$response = substr($response, $pos+12);
			$cookie = substr($response, strpos($response, '", ')+4, 32);
		}
		elseif(($pos = strpos($response, 'Location: http://verify.deviantart.com')) !== false)
		return array(
			'status' => 6,
			'error' => 'Account not verfied, check your email and verify your account first'
		);
		else return array(
			'status' => 4,
			'error' => 'No authtoken found in dAmn client'
		);

		// Because errors still happen, we need to make sure we now have an array!
		if(!$cookie)
		return array(
			'status' => 5,
			'error' => 'Malformed cookie returned'
		);
		// We got a valid cookie!
		return array(
			'status' => 1,
			'cookie' => $cookie
		);
	}

	function send_headers($socket, $host, $url, $referer, $post=null, $cookies=array())
	{
	    try
	    {
		$headers = '';
		if (isset($post))
			$headers .= "POST $url HTTP/1.1\r\n";
		else $headers .= "GET $url HTTP/1.1\r\n";
		$headers .= "Host: $host\r\n";
		$headers .= 'User-Agent: '.$this->Agent."\r\n";
		$headers .= "Referer: $referer\r\n";
		if ($cookies != array())
			$headers .= 'Cookie: '.implode("; ", $cookies)."\r\n";
		$headers .= "Connection: close\r\n";
		$headers .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*\/*;q=0.8\r\n";
		$headers .= "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n";
		$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
		if (isset($post))
			$headers .= 'Content-Length: '.strlen($post)."\r\n\r\n$post";
		else $headers .= "\r\n";
		$response = '';
		fputs($socket, $headers);
		while (!@feof ($socket)) $response .= @fgets ($socket, 8192);
		return $response;
	    }
	    catch (Exception $e)
	    {
		echo 'Exception occured: '.$e->getMessage()."\n";
		return '';
	    }
	}

	public function connect() {                     // This method creates our dAmn connection!
		// First thing we do is create a socket stream using the server config data.
		$this->socket = @stream_socket_client('tcp://'.$this->server['chat']['host'].
		':'.$this->server['chat']['port']);
		// If we managed to open a connection, we need to do one or two things.
		if($this->socket !== false) {
			// First we set the stream to non-blocking, so the bot doesn't pause when reading data.
			stream_set_blocking($this->socket, 0);
			// Now we make our handshake packet. Here we send information about the bot/client to the dAmn server.
			$data = 'dAmnClient '.$this->server['chat']['version'].LBR;
			$data.= 'agent='.$this->Agent.LBR;
			$data.= 'bot='.$this->Client.LBR;
			$data.= 'owner='.$this->owner.LBR;
			$data.= 'trigger='.$this->trigger.LBR;
			$data.= 'creator=photofroggy/froggywillneverdie@msn.com'.LBR.chr(0);
			// This is were we actually send the packet! Quite simple really.
			@stream_socket_sendto($this->socket, $data);
			// Now we have to raise a flag! This tells everything that we are currently trying to connect through a handshake!
			$this->connecting = true;
			// Finally, exit before this if case exits, so we can do the stuff that happens when the socket stream fails.
			return true;
		}
		// All we do here is display an error message and return false dawg.
		$this->Warning('Could not open connection with '.$this->server['chat']['host'].'.');
		return false;
	}

	function login($username, $authtoken) {         // Need to send a login packet? I'm your man!
		$this->login = ( $this->send("login $username\npk=$authtoken\n\0") ? true : true );
	}

	function deform_chat($chat, $discard=false) {
		if(substr($chat, 0, 5)=='chat:') return '#'.str_replace('chat:','',$chat);
		if(substr($chat, 0, 6)=='pchat:') {
			if($discard===false) return $chat;
			$chat = str_replace('pchat:','',$chat);
			$chat1=substr($chat,0,strpos($chat,':'));
			$chat2=substr($chat,strpos($chat,':')+1);
			$mod = true;
			if(strtolower($chat1)==strtolower($discard)) return '@'.$chat1;
			else return '@'.$chat2;
		}
		return (substr($chat,0,1)=='#') ? $chat : (substr($chat, 0, 1)=='@' ? $chat : '#$chat');
	}

	function format_chat($chat, $chat2=false) {
		$chat = str_replace('#','',$chat);
		if($chat2!=false) {
			$chat = str_replace('@','',$chat);
			$chat2 = str_replace('@','',$chat2);
			if(strtolower($chat)!=strtolower($chat2)) {
				$channel = 'pchat:';
				$users = array($chat, $chat2);
				sort($users);
				return $channel.$users[0].':'.$users[1];
			}
		}
		return (substr($chat, 0, 5)=='chat:') ? $chat : (substr($chat, 0, 6)=='pchat:' ? $chat : 'chat:'.$chat);
	}

	/*
	*               Lol, dAmn commands are below. Where you
	*               see the constant "LBR" is a LineBReak, which
	*               is the same as "\n", so if you're having trouble
	*               understanding the packets, just imagine that the
	*               "LBR"s are actually "\n"s, because they are...
	*/

	function join($channel) { $this->send('join '.$channel.LBR); }
	function part($channel) {
		if(strtolower($channel) == 'chat:datashare') return;
		$this->send('part '.$channel.LBR);
	}
	function say($ns, $message, $DATASHARE = FALSE) {
		if(strtolower($ns) == 'chat:irpg') return;
		if(is_array($ns)) {
			// WE CAN SEND MESSAGES TO A PLETHORA OF CHANNELS!
			foreach($ns as $var1 => $var2) {
				$this->say(((is_string($var1)) ? $var1 : $var2), $message);
			}
			return;
		}
		// The type of message is easily changeable.
		$type = (substr($message, 0, 4) == '/me ') ? 'action' : ((substr($message, 0, 7) == '/npmsg ') ? 'npmsg' : 'msg');
		$message = ($type == 'action') ? substr($message, 4) : (($type == 'npmsg') ? substr($message, 7) : $message );
		$message = is_array($message) ? $message = '<bcode>'.print_r($message, true) : $message;
		$message = str_replace('&lt;','<',$message);
		$message = str_replace('&gt;','>',$message);
		if(strtolower($ns) != 'chat:datashare' || $DATASHARE == TRUE)
			$this->send('send '.$ns.LBR.LBR.$type.' main'.LBR.LBR.$message);
	}
	function action($ns, $message, $DATASHARE = FALSE) {
		if(strtolower($ns) == 'chat:irpg') return;
		if(strtolower($ns) != 'chat:datashare')
			$this->say($ns, '/me '.$message);
		elseif($DATASHARE == TRUE)
			$this->say($ns, '/me '.$message, TRUE);
	}
	function npmsg($ns, $message, $DATASHARE = FALSE) {
		if(strtolower($ns) == 'chat:irpg') return;
		if(strtolower($ns) != 'chat:datashare')
			$this->say($ns, '/npmsg '.$message);
		elseif($DATASHARE == TRUE)
			$this->say($ns, '/npmsg '.$message, TRUE);
	}
	function promote($ns, $user, $pc=false) { $this->send('send '.$ns.LBR.LBR.'promote '.$user.LBR.LBR.($pc!=false?$pc:'')); }
	function demote($ns, $user, $pc=false) { $this->send('send '.$ns.LBR.LBR.'demote '.$user.LBR.LBR.($pc!=false?$pc:'')); }
	function kick($ns, $user, $r=false) { $this->send('kick '.$ns.LBR.'u='.$user.LBR.($r!=false?"\n$r\n":'')); }
	function ban($ns, $user) { $this->send('send '.$ns.LBR.LBR.'ban '.$user.LBR); }
	function unban($ns, $user) { $this->send('send '.$ns.LBR.LBR.'unban '.$user.LBR); }
	function get($ns, $property) { $this->send('get '.$ns.LBR.'p='.$property.LBR); }
	function set($ns, $property, $value) { $this->send('set '.$ns.LBR.'p='.$property.LBR.LBR.$value.LBR); }
	function admin($ns, $command) { $this->send('send '.$ns.LBR.LBR.'admin'.LBR.LBR.$command); }
	function disconnect() { $this->send('disconnect'.LBR); }
	// Here's the actual send function which sends the packets.
	function send($data) { @stream_socket_sendto($this->socket, $data.chr(0)); }
	// This is the important one. It reads packets off of the stream and returns them in an array! Numerically indexed.
	function read() {
		$s = array($this->socket); $w=Null;
		if(($s = @stream_select($s,$w,$w,0)) !== false) {
			if($s === 0) return false;
			$data = @stream_socket_recvfrom($this->socket, 8192);
			if($data !== false && $data !== '') {
				$this->buffer .= $data;
				$parts = explode(chr(0), $this->buffer);
				$this->buffer = ($parts[count($parts)-1] != '' ? $parts[count($parts)-1] : '');
				unset($parts[count($parts)-1]);
				if($parts!==Null) return $parts;
				return false;
			} else return array("disconnect\ne=socket closed\n\n");
		} else return array("disconnect\ne=socket error\n\n");
	}

	function is_channel($ns) {
		foreach($this->chat as $namespace => $data)
			if(strtolower($namespace)==strtolower($ns)) return $namespace;
		return false;
	}
}

function parse_tablumps($data) {
	$data = str_replace(dAmnPHP::$tablumps['a1'], dAmnPHP::$tablumps['a2'], $data);
	$data = preg_replace(dAmnPHP::$tablumps['b1'], dAmnPHP::$tablumps['b2'], $data);
	$data = preg_replace('/<abbr title="colors:[A-F0-9]{6}:[A-F0-9]{6}"><\/abbr>/','',$data);
	return preg_replace('/<([^>]+) (width|height|title|alt)=""([^>]*?)>/', "<\\1\\3>", $data);
}

	/*
	*       Oh look! A packet parser! This may
	*       come in handy at a later point.
	*/
function parse_dAmn_packet($data, $sep = '=') {
	$data = parse_tablumps($data);

	$packet = array(
		'cmd' => Null,
		'param' => Null,
		'args' => array(),
		'body' => Null,
		'raw' => $data
	);
	if(stristr($data, "\n\n")) {
		$packet['body'] = trim(stristr($data, "\n\n"));
		$data = substr($data, 0, strpos($data, "\n\n"));
	}
	$data = explode("\n", $data);
	foreach($data as $id => $str) {
		if(strpos($str, $sep) != 0)
			$packet['args'][substr($str, 0, strpos($str, $sep))] = substr($str, strpos($str, $sep)+1);
		elseif(isset($str[1])) {
			if(!stristr($str, ' ')) { $packet['cmd'] = $str; } else {
				$packet['cmd'] = substr($str, 0, strpos($str, ' '));
				$packet['param'] = trim(stristr($str, ' '));
			}
		}
	}
	return $packet;
}

function sort_dAmn_packet($packet) {
	$packet = parse_dAmn_packet($packet); // Told ya so...
	$data = array(
		'event' => 'packet',
		'p' => array($packet['param'], False, False, False, False, False),
		'packet' => $packet,
	);
	if(substr($packet['param'], 0, 6)=='login:') {
		$data['event'] = 'whois';
		$data['p'][0] = $packet['raw'];
		return $data;
	}
	switch($packet['cmd']) {
		case 'dAmnServer':
			$data['event'] = 'connected';
			break;
		case 'login':
			$data['event'] = 'login';
			$data['p'][0] = $packet['args']['e'];
			break;
		case 'join':
		case 'part':
			$data['event'] = $packet['cmd'];
			$data['p'][1] = $packet['args']['e'];
			if(array_key_exists('r', $packet['args'])) $data['p'][2] = $packet['args']['r'];
			break;
		case 'property':
			$data['event'] = 'property';
			$data['p'][1] = $packet['args']['p'];
			$data['p'][2] = $packet['raw'];
			break;
		case 'recv':
			$sub = parse_dAmn_packet($packet['body']);
			$data['event'] = 'recv_'.$sub['cmd'];
			switch($sub['cmd']) {
				case 'msg':
				case 'action':
					$data['p'][1] = $sub['args']['from'];
					$data['p'][2] = $sub['body'];
					break;
				case 'join':
				case 'part':
					$data['p'][1] = $sub['param'];
					if(array_key_exists('r', $sub['args'])) $data['p'][2] = $sub['args']['r'];
					if($sub['cmd']=='join') $data['p'][2] = $sub['body'];
					break;
				case 'privchg':
				case 'kicked':
					$data['p'][1] = $sub['param'];
					$data['p'][2] = $sub['args']['by'];
					if($sub['cmd']=='privchg') $data['p'][3] = $sub['args']['pc'];
					if($sub['body'] !== Null) $data['p'][3] = $sub['body'];
					break;
				case 'admin':
					$data['event'].= '_'.$sub['param'];
					$data['p'] = array($packet['param'],$sub['args']['p'],false,false,false,false);
					if(array_key_exists('by', $sub['args']))
						$data['p'][2] = $sub['args']['by'];
					switch($sub['param']) {
						case 'create':
						case 'update':
							$data['p'][3] = $sub['args']['name'];
							$data['p'][4] = $sub['args']['privs'];
							break;
						case 'rename':
						case 'move':
							$data['p'][3] = $sub['args']['prev'];
							$data['p'][4] = $sub['args']['name'];
							if(array_key_exists('n', $sub['args']))
								$data['p'][5] = $sub['args']['n'];
							break;
						case 'remove':
							$data['p'][3] = $sub['args']['name'];
							$data['p'][4] = $sub['args']['n'];
							break;
						case 'show':
							$data['p'][2] = $sub['body'];
							break;
						case 'privclass':
							$data['p'][2] = $sub['args']['e'];
							if($sub['body']!==Null)
								$data['p'][3] = $sub['body'];
							break;
					}
					break;
			}
			break;
		case 'kicked':
			$data['event'] = 'kicked';
			$data['p'][1] = $packet['args']['by'];
			if($packet['body'] !== Null) $data['p'][2] = $packet['body'];
			break;
		case 'ping':
			$data['event'] = 'ping';
			$data['p'][0]=false;
			break;
		case 'disconnect':
			$data['event'] = 'disconnect';
			$data['p'][0] = $packet['args']['e'];
			break;
		case 'send':
		case 'kick':
		case 'get':
		case 'set':
			$data['event'] = $packet['cmd'];
			$data['p'][1] = (array_key_exists('u',$packet['args'])?$packet['args']['u']:(isset($packet['args']['p'])?$packet['args']['p']:false));
			$id = $data['p'][1] == false ? 1 : 2;
			$data['p'][$id] = $packet['args']['e'];
			break;
		case 'kill':
			$data['event'] = 'kill';
			$data['p'][1] = $packet['args']['e'];
			$data['p'][2] = $packet['cmd'].' '.$packet['param'];
		case '': break;
		default:
			$data['event'] = 'unknown';
			$data['p'][0] = $packet;
			break;
	}
	return $data;
}

?>