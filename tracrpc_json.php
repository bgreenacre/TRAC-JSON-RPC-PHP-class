<?php
/**
 * Trac JSON RPC class.
 *
 * Example usage:
 * <code>
 * include 'tracrpc_json.php';
 *
 * $obj = new Trac_RPC('http://example.org/login/xmlrpc', array('user' => 'username', 'password' => 'password'));
 * 
 * //Example single call
 * $result = $obj->get_ticket('32');
 * if($result === FALSE) {
 *   die('ERROR: '.$obj->get_error());
 * } else {
 *   var_dump($result);
 * }
 *
 * //Example multi call
 * $obj->set_multi_call(TRUE);
 * $ticket = $obj->get_ticket('32');
 * $attachments = $obj->ticket_attachments('list', '32');
 * $obj->exec_call();
 * $ticket = $obj->get_response($ticket);
 * $attachments = $obj->get_resonse($attachments);
 * var_dump($ticket, $attachments);
 * </code>
 *
 * @package	TRAC-RPC-JSON
 * @author	Brian Greenacre
 * @license	http://www.opensource.org/licenses/artistic-license-2.0.php
 * @version	1.01
 */

if(! function_exists('json_encode') AND class_exists('Services_JSON') === TRUE) {
	function json_encode($php_variable)
	{
		$json = new Services_JSON(SERVICES_JSON_SUPPRESS_ERRORS);
		return $json->encode($php_variable);
	}
}

if(! function_exists('json_decode') AND class_exists('Services_JSON') === TRUE) {
	function json_decode($json)
	{
		$json = new Services_JSON(SERVICES_JSON_SUPPRESS_ERRORS);
		return $json->decode($json);
	}
}

class Trac_RPC
{
	var $endpoint		= FALSE;
	var $user			= FALSE;
	var $password		= FALSE;
	var $multi_call		= FALSE;
	var $json_decode	= TRUE;
	var $error			= '';
	var $_payload		= FALSE;
	var $_response		= FALSE;
	var $_curr_id		= 0;
	
	/**
	 * Construtor for Trac_RPC
	 *
	 * @access	public
	 * @param	string	The complete url. Example: https://example.org/login/xmlrpc
	 * @param	array	Name/Value paired array to set properties.
	 * @return	void
	 */
	function Trac_RPC($endpoint='', $params=array())
	{
		$properties_set = array(
			'user',
			'password',
			'multi_call',
			'json_encode'
			);
		
		if(is_array($params) === TRUE) {
			foreach($params as $property => $value) {
				if(! in_array($property, $properties_set)) {
					continue;
				}
				
				$this->{$property} = $value;
			}
		}
		
		$this->endpoint = $endpoint;
	}
	
