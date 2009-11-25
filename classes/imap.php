<?php defined('SYSPATH') or die('No direct script access.');
/**
 * IMAP client class
 *
 * This library is a wrapper around the Imap library functions included in php. This class in particular manages a
 * connection to the server (imap, pop, etc) and allows for the easy retrieval of stored messages. This is based on
 * Robert Hafner's library.
 *
 * @package		Imap
 * @author		Michael Lavers, Robert Hafner
 * @copyright	(c) 2009 Michael Lavers
 * @copyright	(c) 2009 Robert Hafner
 * @license		http://kohanaphp.com/license.html
 * @license		http://www.mozilla.org/MPL/
 */
class Imap
{
	/**
	 * When SSL isn't compiled into PHP we need to make some adjustments to prevent soul crushing annoyances.
	 *
	 * @var bool
	 */
	static $ssl_enable = TRUE;

	/**
	 * These are the flags that depend on ssl support being compiled into imap.
	 *
	 * @var array
	 */
	static $ssl_flags = array('ssl', 'validate-cert', 'novalidate-cert', 'tls', 'notls');

	/**
	 * This is used to prevent the class from putting up conflicting tags. Both directions- key to value, value to key-
	 * are checked, so if "novalidate-cert" is passed then "validate-cert" is removed, and vice-versa.
	 *
	 * @var array
	 */
	static $exclusive_flags = array('validate-cert' => 'novalidate-cert', 'tls' => 'notls');

	/**
	 * This is the domain or server path the class is connecting to.
	 *
	 * @var string
	 */
	protected $server_path;

	/**
	 * This is the name of the current mailbox the connection is using.
	 *
	 * @var string
	 */
	protected $mailbox;

	/**
	 * This is the username used to connect to the server.
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * This is the password used to connect to the server.
	 *
	 * @var string
	 */
	protected $password;

	/**
	 * This is an array of flags that modify how the class connects to the server. Examples include "ssl" to enforce a
	 * secure connection or "novalidate-cert" to allow for self-signed certificates.
	 *
	 * @link http://us.php.net/manual/en/function.imap-open.php
	 * @var array
	 */
	protected $flags = array();

	/**
	 * This is the port used to connect to the server
	 *
	 * @var int
	 */
	protected $port;

	/**
	 * This is the set of options, represented by a bitmask, to be passed to the server during connection.
	 *
	 * @var int
	 */
	protected $options = 0;

	/**
	 * This is the resource connection to the server. It is required by a number of imap based functions to specify how
	 * to connect.
	 *
	 * @var resource
	 */
	protected $imap_stream;

	/**
	 * This is the name of the service currently being used. Imap is the default, although pop3 and nntp are also
	 * options
	 *
	 * @var string
	 */
	protected $service = 'imap';

	/**
	 * This constructor takes the location and service thats trying to be connected to as its arguments.
	 *
	 * @param string $server_path
	 * @param NULL|int $port
	 * @param NULL|string $service
	 */
	public function __construct($server_path, $port = 143, $service = 'imap')
	{
		$this->server_path = $server_path;

		$this->port = $port;

		switch ($port)
		{
			case 143:
				$this->set_flag('novalidate-cert');
				break;

			case 993:
				$this->set_flag('ssl');
				break;
		}

		$this->service = $service;
	}

	/**
	 * This function sets the username and password used to connect to the server.
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function set_authentication($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * This function sets the mailbox to connect to.
	 *
	 * @param string $mailbox
	 */
	public function set_mailbox($mailbox = '')
	{
		$this->mailbox = $mailbox;
		if (isset($this->imap_stream))
		{
			$this->set_imap_stream();
		}
	}

	/**
	 * This function sets or removes flag specifying connection behavior. In many cases the flag is just a one word
	 * deal, so the value attribute is not required. However, if the value parameter is passed FALSE it will clear that
	 * flag.
	 *
	 * @param string $flag
	 * @param NULL|string|bool $value
	 */
	public function set_flag($flag, $value = NULL)
	{
		if(!self::$ssl_enable and in_array($flag, self::$ssl_flags))
			return;

		if (isset(self::$exclusive_flags[$flag]))
		{
			$kill = $flag;
		}
		elseif ($index = array_search($flag, self::$exclusive_flags))
		{
			$kill = $index;
		}

		if (isset($kill) and isset($this->flags[$kill]))
			unset($this->flags[$kill]);

		if (isset($value) and $value !== TRUE)
		{
			if ($value == FALSE)
			{
				unset($this->flags[$flag]);
			}
			else
			{
				$this->flags[] = $flag . '=' . $value;
			}
		}
		else
		{
			$this->flags[] = $flag;
		}
	}

