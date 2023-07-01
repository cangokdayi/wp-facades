<?php

namespace Cangokdayi\WPFacades\Settings;

use Cangokdayi\WPFacades\Setting;
use Cangokdayi\WPFacades\Traits\HandlesViews;

class Checkbox extends Setting
{
    use HandlesViews;

    protected string $type = 'boolean';

    public function render(): void
    {
        $this->renderView('//checkbox-field', [
            'key'       => $this->slug,
            'state'     => checked(boolval($this->value()), true, false)
        ]);
    }
}
