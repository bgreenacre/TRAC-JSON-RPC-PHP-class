<?php
/**
 * Trac JSON RPC class.
 *
 * @package	TRAC-RPC-JSON
 * @author	Brian Greenacre
 * @license	http://www.opensource.org/licenses/artistic-license-2.0.php
 * @version	1.01
 */

class Trac_RPC
{
	var $endpoint		= FALSE;
	var $user			= FALSE;
	var $password		= FALSE;
	var $multi_call		= FALSE;
	var $json_decode	= TRUE;
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
			'port',
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
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
	}
	
	function ticket_status()
	{
		$this->_add_payload('ticket.status.getAll');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
	}
	
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
	}
	
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
	}
	
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
	}
	
	function get_search_filters()
	{
		$this->_add_payload('search.getSearchFilters');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
	}
	
	function get_apiversion()
	{
		$this->_add_payload('system.getAPIVersion');
		
		if($this->multi_call === FALSE AND $this->exec_call() === TRUE) {
			return $this->get_response();
		} elseif($this->multi_call !== FALSE) {
			return $this->_curr_id;
		}
	}
	
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
			$this->_response = $this->_parse_result();
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
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
	
	function _get_payload_id()
	{
		++$this->_curr_id;
		return $this->_curr_id;
	}
	
	function _compile_payload()
	{
		if(is_array($this->_payload) === TRUE) {
			$this->_payload = json_encode(array_pop($this->_payload));
		}
		
		return TRUE;
	}
	
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
		
		curl_close($ch);
		
		if( $this->json_decode === TRUE ) {
			$this->_response = json_decode($response);
		} else {
			$this->_response = $response;
		}
		
		return TRUE;
	}
	
	function _parse_result($response=array())
	{
		if(empty($response)) {
			$response = $this->get_response();
		}
		
		foreach($response->result as $key => $resp) {
			if(isset($resp->result) === TRUE) {
				$response->result[$key] = $this->_parse_result($resp);
				continue;
			}
			
			if(is_array($resp) === TRUE OR is_object($resp) === TRUE) {
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
					}
					
					$response->result[$key] = $value;
				}
			} else {
				$response->result[$key] = $resp;
			}
		}
		
		return $response->result;
	}
	
	function set_user($user='')
	{
		$this->user = $user;
		return TRUE;
	}
	
	function set_password($pass='')
	{
		$this->password = $pass;
	}
	
	function set_endpoint($endpoint=FALSE)
	{
		$this->endpoint = $endpoint;
		return TRUE;
	}
	
	function set_multi_call($multi=FALSE)
	{
		$this->multi_call = ($multi !== FALSE) ? TRUE : FALSE;
		return $this->multi_call;
	}
	
	function set_json_decode($json=FALSE)
	{
		$this->json_decode = ($json !== FALSE) ? TRUE : FALSE;
		return TRUE;
	}
	
	function get_response()
	{
		return $this->_response;
	}
}
?>
