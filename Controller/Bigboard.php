<?php

namespace Kanboard\Plugin\Bigboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Formatter\BoardFormatter;
use Kanboard\Model\UserMetadataModel;

/**
 * Bigboard Controller.
 */
class Bigboard extends BaseController
{
    /**
     * bigboard options :
     * Display all Boards available and select which must be seen on BigBoard.
     */
    public function select()
    {
        $project_ids = $this->projectPermissionModel->getActiveProjectIds($this->userSession->getId());
        $selected_project_ids = $this->bigboardModel->selectFindAllProjectsById($this->userSession->getId());

        $project_ids = array_unique(array_merge($project_ids, $selected_project_ids));

        $nb_projects = count($project_ids);
        $this->listProjects($project_ids);
    }

    /**
     * Store into database ids of selected projects to display on bigboard view.
     */
    public function saveList()
    {
        $user = $this->getUser();
        // clear all selected
        $status = $this->bigboardModel->selectClear($user['id']);
        if (isset($_POST['selection'])) {
            $selection = $_POST['selection'];
            sort($selection);
            // take each project from selection
            foreach ($selection as $selected) {
                $status = $this->bigboardModel->selectTake($selected, $user['id']);
            }
        }
        // if called from bigboard view so refresh it
        if (isset($_POST['boardview'])) {
            return $this->response->redirect($this->helper->url->to('Bigboard', 'index', ['plugin' => 'Bigboard']));
        }
    }

    /**
     * Display a Board which contains multiple projects.
     */
    public function index()
    {
        $user = $this->getUser();
        $project_ids = $this->bigboardModel->selectFindAllProjectsById($user['id']);

        $search = urldecode($this->request->getStringParam('search', $this->userSession->getBigboardSearch()) ?? '');
        $this->userSession->setBigboardSearch($search);
        $nb_projects = count($project_ids);

        $categories_list = $users_list = $custom_filters_list = [];
        foreach ($project_ids as $project_id) {
            $project_categories = $this->categoryModel->getList($project_id, false);
            if (!empty($project_categories)) {
                $categories_list = array_unique(array_merge($categories_list, $project_categories));
            }

            $project_users = $this->projectUserRoleModel->getAssignableUsersList($project_id, false);
            if (!empty($project_users)) {
                $users_list = array_unique(array_merge($users_list, $project_users));
            }

            $project_custom_filters_list = $this->customFilterModel->getAll($project_id, $this->userSession->getId());
            if (!empty($project_custom_filters_list)) {
                foreach ($project_custom_filters_list as $custom_filter) {
                    array_push($custom_filters_list, $custom_filter);
                }
            }
        }

        $this->response->html($this->helper->layout->app('bigboard:board/show', [
            'values' => [
                'search' => $search,
            ],
            'user' => $user,
            'custom_filters_list' => isset($custom_filters_list) ? $custom_filters_list : [],
            'users_list' => isset($users_list) ? $users_list : [],
            'categories_list' => isset($categories_list) ? $categories_list : [],
            'title' => t('BigBoard'),  //.' ('.$nb_projects.') ',
        ]));
        // Draw a header First
        $menu = $this->template->render('bigboard:board/switcher', [
            'bigboarddisplaymode' => $this->userSession->isBigboardCollapsed(),
        ]);
        echo '<section>'.$menu.'</section>';
        echo '<div align=center style="color:lightgray"><span id="status_update"></span></div>';
        $this->showProjects($project_ids);
    }

    public function create(array $values = [], array $errors = [])
    {
        $is_private = isset($values['is_private']) && 1 == $values['is_private'];
        $projects_list = [0 => t('Do not duplicate anything')] + $this->projectUserRoleModel->getActiveProjectsByUser($this->userSession->getId());

        $this->response->html($this->helper->layout->app('project_creation/create', [
            'values' => $values,
            'errors' => $errors,
            'is_private' => $is_private,
            'projects_list' => $projects_list,
            'title' => $is_private ? t('New private project') : t('New project'),
        ]));
    }

    public function collapseAll()
    {
        $this->changeDisplayMode(true);
    }

    public function expandAll()
    {
        $this->changeDisplayMode(false);
    }

