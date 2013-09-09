function Init( data )
{
  /*
  if (data === undefined || data.uid == 0)
    phoxy.SimpleApiRequest("api/login");
    */
  for (var key in data.load)
    phoxy.SimpleApiRequest(data.load[key]);
  $('.hidder').click(function()
  {
	$(this).parent().hide();
  });
  OnClickVideo("video_0");
  OnClickVideo("video_1");
  OnClickVideo("video_2");
}

function SoftLink( url )
{
  if (url == '/')
    phoxy.ApiAnswer({reset: "/"}, "aaa");
  phoxy.MenuCall(url);
}

EJS.Helpers.prototype.soft_link = function(name, url, html_options)
{
  url = "javascript:SoftLink('" + url + "')";
  return this.soft_link(name, url, html_options);
}

Object.size = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
};

function AttrSwitch( id1, id2, attr )
{
  var t = $("#" + id1).attr(attr);
  $("#" + id1).attr(attr, $("#" + id2).attr(attr));
  $("#" + id2).attr(attr, t);
}

function DrawVideo( id )
{
  var src = $("#" + id).attr("img");
  var code = '<div style="position: relative; left: 0; top: 0;">';
  code += "<img src='" + src + "' style='position: relative; top: 0; left: 0; width: 140px; max-width:100%; max-height:100%;' />";
  code += "<img class='play' src='img/spacer.gif' style='position: absolute; top: 20; left: 40; margin: auto; height: 60px; width: 60px; border:0px; border-collapse: collapse;'/>";
  code += "</div>";
  $("#" + id).html(code);
}

function MakeSwap( id )
{
	AttrSwitch(id, "video_main", "src");
	AttrSwitch(id, "video_main", "img");
	DrawVideo(id);
}

function OnClickVideo( id )
{
	DrawVideo(id);
	$("#" + id).click(function()
	{
		MakeSwap(id);
	});
	//$("#" + id).find(".play").mou
}
