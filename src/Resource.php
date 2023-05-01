<?php

namespace Cangokdayi\WPFacades;

use Cocur\Slugify\Slugify;
use Cangokdayi\WPFacades\Database\Model;
use Cangokdayi\WPFacades\Traits\HandlesResources;
use Cangokdayi\WPFacades\Traits\HandlesViews;
use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;
use WP_List_Table;

/**
 * Provides an easy-to-use interface for registering custom WP List Tables
 * and handling custom resources in the admin panel.
 * 
 * All the methods with snake-cased names are part of the WP_List_Table
 * 
 * To cast the values of specific columns, create a new public method and
 * name it with column's name and prefix it with "column_" 
 * Example: "column_first_name"
 * 
 * @todo Sorting doesn't work due to missing handler, add it. 
 */
abstract class Resource extends WP_List_Table
{
    use InteractsWithDatabase, HandlesViews, HandlesResources;

    protected \wpdb $database;

    /**
     * Plural key of this resource, can not be overridden
     */
    protected string $plural;

    /**
     * Singular key of this resource, can not be overridden.
     */
    protected string $singular;

    /**
     * Model of this resource, must be declared before initialization.
     * 
     * @var string|Model
     */
    protected $model;

    /**
     * Key of the WP nonce for singular delete operations, can be overriden.
     */
    protected string $nonce = 'resource__update-item';

    /**
     * Nonce key string that WP Core uses to store nonces on the page.
     */
    protected string $nonceKey = '_wpnonce';

    /**
     * Total items to display per page, defaults to 20.
     * 
     * Too busy to debug and waste time with WP screen options so just 
     * implement it yourself m8.
     */
    protected int $itemsPerPage = 20;

    /**
     * Returns the plural label of this resource
     */
    abstract protected function label(): string;

    /**
     * Returns the singular label of this resource
     */
    abstract protected function singularLabel(): string;

    /**
     * Returns the visible columns list
     * 
     * @return string[] In [column => label] format
     */
    abstract protected function columns(): array;

    public function __construct()
    {
        parent::__construct([
            'singular' => $this->singularLabel(),
            'plural'   => $this->label(),
            'ajax'     => false
        ]);

        if (!is_a($this->model, Model::class, true)) {
            throw new \InvalidArgumentException(
                'The "model" property must be a valid class name of a model'
            );
        }

        $slugger = new Slugify();

        $this->model = $this->newModel();
        $this->database = $this->database();
        $this->plural = $this->_args['plural']
            ?? $slugger->slugify($this->label(), '_');

        $this->singular = $this->_args['singular']
            ?? $slugger->slugify($this->singularLabel(), '_');
    }

    /**
     * Called before rendering the views, you can register/print your static
     * assets here.
     */
    public function loadAssets(): void
    {
        $this->printStyle('//resource.css', 'print_resource_page_assets');
    }

    /**
     * Deletes the given item from the database
     */
    protected function deleteItem(int $itemId)
    {
        return $this->model::find($itemId)->delete();
    }

    /**
     * Returns a new model instance 
     */
    protected function newModel(): Model
    {
        return (new $this->model)->fresh();
    }

    /**
     * Returns the hidden columns list
     * 
     * @return string[] In [column => label] format
     */
    protected function hiddenColumns(): array
    {
        return [];
    }

    /**
     * Returns the sortable columns list
     * 
     * @return string[]
     */
    protected function sortableColumns(): array
    {
        return [];
    }

    /**
     * Returns a list of columns/attributes that can be edited in edit/update
     * resource forms. 
     * 
     * Example: 
     * ```
     * return [
     *      'post_id' => [
     *          'required' => true
     *      ],
     *      'post_state' => [
     *           'required' => false,
     *           'values'   => [
     *                  'published' => __('Published'),
     *                  'draft'     => __('Draft) 
     *            ]
     *      ],
     *      // or 
     *      'post_id' => true >> shorthand for required
     * ];
     * ```
     */
    protected function editableColumns(): array
    {
        return [];
    }

    /**
     * Returns the available bulk-actions list
     * 
     * Only the bulk-delete action is supported by default therefore 
     * you need to implement your own handlers for other actions.
     * 
     * Make sure to wrap your table markup inside a form (POST) element 
     * otherwise the bulk actions won't work.
     * 
     * @return string[] In [action => label] format
     */
    protected function bulkActions(): array
    {
        return [
            'bulk-delete' => __('Delete')
        ];
    }

    /**
     * Returns the edit/create form view for this resource
     * 
     * @param null|int|string $resource ID of the current resource [if any]
     */
    protected function getFormView($resourceId = null): string
    {
        return '';
    }

