function init() {
 document.getElementById('flotLayer').style.visibility = 'visible';
 MoveLayer('flotLayer'); 
}
function MoveLayer(layerName) {
 var x = 50;
 var y = 150; 
 var _y = document.body.scrollTop + y;
 var diff =(_y - parseInt(document.getElementById('flotLayer').style.top))*.20;
 var rey=_y-diff;
 
 document.getElementById('flotLayer').style.top=rey;
 document.getElementById('flotLayer').style.right=x;
 setTimeout("MoveLayer('flotLayer');", 50); 
 
}
