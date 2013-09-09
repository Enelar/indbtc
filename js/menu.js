function menu()
{
  $(window).on('hashchange', menu_hash_changed);
  menu_hash_changed();
}

var prev_menu_fixed, prev_cp_menu;

function menu_hash_changed()
{
 var str = window.location.hash.substring(1).split('/');
 var img = str[1];
 if (img === undefined)
   img = 'home';
  
 if (img != 'cp')
   $('#cp_menu').html('');	
   
 menu_image(img, '_fixed', prev_menu_fixed);
 prev_menu_fixed = img;
 
 img = str[2];
 if (img === undefined && str[1] != 'cp')
   return;
   
 img = translate_image_name(img);
 cp_menu_image(img, '_fixed', prev_cp_menu);
 prev_cp_menu = img;
}

function menu_image( img, end, prev )
{
 $('.' + img + '_button').css("background-image", "url('img/menu/" + img + end + ".png')");	
 if (prev !== undefined && prev != img)
  $('.' + prev + '_button').css("background-image", '');
}

function cp_menu_image( img, end, prev )
{
 $('.' + img).css("background-image", "url('img/cp/" + img + end + ".png')");	
 if (prev !== undefined && prev != img)
  $('.' + prev).css("background-image", '');
}

function translate_image_name( a )
{
  if (a == 'GenericInfo')
    return 'generic';
  if (a == 'MyMatrix')
    return 'cycles';
  if (a == 'Levels')
    return 'levels';
  if (a == 'Settings')
    return 'settings';
  if (a == 'Files')
    return 'files';
  return 'cycles';
}
