function LoadRegForm()
{
  var opt = {
    beforeSubmit: CheckRegForm,
    success: RegResult,
    url: "api/reg/request",
    type: "post",
    dataType: "json"
};
  $('#reg_form').ajaxForm(opt);
  $('#login_place').html('');
  $('#content').html('');
  $('#login_place').parent().show();
  
  FormDecorate();
}

function FormDecorate()
{
  var form = $('#reg_form');  
  Decorate(form, 'email', 'Почта', 'mymail@example.com');
  Decorate(form, 'pass', 'Пароль', 'Сложный пароль - залог успеха.');
  Decorate(form, 'repass', 'Повторить', 'Опечатки в паролях - плохо.');
  Decorate(form, 'phone', 'Телефон', '+7XXX1112233');
  Decorate(form, 'referer', 'Партнер', 'ID вашего партнера в системе');
}

function Decorate( form, id, text, placeholder )
{
  var input = form.find('#' + id);
  var descr = input.parent().prev();
  
  input.attr('placeholder', placeholder);
  descr.html(text);
}

function RegResult( data )
{
  phoxy.ApiAnswer(data);
}

function CheckRegForm(form)
{
  var form = $('#reg_form');

  if (form.find('#email').val().indexOf('@') == -1)
  {
    alert(
    "Почта необходима для совершения минимальных действий, получения уведомлений о закрытии циклов."
    );
    return false;
  }
  if (form.find('#repass').val() != form.find('#pass').val())
  {
    alert('Пароли должны совпадать');
    return false;
  }
  if (form.find('#phone').val().indexOf('+') != 0)
  {
    alert(
    "Телефон должен начинаться с `+` (к примеру +79001234567)\n" +
    "Укажите настоящий телефон, иначе в случае утери доступа вы можете потерять деньги! И мы ничем не поможем."
    );
    return false;
  }
  if (form.find('#referer').length > 0 && !parseInt(form.find('#referer').val()))
    if (!confirm("Внимание! Вы не указали регистрацинный номер вашего партнера в системе.\n" +
    "Это значит что вы не сможете присоедениться к его сети.\n\n" +
    "Это могло произойти по следующей причине: \n - не сработала реферальная ссылка\nПопробуйте открыть ее в новой вкладке, и обновить страницу регистрации.\n" +
    "Разумеется вы всегда можете связаться с нами. \nУдачи!"))
      return false;
  return true;
}

function PrefferLogin()
{
  $('#reg_form').parent().html('');
  phoxy.SimpleApiRequest("api/login");
}
