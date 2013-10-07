function ChildId( child, grand_child )
{
  return 2 * child + grand_child;
}

function HideChild( matrix, child_id, status )
{
  if (status)
    s = 'hidden';
  else
    s = 'visible';
  setTimeout(function()
  {
    AccessChild(matrix, child_id).css('visibility', s);
  }, 100);
}

function AccessChild( matrix, child_id )
{
  return $('#matrix_' + matrix).find('.matrix_' + child_id);
}

function ActiveChild( matrix, child_id )
{
  setTimeout(function()
  {
    AccessChild(matrix, child_id).addClass('commited_matrix');
  }, 100);
}

function AddTitle( level, cid, gcid, obj )
{
  setTimeout(function()
  {
    var a = AccessChild(level, ChildId(cid, gcid));
    a.attr('title', "UID: " + obj.uid);
  }, 200);  
}

function OnMouseOverMatrixChild( obj )
{
  
}