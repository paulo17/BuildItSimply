<?php

/**
 *  Back office controller
 */
class AdminController extends AppController
{
    public $uses = array('Project', 'ProjectType', 'ProjectQuestion', 'ProjectStep', 'ProjectResponse');

    public $layout = 'admin';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     *  TODO set admin role
     */
    public function beforeroute(){
        parent::beforeroute();
        if(!$this->Auth->is('admin')){
            $this->f3->reroute('/');
        }
    }

    /**
     * Back-office's home
     */
    public function main()
    {
        $this->render('admin/');
    }

    /**
     * Add type of project
     */
    public function projectType()
    {
        if ($this->request() == "POST") 
        {

            $request = $this->get('POST');

            if (!empty($this->get('FILES.image.name')))
            {
                $upload = new UploadHelper($this->get('PROJECT_TYPE_FILES'));
                $filename = current($upload->upload());

                if ($filename) 
                    $request['image'] = $filename;
            }

            ProjectType::create($request);



            $this->setFlash("Le type de project a bien été crée");
            $this->f3->reroute($this->get('PATTERN'));
        }

        $types = ProjectType::all();

        $this->render('admin/projects/type', compact('types'));
    }


    /**
     * Manage steps of project
     */
    public function projectStep()
    {
        $responses  = new ProjectResponse();
        $steps = new ProjectStep();

        if ($this->request() == "POST") 
        {
            $request = $this->get('POST');


            if(!empty($request))
            {
                $questions = new ProjectQuestion();

                // Si l'utilisateur veut update
                if($request['button']=='update')
                {
                    $filesList = $this->get('FILES.image.name');

                    foreach ($request['question'] as $key => $question) 
                    {
                        $questions->where('id', $key)->update(['question' => $question]);
                    }

                    $uploadFile = false;
                    foreach ($filesList as $key => $file)
                    {
                        if(!empty($file))
                            $uploadFile = true;
                    }

                    if($uploadFile)
                    {
                        // Recuperation of images
                        $upload = new UploadHelper($this->get('RESPONSE_FILES'));
                        $files = $upload->upload();
                    }
                    
                    foreach ($request['response'] as $key => $response) 
                    {
                        $new_response = $response;
                        if (!empty($filesList[$key])) 
                        {
                            $new_response['image'] = current($files);
                            next($files);
                        }
    
                        $responses->where('id', $key)
                            ->update($this->generateUpdateResponse($new_response));
                    }

                    $this->setFlash("Les étapes ont bien été mises à jours");
                }

                // Si l'utilisateur veut remove
                else if($request['button']=='remove')
                {
                    if(!empty($request['checked_question']))
                    {
                       foreach ($request['checked_question'] as $key => $value) 
                        {
                            $steps->where('project_question_id', $key)->delete();
                            $questions->destroy($key);
                        } 
                    }
                    
                    if(!empty($request['checked_response']))
                    {
                        foreach ($request['checked_response'] as $key => $value) 
                        {
                            $responses->destroy($key);
                        }
                    }


                    $this->setFlash("La sélection a bien été supprimé");
                }
            }

            $this->f3->reroute($this->get('PATTERN'));
        

        }

        $types = ProjectType::all(array('id', 'type'));
        $steps = $steps->where('project_question_id', '>', 0)
            ->join('project_question', 'project_step.project_question_id', '=', 'project_question.id')
            ->orderBy('project_step.step', 'asc')
            ->get();

        $responses = $responses->all();

        $this->render('admin/projects/step', compact('steps', 'types', 'responses'));
    }

    /**
     *    Unset all empty field for response update
     *    @param array data
     *    @return array $data cleaned
     */
    private function generateUpdateResponse($data = array())
    {

        if (empty($data['response']))
            unset($data['response']);

        if (empty($data['image']))
            unset($data['image']);

        if (empty($data['description']))
            unset($data['description']);

        if (empty($data['tag']))
            unset($data['tag']);

        if (empty($data['price'])) 
            unset($data['price']);

        return $data;
    }

    /**
     * Add questions for one type of project
     */
    public function projectQuestion()
    {
        $error = array();

        if ($this->request() == "POST") {
            $request = $this->get('POST');

            if ($this->ProjectQuestion->validate($request)) {
                $question = ProjectQuestion::create([
                    'question' => $request['question'],
                    'description' => $request['description']
                ]);

                $this->addResponses($request, $question->id);

            } else {
                $error = $this->ProjectQuestion->errors;
            }


            if (!$this->ProjectStep->exists($request['project_type'], $request['step'])) {
                ProjectStep::create([
                    'project_type_id' => $request['project_type'],
                    'project_question_id' => $question->id,
                    'step' => $request['step']
                ]);
            }

            $this->setFlash("La question a bien été ajouté  ");
            $this->f3->reroute($this->get('PATTERN'));
        }

        $types = ProjectType::all(array('id', 'type'));

        $this->render('admin/projects/question', compact('types', 'error'));
    }


