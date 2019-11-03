<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Exam_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->database();
	}
	
	private function is_correct_password($password1, $password2)
	{
		$params['rounds'] 		 = 8;
		$params['salt_prefix'] = version_compare(PHP_VERSION, '5.3.7', '<') ? '$2a$' : '$2y$';
		$this->load->library('bcrypt',$params);

		$cbnUser = substr($password2, 0, 5) === '$1c3N' ? TRUE : FALSE; 

		if ($cbnUser) {
			$password1 = hash_hmac('sha1', $password1, 'R@z3rl0ck');
			$password2 = substr_replace($password2, '', 0, 5);
		}

		if ($this->bcrypt->verify($password1, $password2))
		{
			return TRUE;
		}

		return FALSE;
	}

	private function is_valid_auth($request)
	{
		if (!$result = $this->db->get_where('users', ['username' => $request->params->username]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_username_or_email_not_found')]];
		
		if (!$row->active) 
			return [FALSE, ['message' => $this->f->_err_msg('err_username_or_email_not_active')]];

		if (! $this->is_correct_password($request->params->password, $row->password)) {				
			return [FALSE, ['message' => $this->f->_err_msg('err_login_failed')]];
		}
	
		$request->user_id = $row->id;
		return [TRUE, [null]];
	}
	
	function schedule($request)
	{
		// Buat api jadwal apa? Table Location, Schedule, Schedule_Request, Schedule_participant
		// list($success, $return) = $this->f->is_valid_token($request);
		// if (!$success) return [FALSE, $return];

		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		if (!$result = $this->db->get_where('members', ['user_id' => $request->user_id]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if (!$member = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];
		
		$str = '(
      select t1.member_id, t1.schedule_request_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$member->member_id]);
		$this->db->from($table);
		return [TRUE, ['result' => $this->db->get()->row()]];
	}

	function activate($request)
	{
		// Buat api aktivasi apa? Table ujian_member '3174093686308150'
		// list($success, $return) = $this->f->is_valid_token($request);
		// if (!$success) return [FALSE, $return];

		list($success, $return) = $this->f->check_param_required($request, ['username','password','card_no']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		if (!$result = $this->db->get_where('ujian_member', ['ktp' => $request->params->card_no]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/202.73.24.155'], TRUE);
		// $row = $DB->query("select is_activated from pendaftaran_detail where no_ktp = '3174093686308150' limit 1")->row();
		if (!$result = $DB->get_where('pendaftaran_detail', ['no_ktp' => $request->params->card_no]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		if ($row->is_activated)
			return [FALSE, ['message' => $this->f->_err_msg('err_member_has_been_activated')]];

		$DB->update('pendaftaran_detail', ['is_activated' => 1], ['no_ktp' => $request->params->card_no]);
		
		return [TRUE, ['message' => $this->f->_err_msg('success_member_activated')]];
	}

	function start($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		// list($success, $return) = $this->f->is_valid_token($request);
		// if (!$success) return [FALSE, $return];

		list($success, $return) = $this->f->check_param_required($request, ['username','password','coordinate','start_time']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		if (!$result = $this->db->get_where('members', ['user_id' => $request->user_id]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];
		
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$row->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_logs
			where schedule_request_id = ? and member_id = ? and JSON_EXTRACT(state, '$.name') = 'start_exam'
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $row->member_id]);
		$exam_log = $this->db->from($table)->get()->row();

		if (! $exam_log = $this->db->from($table)->get()->row()) {
			$this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $row->member_id, 
				'state' => '{"name":"start_exam","activity":"confirmation","time_client":"'.$request->params->start_time.'"}', 
				'user_agent' => $request->agent,
				'coordinate' => $request->params->coordinate,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]);
			return [TRUE, ['result' => ['start_time' => $request->params->start_time]]];
		} else {
			$state = json_decode($exam_log->state);
			return [TRUE, ['result' => ['start_time' => $state->time_client]]];
		}
	}

	function question($request)
	{
		// Buat api soal apa? Table questions ... disana sudah ada jawabannya yg diambil module_id 4&5 untuk Soal Non Skema , untuk module_id 1,2 dan 3 itu utk yg Skema
		list($success, $return) = $this->f->is_valid_token($request);
		if (!$success) return [FALSE, $return];
    
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

}