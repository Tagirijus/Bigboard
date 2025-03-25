<?php

namespace Kanboard\Plugin\BigBoard\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ProjectModel;

class BigboardModel extends Base
{
    const SELTABLE = 'bigboard_selected';
	const COLTABLE = 'bigboard_collapsed';
	
	
	// SELECT methods :
	// manage list of projects selected to be displayed on the bigboard view
	
    public function selectFindAllProjects($user_id)
    {
        $user_projects = $this->projectUserRoleModel->getProjectsByUser($user_id, [ProjectModel::ACTIVE]);
        $projects = [];
        foreach ($user_projects as $project_id => $user_project) {
            $projects[] = $this->projectModel->getById($project_id);
        }
        return $projects;
    }

    public function selectFindAllProjectsById($user_id)
    {
        $user_projects = $this->projectUserRoleModel->getProjectsByUser($user_id, [ProjectModel::ACTIVE]);
        $projects = [];
        foreach ($user_projects as $project_id => $user_project) {
            $projects[] = $project_id;
        }
		// sort($projects);
        return $projects;
    }

    public function selectFind($project_id, $user_id)
    {
        $selectedProject = $this->db->table(self::SELTABLE)
            ->eq('user_id', $user_id)
            ->eq('project_id', $project_id)
            ->findOne();

		error_log("BB selectFind selectedProject = ".json_encode($selectedProject)." / project $project_id looked for user $user_id ");
        return $selectedProject;
    }

    public function selectTake($project_id, $user_id)
    {
        $status = $this->db->table(self::SELTABLE)->insert(array(
            'project_id' => $project_id,
            'user_id' => $user_id,
        ));

        error_log("BB selectTake STATUS = $status / project $project_id taken for user $user_id ");

        return $status;
    }

    public function selectDrop($internal_id)
    {
        $status = $this->db->table(self::SELTABLE)
            ->eq('id', $internal_id)
            ->remove();

        error_log("BB selectDrop STATUS = $status droped ( internal_id $internal_id )");

        return !$status;
    }
	
	public function selectClear($user_id)
	{
		        $status = $this->db->table(self::SELTABLE)
            ->eq('user_id', $user_id)
            ->remove();

        error_log("BB selectClear STATUS = $status / cleared for user $user_id ");

        return !$status;
	}

	// COLLAPSE methods :
	// manage status of projects which display as collapsed (or else expanded) on the bigboard view
	// if stored it is collapsed
	
	public function collapseFindAllProjects($user_id)
    {
        $collapsedProjects = $this->db->table(self::COLTABLE)
            ->eq('user_id', $user_id)
            ->findAll();

        $projects = array();
        foreach ($collapsedProjects as $collapsedProject) {
            $projects[] = $this->projectModel->getById($collapsedProject['project_id']);
        }

        return $projects;
    }

    public function collapseFindAllProjectsById($user_id)
    {
        $collapsedProjects = $this->db->table(self::COLTABLE)
            ->eq('user_id', $user_id)
            ->findAll();

        $projects = array();
        foreach ($collapsedProjects as $collapsedProject) {
            $projects[] = $collapsedProject['project_id'];
        }
		sort($projects);
        return $projects;
    }

    public function collapseFind($project_id, $user_id)
    {
        $collapsedProject = $this->db->table(self::COLTABLE)
            ->eq('user_id', $user_id)
            ->eq('project_id', $project_id)
            ->findOne();

		error_log("BB collapseFind collapsedProject = ".json_encode($collapsedProject)." / project $project_id looked for user $user_id ");
        return $collapsedProject;
    }

    public function collapseTake($project_id, $user_id)
    {
        $status = $this->db->table(self::COLTABLE)->insert(array(
            'project_id' => $project_id,
            'user_id' => $user_id,
        ));

        error_log("BB collapseTake STATUS = $status / project $project_id taken for user $user_id ");

        return $status;
    }

    public function collapseDrop($internal_id)
    {
        $status = $this->db->table(self::COLTABLE)
            ->eq('id', $internal_id)
            ->remove();

        error_log("BB collapseDrop STATUS = $status droped ( internal_id $internal_id )");

        return !$status;
    }
	
	public function collapseClear($user_id)
	{
		        $status = $this->db->table(self::COLTABLE)
            ->eq('user_id', $user_id)
            ->remove();

        error_log("BB collapseClear STATUS = $status / cleared for user $user_id ");

        return !$status;
	}	
	
}
