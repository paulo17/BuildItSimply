<?php

class AccountController extends AppController{

	public $uses = array('Account', 'Freelance', 'Skill');

	public function __construct(){
		parent::__construct();
	}

	/**
	*	Authenticate and log client
	**/
	public function login(){
		if($this->request() == 'POST'){
			if($user = $this->Account->login($this->f3->get('POST'))){
				$this->Account->setSession($user);
				$this->setFlash('Authentification reussi');
				$this->f3->reroute('/');
			}else{
				$this->setFlash('erreur');
				$this->f3->reroute($this->f3->get('PATTERN'));
			}
		}

		$this->render('accounts/login');
	}

	/**
	*	Logout client and destroy session
	**/
	public function logout(){
		$this->f3->clear('SESSION');
		$this->f3->reroute('/users/login');
	}

	/**
	*	Register a client using form and post data
	**/
	public function register(){
		$user = array();
		$errors = array();

		if($this->request() == 'POST'){
			$user = $this->f3->get('POST');

			if($newUser = $this->Account->register($user)){
					$this->Account->setSession($newUser);
					$this->setFlash('Votre compte a été crée et vous avez automatiquement été connecté.');
					$this->f3->reroute('/users/profile');
			}else{
				$errors = $this->Account->errors;
			}
		}

		$type = $this->Account->getEnumValues('type');
		$this->render('accounts/register', compact('user', 'errors', 'type'));
	}

	/**
	*	Show user profile by ID or User Session ID
	**/
	public function profile($f3, $params = null){

		// get user profile by ID or with session ID
		$user = $this->Account->find((!empty($params['id'])) ?
			$params['id'] :
			$this->f3->get('SESSION.user.id')
		);

		if(!empty($user)){
			if($user['type'] == "FREELANCE"){
				$experiences = $this->Freelance->getEnumValues('experience');
			}

			$this->render('accounts/profile', compact('user',
				(!empty($experiences) ? 'experiences' : ''))
			);
		}else{
			$this->setFlash("Cet utilisateur n'existe pas.");
			$this->f3->reroute('/');
		}
	}

	/**
	*
	**/
	public function update_profile() {
		if($this->request() == 'POST'){
			$profile = $this->f3->get('POST');
			$profile['account_id'] = $this->f3->get('SESSION.user.id');
			$type = $this->f3->get('SESSION.user.type');

			if($type == 'FREELANCE'){
				if($this->Freelance->updateProfile($profile)){
					$skills = $this->Skill->explodeSkills($profile['skills']);

					// TODO INSERT ALL FREELANCE SKILLS
					$this->FreelanceSkill->add($skills);

					$this->setFlash("Votre profil a bien été mis à jour.");
				}else{
					$errors = $this->Freelance->errors;
				}
			}else if($type == 'CLIENT'){

			}
		}
		$this->f3->reroute('/users/profile');
	}


}

?>