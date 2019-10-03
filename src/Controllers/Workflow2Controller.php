<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Linker;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Model\WorkflowAction;
use Exceedone\Exment\Model\WorkflowStatus;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\WorkflowStatusType;
use Exceedone\Exment\Form\Widgets\ModalInnerForm;


class Workflow2Controller extends AdminControllerBase
{
    use HasResourceActions;

    protected $exists = false;

    public function __construct(Request $request)
    {
        $this->setPageInfo(exmtrans("workflow.header"), exmtrans("workflow.header"), exmtrans("workflow.description"), 'fa-share-alt');
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Workflow);
        $grid->column('id', exmtrans("common.id"));
        $grid->column('workflow_name', exmtrans("workflow.workflow_name"))->sortable();
        
        $grid->disableExport();
        if (!\Exment::user()->hasPermission(Permission::SYSTEM)) {
            $grid->disableCreateButton();
        }

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            if (CustomTable::where('workflow_id', $actions->row->id)->exists()) {
                $actions->disableDelete();
            }
            $actions->disableView();
            if (count($actions->row->workflow_statuses) > 0) {
                // add new edit link
                $linker = (new Linker)
                    ->url(admin_urls('workflow', $actions->getKey(), 'edit?action=1'))
                    ->icon('fa-link')
                    ->tooltip(exmtrans('workflow.action'));
                $actions->prepend($linker);
            }
        });

        return $grid;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null)
    {
        $workflow = isset($id) ? Workflow::findOrFail($id) : new Workflow;

        // get workflow_statuses groupby
        $workflow_statuses = $this->getWorkflowStatuses($workflow);

        $form = new Form(new Workflow);

        $form->text('workflow_name', exmtrans("workflow.workflow_name"))
            ->required()
            ->rules("max:40");

        $form->exmheader(exmtrans("workflow.workflow_status"))->hr();

        // get exment version
        $ver = getExmentCurrentVersion() ?? date('YmdHis');
        $form->html(view('exment::workflow.workflow', [
            'css' => asset('/vendor/exment/css/workflow.css?ver='.$ver),
            'js' => asset('/vendor/exment/js/customform.js?ver='.$ver),
            'workflow_statuses' => $workflow_statuses,
            'modalurl_status' => admin_urls('workflow', 'modal', 'status'),
        ]))->setWidth(12, 0);

        if (isset($id) && CustomTable::where('workflow_id', $id)->count() > 0) {
            $form->tools(function (Form\Tools $tools) {
                $tools->disableDelete();
            });
        }

        $form->saving(function (Form $form) {
            $this->exists = $form->model()->exists;
        });

        $form->saved(function (Form $form) use ($id) {
            // create or drop index --------------------------------------------------
            $model = $form->model();

            // redirect workflow action page
            if (!$this->exists) {
                $workflow_action_url = admin_urls('workflow', $model->id, 'edit?action=1');
    
                admin_toastr(exmtrans('workflow.help.saved_redirect_column'));
                return redirect($workflow_action_url);
            }
        });

        return $form;
    }

    /**
     * show form for modal status
     *
     * @return void
     */
    public function modalStatus(Request $request){
        
        $form = new ModalInnerForm();
        $form->disableReset();
        $form->disableSubmit();
        $form->modalAttribute('id', 'data_share_modal');
        $form->modalHeader(exmtrans('common.shared'));
        
        // 
        $form->switchbool('enabled_flg', exmtrans('workflow.enabled_flg'))
            ->default($request->get('enabled_flg'))
            ->help(exmtrans('workflow.help.enabled_flg'))
            ->attribute(['data-filtertrigger' =>true])
            ->setWidth(9, 2);

        $form->text('status_name', exmtrans('workflow.status_name'))
            ->required()
            ->default($request->get('status_name'))
            ->help(exmtrans('workflow.help.status_name'))
            ->attribute(['data-filter' => json_encode(['key' => 'enabled_flg', 'value' => '1'])])
            ->setWidth(9, 2);

        $form->switchbool('editable_flg', exmtrans('workflow.editable_flg'))
            ->default($request->get('editable_flg'))
            ->help(exmtrans('workflow.help.editable_flg'))
            ->attribute(['data-filter' => json_encode(['key' => 'enabled_flg', 'value' => '1'])])
            ->setWidth(9, 2);



        return getAjaxResponse([
            'body'  => $form->render(),
            'script' => $form->getScript(),
            'title' => exmtrans('common.shared')
        ]);
    }

    /**
     * Get workflow statuses
     * Contains set default value
     *
     * @param Workflow $workflow
     * @return array workflow statuses
     */
    protected function getWorkflowStatuses($workflow){
        // get workflow statuses group by database
        $database_workflow_statuses = $workflow->workflow_statuses()
            ->orderBy('status_type')
            ->orderBy('order')
            ->get();
        
        // loop $workflow_statuses_groups
        $workflow_statuses = [];
        foreach(WorkflowStatusType::values() as $workflow_status_type_enum){
            // filter $workflow_statuses_groups
            $workflow_statuses_filter = $database_workflow_statuses->filter(function($workflow_status) use($workflow_status_type_enum){
                return $workflow_status->status_type == $workflow_status_type_enum;
            });

            $enumOptions = $workflow_status_type_enum->getOption(['id' => $workflow_status_type_enum->getValue()]);

            $workflow_status_types = [];
            for($i = 0; $i < $enumOptions['count']; $i++){
                // if contains item, get 
                if($i < count($workflow_statuses_filter)){
                    $workflow_status_type = $workflow_statuses_filter->values()->get($i);
                }
                // else, create new item
                else{
                    $workflow_status_type = new WorkflowStatus;
                    $workflow_status_type->status_type = array_get($enumOptions, 'id');
                    $workflow_status_type->order = $i;
                    $workflow_status_type->editable_flg = boolval(array_get($enumOptions, 'editable_flg'));
                    $workflow_status_type->enabled_flg = boolval(array_get($enumOptions, 'enabled_flg'));

                    $status_name_trans = array_get($enumOptions, 'status_name_trans');
                    $workflow_status_type->status_name = isset($status_name_trans) ? exmtrans($status_name_trans) : null;
                }

                $workflow_status_types[] = $workflow_status_type;
            }

            $workflow_statuses[] = $workflow_status_types;
        }
        
        return $workflow_statuses;
    }

    /**
     * Get workflow status and actions
     * Contains set default value
     *
     * @param Workflow $workflow
     * @return array workflow actions
     */
    protected function getWorkflowStatusActions($workflow, $workflow_statuses, $workflow_actions){
        
        $results = [];
        for($i = 0; $i < count($workflow_statuses); $i++){
            $workflow_status = $workflow_statuses[$i];
            $workflow_status_next = ($i + 1) < count($workflow_statuses) ? $workflow_statuses[$i + 1] : null;
            
            // get action using this and next status
        }

        foreach($workflow_statuses as $workflow_status){
            // get action from $workflow_actions
        }

        // loop $workflow_statuses_groups
        $workflow_statuses = [];
        foreach(WorkflowStatusType::values() as $workflow_status_type_enum){
            // filter $workflow_statuses_groups
            $workflow_statuses_filter = $database_workflow_statuses->filter(function($workflow_status) use($workflow_status_type_enum){
                return $workflow_status->status_type == $workflow_status_type_enum;
            });

            $enumOptions = $workflow_status_type_enum->getOption(['id' => $workflow_status_type_enum->getValue()]);

            $workflow_status_types = [];
            for($i = 0; $i < $enumOptions['count']; $i++){
                // if contains item, get 
                if($i < count($workflow_statuses_filter)){
                    $workflow_status_type = $workflow_statuses_filter->values()->get($i);
                }
                // else, create new item
                else{
                    $workflow_status_type = new WorkflowStatus;
                    $workflow_status_type->status_type = array_get($enumOptions, 'id');
                    $workflow_status_type->order = $i;
                    $workflow_status_type->editable_flg = boolval(array_get($enumOptions, 'editable_flg'));
                    $workflow_status_type->enabled_flg = boolval(array_get($enumOptions, 'enabled_flg'));

                    $status_name_trans = array_get($enumOptions, 'status_name_trans');
                    $workflow_status_type->status_name = isset($status_name_trans) ? exmtrans($status_name_trans) : null;
                }

                $workflow_status_types[] = $workflow_status_type;
            }

            $workflow_statuses[] = $workflow_status_types;
        }
        
        return $workflow_statuses;
    }


    /**
     * Make a action edit form builder.
     *
     * @return Form
     */
    public function action(Request $request, Content $content, $id)
    {
        return $this->AdminContent($content)->body($this->actionForm($id)->edit($id));
    }

    /**
     * Make a action edit form builder.
     *
     * @return Form
     */
    protected function statusForm($id, $is_action)
    {
        $form = new Form(new Workflow);
        $form->progressTracker()->options($this->getProgressInfo($id, $is_action));
        $form->text('workflow_name', exmtrans("workflow.workflow_name"))
            ->required()
            ->rules("max:40");

        $form->hasManyTable('workflow_statuses', exmtrans("workflow.workflow_statuses"), function ($form) {
            $form->text('status_name', exmtrans("workflow.status_name"));
            $form->switchbool('editable_flg', exmtrans("workflow.editable_flg"));
        })->setTableColumnWidth(8, 2, 2)
        ->description(sprintf(exmtrans("workflow.description_workflow_statuses")));
        
        if (isset($id) && CustomTable::where('workflow_id', $id)->count() > 0) {
            $form->tools(function (Form\Tools $tools) {
                $tools->disableDelete();
            });
        }

        $form->saving(function (Form $form) {
            $this->exists = $form->model()->exists;
        });

        $form->saved(function (Form $form) use ($id) {
            // create or drop index --------------------------------------------------
            $model = $form->model();

            // redirect workflow action page
            if (!$this->exists) {
                $workflow_action_url = admin_urls('workflow', $model->id, 'edit?action=1');
    
                admin_toastr(exmtrans('workflow.help.saved_redirect_column'));
                return redirect($workflow_action_url);
            }
        });

        return $form;
    }

    protected function getProgressInfo($id, $is_action) {
        $steps = [];
        $hasAction = false;
        $hasStatus = false;
        $workflow_action_url = null;
        $workflow_status_url = null;
        if (isset($id)) {
            $hasAction = WorkflowAction::where('workflow_id', $id)->count() > 0;
            $hasStatus = WorkflowStatus::where('workflow_id', $id)->count() > 0;
            $workflow_action_url = admin_urls('workflow', $id, 'edit?action=1');
            $workflow_status_url = admin_urls('workflow', $id, 'edit');
        }
        $steps[] = [
            'active' => !$is_action,
            'complete' => $hasStatus,
            'url' => $is_action? $workflow_status_url: null,
            'description' => '状態の定義'
        ];
        $steps[] = [
            'active' => $is_action,
            'complete' => $hasAction,
            'url' => !$is_action? $workflow_action_url: null,
            'description' => 'アクションの設定'
        ];
        return $steps;
    }

    /**
     * Make a action edit form builder.
     *
     * @return Form
     */
    protected function actionForm($id, $is_action)
    {
        $form = new Form(new Workflow);
        $form->progressTracker()->options($this->getProgressInfo($id, $is_action));
        $form->hidden('action')->default(1);
        $form->display('workflow_name', exmtrans("workflow.workflow_name"));

        $statuses = WorkflowStatus::where('workflow_id', $id)->get()->pluck('status_name', 'id');
        $statuses->prepend(exmtrans("workflow.status_init"), 0);

        $form->hasMany('workflow_actions', exmtrans("workflow.workflow_actions"), function ($form) use($id, $statuses) {
            $form->text('action_name', exmtrans("workflow.action_name"))->required();
            $form->select('status_from', exmtrans("workflow.status_from"))->required()
                ->options($statuses);
            $form->select('status_to', exmtrans("workflow.status_to"))->required()
                ->options($statuses);
            $form->hidden('workflow_id')->default($id);
            $form->multipleSelect('has_autority_users', exmtrans("workflow.has_autority_users"))
                ->options(function() {
                    return CustomTable::getEloquent(SystemTableName::USER)->getOptions();
                }
            );
            $form->multipleSelect('has_autority_organizations', exmtrans("workflow.has_autority_organizations"))
                ->options(function() {
                    return CustomTable::getEloquent(SystemTableName::ORGANIZATION)->getOptions();
                }
            );
        });

        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
        });

        $form->ignore(['action']);

        $form->saving(function (Form $form) {
            if (!is_null($form->workflow_actions)) {
                $actions = collect($form->workflow_actions)->filter(function ($value) {
                    return $value[Form::REMOVE_FLAG_NAME] != 1;
                });
                foreach($actions as $action) {
                    if (array_get($action, 'status_from') == array_get($action, 'status_to')) {
                        admin_toastr(exmtrans('workflow.message.status_nochange'), 'error');
                        return back()->withInput();
                    }
                }
            }
        });

        return $form;
    }

    /**
     * validate before delete.
     */
    protected function validateDestroy($id)
    {
        // check referenced from customtable
        $refer_count = CustomTable::where('workflow_id', $id)
            ->count();

        if ($refer_count > 0) {
            return [
                'status'  => false,
                'message' => exmtrans('workflow.message.reference_error'),
            ];
        }
    }
}