    /**
     * List projects : display and select on options view.
     *
     * @param $project_ids : list of projects ids available for current user
     */
    private function listProjects($project_ids)
    {
        $ProjectList = [];
        foreach ($project_ids as $project_id) {
            $project = $this->projectModel->getByIdWithOwner($project_id);
            $search = $this->helper->projectHeader->getSearchQuery($project);

            $this->userMetadataCacheDecorator->set(UserMetadataModel::KEY_BOARD_COLLAPSED.$project_id, $this->userSession->isBigboardCollapsed());

            $Project['id'] = $project_id;
            $Project['nom'] = $project['name'];
            $Project['is_private'] = $project['is_private'];

            $ProjectList[] = $Project;
        }

        echo $this->template->render('bigboard:board/list', ['projectList' => $ProjectList]);
    }

    /**
     * Find all swimlane search strings from the query.
     *
     * @param  string $query
     * @return array
     */
    private function findSwimlaneElements($query='')
    {
        $elements = explode(' ', $query);
        $swimlaneElements = array_filter($elements, function($element) {
            return strpos($element, 'swimlane:') === 0;
        });
        $result = array_map(function($element) {
            return str_replace('"', '', str_replace('swimlane:', '', $element));
        }, $swimlaneElements);
        return $result;
    }

    /**
     * Show projects.
     *
     * @param $project_ids list of project ids to show
     *
     * @return bool
     */
    private function showProjects($project_ids)
    {
        echo "<div id='bigboard'>";
        $user = $this->getUser();
        $search = $this->userSession->getBigBoardSearch();

        // find tasks and the project ids so that on board printing
        // empty results can be skipped at all
        $found_tasks = $this->taskLexer->build($search)->toArray();

        $found_projects = [];
        foreach ($found_tasks as $task) {
            $found_projects[] = $task['project_id'];
        }

        $nb = 0;
        foreach ($project_ids as $project_id) {
            // "|| true" is here, since it could be replaced by an optional
            // config setting which could enable or disable the need of
            // selecting projects; also this option could be used to show the
            // selector button, which I commented out in the template
            if ($this->bigboardModel->selectFind($project_id, $user['id']) || true) {
                $project = $this->projectModel->getByIdWithOwner($project_id);
                $found_tasks = $this->taskLexer->build($search)->toArray();
                if (!in_array($project_id, $found_projects)) {
                    continue;
                }

                ++$nb;

                $this->userMetadataCacheDecorator->set(UserMetadataModel::KEY_BOARD_COLLAPSED.$project_id, $this->userSession->isBigboardCollapsed());

                // swimlane chose logic
                // only show swimlanes, which are filtere by the query or all, if no
                // "swimlane" query search string exists
                $all_swimlanes = $this->taskLexer
                    ->build($search)
                    ->format(BoardFormatter::getInstance($this->container)->withProjectId($project['id']));
                $swimlanes = [];
                if (strpos($search, 'swimlane') === false) {
                    $swimlanes = $all_swimlanes;
                } else {
                    $query_swimlanes = $this->findSwimlaneElements($search);
                    foreach ($all_swimlanes as $all_swimlane) {
                        $all_swimlane_name = $all_swimlane['name'];
                        if (in_array($all_swimlane_name, $query_swimlanes)) {
                            $swimlanes[] = $all_swimlane;
                        }
                    }
                }

                echo $this->template->render('bigboard:board/view', [
                    'no_layout' => true,
                    'board_selector' => false,
                    'project' => $project,
                    'title' => $project['name'],
                    'description' => $this->helper->projectHeader->getDescription($project),
                    'board_private_refresh_interval' => $this->configModel->get('board_private_refresh_interval'),
                    'board_highlight_period' => $this->configModel->get('board_highlight_period'),
                    'swimlanes' => $swimlanes,
                ]);
            }
        }
        if (0 == $nb) {
            echo "<div align=center><p>&nbsp;<p>&nbsp;<p>&nbsp;<p></br><span class='alert'><i class='fa fa-info fa-fw js-modal-medium'></i>";
            echo t('no tasks for all user projects has been found');
            echo '</span></div>';
        }
        echo '</div>';
    }

    private function changeDisplayMode($mode)
    {
        session_set('bigboardCollapsed', $mode);

        if ($this->request->isAjax()) {
            $project_ids = array_reverse($this->projectPermissionModel->getActiveProjectIds(session_get('user')['id']));
            $this->showProjects($project_ids);
        } else {
            $this->response->redirect($this->helper->url->to('Bigboard', 'index', ['plugin' => 'Bigboard']));
        }
    }
}
