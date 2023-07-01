<?php

namespace Cangokdayi\WPFacades\Settings;

use Cangokdayi\WPFacades\Setting;
use Cangokdayi\WPFacades\Traits\HandlesViews;

class Textarea extends Setting
{
    use HandlesViews;

    protected string $type = 'string';

    public function render(): void
    {
        $this->renderView('//textarea-field', [
            'key'       => $this->slug,
            'value'     => $this->value()
        ]);
    }
}
