<?php

namespace Cangokdayi\WPFacades;

/**
 * Services are the generic feature classes where you handle critical business
 * logic for your features and services in your app/plugin.
 */
abstract class Service
{

    /**
     * Registers your hooks and fire things up, just like a constructor.
     *
     * @return void
     */
    abstract public function init(): void;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('init', [$this, 'registerMetaboxes']);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /** 
     * Registers the custom REST API routes of your service
     * 
     * @param \WP_REST_Server $restAPI Server instance
     * 
     * @see https://developer.wordpress.org/reference/hooks/rest_api_init/
     */
    public function registerRoutes(\WP_REST_Server $restAPI): void
    {
        // 
    }

    /** 
     * Registers the custom metaboxes of your service (called in `init` action)
     * 
     * @see \Cangokdayi\WPFacades\Metabox::register()
     * @see https://developer.wordpress.org/reference/hooks/add_meta_boxes/
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function registerMetaboxes(): void
    {
        //
    }

    /** 
     * Registers/enqueues the static assets of your service
     * 
     * @see https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
     */
    public function registerAssets(): void
    {
        // 
    }

    /** 
     * Registers the custom admin menu items of your service
     * 
     * @param string $context Empty context
     * 
     * @see https://developer.wordpress.org/reference/hooks/admin_menu/
     */
    public function registerMenus(string $context): void
    {
        //
    }

    /** 
     * Registers the custom settings of your service using the WP Settings API
     * 
     * @see \Cangokdayi\WPFacades\Setting
     * @see https://developer.wordpress.org/reference/hooks/admin_init/
     * @see https://developer.wordpress.org/plugins/settings/settings-api/
     */
    public function registerSettings(): void
    {
        // 
    }
}
