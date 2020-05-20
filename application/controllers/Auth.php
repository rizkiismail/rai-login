<?php

class Auth extends CI_Controller 
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('form_validation');

	}

	public function index()
	{
		if ($this->session->userdata('email')){
			redirect('user');
		}
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
		$this->form_validation->set_rules('password', 'Password', 'trim|required');
		
		if ($this->form_validation->run() == false){
		$data['title']='Halaman Login';
		$this->load->view('templates/auth_header', $data);
		$this->load->view('auth/login');
		$this->load->view('templates/auth_footer');
		}
		else
		{
			$this->_login();
		}
	}

	private function _login()
	{
		$email = $this->input->post('email');
		$password = $this->input->post('password');

		$user=$this->db->get_where('user',['email'=>$email])->row_array();
		
		if($user){
			if(password_verify($password, $user['password'])){
				$data=[
					'email' => $user['email'],
					'role_id' => $user['role_id']
				];
				$this->session->set_userdata($data);
				if($user['role_id']==1){
					redirect('admin');	
				}
				else{
				redirect('user');
				}
			}else{
				$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Kata sandi salah!</div>');
				redirect('auth');	
			}
		}else{
			$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Email belum terdaftar!</div>');
			redirect('auth');
		}
	}

	public function registration()
	{
		if ($this->session->userdata('email')){
			redirect('user');
		}
		$this->form_validation->set_rules('name','Name','required|trim');
		$this->form_validation->set_rules('email','Email','required|trim|valid_email|is_unique[user.email]',
			['is_unique'=> 'Email ini telah terdaftar!']);
		$this->form_validation->set_rules('password1','Password','required|trim|min_length[3]|matches[password2]',[
			'matches' => 'Kata sandi tidak sesuai!',
			'min_length' => 'Kata sandi terlalu pendek'
	]);
		$this->form_validation->set_rules('password2','Password','required|trim|matches[password1]');

		if ($this->form_validation->run() == false){
			$data['title'] = 'Pendaftaran Pengguna';
			$this->load->view('templates/auth_header', $data);
			$this->load->view('auth/registration.php');
			$this->load->view('templates/auth_footer');	
		}
		else 
		{
			$email = $this->input->post('email', true);
			$data = [
				'name'=> htmlspecialchars($this->input->post('name', true)),
				'email'=> htmlspecialchars($email),
				'image'=> 'default.jpg',
				'password'=> password_hash($this->input->post('password1'),PASSWORD_DEFAULT),
				'role_id'=> 2,
				'is_active'=> 0, //1 jika aktif
				'date_created'=> time()
			];
			//siapkan token
			$token=base64_encode(random_bytes(32));
			$user_token = ['email' => $email,
							'token' => $token,
							'date_created' => time()];

			$this->db->insert('user',$data);

			//insert tabel user_token
			$this->db->insert('user_token',$user_token);

			//kirim email
			$this->_sendEmail($token, 'verify');

			$this->session->set_flashdata('message','<div class="alert-success" role="alert">Akun Anda telah dibuat! Silahkan Aktivasi Akun Anda!</div>');
			redirect('auth');
		}
	}

	//method kirim email
	private function _sendEmail($token, $type)
	{
		$config = [
			'protocol'  => 'smtp',
			'smtp_host' => 'ssl://smtp.googlemail.com',
			'smtp_user' => 'raiproject04@gmail.com',
			'smtp_pass' => 'andriana17',
			'smtp_port' => 	465,
			'mailtype'  => 'html',
			'charset'	=> 'utf-8',
			'newline'	=> "\r\n"
		];

		$this->email->initialize($config);
		
		$this->email->from('raiproject04@gmail.com', 'Rizki A Ismail');
		$this->email->to($this->input->post('email'));

		if($type=='verify'){
		$this->email->subject('Verifikasi Akun');
		$this->email->message('Klik link ini untuk verifikasi akun Anda : <a href="'.base_url().'auth/verify?email='.$this->input->post('email').'&token='.urlencode($token).'">Aktivasi</a>');
		} else if($type=='forgot'){
		$this->email->subject('Reset Kata Sandi');
		$this->email->message('Klik link ini untuk reset kata sandi Anda : <a href="'.base_url().'auth/resetpassword?email='.$this->input->post('email').'&token='.urlencode($token).'">Reset Kata Sandi</a>');
		} 

		if($this->email->send()) {
			return true;
		}else{
			echo $this->email->print_debugger();
			die;
		}
	}

	//method verify
	public function verify(){
		$email = $this->input->get('email');
		$token = $this->input->get('token');

		$user = $this->db->get_where('user', ['email' => $email])->row_array();

		if($user){
			$user_token=$this->db->get_where('user_token', ['token' => $token])->row_array();
			if($user_token){
				if(time() - $user_token['date_created']<(60*60*24)){
					$this->db->set('is_active',1);
					$this->db->where('email', $email);
					$this->db->update('user');
					$this->db->delete('user_token',['email'=> $email]);

					$this->session->set_flashdata('message','<div class="alert-success" role="alert">'.$email.' telah diaktivasi! Silahkan Login!</div>');
		redirect('auth');

				}else{
					$this->db->delete('user',['email' => $email]);
					$this->db->delete('user_token', ['email' => $email]);

					$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Aktivasi akun gagal! Token Kadaluarsa!</div>');
		redirect('auth');
				}
			}else{
				$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Aktivasi akun gagal! Token salah!</div>');
		redirect('auth');
			}
		} else {
			$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Aktivasi akun gagal! Email salah!</div>');
		redirect('auth');
		}
	}


	public function logout()
	{
		$this->session->unset_userdata('email');
		$this->session->unset_userdata('role_id');
		
		$this->session->set_flashdata('message','<div class="alert-success" role="alert">Anda telah logout!</div>');
		redirect('auth');
	}

	public function blocked(){
		$this->load->view('auth/blocked');
	}

	//method lupa password
	public function forgotPassword(){

		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');

		if($this->form_validation->run()==false){
		$data['title']='Lupa Kata Sandi';
		$this->load->view('templates/auth_header', $data);
		$this->load->view('auth/forgot-password');
		$this->load->view('templates/auth_footer');	
		}
		else{
			$email = $this->input->post('email');
			$user = $this->db->get_where('user',['email' => $email, 'is_active' => 1])->row_array();
			if ($user){
				$token = base64_encode(random_bytes(32));
				$user_token = [
					'email' => $email,
					'token' => $token,
					'date_created' => time()
				];

				$this->db->insert('user_token', $user_token);
				$this->_sendEmail($token, 'forgot');

				$this->session->set_flashdata('message','<div class="alert-success" role="alert">Silahkan periksa email Anda untuk reset kata sandi!</div>');
		redirect('auth/forgotpassword');

			} else{
				$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Email belum terdaftar atau belum diaktivasi!</div>');
		redirect('auth/forgotpassword');
			}
		}	
	}

	public function resetPassword(){
		$email = $this->input->get('email');
		$token = $this->input->get('token');

		$user = $this->db->get_where('user', ['email' => $email])->row_array();

		if($user){
			$user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();
			if($user_token){
				$this->session->set_userdata('reset_email', $email);
				$this->changePassword();

			} else{
				$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Reset kata sandi gagal ! Token salah!</div>');
		redirect('auth');
			}
		} else{
			$this->session->set_flashdata('message','<div class="alert-danger" role="alert">Reset kata sandi gagal ! Email salah!</div>');
		redirect('auth');
		}
	}

	public function changePassword(){
		if(!$this->session->userdata('reset_email')){
			redirect('auth');
		}
		$this->form_validation->set_rules('password1','Password', 'trim|required|min_length[3]|matches[password2]');
		$this->form_validation->set_rules('password2','Repeat Password', 'trim|required|min_length[3]|matches[password1]');
		if($this->form_validation->run==false){
		$data['title']='Ganti Kata Sandi';
		$this->load->view('templates/auth_header', $data);
		$this->load->view('auth/change-password');
		$this->load->view('templates/auth_footer');
		}else{
			$password=password_hash($this->input->post('password1'), PASSWORD_DEFAULT);
			$email=$this->session->userdata('reset_email');

			$this->db->set('password', $password);
			$this->db->where('email', $email);
			$this->db->update('user');

			$this->session->unset_userdata('reset_email');
			$this->session->set_flashdata('message','<div class="alert-success" role="alert">Kata sandi telah diganti! Silahkan Login!</div>');
		redirect('auth');

		}
	}


}