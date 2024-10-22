<?php
class language {
    public $data;
    function __construct($language) {
        $data = (file_exists("./locale/".$language.".json")) ? file_get_contents("./locale/".$language.".json"):file_get_contents("./locale/en.json");
        $this->data = json_decode($data);
    }

    function translate() {
        return $this->data;
   }
}

$language = new language("en");
$lang = $language->translate();
echo $lang->actions->New;
?>