<?php

namespace Cangokdayi\WPFacades;

use BadMethodCallException;
use Cangokdayi\WPFacades\Traits\HasProperties;
use InvalidArgumentException;

/**
 * Represents a custom plugin setting and provides an easy-to-use interface
 * to register plugin settings using the settings API of WordPress.
 * 
 * You can add your custom setting in addition to the 3 default setting by
 * extending this base class.
 * 
 * @example
 * ```php
 * use Cangokdayi\WPFacades\Settings\Textbox;
 * 
 * $settings = [
 * 
 *  (new Textbox('Blog Name'))
 *      ->optionGroup('blog_settings')
 *      ->default('Lorem ipsum')
 *      ->slug('blog_name')
 *      ->page('site-settings'),
 *  
 *   // ...
 * ];
 * 
 * foreach ($settings as $setting) {
 *      $setting->register();
 * 
 *      // OR 
 * 
 *      $setting->register($this->commonOptionGroup, $this->commongPageSlug);
 * }
 * 
 * ```
 * 
 * @see https://developer.wordpress.org/plugins/settings/settings-api/
 * @see https://developer.wordpress.org/reference/functions/register_setting/
 */
abstract class Setting
{
    use HasProperties;

    /**
     * Slug/identifier of this setting
     * 
     * @var null|string
     */
    protected ?string $slug;

    /**
     * Type of this setting field
     * 
     * Available values:
     * - string 
     * - boolean 
     * - integer
     * - number
     * - array
     * - object
     * 
     * @var string
     */
    protected string $type;

    /**
     * Label to display on client-side
     * 
     * @var null|string
     */
    protected ?string $label;

    /**
     * Default value of this setting
     * 
     * @var mixed
     */
    protected $defaultValue = null;

    /**
     * Current value of this setting.
     * 
     * This is set once the value is retrieved for the first time so that we
     * don't have to query the database multiple times.
     * 
     * @var mixed
     */
    protected $value;

    /**
     * Option group key/identifier to register this setting under
     *
     * @var string
     */
    protected string $optionGroup;

    /**
     * Page slug to display this setting on
     *
     * @var string
     */
    protected string $pageSlug;

    /**
     * Renders the input element markup of this field (for the edit forms)
     * 
     * @return void
     */
    abstract public function render(): void;

    public function __construct(
        ?string $label = null,
        ?string $slug = null
    ) {
        $this->label = $label;
        $this->slug = $slug;
    }

    /**
     * Registers this field under the given option group and on the specified
     * plugin settings page.
     * 
     * **This method should only be called inside the "admin_init" action hook
     * of WordPress.**
     * 
     * @param ?string $group Option group name of which this field will be
     *                       registered under.
     * 
     * @param ?string $page Plugin settings page slug of which this field will
     *                      be displayed at.
     * 
     * @throws InvalidArgumentException If the type of this field is invalid
     * @throws InvalidArgumentException If the group/page arguments aren't set
     *                                  nor given as a parameter
     * 
     * @throws BadMethodCallException If this method is called outside of the
     *                                "admin_init" action.
     */
    public function register(?string $group = null, ?string $page = null): void
    {
        if (!doing_action('admin_init')) {
            throw new BadMethodCallException(
                'You can\'t register custom settings outside the "admin_init"'
                    . 'action hook'
            );
        }

        if (is_null($group) && !isset($this->optionGroup)) {
            throw new InvalidArgumentException(
                'The option group argument is missing'
            );
        }

        if (is_null($page) && !isset($this->pageSlug)) {
            throw new InvalidArgumentException(
                'The page slug argument is missing'
            );
        }

        register_setting($group ?? $this->optionGroup, $this->slug, [
            'type'    => $this->validateType($this->type),
            'default' => $this->defaultValue
        ]);

        add_settings_section(
            'default',
            'Settings',
            '__return_null',
            $page ?? $this->pageSlug
        );

        add_settings_field(
            $this->slug,
            $this->label,
            [$this, 'render'],
            $page ?? $this->pageSlug
        );
    }

    /**
     * Sets/gets the default value of this setting
     * 
     * @param mixed $value
     * @return mixed|static
     */
    public function default($value = 0xf)
    {
        return $this->getSet('defaultValue', $value, 0xf);
    }

    /**
     * Sets/gets the slug of this setting
     * 
     * @param string $slug
     * @return string|static
     */
    public function slug($slug = 0x0)
    {
        return $this->getSet('slug', $slug, 0x0);
    }

    /**
     * Sets/gets the option group of this setting
     *
     * @param string $group
     * @return string|static
     */
    public function optionGroup($group = 0x0)
    {
        return $this->getSet('optionGroup', $group, 0x0);
    }

    /**
     * Sets/gets the page slug of this setting
     *
     * @param string $slug
     * @return string|static
     */
    public function page($slug = 0x0)
    {
        return $this->getSet('pageSlug', $slug, 0x0);
    }

    /**
     * Returns the current value of this setting
     * 
     * @return mixed
     */
    public function value()
    {
        if (!isset($this->value)) {
            $this->value = get_option($this->slug, $this->defaultValue);
        }

        return $this->value;
    }

    /**
     * Validates the given type value and returns it 
     * 
     * See the documentation of the "register_setting" function linked above
     * for the available values.
     * 
     * @throws InvalidArgumentException If it's an unrecognized value
     */
    private function validateType(string $value)
    {
        $availableTypes = [
            'string', 'boolean', 'integer', 'number', 'array', 'object'
        ];

        if (!in_array($value, $availableTypes)) {
            throw new InvalidArgumentException(
                "The given type \"$value\" is unrecognized"
            );
        }

        return $value;
    }
}
