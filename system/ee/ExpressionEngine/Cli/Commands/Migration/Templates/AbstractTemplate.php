<?php

namespace ExpressionEngine\Cli\Commands\Migration\Templates;

use Exception;
use ExpressionEngine\Library\Filesystem\Filesystem;

abstract class AbstractTemplate
{
    public $vars;

    public function __construct($vars)
    {
        $this->vars = $vars;
    }

    public function getParsedTemplate()
    {
        $this->checkForRequiredVars();

        $templateText = $this->getTemplateText();

        foreach ($this->vars as $var => $value) {
            $templateText = str_replace('{' . $var . '}', $value, $templateText);
        }

        return $templateText;
    }

    public function checkForRequiredVars()
    {
        $missing = array();
        foreach ($this->requiredVars() as $requiredVar) {
            if (! array_key_exists($requiredVar, $this->vars)) {
                $missing[] = $requiredVar;
            }
        }

        // If we found a required variable that is missing, throw and error and let the user know
        if (!empty($missing)) {
            throw new Exception(lang('command_make_migration_missing_required_template_variable') . implode(",", $missing), 1);
        }
    }

    protected function requiredVars()
    {
        return array('classname');
    }

    abstract protected function getTemplateText();
}