	/**
	 * Get the recent changed tickets.
	 *
	 * @access	public
	 * @param	int		A timestamp integer. Defaults to current day.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_recent_changed_tickets($date=0)
	{
		if($date == FALSE) {
			$date = array('datetime', date("o-m-d\T00:00:00"));
		} elseif(is_numeric($date) === TRUE) {
			$date = array('datetime', date("omd\TH:i:s+00:00", $date));
		}
		
		$this->_add_payload('ticket.getRecentChanges', array(array('__jsonclass__' => $date)));
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif( $this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get a ticket.
	 *
	 * @access	public
	 * @param	string	The id of the ticket.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_ticket($id='')
	{
		if($id == '') {
			return FALSE;
		}
		
		$this->_add_payload('ticket.get', $id);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif( $this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get a all ticket fields.
	 *
	 * @access	public
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_ticket_fields()
	{
		$this->_add_payload('ticket.getTicketFields');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif( $this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get the recent changed tickets.
	 *
	 * @access	public
	 * @param	string	The id of the ticket.
	 * @param	int		When in the changelog.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_ticket_changelog($id='', $when=0)
	{
		if($id == '') {
			return FALSE;
		}
		
		$this->_add_payload('ticket.changeLog', array($id, $when));
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get a ticket actions.
	 *
	 * @access	public
	 * @param	string	The id of the ticket.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_ticket_actions($id='')
	{
		if($id == '') {
			return FALSE;
		}
		
		$this->_add_payload('ticket.getActions', $id);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get a ticket.
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket attachments.
	 *					Possible values list, get, delete, or create.
	 *					Default list.
	 * @param	string	The id of the ticket.
	 * @param	string	Filenamepath of the file to add to the ticket.
	 * @param	string	Description of the attachment.
	 * @param	bool	TRUE will replace the attachment if it exists FALSE will not replace it.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_attachments($action='list', $id='', $file='', $desc='', $replace=TRUE)
	{
		if($id == '') {
			return FALSE;
		}
		
		$method = '';
		$params = array($id);
		
		switch($action)
		{
			case 'list':
			default:
				$method = 'ticket.listAttachments';
				break;
			case 'get':
				if($file == '') {
					return FALSE;
				}
				
				$method = 'ticket.getAttachment';
				$params[] = $file;
				break;
			case 'delete':
				if($file == '') {
					return FALSE;
				}
				
				$method = 'ticket.deleteAttachment';
				$params[] = $file;
				break;
			case 'create':
				if(! @is_file($file)) {
					return FALSE;
				}
				
				$contents = file_get_contents($file, FILE_BINARY);
				
				if($contents !== FALSE) {
					$contents = array(
						'__jsonclass__' => array(
							'binary', base64_encode($contents)
							)
						);
				}
				
				$method = 'ticket.putAttachment';
				$params[] = basename($file);
				$params[] = $desc;
				$params[] = $contents;
				$params[] = $replace;
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Create, delete, or update a ticket.
	 *
	 * @access	public
	 * @param	string	What action to perform for a ticket.
	 *					Possible values create, update, or delete.
	 *					Default create.
	 * @param	string	The id of the ticket.
	 * @param	array	Name/value paired array of data for the ticket.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_update($action='create', $id='', $data=array())
	{
		$method = '';
		$params = array();
		
		switch($action)
		{
			case 'create':
			default:
				$method = 'ticket.create';
				$params	= array(
					0	=> (isset($data['summary']) === TRUE) ? $data['summary'] : '',
					1	=> (isset($data['desc']) === TRUE) ? $data['desc'] : '',
					2	=> (isset($data['attr']) === TRUE) ? $data['attr'] : array(),
					3	=> (isset($data['notify']) === TRUE) ? $data['notify'] : FALSE
					);
				break;
			case 'update':
				$method = 'ticket.update';
				$params	= array(
					0	=> $id,
					1	=> (isset($data['comment']) === TRUE) ? $data['comment'] : '',
					2	=> (isset($data['attr']) === TRUE) ? $data['attr'] : array(),
					3	=> (isset($data['notify']) === TRUE) ? $data['notify'] : FALSE
					);
				break;
			case 'delete':
				if($id == '') {
					return FALSE;
				}
				
				$method = 'ticket.delete';
				$params = $id;
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Search for tickets.
	 *
	 * @access	public
	 * @param	string	Query string to search.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_search($query='')
	{
		if($query == '') {
			return FALSE;
		}
		
		$this->_add_payload('ticket.query', $query);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket components, get a specific component, create a component, edit an existing component, or delete a component
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket component.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the component.
	 * @param	array	Name/value paired array of data for the ticket component.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_component($action='get_all', $name='', $attr=array())
	{
		$method = '';
		$params = '';
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.component.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.component.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == FALSE) {
					return FALSE;
				}
				
				$method = 'ticket.component.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR ! is_array($attr)) {
					return FALSE;
				}
				
				$method = 'ticket.component.'.$action;
				$params = array($name, $attrs);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket milestones, get a specific milestone, create a milestone, edit an existing milestone, or delete a milestone
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket milestone.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the milestone.
	 * @param	array	Name/value paired array of data for the ticket milestone.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_milestone($action='get_all', $name='', $attr=array())
	{
		$method = '';
		$params = '';
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.milestone.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.milestone.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.milestone.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR ! is_array($attr)) {
					return FALSE;
				}
				
				$method = 'ticket.milestone.'.$action;
				$params = array($name, $attr);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket prioritys, get a specific priority, create a priority, edit an existing priority, or delete a priority
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket priority.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the priority.
	 * @param	string	Priority name.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_priority($action='get_all', $name='', $attr='')
	{
		$method = '';
		$params = '';
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.priority.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.priority.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.priority.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR $attr == '') {
					return FALSE;
				}
				
				$method = 'ticket.priority.'.$action;
				$params = array($name, $attr);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket resolutions, get a specific resolution, create a resolution, edit an existing resolution, or delete a resolution
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket resolution.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the resolution.
	 * @param	string	Resolution name.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_resolution($action='get_all', $name='', $attr='')
	{
		$method = '';
		$params = '';
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.resolution.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.resolution.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.resolution.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR $attr == '') {
					return FALSE;
				}
				
				$method = 'ticket.resolution.'.$action;
				$params = array($name, $attr);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket severitys, get a specific severity, create a severity, edit an existing severity, or delete a severity
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket severity.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the severity.
	 * @param	string	Severity name.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_severity($action='get_all', $name='', $attr='')
	{
		$method = '';
		$params = '';
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.severity.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.severity.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.severity.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR $attr == '') {
					return FALSE;
				}
				
				$method = 'ticket.severity.'.$action;
				$params = array($name, $attr);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket types, get a specific type, create a type, edit an existing type, or delete a type
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket type.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the type.
	 * @param	string	Type name.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_type($action='get_all', $name='', $attr='')
	{
		$method = '';
		$params = '';
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.type.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.type.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.type.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR $attr == '') {
					return FALSE;
				}
				
				$method = 'ticket.type.'.$action;
				$params = array($name, $attr);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all ticket versions, get a specific version, create a version, edit an existing version, or delete a version
	 *
	 * @access	public
	 * @param	string	What action to perform for ticket version.
	 *					Possible values get_all, get, delete, update, or create.
	 *					Default get_all.
	 * @param	string	The name of the version.
	 * @param	array	Name/value paired array of data for the ticket version.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_version($action='get_all', $name='', $attr=array())
	{
		$method = '';
		$params = array();
		
		switch($action)
		{
			case 'get_all':
			default:
				$method = 'ticket.version.getAll';
				break;
			case 'get':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.version.get';
				$params	= array(0 => $name);
				
				break;
			case 'delete':
				if($name == '') {
					return FALSE;
				}
				
				$method = 'ticket.version.delete';
				break;
			case 'update':
			case 'create':
				if($name == '' OR ! is_array($attr)) {
					return FALSE;
				}
				
				$this->set_method('ticket.version.'.$action);
				break;
		}
		
		$this->_add_payload($method, $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all status.
	 *
	 * @access	public
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function ticket_status()
	{
		$this->_add_payload('ticket.status.getAll');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Perform a global search in TRAC.
	 *
	 * @access	public
	 * @param	string	Query string to search for,
	 * @param	array	Search filters to use.
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function trac_search($query='', $filter=array())
	{
		$params = array();
		
		if($query != '') {
			$params[0] = $query;
		} else {
			return FALSE;
		}
		
		if(is_array($filter) === TRUE AND ! empty($filter)) {
			$params[1] = $filter;
		}
		
		$this->_add_payload('search.getSearchFilters', $params);
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all search filter
	 *
	 * @access	public
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_search_filters()
	{
		$this->_add_payload('search.getSearchFilters');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Get all the API version from TRAC.
	 *
	 * @access	public
	 * @return	mixed	The result of the requet or the integer id on a muli_call. FALSE on error.
	 */
	function get_apiversion()
	{
		$this->_add_payload('system.getAPIVersion');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
		
		return FALSE;
	}
	