	/**
	 * This funtion is used to set various options for connecting to the server.
	 *
	 * @param int $bitmask
	 */
	public function set_options($bitmask = 0)
	{
		if ( ! is_numeric($bitmask))
			throw new Imap_Exception();


		$this->options = $bitmask;
	}

	/**
	 * This function gets the current saved imap resource and returns it.
	 *
	 * @return resource
	 */
	public function get_imap_stream()
	{
		if ( ! isset($this->imap_stream))
			$this->set_imap_stream();

		return $this->imap_stream;
	}

	/**
	 * This function takes in all of the connection date (server, port, service, flags, mailbox) and creates the string
	 * thats passed to the imap_open function.
	 *
	 * @return string
	 */
	protected function get_server_string()
	{
		$mailbox_path = '{' . $this->server_path;

		if (isset($this->port))
			$mailbox_path .= ':' . $this->port;

		if ($this->service != 'imap')
			$mailbox_path .= '/' . $this->service;

		foreach($this->flags as $flag)
		{
			$mailbox_path .= '/' . $flag;
		}

		$mailbox_path .= '}';

		if (isset($this->mailbox))
			$mailbox_path .= $this->mailbox;

		return $mailbox_path;
	}

	/**
	 * This function creates or reopens an imap_stream when called.
	 *
	 */
	protected function set_imap_stream()
	{
		if (isset($this->imap_stream))
		{
			if ( ! imap_reopen($this->imap_stream, $this->mailbox, $this->options, 1))
				throw new Imap_Exception(imap_last_error());
		}
		else
		{
			$imap_stream = imap_open($this->get_server_string(), $this->username, $this->password, $this->options, 1);

			if ($imap_stream === FALSE)
				throw new Imap_Exception(imap_last_error());

			$this->imap_stream = $imap_stream;
		}
	}

	/**
	 * This returns the number of messages that the current mailbox contains.
	 *
	 * @return int
	 */
	public function num_messages()
	{
		return imap_num_msg($this->get_imap_stream());
	}

	/**
	 * This function returns an array of Imap_Message object for emails that fit the criteria passed. The criteria string
	 * should be formatted according to the imap search standard, which can be found on the php "imap_search" page or in
	 * section 6.4.4 of RFC 2060
	 *
	 * @link http://us.php.net/imap_search
	 * @link http://www.faqs.org/rfcs/rfc2060
	 * @param string $criteria
	 * @param NULL|int $limit
	 * @return array An array of Imap_Message objects
	 */
	public function search($criteria = 'ALL', $limit = NULL)
	{
		if ($results = imap_search($this->get_imap_stream(), $criteria, SE_UID))
		{
			if (isset($limit) and count($results) > $limit)
				$results = array_slice($results, 0, $limit);

			$stream = $this->get_imap_stream();
			$messages = array();

			foreach ($results as $message_id)
				$messages[] = new Imap_Message($message_id, $this);

			return $messages;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * This function returns the recently received emails as an array of Imap_Message objects.
	 *
	 * @param NULL|int $limit
	 * @return array An array of Imap_Message objects for emails that were recently received by the server.
	 */
	public function get_recent_messages($limit = NULL)
	{
		return $this->search('Recent', $limit);
	}

	/**
	 * Returns the emails in the current mailbox as an array of Imap_Message objects.
	 *
	 * @param NULL|int $limit
	 * @return array
	 */
	public function get_messages($limit = NULL)
	{
		$num_messages = $this->num_messages();

		if (isset($limit) and is_numeric($limit) and $limit < $num_messages)
			$num_messages = $limit;

		if ($num_messages < 1)
			return FALSE;

		$stream = $this->get_imap_stream();
		$messages = array();
		for ($i = 1; $i <= $num_messages; $i++)
		{
			$uid = imap_uid($stream, $i);
			$messages[] = new Imap_Message($uid, $this);
		}

		return $messages;
	}

	/**
	 * This function removes all of the messages flagged for deletion from the mailbox.
	 *
	 * @return bool
	 */
	public function expunge()
	{
		return imap_expunge($this->get_imap_stream());
	}
}

/**
 * Rather than make the Imap class dependant on anything in Mortar we're going to put this dependency check here where
 * it can easily be taken out or replaced in other libraries.
 */
//Imap::$ssl_enable = (bool) phpInfo::getExtensionProperty('imap', 'SSL Support');
