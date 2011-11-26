<?php
class cleanup extends extension {
	public $name = 'Cleanup';
	public $version = 1;
	public $about = 'Cleans up banned users from a room.';
	public $status = true;
	public $author = 'lwln';
	public $type = EXT_SYSTEM;
	
	protected $room;
	protected $person;
	
	function init() {
		$this->addCmd('cleanup', 'c_cleanup', 100);
	}
	
	function c_cleanup($ns, $from, $message, $target) {
		$this->dAmn->admin($ns, 'show users');
		$this->room = !empty($target) ? $target : $ns;
		$this->person = $from;
		$this->hook('e_cleanup', 'recv_admin_show');
	}
	
	function e_cleanup() {
		$users = $GLOBALS['crap'];
		$list = explode(" ", $users);
		$count = preg_match_all("/(\!)([\w'-])*/im", $users, $users);
		foreach($users[0] as $user) {
			$this->dAmn->unban($this->room, substr($user, 1));
		}
		$this->dAmn->say($this->room, '<abbr title="'.$this->person.'"></abbr>Cleaned #'.substr($this->room, 5).' <sub>['.$count.' users found]</sub>');
		$this->unhook('e_cleanup');
	}
}
new cleanup($core);
?>