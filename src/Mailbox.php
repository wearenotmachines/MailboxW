<?php namespace WeAreNotMachines\MailboxW;

class Mailbox {

	/**
	 * The datastream opened against the imap mailbox
	 * @var Resource: Stream
	 */
	private $stream;
	/**
	 * An array of imap errors
	 * @var array
	 */
	private $errors = [];
	/**
	 * An array of configuration options for setting up and co-ordinating the imap connection
	 * @var [type]
	 */
	private $config = [
		"path" => "",
		"username" => "",
		"password" => "",
		"server" => "",
		"port" => "",
		"protocol"=> "",
		"mailbox" => "INBOX",
		"flags" => [],
		"readonly"=>true
	];


	/**
	 * The mailbox index - a collection of message headers from which to drill down into messages
	 * @var array
	 */
	private $index = [];

	private $status;

	

	/**
	 * Sets up a new mailbox object passing in the optional configuration
	 * @param array $config configuration options to merge
	 */
	public function __construct($config=[]) {
		$this->configure($config);
		$this->status = new MailboxStatus();
	}

	/**
	 * Sets a single or block of configuration options.  Pass a single key / value pair to set individually or an array to set en masse
	 * @param  string|array $key   The set / option to pass
	 * @param  * $value the value for a single option
	 * @return self        for chaining
	 */
	public function configure($key, $value=null) {
		if (is_array($key)) {
			$this->config = array_merge($this->config, $key);
		} else {
			$this->config[$key] = $value;
		}
		asort($this->config);
		return $this;
	}

	/**
	 * Constructs a connection string for the configuration options set
	 * @return string an imap connection path string
	 */
	public function buildMailboxPath() {
		if (!empty($this->config['path'])) {
			return "{".trim($this->config['path'])."}";
		} else {
			if (empty($this->config['server'])) throw new \RuntimeException("Cannot build mailbox path: Server address is not set in config");
			$this->state("ready");
			return 	"{".
					(!empty($this->config['protocol']) ? $this->config['protocol']."://" : $this->config['protocol']).
					$this->config['server'].
					(!empty($this->config['port']) ? ":".$this->config['port'] : "").
					(!empty($this->config['flags']) ? "/".implode("/", array_map(function($i) { return trim($i, "/"); }, $this->config['flags'])) : "").
					(!empty($this->config['readonly']) ? "/readonly" : "").
					"}".
					$this->config['mailbox'];
		}
	}

	public function state($newState=null) {
		if (empty($newState)) return $this->status->state;
		$this->status->state = $newState;
	}

	/**
	 * Accessor for the config array
	 * @param  string $key get a particular value
	 * @return *      with no key passed returns the entire config array, with a key passed returns the set value for that key
	 */
	public function getConfig($key=null) {
		return empty($key) ? $this->config : $this->config[$key];
	}

	/**
	 * attempts a connection using imap_open to the mailbox specified in the config options. Changes the object state to connected / connection error depending on success
	 * @throws  RuntimeException when an error is returned from imap_open or when the username or password for the mailbox are not set
	 * @return self for chaining
	 */
	public function connect($reconnect =false) {
		if (empty($this->config['username'])) throw new \RuntimeException("Cannot connect to mailbox - a username is not specified");
		if (empty($this->config['password'])) throw new \RuntimeException("Cannot connect to mailbox - a password is not specified");
		if (!$reconnect) {
			$this->stream = imap_open($this->buildMailboxPath(), $this->config['username'], $this->config['password']);
		} else {
			if (!is_resource($this->stream)) {
				return $this->connect(false);
			} else {
				if (!imap_reopen($this->stream, $this->config['mailbox'])) {
					$this->stream = null;
				} 
			}
		}	
		if ($this->stream) {
			$this->state("connected");
			return $this;
		} else {
			$this->state("connection_error");
			$this->errors = imap_errors();
			throw new \RuntimeException("An error has occurred whilst ".($reconnect ? "re-" : "")."connecting to the mailbox"); 
		}
	}

	/**
	 * Accessor for the errors array
	 * @return array an array of error messages
	 */
	public function getErrors() {
		return $this->errors;
	}

	public function refresh() {
		if (!is_resource($this->stream) || !imap_ping($this->stream)) {
			return $this->connect(true);
		} else {
			$this->updateSummary();
		}
		return $this;
	}

	public function getMessageListing($offset=1, $limit=null) {
		$this->refresh();
		$sequence = $offset.":".(is_null($limit) ?  $offset + ($this->status->size-$offset): ($offset+($limit-1) < $this->status->size ? $offset+($limit-1) : $this->status->size));
		$this->index = imap_fetch_overview($this->stream, $sequence);
		return $this->index;
	}

	public function updateSummary() {
		if (!imap_ping($this->stream)) {
			$this->connect(true);
		}
		$summary = imap_check($this->stream);
		if (!$summary) {
			$this->errors = imap_errors($this->stream);
			throw new \RuntimeException("An error occurred whilst updating the mailbox summary");
		} else {
			$this->status->init([
				"size" => $summary->Nmsgs,
				"recentSize" => $summary->Recent,
				"currentMailbox" => $summary->Mailbox,
				"lastUpdated" => new \DateTime($summary->Date)
			]);
		}
		return $this;
	}

	public function info() {
		return $this->status;
	}


}