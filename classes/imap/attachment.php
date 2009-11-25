<?php defined('SYSPATH') or die('No direct script access.');
/**
 * IMAP client class
 *
 * This library is a wrapper around the Imap library functions included in php. This class wraps around an attachment
 * in a message, allowing developers to easily save or display attachments. This is based on Robert Hafner's library.
 *
 * @package		Imap
 * @subpackage	Imap_Attachment
 * @author		Michael Lavers, Robert Hafner
 * @copyright	(c) 2009 Michael Lavers
 * @copyright	(c) 2009 Robert Hafner
 * @license		http://kohanaphp.com/license.html
 * @license		http://www.mozilla.org/MPL/
 */
class Imap_Attachment
{
	/**
	 * This is the structure object for the piece of the message body that the attachment is located it.
	 *
	 * @var stdClass
	 */
	protected $structure;

	/**
	 * This is the unique identifier for the message this attachment belongs to.
	 *
	 * @var unknown_type
	 */
	protected $message_id;

	/**
	 * This is the ImapResource.
	 *
	 * @var resource
	 */
	protected $imap_stream;

	/**
	 * This is the id pointing to the section of the message body that contains the attachment.
	 *
	 * @var unknown_type
	 */
	protected $part_id;

	/**
	 * This is the attachments filename.
	 *
	 * @var unknown_type
	 */
	protected $filename;


	/**
	 * This is the size of the attachment.
	 *
	 * @var int
	 */
	protected $size;

	/**
	 * This stores the data of the attachment so it doesn't have to be retrieved from the server multiple times. It is
	 * only populated if the get_data() function is called and should not be directly used.
	 *
	 * @internal
	 * @var unknown_type
	 */
	protected $data;

	/**
	 * This function takes in an Imap_Message, the structure object for the particular piece of the message body that the
	 * attachment is located at, and the identifier for that body part. As a general rule you should not be creating
	 * instances of this yourself, but rather should get them from an Imap_Message class.
	 *
	 * @param Imap_Message $message
	 * @param stdClass $structure
	 * @param string $part_identifier
	 */
	public function __construct(Imap_Message $message, $structure, $part_identifier = NULL)
	{
		$this->message_id = $message->get_uid();
		$this->imap_stream = $message->get_imap_box()->get_imap_stream();
		$this->structure = $structure;

		if (isset($part_identifier))
			$this->part_id = $part_identifier;

		$parameters = Imap_Message::get_parameters_from_structure($structure);

		if (isset($parameters['filename']))
		{
			$this->filename = $parameters['filename'];
		}
		elseif (isset($parameters['name']))
		{
			$this->filename = $parameters['name'];
		}

		$this->size = $structure->bytes;

		$this->mime_type = Imap_Message::type_id_to_string($structure->type);

		if (isset($structure->subtype))
			$this->mime_type .= '/' . strtolower($structure->subtype);

		$this->encoding = $structure->encoding;
	}

	/**
	 * This function returns the data of the attachment. Combined with getmime_type() it can be used to directly output
	 * data to a browser.
	 *
	 * @return binary
	 */
	public function get_data()
	{
		if ( ! isset($this->data))
		{
			$message_body = isset($this->part_id) ?
				imap_fetchbody($this->imap_stream, $this->message_id, $this->part_id, FT_UID)
				: imap_body($this->imap_stream, $this->message_id, FT_UID);

			$message_body = Imap_Message::decode($message_body, $this->encoding);
			$this->data = $message_body;
		}
		
		return $this->data;
	}

	/**
	 * This returns the filename of the attachment, or FALSE if one isn't given.
	 *
	 * @return string
	 */
	public function get_file_name()
	{
		return (isset($this->filename)) ? $this->filename : FALSE;
	}

	/**
	 * This function returns the mime_type of the attachment.
	 *
	 * @return string
	 */
	public function getmime_type()
	{
		return $this->mime_type;
	}

	/**
	 * This returns the size of the attachment.
	 *
	 * @return int
	 */
	public function get_size()
	{
		return $this->size;
	}

	/**
	 * This function saves the attachment to the passed directory, keeping the original name of the file.
	 *
	 * @param string $path
	 */
	public function save_to_directory($path)
	{
		$path = rtrim($path, '/') . '/';

		if (is_dir($path))
			return $this->save_as($path . $this->get_file_name());

		return FALSE;
	}

	/**
	 * This function saves the attachment to the exact specified location.
	 *
	 * @param path $path
	 */
	public function save_as($path)
	{
		$dirname = dirname($path);
		if (file_exists($path))
		{
			if ( ! is_writable($path))
				return FALSE;
		}
		elseif ( ! is_dir($dirname) or ! is_writable($dirname))
		{
			return FALSE;
		}

		if (($file_pointer = fopen($path, 'w')) == FALSE)
			return FALSE;

		$results = fwrite($file_pointer, $this->get_data());
		fclose($file_pointer);
		return is_numeric($results);
	}
}
