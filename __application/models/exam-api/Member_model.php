<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Member_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		// $this->load->database();
	}
	
	function is_activated($request)
	{
    // {"phone":"89530769307","fullname":"Antonio Chan","card_no":"3174093686308150"}
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
    
		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/202.73.24.155'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['nama' => $request->params->fullname,'no_ktp' => $request->params->card_no,'phone' => $request->params->phone]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		if ($row->is_activated)
			return [TRUE, ['message' => $this->f->_err_msg('err_member_has_been_activated')]];

		return [FALSE, ['message' => $this->f->_err_msg('err_member_not_activated_yet')]];
	}

	function activate($request)
	{
    // {"phone":"89530769307","fullname":"Antonio Chan","card_no":"3174093686308150"}
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
    
		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/202.73.24.155'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['nama' => $request->params->fullname,'no_ktp' => $request->params->card_no,'phone' => $request->params->phone]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		$DB->update('pendaftaran_detail', ['is_activated' => 1], ['nama' => $request->params->fullname,'no_ktp' => $request->params->card_no,'phone' => $request->params->phone]);
		
		return [TRUE, ['message' => $this->f->_err_msg('success_member_activated')]];
	}

}