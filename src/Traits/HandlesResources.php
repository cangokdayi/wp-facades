<?php

namespace Cangokdayi\WPFacades\Traits;

use Cangokdayi\WPFacades\Traits\HandlesViews;
use WP_Post;

/**
 * Helper methods for resource classes
 */
trait HandlesResources
{
    use HandlesViews;

    /**
     * Returns the value of the given column
     * 
     * @param object|array $item
     * @return int|string|null
     */
    public function getColumn($item, string $column)
    {
        return is_object($item)
            ? $item->{$column}
            : ($item[$column] ?? null);
    }

    /**
     * Appends the column labels to the given editable columns list
     * 
     * @param array $editableColumns Editable columns config
     * @param array $columns Columns conf as defined in the columns() method
     */
    public function processEditableColumns(
        array $editableColumns,
        array $columns
    ): array {
        foreach ($editableColumns as $column => $conf) {
            if (array_key_exists('label', $conf)) {
                continue;
            }

            $editableColumns[$column]['label'] = $columns[$column];
        }

        return $editableColumns;
    }

    /**
     * Converts the given arguments to query string
     */
    public function toQueryString(array $args): string
    {
        $queryStr = array_map(
            fn ($key, $val) => "$key=$val",
            array_keys($args),
            $args
        );

        return '?' . implode('&', $queryStr);
    }

    /**
     * Validates the editable column values of form submissions on edit/update
     * resource forms/pages.
     * 
     * @param array $columns Editable columns
     * @param array $values Submitted values from $_POST
     * 
     * @throws \InvalidArgumentException If there are invalid or missing values
     */
    public function validateEditableColumns(array $columns, array $values): void
    {
        foreach ($columns as $column => $settings) {
            $isRequired = $settings['required'] ?? $settings;
            $enumVals = $settings['values'] ?? null;
            $value = $values[$column] ?? null;
            $isNull = is_null($value) || !strlen($value);

            if ($isRequired && $isNull) {
                throw new \InvalidArgumentException(
                    "The \"$column\" field is required therefore cannot be null"
                );
            }

            if (is_array($enumVals) && !in_array($value, $enumVals)) {
                throw new \InvalidArgumentException(
                    "Unrecognized value were found for the \"$column\" column"
                );
            }
        }
    }

    /**
     * Filters the guarded (non-editable) attributes from the given values.
     * 
     * @param array $columns Editable columns conf in [column => conf] format
     * @param array $values Values passed from the edit resource form 
     */
    public function filterGuardedAttributes(
        array $columns,
        array $values
    ): array {
        return array_filter(
            $values,
            fn ($col) => in_array($col, array_keys($columns)),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Displays the given admin notice on edit resource pages
     */
    public function displayNotification(
        string $message,
        string $type = 'error',
        bool $dismissable = false
    ) {
        $type = in_array($type, ['error', 'success', 'warning', 'info'])
            ? $type
            : 'error';

        $notice = $this->getView('admin-notice', [
            'type'        => $type,
            'dismissable' => $dismissable ? 'is-dismissable' : '',
            'message'     => $message
        ]);

        $this->printMarkupOnAction('yuble_api_admin_notices', $notice);
    }


    /**
     * Returns a delete button markup for row actions on the primary column
     * 
     * Example usage with row actions:
     * ```
     * echo $this->row_actions([
     *      'delete' => $this->getDeleteButton($item)
     * ]);
     * 
     * # or with multiple actions
     * echo $this->row_actions([
     *      'delete' => $this->getDeleteButton($item),
     *      'edit'   => $this->getEditButton($item),
     *      'view'   => "<button>View</button>"
     * ]);
     * ```
     * 
     * @param object|array $item Current resource
     */
    protected function getDeleteButton($item): string
    {
        $args = [
            'page'          => esc_attr($_REQUEST['page']),
            'action'        => 'delete',
            $this->nonceKey => wp_create_nonce($this->nonce),
            $this->singular => $this->getColumn($item, 'id'),
        ];

        $queryStr = array_map(
            fn ($key, $val) => "$key=$val",
            array_keys($args),
            $args
        );

        $queryStr = '?' . implode('&', $queryStr);

        return sprintf('<a href="%s">%s</a>', $queryStr, __('Delete'));
    }

    /**
     * Returns an edit button markup for row actions on the primary column
     * 
     * @param object|array $item Current resource
     */
    protected function getEditButton($item): string
    {
        $args = [
            'page'          => esc_attr($_REQUEST['page']),
            'action'        => 'edit',
            $this->singular => $this->getColumn($item, 'id')
        ];

        return sprintf(
            '<a href="%s" class="resource__actions edit-btn">%s</a>',
            $this->toQueryString($args),
            __('Edit')
        );
    }

    /**
     * Returns a "add new" button markup for header actions
     * 
     * @param null|string $label Button label text, defaults to "Add new"
     */
    public function getCreateButton(?string $label = null): string
    {
        $args = [
            'page'   => esc_attr($_REQUEST['page']),
            'action' => 'create'
        ];

        return sprintf(
            '<a href="%s" class="button button-secondary">%s</a>',
            $this->toQueryString($args),
            $label ?? __('Add New')
        );
    }
}
