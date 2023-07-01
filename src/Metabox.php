<?php

namespace Cangokdayi\WPFacades;

use Cangokdayi\WPFacades\Traits\HandlesViews;
use Closure;
use WP_Post;
use WP_Screen;
use WP_Post_Type;

/**
 * Helper class for registering custom metaboxes
 */
final class Metabox
{
    use HandlesViews;

    /**
     * Post type or WP_Screen to render this metabox in.
     */
    private null|string|WP_Screen $screen = 'post';

    /**
     * Default location on edit page
     */
    private string $context = 'advanced';

    /**
     * Display priority inside the given context/location
     */
    private string $priority = 'default';

    /**
     * Additional arguments to pass to the render callback
     */
    private ?array $callbackParams = null;

    /**
     * POST controller callback for saving the settings inside the metabox
     *
     * @var callable
     */
    private Closure $postController;

    /**
     * Prefix that's used on the name attr of the setting fields inside the box.
     * 
     * Default value is $key prop followed by 2x underscores
     */
    private string $settingsPrefix;

    /**
     * Render callback for the inner content of the metabox
     */
    private Closure $renderCallback;

    /**
     * Title to display on metabox heading
     */
    private string $title = 'Custom Metabox';

    /**
     * @param string $key Unique ID - word boundary chars only (\w)
     * 
     * @throws \InvalidArgumentException If the given key has invalid characters
     */
    public function __construct(private string $key)
    {
        if (preg_match('/\W/', $key)) {
            throw new \InvalidArgumentException(
                'Identifier key can only contain word boundary characters'
            );
        }

        $this->postController = Closure::fromCallable([$this, 'saveSettings']);
    }

    /**
     * Sets the screen argument
     */
    public function screen(null|string|WP_Screen|WP_Post_Type $value): self
    {
        if ($value instanceof WP_Post_Type) {
            $value = $value->name;
        }

        $this->screen = $value ?? $this->screen;

        return $this;
    }

    /**
     * Sets the context argument
     * 
     * @param string $value Can be "advanced", "normal" or "side"
     * @throws \InvalidArgumentException If the given context is unrecognized
     */
    public function context(string $value): self
    {
        $this->assertEnumValue('context', $value);
        $this->context = $value;

        return $this;
    }

    /**
     * Sets the priority argument
     *
     * @param string $value Can be "high", "core", "low" or "default" 
     * @throws \InvalidArgumentException If the given priority is invalid
     */
    public function priority(string $value): self
    {
        $this->assertEnumValue('priority', $value);
        $this->priority = $value;

        return $this;
    }

    /**
     * Sets the metabox title value
     *
     * @param string $value
     */
    public function label(string $value): self
    {
        $this->title = $value;

        return $this;
    }

    /**
     * Sets the additional params to pass to the render callback
     *
     * @param array $params
     */
    public function callbackParams(array $args): self
    {
        $this->callbackParams = $args;

        return $this;
    }

    /**
     * Sets the settings name prefix for the setting fields inside the box
     */
    public function settingsPrefix(string $prefix): self
    {
        $this->settingsPrefix = $prefix;

        return $this;
    }

    /**
     * Sets a custom POST handler for saving setting fields
     *
     * @param callable|Closure $callback Controller method to call
     */
    public function postController(callable|Closure $callback): self
    {
        $this->postController = $callback instanceof Closure
            ? $callback
            : Closure::fromCallable($callback);

        return $this;
    }

    /**
     * Sets the render callback for displaying the inner content of the box
     *
     * @param callable $callback Function should echo its output and the markup
     *                           shouldn't include the main wrapper `div.inside`
     */
    public function renderCallback(callable $callback): self
    {
        $this->renderCallback = Closure::fromCallable($callback);

        return $this;
    }

    /**
     * Initiates the hooks and registers the metabox
     */
    public function register(): void
    {
        $hookSuffix = is_string($this->screen)
            ? "_{$this->screen}"
            : '';

        $registerBox = fn () => add_meta_box(
            $this->key,
            $this->title,
            [$this, 'render'],
            $this->screen,
            $this->context,
            $this->priority,
            $this->callbackParams
        );

        add_action("add_meta_boxes{$hookSuffix}", $registerBox);
        add_action("save_post{$hookSuffix}", $this->postController, 10, 3);
    }

    /**
     * Renders the metabox on client-side
     */
    public function render(WP_Post $post): void
    {
        $content = '';

        if (isset($this->renderCallback)) {
            ob_start();
            call_user_func($this->renderCallback, $post);

            $content = ob_get_clean();
        }

        $this->renderView('//custom-metabox', [
            'content' => $content
        ]);
    }

    /**
     * Handles the POST calls for saving the setting fields inside the metabox
     * 
     * Keep in mind that the array values will be saved as JSON encoded strings.
     */
    public function saveSettings(int $postId): void
    {
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $keyPattern = "/^{$this->settingsPrefix}/";
        $booleanSettings = $this->getBooleanSettings($postId, $keyPattern);

        foreach ($_POST as $key => $value) {
            if (!preg_match($keyPattern, $key)) {
                continue;
            }

            $value = is_array($value)
                ? json_encode($value)
                : $value;

            if (update_post_meta($postId, $key, $value) === false) {
                $this->createAdminNotification(
                    'An error has occurred while saving the settings'
                );
            }
        }

        $this->toggleBooleanSettings($postId, $booleanSettings, $_POST);
    }

    /**
     * Toggles off the bool type (checkbox, toggle control, etc.) meta fields
     *
     * @param array $fields Currently toggled fields in key => value format
     * @param array $form Submitted form entries
     */
    private function toggleBooleanSettings(
        int $postId,
        array $fields,
        array $form
    ): void {
        $unToggledFields = array_diff_key($fields, $form);

        foreach ($unToggledFields as $key => $value) {
            delete_post_meta($postId, $key, $value);
        }
    }

    /**
     * Asserts that the given value is present in the list of allowed values
     *
     * @throws \InvalidArgumentException If the value is unrecognized
     */
    private function assertEnumValue(string $argument, string $value): void
    {
        $allowedValues = match ($argument) {
            'context'  => ['normal', 'side', 'advanced'],
            'priority' => ['high', 'core', 'low', 'default'],
            default    => []
        };

        if (!in_array($value, $allowedValues)) {
            throw new \InvalidArgumentException(
                "Invalid value were given for the \"{$argument}\" argument"
            );
        }
    }

    /**
     * Returns the boolean type metadata fields such as the checkbox element for
     * toggling them inside the database since they're not present in $_POST if 
     * they are unchecked.
     * 
     * Only the metadata settings with truthy values ("true" or "1") and will be
     * included in the returned array.
     *
     * @param int $postId
     * @param null|string $keyPattern If set, only the fields with keys matching
     *                                the given regex pattern will be included.
     * 
     * @return array<string, mixed> Fields in key => value format
     */
    private function getBooleanSettings(
        int $postId,
        ?string $keyPattern = null
    ): array {
        $postMeta = flattenArray((get_post_meta($postId) ?: []), true);
        $truthy = ['1', 'true'];

        $isValidKey = fn ($k) => (is_null($keyPattern) || empty($keyPattern))
            ?: (bool) preg_match($keyPattern, $k);

        return array_filter(
            $postMeta,
            fn ($val, $key) => in_array($val, $truthy) && $isValidKey($key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
