<?php

class faq extends api
{
  protected function Reserve()
  {
    return array
    (
      "result" => "content",
      "design" => "faq/default",
      "data" => array(),
    );
  }
}
