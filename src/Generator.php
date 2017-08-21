<?php

namespace FieldGenerator\Src;

use Exception;

class Generator
{
    /**
     * Field human-readable label.
     *
     * @var string
     */
    protected $field_label;

    /**
     * Field machine name.
     *
     * @var string
     */
    protected $field_name;

    /**
     * Description.
     *
     * @var string
     */
    protected $field_description;

    /**
     * The module this field will be part of.
     *
     * @var string
     */
    protected $module_name;

    /**
     * Controlled vocabulary term such as germplasm_summary.
     *
     * @var string
     */
    protected $cv_term;

    /**
     * Controlled vocabulary such as SIO.
     *
     * @var
     */
    protected $cv_name;

    /**
     * Accession for the cv term such as 873210 or germplasm_summary.
     * Verify that this term is available in the `chado`.`dxref`.
     *
     * @var string
     */
    protected $field_accession;

    /**
     * Prompt questions.
     * Each key should point to a property to save the answer to.
     *
     * @usage Example:
     *          'Field Description: ' => 'field_description'
     *        where field_description is a protected property of this class
     *        and accessible as $this->field_description.
     *
     * @var array
     */
    protected $questions;

    /**
     * Holds the prompt output/input handler.
     *
     * @var \FieldGenerator\Src\CLIPrompt
     */
    protected $prompt;

    /**
     * Generator constructor.
     * Set all questions here.
     *
     * @return void
     */
    public function __construct()
    {
        $this->questions = [
            'Field Label (E,g. Germplasm Summary): ' => 'field_label',
            'Field Description: ' => 'field_description',
            'Module Name (E,g. tripal_germplasm_module): ' => 'module_name',
            'Controlled Vocabulary Name (E,g. local): ' => 'cv_name',
            'Controlled Vocabulary Term (E,g. germplasm_summary): ' => 'cv_term',
            'Accession (E,g. 30021 or germplasm): ' => 'field_accession',
        ];

        $this->prompt = new CLIPrompt();
    }

    /**
     * Prompt the user and save answers then generate the files.
     *
     * @return bool
     */
    public function run()
    {
        $this->prompt->line('Please fill the following form to generate a Tripal Field.');

        foreach ($this->questions as $question => $field) {
            $this->{$field} = $this->prompt->ask($question);
        }

        // Auto construct field name
        $this->field_name = "{$this->cv_name}__{$this->cv_term}";
        $this->questions[$this->field_name] = 'field_name';

        $files = $this->generate();

        try {
            return $this->make($files);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    /**
     * Generate the field files content by replacing the available variables.
     * This function does not create the directories and files.
     *
     * @return array
     */
    protected function generate()
    {
        $fields_stub = file_get_contents(__DIR__.'/../stubs/fields');
        $class_stub = file_get_contents(__DIR__.'/../stubs/field_class');
        $formatter_stub = file_get_contents(__DIR__.'/../stubs/field_formatter');
        $widget_stub = file_get_contents(__DIR__.'/../stubs/field_widget');

        // Find and replace variables in stubs.
        // The structure of variables are $$name_of_var$$ and they correspond
        // to saved class properties.
        foreach ($this->questions as $question => $field) {
            $fields_stub = str_replace('$$'.$field.'$$', $this->{$field}, $fields_stub);
            $class_stub = str_replace('$$'.$field.'$$', $this->{$field}, $class_stub);
            $formatter_stub = str_replace('$$'.$field.'$$', $this->{$field}, $formatter_stub);
            $widget_stub = str_replace('$$'.$field.'$$', $this->{$field}, $widget_stub);
        }

        return [
            'fields' => $fields_stub,
            'class' => $class_stub,
            'formatter' => $formatter_stub,
            'widget' => $widget_stub,
        ];
    }

    /**
     * Make each field file.
     *
     * @param $files
     * @return bool
     */
    protected function make($files)
    {
        // Make output directory
        $current = getcwd();
        $path = "{$current}/{$this->field_name}_output";

        if (! mkdir($path)) {
            throw new Exception('Could not create directory '.$path.'. Please check path permissions or remove directory if it exists.');
        }

        // Field settings
        file_put_contents("$path/{$this->module_name}.fields.inc", $files['fields']);

        // Create the field dir
        $field_path = "{$path}/{$this->field_name}";
        if (! mkdir($field_path)) {
            throw new Exception('Could not create directory '.$field_path.'. Please check path permissions or remove the directory if it exists.');
        }

        // Create the class files
        file_put_contents("$field_path/{$this->field_name}.inc", $files['class']);
        file_put_contents("$field_path/{$this->field_name}_widget.inc", $files['widget']);
        file_put_contents("$field_path/{$this->field_name}_formatter.inc", $files['formatter']);

        return $path;
    }

    /**
     * Give public access to the prompt.
     *
     * @return \FieldGenerator\Src\CLIPrompt
     */
    public function prompt()
    {
        return $this->prompt;
    }
}
