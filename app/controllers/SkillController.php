<?php

class SkillController extends AppController
{

    public $uses = array('Skill', 'FreelanceSkill');

    /**
     *
     */
    public function getAll()
    {
        $skills = $this->Skill->all(['id', 'name']);
        echo $this->encode('skills', $skills);
    }

    /**
     * Delete a freelance skill in AJAX
     * Use the tokeninput field on the edit profile form
     */
    public function deleteFreelanceSkill()
    {
        if ($this->f3->get('AJAX')) {
            $skill = $this->Skill->where('name', $this->f3->get('POST.name'))->first();
            if ($skill) {
                $delete = $this->FreelanceSkill
                    ->where('account_id', $this->f3->get('SESSION.user.id'))
                    ->where('skill_id', $skill->id)
                    ->delete();

                $message = ($delete)
                    ? "La compétence " . $skill->name . " a bien été supprimé."
                    : "Erreur lors de le suppresion de la compétence " . $skill->name . ".";
            }
            echo $this->encode('delete', ['message' => $message]);
        }
    }


}

?>