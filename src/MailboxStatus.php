<?php namespace WeAreNotMachines\MailboxW;

use \ReflectionClass;

class MailboxStatus {

	public $state="initialized";
	public $size = 0;
	public $recentSize = 0;
	public $lastUpdated = null;

	public function __construct($withData=array()) {
		$this->init($withData);
	}

	public function init($withData=array()) {
		$r = new ReflectionClass($this);
		foreach (array_keys($withData) AS $prop) {
			if ($r->hasProperty($prop)) {
				$this->{$prop} = $withData[$prop];
			}
		}
		return $this;
	}

	public function __toString() {
		return "State: ".$this->state.", Size: ".$this->size.", Recent: ".$this->recentSize.", Last updated: ".$this->lastUpdated->format("d/m/Y g:ia");
	}

}