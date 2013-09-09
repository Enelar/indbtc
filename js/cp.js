function HeadMatrix( id, title )
{
  $('#matrix_header_' + id).html(title);
}
function ShowMatrix( id, title, design, data )
{
  setTimeout(function() {
    
  HeadMatrix(id, title);
  phoxy.Render("ejs/" + design, 'matrix_' + id, data);
  }, 100);
}

function MakeCpMenu()
{
  if ($('#cp_menu').html() == '')
    phoxy.DeferRender("ejs/cp/menu", "cp_menu");
  require(['js/menu.js'], function()
  {
	  setTimeout("menu_hash_changed()", 300);
  });
  $(".circle_matrix").each(function()
  {
	//$(this).html("<img src=");
  });
}

window.btc_sum = 0;
window.btc_ticks = 0;
window.btc_rur_currency = 0;

function OnlineConverter()
{
  if (window.btc_rur_currency == 0)
  require(["https://socketio.mtgox.com/socket.io/socket.io.js"], function()
  {
	var conn = io.connect('https://socketio.mtgox.com/mtgox?Currency=RUB');
    conn.on('message', function(data) {
		if (data.depth == undefined)
		  return;		
		window.btc_sum += parseFloat(data.depth.price);
		window.btc_ticks++;
		window.btc_rur_currency = window.btc_sum / window.btc_ticks;
		
		//alert(data.depth.price);
	   //alert(data);
       //btc_rur_currency // Handle incoming data object.
    });
  });
  var find = $(".currency_convert");
  find.each(OnlineConverter_builder);
  
  find.on('mouseenter', function()
  {
	var obj = this;
    this.iid = setInterval(function() {
	  $(this).each(function(){OnlineConverter_callback(obj)});;
       // do something           
    }, 500);
  }).on('mouseleave', function()
  {
    this.iid && clearInterval(this.iid);
  });
  find.mouseout(OnlineConverter_builder);
  
}

function NiceBitcoinShow( n )
{
  if (n == 0)
    return 0;
  ret = n;
  pre = "";    
  /*
  else if (n > 1E-3)
  {
    ret = n / 1E-3;
    pre = "m";
  }
  else if (n > 1E-6)
  {
    ret = n / 1E-6;
    pre = "µ";
  }*/
  return parseFloat(ret).toFixed(2) + " " + pre; 
}

function OnlineConverter_builder( )
{
  val = $(this).attr('value');
  $(this).html(NiceBitcoinShow(val) + "฿ (btc)");
}

function OnlineConverter_callback( obj )
{
  var val = parseFloat($(obj).attr('value'));  
  $(obj).html(val.toFixed(2) + "฿ (btc)");  
return; // feature locked
  var str = "Р (rur)";
  var val = parseFloat($(obj).attr('value'));
  var rur = val * window.btc_rur_currency;
  if (window.btc_rur_currency != 0)
  $(obj).html(rur.toFixed(2) + str);
  else
  $(obj).html("Converting");
}

function SettingsSave()
{
  $("#settings_form").ajaxSubmit(function(data)
  {
    phoxy.ApiAnswer(JSON.parse(data));
  }
  );
  return false;
}

