<?php

class feedback extends api
{
  protected function Reserve()
  {
    return array
    (
      "result" => "content",
      "design" => "feedback/default",
      "data" => array(),
      "script" => array("feedback")
    );
  }
  
  protected function LeaveFeedback()
  {
    $email = $_POST['email'];
    $text = $_POST['feedback'];
    
    $login = LoadModule('api', 'login');
    if ($login->IsLogined())
      $uid = $login->UID();
    else
      $uid = null;
    
    $row = db::Query("INSERT INTO public.feedback(email, text, ip, uid) VALUES ($1, $2, $3, $4) RETURNING id",
      array($email, $text, _ip_, $uid), true);
    if (!count($row))
      return array("error" => "В ходе выполнения операции произошла ошибка. Ваше обращение не было сохранено.");
    return array("error" => "Обращение #{$row['id']} успешно сохранено. В скором времени мы с вами свяжемся.", "reset" => '/');
  }
}
