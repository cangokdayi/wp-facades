<?php

namespace Cangokdayi\WPFacades\Settings;

use Cangokdayi\WPFacades\Setting;
use Cangokdayi\WPFacades\Traits\HandlesViews;

class Textbox extends Setting
{
    use HandlesViews;

    protected string $type = 'string';

    /**
     * Help text message
     */
    protected string $description = '';

    public function render(): void
    {
        $this->renderView('//textbox-field', [
            'key'       => $this->slug,
            'value'     => $this->value(),
            'help_text' => $this->description
        ]);
    }

    /**
     * Sets a help text to display below the textbox field 
     * 
     * @return static
     */
    public function helpText(string $message): self
    {
        $this->description = $message;

        return $this;
    }
}
