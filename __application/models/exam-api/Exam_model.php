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
		// GET USERS
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
	
		// GET MEMBER
		if (!$result = $this->db->get_where('members', ['user_id' => $request->user_id]))
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		$request->member_id = $row->member_id;

		return [TRUE, [null]];
	}
	
	function login($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password','date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		$str = "(
      select t1.member_id, t1.schedule_request_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where t1.member_id = ? and t3.date = ? and t3.pre <= ? limit 1
		) g0";
		$table = $this->f->compile_qry($str, [$request->member_id, $request->params->date_client, $request->params->time_client]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => $this->f->_err_msg('err_not_in_schedule')]];

		return [TRUE, ['message' => $this->f->_err_msg('success_member_in_schedule')]];
	}

	function schedule($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		$str = '(
      select t1.member_id, t1.schedule_request_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member_id]);
		$this->db->from($table);
		return [TRUE, ['result' => $this->db->get()->row()]];
	}

	function start($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password','coordinate','start_time']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) {
			$result = $this->db->insert('exam_results', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'category_id' => $schedule->category_id, 
				'member_id' => $request->member_id, 
				'begin' => strtotime(date('Y-m-d H:i:s')),
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member_id, 
				'state' => '{"name":"start_exam","activity":"confirmation","time_client":"'.$request->params->start_time.'"}', 
				'user_agent' => $request->agent,
				'coordinate' => $request->params->coordinate,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			return [TRUE, ['result' => ['start_time' => $request->params->start_time]]];
		} else {
			return [TRUE, ['result' => ['start_time' => (new DateTime("@$exam_results->begin"))->format('Y-m-d H:i:s')]]];
		} 

	}

	function answer($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password','question_id','answer_key']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_exam_not_started')]];

		$result = $this->db->update('exam_results', 
			[
				'question_ids' => ($exam_results->question_ids ? $exam_results->question_ids.',' : '' ).$request->params->question_id,
				'answer_keys' => ($exam_results->answer_keys ? $exam_results->answer_keys.',' : '' ).$request->params->answer_key,
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, ['message' => $this->f->_err_msg('success_save_answer')]];
	}

	function finish($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password','coordinate','finish_time']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_exam_not_started')]];

		$result = $this->db->update('exam_results', 
			[
				'status' => 'completed',
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		$str = "(
			SELECT * FROM exam_logs
			where schedule_request_id = ? and member_id = ? and JSON_EXTRACT(state, '$.name') = 'finish_exam'
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member_id]);
		if (! $exam_log = $this->db->from($table)->get()->row()) {
			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member_id, 
				'state' => '{"name":"finish_exam","activity":"confirmation","time_client":"'.$request->params->finish_time.'"}', 
				'user_agent' => $request->agent,
				'coordinate' => $request->params->coordinate,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			return [TRUE, ['result' => ['finish_time' => $request->params->finish_time]]];
		} else {
			$state = json_decode($exam_log->state);
			return [TRUE, ['result' => ['finish_time' => $state->time_client]]];
		}
	}

	function question_all($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = '(
      select id, sts, module_id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, option_ganda, answer_key, score from questions
			where module_id in (select module_id from category_modules where category_id = ?)
		) g0';
		$table = $this->f->compile_qry($str, [$schedule->category_id]);
		$this->db->from($table);
		return [TRUE, ['result' => $this->db->get()->result()]];
	}

	function check_score($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_exam_not_started')]];

		if ($exam_results->click_score >= 3)
			return [FALSE, ['message' => $this->f->_err_msg('err_check_score_had_reached')]];

		$result = $this->db->update('exam_results', 
			[
				'click_score' => $exam_results->click_score + 1,
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, ['result' => ['score' => $exam_results->score]]];
	}

}