	/**
	 * Execute a RPC request to TRAC.
	 *
	 * @access	public
	 * @return	bool	TRUE on a successful request. FALSE on error.
	 */
	function exec_call()
	{
		if(empty($this->_payload)) {
			return FALSE;
		}
		
		if($this->multi_call !== FALSE) {
			$this->_add_payload('system.multicall');
		}
		
		$this->_compile_payload();
		
		if($this->_curl_action() === TRUE) {
			$this->_parse_result();
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Add a method to call with arguments to the payload
	 *
	 * @access	private
	 * @param	string	The method name to call.
	 * @param	array	Arguments to pass with the call.
	 * @param	string	The id to set to the call.
	 * @return	bool	Always TRUE.
	 */
	function _add_payload($method='', $args=array(), $id='')
	{
		if($method == '') {
			return FALSE;
		}
		
		if(! is_array($args)) {
			$args = array($args);
		}
		
		if(! is_array($this->_payload)) {
			$this->_payload = array();
		}
		
		if(empty($id)) {
			$id = $this->_get_payload_id();
		}
		
		if($method == 'system.multicall') {
			$payload = array(
				'method'	=> $method,
				'params'	=> $this->_payload,
				'id'		=> $id
				);
			
			$this->_payload = array(0 => $payload);
		} else {
			$this->_payload[] = array(
				'method'	=> $method,
				'params'	=> $args,
				'id'		=> $id
				);
		}
		
		return TRUE;
	}
	
	/**
	 * Increment the current payload id by 1 and returns it.
	 *
	 * @access	private
	 * @return	int		The current id.
	 */
	function _get_payload_id()
	{
		++$this->_curr_id;
		return $this->_curr_id;
	}
	
	/**
	 * Serialixe the _payload into a json string.
	 *
	 * @access	private
	 * @return	bool	Always TRUE.
	 */
	function _compile_payload()
	{
		if(is_array($this->_payload) === TRUE) {
			$this->_payload = json_encode(array_pop($this->_payload));
		}
		
		return TRUE;
	}
	
	/**
	 * Make the request using CURL.
	 *
	 * @access	private
	 * @return	bool	TRUE is a successful CURL request. FALSE CURL isn't installed or the url or payload is empty.
	 */
	function _curl_action()
	{
		if(! function_exists('curl_init') OR $this->endpoint == '' OR empty($this->_payload)) {
			return FALSE;
		}
		
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->endpoint);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		if(! ini_get('safe_mode')) {
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_payload);
		
		if($this->user != '') {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC|CURLAUTH_DIGEST);
			curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
		}
		
		$response = trim(curl_exec($ch));
		
		if(curl_errno($ch) > 0) {
			$this->error = curl_error($ch);
		}
		
		curl_close($ch);
		
		if( $this->json_decode === TRUE ) {
			$this->_response = json_decode($response);
		} else {
			$this->_response = $response;
		}
		
		return TRUE;
	}
	
