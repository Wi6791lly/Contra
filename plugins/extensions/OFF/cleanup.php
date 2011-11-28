<?php
class Cleanup extends extension {
	/*
	*	Cleanup module.
	*	Made in Contra v5.4
	*	Created by lwln
	*	
	*	This is a simple module for administrators of chatrooms to help them keep things organized.
	*	The code is very sloppy, and needs work, but it does what needs to be done.
	*	Your bot needs full administrator access to the room in order for this module to work correctly. 
	*	Otherwise, you shouldn't be running this module.
	*/

	public $name = 'Cleanup';
	public $version = 1;
	public $about = 'Cleans up banned users from a room.';
	public $status = true;
	public $author = 'lwln';
	public $type = EXT_SYSTEM;
	
	protected $room;
	protected $person;
	protected $cleaned;
	
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
		$this->cleaned = $count;
		$this->unhook('e_cleanup', 'recv_admin_show');
		$this->hook('e_post', 'recv_admin_show');
		$this->dAmn->admin($this->room, 'show users');
	}
	
	function e_post() {
		$users = $GLOBALS['crap'];
		$list = explode(" ", $users);
		$count = preg_match_all("/(\!)([\w'-])*/im", $users, $users);
		$this->dAmn->say($this->room, '<abbr title="'.$this->person.'"></abbr>Cleaned #'.substr($this->room, 5).' <sub>['.$this->cleaned.' users found, '.($this->cleaned - $count).' removed]</sub>');
		$this->unhook('e_post', 'recv_admin_show');
	}
}
new Cleanup($core);
?>