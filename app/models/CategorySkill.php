<?php

/**
 * Class model for skill's category
 */
class CategorySkill extends AppModel
{

    public $timestamps = true;
    public $errors;

    protected $table = 'categories_skills';
    protected $guarded = array('id');

}