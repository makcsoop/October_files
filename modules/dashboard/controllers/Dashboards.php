<?php namespace Dashboard\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Dashboard\Models\Dashboard;

/**
 * Dashboards controller for the dashboard
 *
 * @package october\dashboard
 * @author Alexey Bobkov, Samuel Georges
 *
 */
class Dashboards extends Controller
{
    use \Backend\Traits\InspectableContainer;

    /**
     * @var array Extensions implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    /**
     * @var array `FormController` configuration.
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var array `ListController` configuration.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array requiredPermissions to view this page.
     */
    public $requiredPermissions = ['dashboard.manage'];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.Dashboard', 'dashboard');
    }

    /**
     * index
     */
    public function index()
    {
        $this->asExtension('ListController')->index();

        $this->bodyClass = 'compact-container sidenav-responsive';

        $this->vars['dashboards'] = Dashboard::listDashboards(\Dashboard\Controllers\Index::class);
    }

    /**
     * formBeforeSave
     */
    public function formBeforeSave($model)
    {
        $model->is_custom = 1;
        $model->owner_type = \Dashboard\Controllers\Index::class;

        if ($model->owner_field) {
            $model->code = $model->owner_field;
        }
    }

    /**
     * formExtendFields
     */
    public function formExtendFields($host, $fields)
    {
        $model = $host->getModel();

        if ($model->owner_field && $fields->code) {
            $fields->code->disabled = true;
        }
    }

    /**
     * listExtendQuery
     */
    public function listExtendQuery($query)
    {
        $query->applyOwner(Index::class);
    }

    /**
     * formExtendQuery
     */
    public function formExtendQuery($query)
    {
        $query->applyOwner(Index::class);
    }
}