    /**
     * For answer one question added previously
     */
    public function projectResponse()
    {

        if ($this->request() == "POST") 
        {
            $request = $this->get('POST');

            $this->addResponses($request);

            $this->setFlash("Les réponses ont bien été ajoutées");
            $this->f3->reroute($this->get('PATTERN'));
        }

        $questions = ProjectQuestion::all(array('id', 'question'));
        $responses = ProjectResponse::all(array('response', 'question_id'));

        $this->render('admin/projects/response', compact('questions', 'responses'));
    }


    /**
     * Function for add answer
     * @param array $request contain post's data
     * @param int idQuestion 
     */
    public function addResponses($request, $idQuestion = null)
    {

        if(empty($idQuestion))
            $idQuestion = $request['project_question'];

        $upload = new UploadHelper($this->get('RESPONSE_FILES'));
        $files = $upload->upload();
        $fileList = $this->get('FILES.image.name');

        foreach ($request['response'] as $key => $response) 
        {

            $new_response = $response;
            if ($this->ProjectResponse->validate($new_response)) 
            {
                if (!empty($fileList[$key])) 
                {
                    $new_response['image']= current($files);
                    next($files);
                }

                if(!empty($new_response['image']))
                {
                    ProjectResponse::create([
                        'response'      => $new_response['response'],
                        'description'   => $new_response['description'],
                        'price'         => $request['price'],
                        'tag'           => $request['tag'],
                        'question_id'   => $idQuestion,
                        'image'         => $new_response['image']
                    ]);
                }
                else
                {
                    ProjectResponse::create([
                        'response'      => $new_response['response'],
                        'description'   => $new_response['description'],
                        'price'         => $request['price'],
                        'tag'           => $request['tag'],
                        'question_id'   => $idQuestion
                    ]);
                }

            }
        }
    }


    /**
     * POST request for add skill in the database
     */
    public function freelanceSkillNew()
    {
        if($this->request() == "POST")
        {

            if( Skill::create($this->get('POST')))
            {
                $this->setFlash("La compétence a bien été ajouté.");
                $this->f3->reroute($this->get('PATTERN'));
            }
        }

        $categories = CategorySkill::all();
        $this->render('admin/freelance/skill/new', compact('categories'));
    }



    /**
     *  List of freelances
     */
    public function freelanceList()
    {
        if($this->request() == "POST")
        {
            $request = $this->get('POST');

            $this->listManagement($request, 'freelance');
        }

        $freelances = Account::where('type', '=', 'FREELANCE')->get();
        $this->render('admin/freelance/list', compact('freelances'));
    }


    /**
     *  List of clients
     */
    public function clientList()
    {
        if($this->request() == "POST")
        {
            $request = $this->get('POST');

            $this->listManagement($request, 'client');
        }

        $clients = Account::where('type', '=', 'CLIENT')->get();
        $this->render('admin/client/list', compact('clients'));
    }



    /**
     *  Manage list of user
     *  @param array $request  post's data
     *  @param String $type type of person: freelance | client
     */
    public function listManagement($request, $type)
    {
        // After submit modifications 
        if(!empty($request['submit']))
        {

            Account::where('id', $request['id'])->update(
                [
                    'lastname' => $request['lastname'],
                    'firstname' => $request['firstname'],
                    'mail' => $request['mail'],
                ]);

            $this->setFlash("L'utilisateur a bien été mis à jour.");
            $this->f3->reroute($this->get('PATTERN'));
        }

        // After select one user for modify
        else if(!empty($request['modify']))
        {
            $user = Account::where('id', $request['modify'])->first();
            $this->render('admin/'.$type.'/modify', compact('user'));
        }

        // If multiple remove requested
        else if(!empty($request['button']) && $request['button']=='remove')
        {
            $accounts = new Account();

            if($type == 'freelance')
                $users =   new Freelance();
            else if($type == 'client')
                $users =   new Client();

            foreach ($request['checked_user'] as $key => $value) 
            {
                $users->where('account_id', $key)->delete();
                $accounts->destroy($key);
            }
            $this->setFlash("La sélection a bien été supprimé.");
            $this->f3->reroute($this->get('PATTERN'));
        }
    }



}