<?php defined('SYSPATH') or die('No direct script access.');
/**
 * IMAP client class
 *
 * This is a specific exception for the Imap classes. It extends the CoreWarning class- if you want to use this library
 * outside of Mortar just have it extend your own exceptions, or the main Exception class itself. This is based on
 * Robert Hafner's library.
 *
 * @package		Imap
 * @subpackage	Imap_Exception
 * @author		Michael Lavers, Robert Hafner
 * @copyright	(c) 2009 Michael Lavers
 * @copyright	(c) 2009 Robert Hafner
 * @license		http://kohanaphp.com/license.html
 * @license		http://www.mozilla.org/MPL/
 */
class Imap_Exception extends Kohana_Exception {}
