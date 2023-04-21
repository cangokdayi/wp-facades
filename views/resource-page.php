<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap resources">
    <h1>{{page_title}}</h1>

    <?php do_action('resource_page_notices'); ?>

    <div class="header-actions">
        {{header_actions}}
    </div>

    <hr class="wp-header-end">

    <div class="container resource card">
        <form method="POST">
            {{list_table}}
        </form>
    </div>
</div>