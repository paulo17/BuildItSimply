<?php

/**
 *  Projects controller
 */
class ProjectController extends AppController
{

    public $uses = [
        'Account',
        'Project',
        'Client',
        'ProjectType',
        'Participate',
        'ProjectStep',
        'ProjectResponse',
        'ProjectTag',
        'ProjectFile'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Trigger execute before routing the client
     * Overload the parent beforeroute method
     * Adding some control routing for the project controller
     */
    public function beforeroute()
    {
        // call parent beforeroute
        parent::beforeroute();

        if ($this->get('PATTERN') == "/projects/@id")
        {
            $project = $this->Project->find($this->get('PARAMS.id'));

            if ($project)
            {
                $this->setSeo([
                    'title'       => $project->name,
                    'description' => $project->description
                ]);

                if ($project->client_id == $this->Auth->getId())
                {
                    $this->f3->reroute('/projects/edit/' . $this->get('PARAMS.id'));
                }

            } else
            {
                $this->f3->reroute('/projects/');
            }
        }

        if ($this->get('PATTERN') == "/projects/edit/@id")
        {
            if (!$this->Auth->is('client'))
            {
                $this->setFlash("En tant que Freelance vous ne pouvez pas acceder à cette zone");
                $this->f3->reroute('/projects/');
            }
        }

        // if request content @id params
        // check if project exist
        if (!empty($this->get('PARAMS.id')))
        {
            if (!$this->Project->exists($this->get('PARAMS.id')))
            {
                $this->setFlash("Ce projet n'existe pas.");
                $this->f3->reroute('/projects/');
            }
        }

        if (in_array($this->get('PATTERN'),
            ["/projects/detail/step", "/projects/detail/step/@step", "/projects/finish"]))
        {

            // if user is not client or haven't create project
            if (!$this->Auth->is('client') || !$this->get('SESSION.project'))
            {
                $this->f3->reroute('/projects/');
            }

            // if project already publish redirect to project edit page
            if ($project = $this->get('SESSION.project'))
            {

                if ($this->Project->getStatus($project['id']) != "EN CREATION")
                {
                    $this->f3->reroute('/projects/' . $project['id']);
                }
            }
        }
    }

    /**
     * Initialize a project
     */
    public function init()
    {
        $project = [];
        $errors = [];

        if ($this->request() == "POST")
        {
            $newProject = $this->get('POST');
            if ($project = $this->Project->initialize($newProject))
            {
                $newProject['id'] = $project->id;
                $this->set('SESSION.project', $newProject);
                $this->f3->reroute('/projects/detail/step');

            } else
            {
                $project = $newProject;
                $errors = $this->Project->errors;
            }
        }

        $this->render('projects/init', compact('project', 'errors'));
    }

    /**
     * Show a project by ID
     */
    public function show()
    {
        $project = $this->Project->show($this->get('PARAMS.id'));

        if ($project)
        {
            $tags = $project->tags()->get();
            $propositions = $this->Participate->proposition($this->get('PARAMS.id'));
            $project['type'] = $project->type()->first();
            $files = $project->files()->get();

            $this->render('projects/show', compact('project', 'tags', 'propositions', 'files'));
        } else
        {
            $this->setFlash("Ce projet n'existe pas.");
            $this->f3->reroute('/projects/');
        }
    }

    /**
     * Get all projects and display them on a list
     */
    public function all()
    {
        $this->setSeo([
            'title'       => "Nos projets disponibles",
            'description' => "Les recruteurs ont besoin de vous, n’hésitez pas à proposer vos services !"
        ]);

        $validator = new Validate();
        $page = ($this->get('PARAMS.page')) ? $this->get('PARAMS.page') : 0;

        if (!$validator->isNumber($page))
        {
            $page = 0;
        }

        $offset = ($page > 0) ? ($page - 1) * ($this->get('PROJECT_PER_PAGE')) : 0;

        $number_project = $this->Project->publicated()->count();
        $number_page = ceil($number_project / $this->get('PROJECT_PER_PAGE'));

        $projects = $this->Project->whereNotIn('status', ['EN CREATION', 'ANNULE'])
                                  ->with('type')
                                  ->recent()
                                  ->skip($offset)->limit($this->get('PROJECT_PER_PAGE'))
                                  ->get();

        $categories = $this->ProjectType->all();

        $categories->each(function ($type)
        {
            $type->number_project = $this->Project->countCategory($type->id);
        });

        if ($projects->count() > 0)
        {
            $projects = $this->Project->getInformation($projects);
        } else
        {
            $this->setFlash("Aucun projet.");
        }

        $this->render('projects/all', compact('projects', 'categories', 'number_project', 'number_page', 'page'));
    }

    /**
     * Get a list of project by category
     */
    public function category()
    {
        if ($this->get('AJAX'))
        {
            if ($category = $this->get('PARAMS.category'))
            {
                $projects = $this->Project->getByCategory($category);

                $this->render('projects/list', compact('projects'));
            } else
            {
                $this->f3->reroute($this->get('PATTERN'));
            }
        }
    }


    /**
     * Call in AJAX
     * Get projects that contains keywords and display them on a list
     *
     * @return int 1
     */
    public function search()
    {
        if ($this->request() == "POST" && $this->get('AJAX'))
        {
            $this->validator = new Validate();
            $searchWords = $this->get('POST')['searchWords'];
            $this->words = explode(' ', $searchWords);

            $request = $this->Project->publicated()->with('type');

            $request->where(function ($query)
            {
                foreach ($this->words as $word)
                {
                    if ($this->validator->isKeyword($word, 100))
                    {
                        $query->orWhere('projects.name', 'like', '%' . $word . '%')
                              ->orWhere('projects.description', 'like', '%' . $word . '%')
                              ->orWhere('projects.targets', 'like', '%' . $word . '%');
                    }
                }
            });

            $projects = $request->recent()->get();

            if ($projects->count() > 0)
            {
                $projects = $this->Project->getInformation($projects);
            }

            $this->render('projects/list', compact('projects', 'searchWords'));
        }
        return 1;
    }


    /**
     * Ask a client for participate to his project
     * Create a new project demand with pending status
     *
     * @param GET ID of the project
     */
    public function join()
    {
        $project = Project::find($this->get('PARAMS.id'));
        $user = $this->get('SESSION.user');

        // check if project is status OPEN
        if ($project->status != "OUVERT")
        {
            $this->setFlash("Ce projet n'est pas ouvert à de nouveaux freelances.");
            $this->f3->reroute("/projects/" . $project->id);
        }

        if ($project && $this->Auth->is('freelance'))
        {

            $client = $project->account()->first();

            if ($this->Participate->demand($project->id, $user['id']))
            {

                $this->setFlash("Votre participation a bien été prise en compte.");

                // send notification mail
                $this->MailHelper->sendMail("demand", $client->mail, [
                    'subject' => "Nouvelle demande pour votre projet " . $project->name,
                    'user'    => Account::find($user['id'])->with('freelance')->first(),
                    'project' => $project
                ]);

            } else
            {
                $this->setFlash("Vous participez déjà à ce projet.");
            }
        }

        $this->f3->reroute('/projects/' . $this->get('PARAMS.id'));
    }

    /**
     * Edit a project with his ID
     *
     * @return reroute for break function
     */
    public function edit()
    {
        $project = $this->Project->show($this->get('PARAMS.id'));

        // if project doesn't exist or isn't own by the client
        if (empty($project) || $project->client_id != $this->get('SESSION.user.id'))
        {
            return $this->f3->reroute('/projects/');
        }

        /*
         * Get all proposition filter by status accept
         * if project is in status DECISION
         */
        $status = $this->Participate->statusReference($project->status);
        $propositions = $this->Participate->proposition($project->id, $status);
        $project['type'] = $project->type()->first();
        $tags = $this->ProjectTag->where('project_id', $this->get('PARAMS.id'))->get();
        $files = $project->files()->get();
        $recommendation = $project->recommendation()->count();

        // update project information
        if ($this->request() == "POST")
        {
            if ($this->Project->updateProject($project->id, $this->get('POST')))
            {
                $this->setFlash("Les modifications de votre projet ont bien été effectué.");
                $this->f3->reroute('/projects/edit/' . $project->id);
            }
        }

        $this->render('projects/edit', compact('project', 'propositions', 'tags', 'files', 'recommendation'));
    }

    /**
     * Cancel a project and update his status to "ANNULE"
     */
    public function delete()
    {
        $project = Project::find($this->get('PARAMS.id'));
        if ($project->client_id == $this->get('SESSION.user.id'))
        {
            $delete = $project->update([
                'status' => 'ANNULE'
            ]);
            if ($delete)
            {
                $this->setFlash("Votre projet a bien été annulé");
            }
        }
        $this->f3->reroute('/projects/' . $this->get('PARAMS.id'));
    }

    /**
     * Show all client's projects
     */
    public function client_list()
    {
        $projects = $this->Project->where('client_id', $this->get('SESSION.user.id'))
                                  ->whereIn('status', ['OUVERT', 'EN COURS', 'TERMINE'])
                                  ->recent()
                                  ->get();

        if ($projects->count() > 0)
        {
            $projects = $this->Project->getInformation($projects);
        } else
        {
            $this->setFlash("Aucun projet.");
        }

        $this->render('projects/client_list', compact('projects'));
    }


    /**
     * Update the proposition status with the client's choice
     * Call in AJAX mode
     *
     * @return JSON
     */
    public function choice()
    {
        if ($this->get('AJAX'))
        {

            $req = $this->get('POST');

            $participate = $this->Participate->where('project_id', $req['project_id'])
                                             ->where('freelance_id', $req['freelance_id'])
                                             ->first();

            if (in_array($participate->status, ["PENDING", "ACCEPT"]))
            {

                // check for status ACCEPT for security
                if ($participate->status == "ACCEPT")
                {
                    // update participation status
                    $update = $this->Participate->choice($req['project_id'], $req['freelance_id'], "CHOOSEN");
                    // update project status
                    $this->Project->updateStatus($req['project_id'], "EN COURS");
                } else if ($participate->status == "PENDING")
                {
                    $update = $this->Participate->choice($req['project_id'], $req['freelance_id'], $req['status']);
                }

                // update status
                if ($update)
                {
                    echo $this->encode("proposition", [
                        "error"   => false,
                        "status"  => $req['status'],
                        "message" => "freelance " . $req['status']
                    ]);

                } else
                {
                    echo $this->encode("proposition", [
                        "error"   => true,
                        "message" => "Vous ne pouvez choisir que 3 freelances au maximum"
                    ]);
                }
            }
        }
    }

    /**
     * Send mail confirmation for project accepted
     */
    public function sendResponse()
    {
        if ($this->get('AJAX'))
        {
            $req = $this->get('POST');
            $project = Project::find($req['project_id'])->with('account')->first();
            $freelance = Account::find($req['freelance_id']);

            $this->MailHelper->sendMail('response', $freelance->mail, [
                'subject' => "Votre demande concernant le projet " . $project->name,
                'project' => [
                    'name'      => $project->name,
                    'firstname' => $project->firstname,
                    'lastname'  => $project->lastname
                ],
                'demand'  => [
                    'status' => $req['status']
                ]
            ]);
        }
    }

    /**
     * Close proposition for this project
     */
    public function close()
    {
        $project_id = $this->get('PARAMS.id');

        // stay in request builder mode
        $propositions = $this->Participate->where('project_id', $project_id);

        if ($propositions->where('status', 'ACCEPT')->count() >= 1)
        {
            $update = $this->Project->where('id', $project_id)->update([
                'status' => 'DECISION'
            ]);
            if ($update)
            {
                $this->setFlash("Les demandes pour votre projet sont maintenant fermés.");
            }
        } else
        {
            $this->setFlash("Vous ne pouvez cloturer les demandes que si vous en avez au moins accepté une.");
        }

        $this->f3->reroute("/projects/" . $project_id);
    }

    /**
     * When project is done and finish by the freelance
     */
    public function end()
    {
        $project = Project::find($this->get('PARAMS.id'));

        if ($project->status == "EN COURS")
        {
            if ($project->participates()->status('choosen')->count() == 1)
            {

                $this->Project->updateProject($project->id, [
                    'status' => 'TERMINE'
                ]);
                $this->setFlash("Le projet est maintenant terminé,
                n'oubliez pas de laisser un commentaire pour votre freelance.");
            }
        } else
        {
            $this->setFlash("Votre projet n'est pas en cours, vous ne pouvez donc pas le terminer.");
        }
        $this->f3->reroute('/projects/' . $project->id);
    }

    /**
     * First step of detail information about the project
     * Client choose between our project type
     */
    public function startingStep()
    {
        $step = 0;
        $types = ProjectType::all();
        $this->set('SESSION.project.price', 0);
        $this->set('SESSION.old_price', 0);
        $this->set('SESSION.project.tag', '');
        $this->f3->clear('SESSION.step');
        $this->render('projects/start', compact('types', 'step'));
    }

    /**
     * AJAX Call
     * Get next or prev step for project details informations
     * After all step redirect client to the finish page
     *
     * @return int 0
     */
    public function step()
    {
        if ($this->get('AJAX'))
        {

            $request = $this->get('POST');
            $questions = $this->ProjectStep->changeStep($request['step'], $request['type']);
            $current_step = $request['step'] - 1;

            $response = ProjectResponse::find($request['choice']);

            $this->set('SESSION.step.' . $current_step, $request['choice']);
            $this->set('SESSION.project.project_type_id', $request['type']);


            if (!empty($response['tag']) && $current_step > 0)
            {
                $this->set('SESSION.old_price', $response->price);
                $this->set('SESSION.project.price', $this->get('SESSION.project.price') + $response->price);
                $this->set('SESSION.project.tag',
                    $this->get('SESSION.project.tag') . ',' . $response['tag']);
            }

            if ($request['change'] == "prev")
            {
                $this->set('SESSION.project.price',
                    $this->get('SESSION.project.price') - $this->get('SESSION.old_price'));
            }

            if ($questions)
            {
                $step = $request['step'];
                $type = $request['type'];
                $price = $this->get('SESSION.project.price');

                $this->render('projects/step', compact('step', 'questions', 'type', 'price'));
            } else
            {
                // all step finish send json object for redirect client to the validation page
                echo $this->encode('step',
                    ['status' => 'finish', 'redirect' => $this->config['home'] . 'projects/finish']);
            }

        }

        return 0;
    }

    /**
     *  Last step for add a project
     */
    public function finish()
    {
        $project = $this->get('SESSION.project');
        $steps = $this->get('SESSION.step');

        if (empty($project))
        {
            $this->f3->reroute($this->get('PATTERN'));
        }

        $type = ProjectType::find($project['project_type_id']);
        $responses = $this->ProjectResponse->getResponses($steps);

        if ($this->request() == "POST")
        {
            $request = $this->get('POST');
            $request['project_type_id'] = $project['project_type_id'];
            $request['price'] = $project['price'];


            $uploadFile = false;
            $filesList = $this->f3->get('FILES.file');
            foreach ($filesList['name'] as $key => $file)
            {
                if (!empty($file))
                    $uploadFile = true;

            }

            if ($uploadFile)
            {
                // Recuperation of images
                $upload = new UploadHelper($this->f3->get('PROJECT_FILES'), true);
                $files = $upload->upload();
            }

            if ($this->Project->publish($project['id'], $request))
            {

                if ($uploadFile)
                    $this->ProjectFile->addFiles($files, $filesList, $project['id']);

                // adding tags
                $this->ProjectTag->addTags($project['id'], $project['tag']);

                // cleaning session
                $this->f3->clear('SESSION.project');
                $this->f3->clear('SESSION.step');

                $this->setFlash("Votre projet vient d'être publié, merci.");
                $this->f3->reroute('/projects/' . $project['id']);

            } else
            {
                $this->setFlash("Les informations renseignées ne sont pas valides.");
                $this->f3->reroute($this->get('PATTERN'));
            }
        }

        $this->render('projects/finish', compact('project', 'responses', 'type'));
    }

}