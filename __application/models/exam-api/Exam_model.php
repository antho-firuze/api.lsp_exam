<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Exam_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->database();
	}
	
	function schedule($request)
	{
		// Buat api jadwal apa? Table Location, Schedule, Schedule_Request, Schedule_participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];
		// "SELECT id,category_id,NAME,province_id,city_id,subdistrict_id,DATE,pre,BEGIN,duration FROM schedules WHERE id = '".$id."'"
	}

	function activate($request)
	{
		// Buat api aktivasi apa? Table ujian_member
		$DB = $this->load->database(DB_CONN['lspdev.rynest-technology.com:8080'], TRUE);
		$rows = $DB->query("select * from pendaftaran_detail where no_ktp = '3174093686308150'")->result();
		// $rows = $DB->get_where('pendaftaran_detail', ['no_ktp' => '3174093686308150'])->result();
		print_r($rows);
		exit();
		list($success, $return) = $this->f->check_param_required($request, ['username','password','card_no']);
		if (!$success) return [FALSE, $return];

		$str = '(
      select partner_id, first_name, last_name, phone, fax, email, sex  
			from c_partner
      where client_id = ? and partner_id = ? 
		) g0';
		$table = $this->f->compile_qry($str, [$request->client_id, $request->partner_id]);
		$this->db->from($table);
		return $this->f->get_result_($request);
	}

	function start($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];
	}

	function question($request)
	{
		// Buat api soal apa? Table questions ... disana sudah ada jawabannya yg diambil module_id 4&5 untuk Soal Non Skema , untuk module_id 1,2 dan 3 itu utk yg Skema
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];
		// "SELECT id, sts, module_id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, option_ganda,answer_key,score FROM questions WHERE id = '".$id."' AND module_id IN(4,5)"
		$str = '(
      select partner_id, first_name, last_name, phone, fax, email, sex  
			from c_partner
      where client_id = ? and partner_id = ? 
		) g0';
		$table = $this->f->compile_qry($str, [$request->client_id, $request->partner_id]);
		$this->db->from($table);
		return $this->f->get_result_($request);
	}

	function add_santri($request)
	{
		list($success, $return) = $this->f->is_valid_token($request);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->f->check_param_required($request, ['nis']);
		if (!$success) return [FALSE, $return];

		if (isset($request->params->fields) && !empty($request->params->fields))
			$this->db->select($request->params->fields);
		else 
			$this->db->select('partner_id, reg_no, TRIM(CONCAT(first_name, " ", IFNULL(last_name,""))) as full_name');

		if (!$result = $this->db->get_where('c_partner', ['client_id' => $request->client_id, 'reg_no' => $request->params->nis]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_nis_not_found')]];
		
		if (!$result = $this->db->get_where('a_login_santri', ['client_id' => $request->client_id, 'login_id' => $request->login_id, 'partner_id' => $row->partner_id]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if ($row2 = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_nis_duplicate')]];

		if (!$result = $this->db->insert('a_login_santri', ['client_id' => $request->client_id, 'login_id' => $request->login_id, 'partner_id' => $row->partner_id]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		return [TRUE, ['message' => $this->f->lang('sucess_adding_santri'), 'result' => (object)['nis' => $row->reg_no, 'full_name' => $row->full_name]]];
	}

}