	/**
	 * Loop through the results and do any parsing needed.
	 *
	 * JSON RPC doesn't have datatypes so special objects are made for datetime
	 * and base64 value. This method finds those objects and converts them into
	 * proper php values. For datetime types, the value is converted into a UNIX
	 * timestamp. Base64 decodes the value.
	 *
	 * @access	private
	 * @return	bool	TRUE on a non-empty result and FALSE if it is empty.
	 */
	function _parse_result($response=array())
	{
		if(empty($response)) {
			$response = $this->get_response();
			$this->_response = array();
		}
		
		if(! is_object($response) AND ! is_array($response)) {
			return FALSE;
		}
		
		foreach($response->result as $key => $resp) {
			if(isset($resp->result) === TRUE) {
				$this->_parse_result($resp);
				continue;
			}
			
			if(is_array($resp) === TRUE OR is_object($resp) === TRUE) {
				$values = array();
				foreach($resp as $r_key => $value) {
					if($r_key === '__jsonclass__') {
						switch($value[0])
						{
							case 'datetime':
								$value = strtotime($value[1]);
								break;
							case 'binary':
								$value = base64_decode($value[1]);
								break;
						}
						
						$values = $value;
					} else {
						$values[$r_key] = $value;
					}
				}
				
				$response->result[$key] = $values;
			} else {
				$response->result[$key] = $resp;
			}
		}
		
		$id = 0;
		if(isset($response->id) === TRUE AND $response->id != NULL) {
			$id = $response->id;
		}
		
		$this->_response[$id] = $response->result;
		$this->error[$id] = FALSE;
		
		if(isset($response->error) === TRUE) {
			$this->error[$id] = $response->error;
		}
		
		return TRUE;
	}
	
	/**
	 * Set the property user.
	 *
	 * @access	public
	 * @return	bool	Always TRUE.
	 */
	function set_user($user='')
	{
		$this->user = $user;
		return TRUE;
	}
	
	/**
	 * Set the property password.
	 *
	 * @access	public
	 * @return	bool	Always TRUE.
	 */
	function set_password($pass='')
	{
		$this->password = $pass;
		return TRUE;
	}
	
	/**
	 * Set the property endpoint.
	 *
	 * @access	public
	 * @return	bool	Always TRUE.
	 */
	function set_endpoint($endpoint=FALSE)
	{
		$this->endpoint = $endpoint;
		return TRUE;
	}
	
	/**
	 * Set the property multi_call.
	 *
	 * @access	public
	 * @return	bool	Always TRUE.
	 */
	function set_multi_call($multi=FALSE)
	{
		$this->multi_call = ($multi !== FALSE) ? TRUE : FALSE;
		return $this->multi_call;
	}
	
	/**
	 * Set the property json_decode.
	 *
	 * @access	public
	 * @return	bool	Always TRUE.
	 */
	function set_json_decode($json=FALSE)
	{
		$this->json_decode = ($json !== FALSE) ? TRUE : FALSE;
		return TRUE;
	}
	
	/**
	 * Get the response from the request.
	 *
	 * @access	public
	 * @param	int		The id of the call.
	 * @return	object	stdClass
	 */
	function get_response($id=FALSE)
	{
		if(is_object($this->_response) === TRUE) {
			return $this->_response;
		} elseif(is_array($this->_response) === TRUE) {
			if($id !== FALSE) {
				if(! is_array($id)) {
					return $this->_response[$id];
				} else {
					$ret = array();
					
					foreach($id as $key) {
						if(! isset($this->_response[$key])) {
							continue;
						}
						
						$ret[$key] = $this->_response[$key];
					}
					
					return $ret;
				}
			} else {
				return current($this->_response);
			}
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Get any error message set for the request.
	 *
	 * @access	public
	 * @param	bool	The id of the call made. Used for multi_calls.
	 * @return	string	The error message
	 * 
	 */
	function get_error_message($id=FALSE)
	{
		if($id !== FALSE) {
			if(! is_array($id) AND isset($this->error[$id]) === TRUE) {
				return $this->error[$id];
			} elseif(is_array($id) === TRUE) {
				$ret = array();
				
				foreach($id as $eid) {
					if(! isset($this->error[$eid])) {
						continue;
					}
					
					$ret[$eid] = $this->error[$eid];
				}
				
				return $ret;
			}
		}
		
		return $this->error;
	}
}
?>
