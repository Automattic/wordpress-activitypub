<?php

namespace Activitypub;

class Notification {

	public $type;
	public $actor;
	public $object;
	public $target;

	public function __construct( $type, $actor, $object, $target ) {
		$this->type = $type;
		$this->actor = $actor;
		$this->object = $object;
		$this->target = $target;
	}

	public function send() {
		do_action( 'activitypub_notification', $this );
	}
}