    /**
     * Returns the view template of this resource for list table screens.
     * 
     * It's recommended that you override this view and use your own.
     */
    protected function getDefaultView(): string
    {
        return $this->getView('//resource-page', [
            'page_title'     => $this->label(),
            'header_actions' => $this->getCreateButton(),
            'list_table'     => $this->display()
        ]);
    }

    /**
     * Handles the delete and bulk delete actions
     */
    protected function handleListActions(): void
    {
        $isBulkDelete = in_array('bulk-delete', [
            ($_POST['action'] ?? null),
            ($_POST['action2'] ?? null)
        ]);
        
        if ('delete' != $this->current_action() && !$isBulkDelete) {
            return;
        }

        $this->verifyNonce();

        $items = ($_POST[$this->plural] ?? null)
            ?: [$_GET[$this->singular]];

        foreach ($items as $item) {
            $this->deleteItem($item);
        }
    }

    /**
     * Handles the edit/update resource form submissions, if any.
     * 
     * @throws \InvalidArgumentException On validation errors
     */
    protected function handleFormAction(): void
    {
        $action = $_REQUEST['action'] ?? null;
        $editableColumns = $this->editableColumns();

        $isFormAction = in_array($action, ['edit', 'create']);
        $isAdmin = current_user_can('manage_options');
        $shouldSkip = 'GET' === $_SERVER['REQUEST_METHOD']
            || empty($_POST)
            || is_null($action);

        if ($shouldSkip || !$isFormAction || !$isAdmin) {
            return;
        }

        try {
            $this->validateEditableColumns($editableColumns, $_POST);
        } catch (\Throwable $e) {
            $this->displayResourceNotice($e->getMessage());
            return;
        }

        $primaryKey = $this->newModel()->getPrimaryColumn();
        preg_match('/\w+/', $_POST[$primaryKey] ?? '', $matches);

        $resourceId = $matches[0] ?? '';
        $values = $this->filterGuardedAttributes($editableColumns, $_POST);

        $model = !strlen($resourceId)
            ? $this->newModel()
            : $this->model::find($resourceId);

        $model->fill($values);
        $model->saveOrUpdate();

        $this->displayResourceNotice('Resource was updated', 'success');

        // to display the edit page after creating new resources
        if ($action === 'create') {
            $this->model = $model;
        }
    }

    /**
     * Validates the nonce for bulk actions and terminates the current request
     * with a 403 status code if it's invalid
     */
    protected function verifyNonce(): void
    {
        $nonce = $_REQUEST[$this->nonceKey] ?? '';
        $nonceIsValid = wp_verify_nonce($nonce, $this->nonce)
            ?: wp_verify_nonce($nonce, "bulk-{$this->plural}");

        if (!$nonceIsValid) {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Renders the list table or the edit form view
     */
    public function render(): void
    {
        $this->prepare_items();
        $this->handleFormAction();
        $this->loadAssets();

        $action = $_REQUEST['action'] ?? null;
        $primaryKey = $this->model->getPrimaryColumn();
        $resourceId = $_REQUEST[$this->singular]
            ?? $this->model->{$primaryKey}
            ?? null;

        echo $action && in_array($action, ['edit', 'create'])
            ? $this->getFormView($resourceId)
            : $this->getDefaultView();

        do_action('print_resource_page_assets');
    }

    /**
     * We override this because we don't need it to echo the list table, we only
     * need the markup since we just insert the table to our custom view and
     * echo the view instead.
     */
    final public function display(): string
    {
        ob_start();

        parent::display();

        return ob_get_clean();
    }

    final public function get_columns(): array
    {
        $checkboxCol = ['cb' => '<input type="checkbox"/>'];

        return $this->bulkActions()
            ? $checkboxCol + $this->columns()
            : $this->columns();
    }

    final public function get_sortable_columns(): array
    {
        return $this->sortableColumns();
    }

    final public function get_bulk_actions(): array
    {
        return $this->bulkActions();
    }

    final public function prepare_items(): void
    {
        $limit = $this->itemsPerPage;
        $offset = $limit * ($this->get_pagenum() - 1);
        $totalItems = $this->newModel()->getTotalItems();

        $this->_column_headers = [
            $this->get_columns(),
            $this->hiddenColumns(),
            $this->get_sortable_columns()
        ];

        $this->handleListActions();

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $limit
        ]);

        $this->items = $this->model::all($limit, $offset);
    }

    final public function column_default($item, $column)
    {
        return $this->getColumn($item, $column)
            ?? 'N/A';
    }

    /**
     * Renders the checkbox col markup for bulk actions row
     */
    final public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="%s[]" value="%s" />',
            $this->plural,
            $this->getColumn($item, 'id')
        );
    }
}
