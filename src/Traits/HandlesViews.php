<?php

namespace Cangokdayi\WPFacades\Traits;

use function Cangokdayi\WPFacades\getPackageRoot;
use function Cangokdayi\WPFacades\getProjectRoot;

/**
 * Helper methods for processing views and static assets.
 * 
 * You can use this trait for your views and static assets on your plugin root
 * directory, we're using the package root level for our internal view files and
 * such. So the default paths will always resolve to the plugin/project root.
 */
trait HandlesViews
{
    /**
     * Relative path to the styles folder (dist version)
     * 
     * You can override this in your class where you use this trait
     */
    protected string $stylesFolder = 'public/dist/css';

    /**
     * Relative path to the scripts folder (dist version)
     * 
     * You can override this in your class where you use this trait
     */
    protected string $scriptsFolder = 'public/dist/js';

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
        $file = $this->getBasePath(
            "views/{$name}.php",
            $this->isInternalFile($name)
        );

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
        bool $external = false,
        array $dependencies = []
    ): void {
        $isInternal = $this->isInternalFile($fileName);
        $basePath = $this->getBasePath($this->stylesFolder, $isInternal);
        $baseURL = $this->getBaseURI($this->stylesFolder, $isInternal);

        add_action(
            $hookName,
            fn () => wp_register_style(
                $handle,
                $external ? $fileName : "$baseURL/$fileName",
                $dependencies,
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
        bool $external = false,
        array $dependencies = []
    ): void {
        $isInternal = $this->isInternalFile($fileName);
        $basePath = $this->getBasePath($this->scriptsFolder, $isInternal);
        $baseURL = $this->getBaseURI($this->scriptsFolder, $isInternal);

        add_action(
            $hookName,
            fn () => wp_register_script(
                $handle,
                $external ? $fileName : "$baseURL/$fileName",
                $dependencies,
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
        $file = $this->getStaticFile($fileName, $this->stylesFolder);
        $markup = "<style>{$file}</style>";

        $this->printMarkupOnAction($hookName, $markup);
    }

    /**
     * Renders the given JS file as inline script on action hook
     */
    public function printScript(
        string $fileName,
        string $hookName = 'wp_footer'
    ): void {
        $file = $this->getStaticFile($fileName, $this->scriptsFolder);
        $markup = "<script>{$file}</script>";

        $this->printMarkupOnAction($hookName, $markup);
    }

    /**
     * Loads the given registered style on the specified action hook
     */
    public function enqueueStyle(
        string $handle,
        string $hookName = 'wp_enqueue_scripts'
    ): void {
        add_action($hookName, fn () => wp_enqueue_style($handle));
    }

    /**
     * Loads the given registered script on the specified hook
     */
    public function enqueueScript(
        string $handle,
        string $hookName = 'wp_footer'
    ): void {
        add_action($hookName, fn () => wp_enqueue_script($handle));
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
                : (!is_null($current) && $value == $current);

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
     * @param string $hookName Action hook to display this notice in
     */
    public function createAdminNotification(
        string $message,
        string $type = 'error',
        bool $dismissable = false,
        string $hookName = 'admin_notices'
    ): void {
        $type = in_array($type, ['error', 'success', 'warning', 'info'])
            ? $type
            : 'error';

        $notice = $this->getView('//admin-notice', [
            'type'        => $type,
            'dismissable' => $dismissable ? 'is-dismissable' : '',
            'message'     => $message
        ]);

        $this->printMarkupOnAction($hookName, $notice);
    }

    /**
     * Returns the contents of the given static asset file
     * 
     * @param string $dir Relative path to the styles or scripts folder
     */
    private function getStaticFile(string $fileName, string $dir): string
    {
        $file = $this->getBasePath(
            $dir,
            $this->isInternalFile($fileName)
        ) . "/$fileName";

        return file_exists($file)
            ? file_get_contents($file)
            : '';
    }

    private function getBasePath(
        string $path = '',
        bool $isInternal = false
    ): string {
        $root = $isInternal
            ? getPackageRoot()
            : getProjectRoot();

        return $root . (strlen($path) ? "/$path" : '');
    }

    private function getBaseURI(
        string $path = '',
        bool $isInternal = false
    ): string {
        $root = $isInternal
            ? getPackageRoot()
            : getProjectRoot();

        return plugin_dir_url("{$root}/composer.json") . $path;
    }

    /**
     * Returns true if the given filename is for an internal view or static file
     * 
     * It will also remove the '//' part from the file if it's internal
     */
    private function isInternalFile(string &$file): bool
    {
        $isInterval = '//' === substr($file, 0, 2);

        $file = $isInterval
            ? substr($file, 2)
            : $file;

        return $isInterval;
    }
}
