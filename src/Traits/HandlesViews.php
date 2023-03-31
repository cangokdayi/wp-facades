<?php

namespace Cangokdayi\WPFacades\Traits;

/**
 * Helper methods for processing views and static assets.
 */
trait HandlesViews
{
    private string $stylesFolder = 'public/dist/css';
    private string $scriptsFolder = 'public/dist/js';

    /**
     * Returns the given view template's content
     * 
     * @param string $name Name of the view file without the file extension
     * @param array $args Values to pass to the view file [optional]
     * 
     * @throws \InvalidArgumentException If the given view file doesn't exist
     */
    public function getView(string $name, array $args = []): string
    {
        $file = $this->getBasePath("views/{$name}.php");

        if (!file_exists($file)) {
            throw new \InvalidArgumentException(
                'The requested view doesn\'t exist'
            );
        }

        ob_start();

        include $file;

        return preg_replace_callback(
            '/\{\{(.*?)\}\}/',
            fn ($matches) => $args[$matches[1]] ?? '',
            ob_get_clean()
        );
    }

    /**
     * Prints the given view's content
     * 
     * @param string $name Name of the view file without the file extension
     * @param array $args Values to pass to the view file [optional]
     */
    public function renderView(string $name, array $args = []): void
    {
        echo $this->getView($name, $args);
    }

    /**
     * Registers the given CSS file
     * 
     * @param boolean $external When set to TRUE, the given file name will be used
     *                          as the full URL for external assets.
     */
    public function registerStyle(
        string $handle,
        string $fileName,
        string $hookName = 'wp_enqueue_scripts',
        bool $external = false
    ): void {
        $basePath = $this->getBasePath($this->stylesFolder);
        $baseURL = $this->getBaseURI($this->stylesFolder);

        add_action(
            $hookName,
            fn () => wp_register_style(
                $handle,
                $external ? $fileName : "$baseURL/$fileName",
                [],
                $external ? false : filemtime("$basePath/$fileName")
            )
        );
    }

    /**
     * Registers the given JS file
     * 
     * @param boolean $external When set to TRUE, the given file name will be used
     *                          as the full URL for external assets.
     */
    public function registerScript(
        string $handle,
        string $fileName,
        string $hookName = 'wp_enqueue_scripts',
        bool $external = false
    ): void {
        $basePath = $this->getBasePath($this->scriptsFolder);
        $baseURL = $this->getBaseURI($this->scriptsFolder);

        add_action(
            $hookName,
            fn () => wp_register_script(
                $handle,
                $external ? $fileName : "$baseURL/$fileName",
                [],
                $external ? false : filemtime("$basePath/$fileName")
            )
        );
    }

    /**
     * Renders the given CSS file as inline style on action hook
     */
    public function printStyle(
        string $fileName,
        string $hookName = 'wp_footer'
    ): void {
        $file = $this->getBasePath($this->stylesFolder) . "/$fileName";
        $markup = sprintf(
            "<style>%s</style>",
            file_exists($file) ? file_get_contents($file) : ''
        );

        $this->printMarkupOnAction($hookName, $markup);
    }

    /**
     * Renders the given JS file as inline script on action hook
     */
    public function printScript(
        string $fileName,
        string $hookName = 'wp_footer'
    ): void {
        $file = $this->getBasePath($this->scriptsFolder) . "/$fileName";
        $markup = sprintf(
            "<script>%s</script>",
            file_exists($file) ? file_get_contents($file) : ''
        );

        $this->printMarkupOnAction($hookName, $markup);
    }

    /**
     * Prints the given markup on the specified action hook
     */
    public function printMarkupOnAction(string $hookName, string $content): void
    {
        add_action($hookName, function () use ($content) {
            echo $content;
        });
    }

    /**
     * Creates an option elements markup for select elements with the given
     * values.
     * 
     * You can pass an array as the $current param for multiselect fields.
     * 
     * @param array $options Options in [value => label] format
     * @param null|int|string|array<string, mixed> $current Currently selected
     *                                                      value(s) [optional]
     */
    public function createOptionsMarkup(array $options, $current = null): string
    {
        $markup = '';

        foreach ($options as $value => $label) {
            $isSelected = is_array($current)
                ? in_array($value, $current)
                : (!is_null($current) && $value === $current);

            $markup .= sprintf(
                '<option value="%s" %s>%s</option>',
                $value,
                selected($isSelected, true, false),
                $label
            );
        }

        return $markup;
    }

    /**
     * Displays an admin notice with the given arguments
     * 
     * @param string $type Can be "error", "success", "warning", or "info"
     */
    public function createAdminNotification(
        string $message,
        string $type = 'error',
        bool $dismissable = false
    ): void {
        $type = in_array($type, ['error', 'success', 'warning', 'info'])
            ? $type
            : 'error';

        $notice = $this->getView('admin-notice', [
            'type'        => $type,
            'dismissable' => $dismissable ? 'is-dismissable' : '',
            'message'     => $message
        ]);

        $this->printMarkupOnAction('admin_notices', $notice);
    }

    private function getBasePath(string $path = ''): string
    {
        return $_ENV['WPF_PROJECT_ROOT']
            . (strlen($path) ? "/$path" : '');
    }

    private function getBaseURI(string $path = ''): string
    {
        $composerFile = "{$_ENV['WPF_PROJECT_ROOT']}/composer.json";

        return plugin_dir_url($composerFile) . $path;
    }
}
