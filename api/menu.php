<?php

class menu extends api
{
  protected function Reserve()
  {
    return array(
      "design" => "menu/default",
      "result" => "menu",
      "script" => array("menu"),
      "routeline" => "menu",
      "data" =>
        array
        (
          "menu" =>
            array
            (
              "home_button" => "/",
              "cp_button" => "api/cp",
              "feedback_button" => "api/feedback",
              "faq_button" => "api/faq",
            )
        )
    );
  }
}
