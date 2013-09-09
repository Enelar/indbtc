function LoadLoginForm( data )
{
  var opt = {
    beforeSubmit: CheckLoginForm,
    success: LoginResult,
    url: "api/login/request",
    type: "post",
    dataType: "json"
  };
  $('#login_form').ajaxForm(opt);
  $('#reg_place').html('');
  $('#reg_place').parent().show();
}

function LoginResult( data )
{
  phoxy.ApiAnswer(data);
}

function CheckLoginForm(form)
{
  return true;
}

function PrefferRegister()
{
  $('#login_form').parent().html('');
  phoxy.SimpleApiRequest("api/reg");
}

function InviteDelogin( data )
{
  if (confirm("Вы перешли по пригласительной ссылке, но уже зарегистрированны в системе.\n" +
"Хотите выйти из аккаунта, что бы провести регистрацию вашего партнера?"))
    location.hash = data.url;
  else
    history.back();
}
